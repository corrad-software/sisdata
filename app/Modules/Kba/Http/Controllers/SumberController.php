<?php

namespace App\Modules\Kba\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Kba\Contracts\EmbeddingProvider;
use App\Modules\Kba\Contracts\LlmGateway;
use App\Modules\Kba\Jobs\IndeksDokumenDms;
use App\Modules\Kba\Jobs\IndeksSumber;
use App\Modules\Kba\Models\PemetaanKebenaran;
use App\Modules\Kba\Models\SumberDokumen;
use App\Modules\Kba\Services\Pengindeks;
use App\Modules\Kba\Services\PengurusSumber;
use App\Modules\Kba\Services\Penjawab;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Content admin: sources, index status, re-index, classification–role mapping (SRS-KBA-01/02/08). */
class SumberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = SumberDokumen::query()
            ->when($request->filled('asal'), fn ($b) => $b->where('asal', $request->string('asal')))
            ->when($request->filled('status_indeks'), fn ($b) => $b->where('status_indeks', $request->string('status_indeks')))
            ->when($request->filled('jenis_dokumen'), fn ($b) => $b->where('jenis_dokumen', $request->string('jenis_dokumen')))
            ->when($request->filled('q'), fn ($b) => $b->where('tajuk', 'ilike', '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $request->string('q')).'%'))
            ->orderBy('asal')->orderBy('tajuk');

        return response()->json(['data' => $q->get()->map(fn (SumberDokumen $s) => $this->present($s))->values()]);
    }

    public function status(Penjawab $penjawab, LlmGateway $llm, EmbeddingProvider $embedder): JsonResponse
    {
        $seg = DB::table('kba_segmen')->where('status', 'aktif')
            ->selectRaw('COUNT(*) AS jumlah, COUNT(embedding) AS berembedding, SUM(CASE WHEN mengandungi_arahan THEN 1 ELSE 0 END) AS berarahan')->first();

        return response()->json(['data' => [
            'sumber' => SumberDokumen::query()->selectRaw('status_indeks, COUNT(*) AS n')->groupBy('status_indeks')->pluck('n', 'status_indeks'),
            'sumber_aktif' => SumberDokumen::query()->where('aktif', true)->count(),
            'segmen_aktif' => (int) ($seg->jumlah ?? 0),
            'segmen_berembedding' => (int) ($seg->berembedding ?? 0),
            'segmen_mengandungi_arahan' => (int) ($seg->berarahan ?? 0),
            'segmen_lapuk' => DB::table('kba_segmen')->where('status', 'lapuk')->count(),
            'model_embedding' => $embedder->model(),
            'model_embedding_segmen' => DB::table('kba_segmen')->where('status', 'aktif')->whereNotNull('model_embedding')->distinct()->pluck('model_embedding'),
            'model_llm' => $llm->model(),
            'llm_tersedia' => $llm->available(),
            'mod_jawapan' => $penjawab->mode(),
        ]]);
    }

    /** Upload a manual / SOP / release note (Markdown, text, PDF, DOCX). */
    public function store(Request $request, PengurusSumber $pengurus): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'fail' => ['required', 'file', 'max:20480', 'extensions:md,markdown,txt,pdf,docx'],
            ...$this->metaRules(),
        ]);
        try {
            $sumber = $pengurus->daftarMuatNaik($request->file('fail'), $data, $user);
        } catch (DocumentRejected $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'dms_ditolak'], 422);
        } catch (RuntimeException $e) {
            return response()->json(['message' => __('kba::kba.sumber.gagal_baca', ['ralat' => $e->getMessage()]), 'code' => 'tidak_dapat_dibaca'], 422);
        }
        IndeksSumber::dispatch($sumber->id, false)->afterCommit();

        return response()->json(['data' => $this->present($sumber->refresh()), 'message' => __('kba::kba.sumber.didaftar')], 201);
    }

    /** Register an existing DMS document as a source; its text comes from DMS's own extraction. */
    public function storeDms(Request $request, PengurusSumber $pengurus): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'id_dokumen_dms' => ['required', 'uuid'],
            'jenis_dokumen' => ['required', Rule::in(SumberDokumen::JENIS)],
            'modul' => ['nullable', 'string', 'max:10', 'regex:/^[a-z]{3}$/i'],
            'senarai_peranan_dibenarkan' => ['nullable', 'array'],
            'senarai_peranan_dibenarkan.*' => ['string', 'max:64'],
        ]);
        $sumber = $pengurus->daftarDms($data['id_dokumen_dms'], $data, $user);
        abort_if($sumber === null, 404, __('kba::kba.sumber.dms_tiada'));
        IndeksDokumenDms::dispatch($data['id_dokumen_dms'], 0)->afterCommit();

        return response()->json(['data' => $this->present($sumber->refresh()), 'message' => __('kba::kba.sumber.didaftar')], 201);
    }

    /** Classification / roles / metadata — applied to the indexed segments at once (no re-embedding). */
    public function update(Request $request, int $id, Pengindeks $pengindeks): JsonResponse
    {
        $sumber = SumberDokumen::query()->findOrFail($id);
        $rules = $this->metaRules(false);
        $rules['aktif'] = ['sometimes', 'boolean'];
        $rules['peraturan_seksyen'] = ['sometimes', 'array', 'max:20'];
        $rules['peraturan_seksyen.*.padanan'] = ['required', 'string', 'max:200'];
        $rules['peraturan_seksyen.*.klasifikasi'] = ['nullable', Rule::in(SumberDokumen::KLASIFIKASI)];
        $rules['peraturan_seksyen.*.peranan'] = ['nullable', 'array'];
        $data = $request->validate($rules);
        if ($sumber->asal === 'dms') {
            unset($data['klasifikasi'], $data['kod_daerah']); // DMS owns these — change them in MOD-DMS
        }
        if ($sumber->asal === 'artikel') {
            abort(422, __('kba::kba.sumber.urus_melalui_artikel'));
        }
        if (isset($data['modul'])) {
            $data['modul'] = strtolower((string) $data['modul']);
        }
        $sumber->fill($data)->save();

        if (array_key_exists('aktif', $data) && ! $data['aktif']) {
            $pengindeks->lapukkan($sumber, __('kba::kba.sumber.dinyahaktif'));
        } elseif (array_key_exists('aktif', $data) && $sumber->bilangan_segmen === 0) {
            IndeksSumber::dispatch($sumber->id, true)->afterCommit();
        } else {
            $pengindeks->kemasKiniMetadata($sumber);
        }

        return response()->json(['data' => $this->present($sumber->refresh()), 'message' => __('kba::kba.sumber.dikemas_kini')]);
    }

    public function reindex(int $id): JsonResponse
    {
        $sumber = SumberDokumen::query()->findOrFail($id);
        if (! $sumber->aktif) {
            $sumber->forceFill(['aktif' => true])->save();
        }
        $sumber->forceFill(['status_indeks' => 'menunggu'])->saveQuietly();
        IndeksSumber::dispatch($sumber->id, true)->afterCommit();

        return response()->json(['data' => $this->present($sumber->refresh()), 'message' => __('kba::kba.sumber.indeks_dijadual')], 202);
    }

    public function reindexAll(): JsonResponse
    {
        $ids = SumberDokumen::query()->where('aktif', true)->pluck('id');
        foreach ($ids as $id) {
            IndeksSumber::dispatch((int) $id, false)->afterCommit();
        }

        return response()->json(['message' => __('kba::kba.sumber.indeks_semua_dijadual', ['bil' => $ids->count()])], 202);
    }

    public function pemetaan(): JsonResponse
    {
        return response()->json(['data' => PemetaanKebenaran::query()->orderBy('klasifikasi')->orderBy('peranan')->get()]);
    }

    /** Replace the classification → role mapping (each change audited by MOD-AUD). */
    public function simpanPemetaan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pemetaan' => ['present', 'array', 'max:200'],
            'pemetaan.*.klasifikasi' => ['required', Rule::in(['sulit', 'rahsia', 'dalaman'])],
            'pemetaan.*.peranan' => ['required', 'string', 'max:64'],
            'pemetaan.*.jenis_capaian' => ['required', Rule::in(['baca', 'semak', 'terbit'])],
            'pemetaan.*.tarikh_berkuat_kuasa' => ['required', 'date'],
        ]);
        DB::transaction(function () use ($data) {
            $keep = [];
            foreach ($data['pemetaan'] as $row) {
                $m = PemetaanKebenaran::query()->updateOrCreate(
                    ['klasifikasi' => $row['klasifikasi'], 'peranan' => $row['peranan'], 'jenis_capaian' => $row['jenis_capaian']],
                    ['tarikh_berkuat_kuasa' => $row['tarikh_berkuat_kuasa']],
                );
                $keep[] = $m->id;
            }
            PemetaanKebenaran::query()->whereNotIn('id', $keep ?: [0])->get()->each->delete();
        });

        return $this->pemetaan();
    }

    /** @return array<string, list<mixed>> */
    private function metaRules(bool $required = true): array
    {
        $req = $required ? 'required' : 'sometimes';

        return [
            'tajuk' => [$req, 'string', 'max:250'],
            'jenis_dokumen' => [$req, Rule::in(SumberDokumen::JENIS)],
            'versi' => ['nullable', 'string', 'max:40'],
            'modul' => ['nullable', 'string', 'max:10', 'regex:/^[a-z]{3}$/i'],
            'klasifikasi' => [$req, Rule::in(SumberDokumen::KLASIFIKASI)],
            'senarai_peranan_dibenarkan' => ['nullable', 'array'],
            'senarai_peranan_dibenarkan.*' => ['string', 'max:64'],
            'kod_daerah' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** @return array<string, mixed> */
    private function present(SumberDokumen $s): array
    {
        return [
            'id' => $s->id, 'kod' => $s->kod, 'asal' => $s->asal, 'tajuk' => $s->tajuk, 'jenis_dokumen' => $s->jenis_dokumen,
            'versi' => $s->versiPaparan(), 'modul' => $s->modul, 'klasifikasi' => $s->klasifikasi,
            'senarai_peranan_dibenarkan' => $s->senarai_peranan_dibenarkan, 'kod_daerah' => $s->kod_daerah, 'peraturan_seksyen' => $s->peraturan_seksyen,
            'format' => $s->format, 'status_indeks' => $s->status_indeks, 'mesej_indeks' => $s->mesej_indeks, 'bilangan_segmen' => $s->bilangan_segmen,
            'tarikh_indeks_akhir' => $s->tarikh_indeks_akhir?->toIso8601String(), 'aktif' => $s->aktif, 'id_dokumen_dms' => $s->id_dokumen_dms,
            'laluan_fail' => $s->laluan_fail, 'pautan' => '/admin/kba/sumber/'.$s->id,
        ];
    }
}
