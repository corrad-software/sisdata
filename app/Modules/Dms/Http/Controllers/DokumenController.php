<?php

namespace App\Modules\Dms\Http\Controllers;

use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Http\Resources\DokumenPresenter;
use App\Modules\Dms\Jobs\IndeksKandunganDokumen;
use App\Modules\Dms\Models\Dokumen;
use App\Modules\Dms\Models\DokumenKategori;
use App\Modules\Dms\Services\Access\DocumentAccess;
use App\Modules\Dms\Services\DmsSettings;
use App\Modules\Dms\Services\DocumentSearch;
use App\Modules\Dms\Services\MetadataNormalizer;
use App\Modules\Dms\Services\Upload\DocumentIngestor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DokumenController extends DmsController
{
    public function __construct(
        DocumentAccess $access,
        private DokumenPresenter $presenter,
        private DocumentSearch $search,
        private DmsSettings $settings,
        private AuditLogger $audit,
    ) {
        parent::__construct($access);
    }

    /** SRS-DMS-12/13: search + filters; rows and totals are access-filtered. */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'kategori' => ['nullable'],
            'modul_sumber' => ['nullable', 'string', 'max:20'],
            'jenis_entiti_induk' => ['nullable', 'string', 'max:64'],
            'id_rekod_induk' => ['nullable', 'string', 'max:64'],
            'kod_daerah' => ['nullable'],
            'kod_mukim' => ['nullable'],
            'tahun' => ['nullable', 'integer'],
            'status' => ['nullable'],
            'status.*' => [Rule::in(Dokumen::STATUS)],
            'klasifikasi' => ['nullable'],
            'klasifikasi.*' => [Rule::in(Dokumen::KLASIFIKASI)],
            'pemilik_proses_id' => ['nullable', 'integer'],
            'tarikh_dari' => ['nullable', 'date'],
            'tarikh_hingga' => ['nullable', 'date'],
            'kata_kunci' => ['nullable'],
            'meta' => ['nullable', 'array'],
            'luput_dalam_hari' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'status_indeks' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = $this->search->query($this->user($request), $filters)->paginate((int) ($filters['per_page'] ?? 20));

        return response()->json([
            'data' => collect($page->items())->map(fn (Dokumen $d) => $this->presenter->summary($d))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
        ]);
    }

    /** SRS-DMS-02/03..09: batch upload; each file gets its own accepted / rejected result. */
    public function store(Request $request, DocumentIngestor $ingestor): JsonResponse
    {
        return $this->upload($request, $ingestor, $request->input('jenis_entiti_induk'), $request->input('id_rekod_induk'));
    }

    /** SRS-DMS-16: documents of one record, for any module's record page. */
    public function forRecord(Request $request, string $jenis, string $id): JsonResponse
    {
        $user = $this->user($request);
        $docs = $this->search->query($user, ['jenis_entiti_induk' => $jenis, 'id_rekod_induk' => $id, 'status' => ['aktif', 'tamat', 'diarkib']])
            ->limit(500)
            ->get();

        return response()->json(['data' => $docs->map(fn (Dokumen $d) => $this->presenter->summary($d))->values()]);
    }

    public function storeForRecord(Request $request, DocumentIngestor $ingestor, string $jenis, string $id): JsonResponse
    {
        return $this->upload($request, $ingestor, $jenis, $id);
    }

    public function show(Request $request, string $dokumen): JsonResponse
    {
        $user = $this->user($request);
        $doc = $this->visible($dokumen, $user);

        // Rahsia: "capaian terhad dan diaudit setiap paparan" (SRS §4.2).
        if ($doc->isTerhad()) {
            $this->audit->record('lihat', 'dms', 'dokumen', $doc->id, meta: ['klasifikasi' => $doc->klasifikasi_keselamatan]);
        }

        return response()->json(['data' => $this->presenter->detail($doc, $user)]);
    }

    /** SRS-DMS-10/11: metadata edits (audited field-by-field by MOD-AUD's model observer). */
    public function update(Request $request, MetadataNormalizer $normalizer, string $dokumen): JsonResponse
    {
        $user = $this->user($request);
        $doc = $this->visible($dokumen, $user);
        if ($doc->status === 'dilupus') {
            throw new HttpException(409, __('dms::mesej.dilupus'));
        }

        $data = $request->validate([
            'tajuk' => ['sometimes', 'required', 'string', 'max:255'],
            'klasifikasi' => ['sometimes', Rule::in(Dokumen::KLASIFIKASI)],
            'tarikh_dokumen' => ['sometimes', 'nullable', 'date'],
            'tarikh_luput' => ['sometimes', 'nullable', 'date'],
            'tahun' => ['sometimes', 'nullable', 'integer', 'min:1800', 'max:2200'],
            'kod_daerah' => ['sometimes', 'nullable', 'string', 'max:20'],
            'kod_mukim' => ['sometimes', 'nullable', 'string', 'max:20'],
            'pemilik_proses_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'kata_kunci' => ['sometimes'],
            'metadata' => ['sometimes', 'array'],
        ]);

        // Raising or lowering classification changes who may see it: needs the matching clearance.
        if (isset($data['klasifikasi']) && $data['klasifikasi'] !== $doc->klasifikasi_keselamatan) {
            foreach ([$data['klasifikasi'], $doc->klasifikasi_keselamatan] as $level) {
                if ($level === 'sulit' || $level === 'rahsia') {
                    $this->requirePermission($user, 'dms.'.$level.'.lihat');
                }
            }
        }

        $normalized = $normalizer->normalize(
            [...$data, 'metadata' => $data['metadata'] ?? []],
            $doc->kategori,
            $doc->metadata,
            partial: true,
        );

        $changes = $normalized['core'];
        unset($changes['modul_sumber'], $changes['pautan_induk']);
        if (isset($data['klasifikasi'])) {
            $changes['klasifikasi_keselamatan'] = $data['klasifikasi'];
        }
        $changes['metadata'] = $normalized['extra'];

        if (array_key_exists('tarikh_luput', $changes) && $changes['tarikh_luput'] !== $doc->tarikh_luput?->toDateString()) {
            $changes['amaran_luput_pada'] = null;
            if ($doc->status === 'tamat' && $changes['tarikh_luput'] !== null && $changes['tarikh_luput'] >= now()->toDateString()) {
                $changes['status'] = 'aktif';
            }
        }

        $doc->forceFill($changes)->save();

        return response()->json(['data' => $this->presenter->detail($doc->refresh(), $user)]);
    }

    /** Logical delete only (SRS Modul 11 security control); archived documents need the disposal flow. */
    public function destroy(Request $request, string $dokumen): JsonResponse
    {
        $doc = $this->visible($dokumen, $this->user($request));
        if (in_array($doc->status, ['diarkib', 'dilupus'], true)) {
            throw new HttpException(409, __('dms::mesej.diarkib_tidak_boleh_padam'));
        }

        $doc->delete();

        return response()->json(['message' => __('dms::mesej.dipadam')]);
    }

    /** Office / TIFF / KML preview fallback: the extracted (or OCR) text. */
    public function text(Request $request, string $dokumen): JsonResponse
    {
        $doc = $this->visible($dokumen, $this->user($request));
        $this->audit->record('pratonton', 'dms', 'dokumen', $doc->id, meta: ['mod' => 'teks', 'versi' => $doc->versi_semasa]);

        return response()->json(['data' => [
            'status_indeks' => $doc->status_indeks,
            'kaedah' => $doc->kaedah_indeks,
            'teks' => $doc->teks_kandungan !== null ? mb_substr($doc->teks_kandungan, 0, 200000) : null,
        ]]);
    }

    public function reindex(Request $request, string $dokumen): JsonResponse
    {
        $doc = $this->visible($dokumen, $this->user($request));
        $doc->forceFill(['status_indeks' => 'menunggu'])->saveQuietly();
        IndeksKandunganDokumen::dispatch($doc->id, $doc->versi_semasa);

        return response()->json(['message' => __('dms::mesej.indeks_semula')], 202);
    }

    private function upload(Request $request, DocumentIngestor $ingestor, ?string $ownerType, ?string $ownerId): JsonResponse
    {
        $user = $this->user($request);
        $limit = $this->settings->batchLimit();

        $request->merge(['jenis_entiti_induk' => $ownerType, 'id_rekod_induk' => $ownerId]);
        $data = $request->validate([
            'fail' => ['required', 'array', 'min:1', 'max:'.$limit],
            'fail.*' => ['required', 'file'],
            'kategori' => ['required', 'string', Rule::exists('dokumen_kategori', 'kod')->where('aktif', true)],
            'klasifikasi' => ['nullable', Rule::in(Dokumen::KLASIFIKASI)],
            'jenis_entiti_induk' => ['nullable', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'id_rekod_induk' => ['nullable', 'required_with:jenis_entiti_induk', 'string', 'max:64'],
            'modul_sumber' => ['nullable', 'string', 'max:20', 'regex:/^[a-z0-9_]+$/'],
            'pautan_induk' => ['nullable', 'string', 'max:255', 'regex:#^/[A-Za-z0-9/_\-.?=&]*$#'],
            'tajuk' => ['nullable', 'string', 'max:255'],
            'tarikh_dokumen' => ['nullable', 'date'],
            'tarikh_luput' => ['nullable', 'date'],
            'tahun' => ['nullable', 'integer', 'min:1800', 'max:2200'],
            'kod_daerah' => ['nullable', 'string', 'max:20'],
            'kod_mukim' => ['nullable', 'string', 'max:20'],
            'pemilik_proses_id' => ['nullable', 'integer', 'exists:users,id'],
            'kata_kunci' => ['nullable'],
            'metadata' => ['nullable', 'array'],
            'id_transaksi' => ['nullable', 'uuid'],
        ], [
            'fail.max' => __('dms::mesej.kelompok_terlalu_banyak', ['had' => $limit]),
        ]);

        $classification = $data['klasifikasi'] ?? null;
        if ($classification === 'sulit' || $classification === 'rahsia') {
            // Uploading at a level you couldn't read would lock you out of other users' context.
            $this->requirePermission($user, 'dms.'.$classification.'.lihat');
        }

        $kategori = DokumenKategori::query()->where('kod', $data['kategori'])->firstOrFail();
        $metadata = array_intersect_key($data, array_flip(MetadataNormalizer::CORE)) + ['metadata' => $data['metadata'] ?? []];
        // Validate the shared metadata once, before any file is processed.
        app(MetadataNormalizer::class)->normalize($metadata, $kategori);

        /** @var list<UploadedFile> $files */
        $files = $request->file('fail');
        $results = [];
        $accepted = 0;
        foreach ($files as $index => $file) {
            // One title can't name a whole batch: each file is then titled after its name.
            $fileMeta = count($files) > 1 ? [...$metadata, 'tajuk' => null] : $metadata;
            try {
                $doc = $ingestor->ingest($file, $kategori, $user, $ownerType, $ownerId, $classification, $fileMeta, $data['id_transaksi'] ?? null);
                $results[] = ['indeks' => $index, 'nama_fail' => $doc->nama_fail, 'status' => 'diterima', 'dokumen' => $this->presenter->summary($doc->load('kategori'))];
                $accepted++;
            } catch (DocumentRejected $e) {
                $results[] = ['indeks' => $index, 'nama_fail' => $file->getClientOriginalName(), 'status' => 'ditolak', 'sebab' => $e->reason, 'mesej' => $e->getMessage()];
            }
        }

        $rejected = count($results) - $accepted;
        $body = [
            'message' => __('dms::mesej.ringkasan_muat_naik', ['diterima' => $accepted, 'ditolak' => $rejected]),
            'diterima' => $accepted,
            'ditolak' => $rejected,
            'keputusan' => $results,
        ];
        if ($accepted === 0) {
            $body['code'] = 'DOCUMENT_REJECTED';
            $body['message'] = $results[0]['mesej'] ?? $body['message'];
        }

        return response()->json($body, $accepted > 0 ? 201 : 422);
    }
}
