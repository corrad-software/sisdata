<?php

namespace App\Modules\Pjk\Http\Controllers;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Pjk\Http\Present;
use App\Modules\Pjk\Models\Bayaran;
use App\Modules\Pjk\Models\DepositCagaran;
use App\Modules\Pjk\Models\JadualCaj;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\PelarasanLejar;
use App\Modules\Pjk\Models\Penyewa;
use App\Modules\Pjk\Models\PindaanKontrak;
use App\Modules\Pjk\Models\SejarahStatusKontrak;
use App\Modules\Pjk\Models\SuratDijana;
use App\Modules\Pjk\Models\TransaksiGateway;
use App\Modules\Pjk\Models\VersiKontrak;
use App\Modules\Pjk\Services\JadualCajService;
use App\Modules\Pjk\Services\KontrakService;
use App\Modules\Pjk\Services\LejarService;
use App\Modules\Pjk\Services\PindaanService;
use App\Modules\Pjk\Services\SemakanKadar;
use App\Modules\Pjk\Support\Akses;
use App\Modules\Pjk\Support\Dokumen;
use App\Modules\Pjk\Support\Kod;
use App\Modules\Pjk\Support\Masker;
use App\Modules\Pjk\Support\Wang;
use App\Modules\Pro\Contracts\LotRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/** SRS-PJK-01/02/05/07/10/11/12: contracts and their lifecycle, schedule, ledger and versions. */
class KontrakController
{
    public function __construct(
        private KontrakService $service,
        private LejarService $lejar,
        private JadualCajService $jadual,
        private LotRegistry $lots,
        private AuditLogger $audit,
        private Akses $akses,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $s = trim((string) $request->query('q', ''));
        $page = Kontrak::query()->with('premis:id,kod_premis,nama_premis')
            ->when($s !== '', fn ($w) => $w->where(fn ($x) => $x->where('no_kontrak', 'ilike', '%'.$s.'%')->orWhere('penyewa', 'ilike', '%'.$s.'%')
                ->orWhereIn('premis_id', fn ($p) => $p->select('id')->from('premis_aset')->where('nama_premis', 'ilike', '%'.$s.'%'))))
            ->when($request->filled('status'), fn ($w) => $w->where('status', $request->query('status')))
            ->when($request->filled('kod_daerah'), fn ($w) => $w->where('kod_daerah', $request->query('kod_daerah')))
            ->when($request->filled('kod_mukim'), fn ($w) => $w->where('kod_mukim', $request->query('kod_mukim')))
            ->when($request->filled('kategori_hartanah'), fn ($w) => $w->where('kategori_hartanah', $request->query('kategori_hartanah')))
            ->when($request->filled('bahagian'), fn ($w) => $w->where('bahagian', $request->query('bahagian')))
            ->when($request->filled('lot_id'), fn ($w) => $w->where('lot_id', $request->query('lot_id')))
            ->when($request->filled('premis_id'), fn ($w) => $w->where('premis_id', $request->query('premis_id')))
            ->when($request->filled('penyewa_id'), fn ($w) => $w->where('penyewa_id', $request->query('penyewa_id')))
            ->when(is_numeric($request->query('tamat_dalam_hari')), fn ($w) => $w->whereIn('status', Kod::BERKUAT_KUASA)->whereDate('tarikh_tamat', '<=', Carbon::today()->addDays((int) $request->query('tamat_dalam_hari'))))
            ->when($request->boolean('tunggakan'), fn ($w) => $w->whereIn('id', JadualCaj::query()->select('kontrak_id')->where('dikeluarkan', true)->whereIn('status_caj', ['belum_bayar', 'separa'])->whereDate('tarikh_akhir', '<', Carbon::today())))
            ->orderByDesc('created_at')->paginate(min(100, (int) $request->query('per_page', 20)));

        return response()->json([
            'data' => collect($page->items())->map(fn (Kontrak $k) => Present::kontrak($k))->all(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function show(Request $request, Kontrak $kontrak): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $kontrak->load('premis');
        $this->audit->record('lihat', 'pjk', Kontrak::SUBJECT, $kontrak->id, meta: ['klasifikasi' => 'sulit']);
        $penyewa = $kontrak->penyewa_id ? Penyewa::query()->find($kontrak->penyewa_id) : null;
        $lot = $this->lots->find($kontrak->lot_id, $user);

        return response()->json(['data' => Present::kontrak($kontrak, Wang::dec($this->lejar->bakiSen($kontrak->id))) + [
            'penyewa_rekod' => $penyewa ? Present::penyewa($penyewa, $this->akses->bolehLihatPenuh($user)) : null,
            'lot' => $lot ? ['id' => $lot['id'], 'no_lot' => $lot['no_lot'], 'kod_mukim' => $lot['kod_mukim'], 'pautan' => $this->lots->profileLink($lot['id'])] : null,
            'umur_hutang' => $this->jadual->ageing($kontrak->id),
            'pindaan_menunggu' => PindaanKontrak::query()->where('kontrak_id', $kontrak->id)->where('status', 'menunggu')->exists(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $k = $this->service->create($request->all(), $user);

        return response()->json(['data' => Present::kontrak($k), 'message' => __('pjk::pjk.mesej.kontrak_didaftar')], 201);
    }

    public function update(Request $request, Kontrak $kontrak): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => Present::kontrak($this->service->update($kontrak, $request->all(), $user)), 'message' => __('pjk::pjk.mesej.disimpan')]);
    }

    public function submit(Request $request, Kontrak $kontrak): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => Present::kontrak($this->service->submit($kontrak, $user)), 'message' => __('pjk::pjk.mesej.dihantar_kelulusan')]);
    }

    public function charges(Kontrak $kontrak): JsonResponse
    {
        $rows = JadualCaj::query()->where('kontrak_id', $kontrak->id)->orderBy('tarikh_caj')->orderBy('id')->get();

        return response()->json(['data' => $rows->map(fn (JadualCaj $c) => Present::caj($c))->all(), 'umur_hutang' => $this->jadual->ageing($kontrak->id)]);
    }

    public function ledger(Request $request, Kontrak $kontrak): JsonResponse
    {
        $st = $this->lejar->statement($kontrak, $request->query('dari') ? (string) $request->query('dari') : null, $request->query('hingga') ? (string) $request->query('hingga') : null);

        return response()->json(['data' => array_map(fn ($l) => Present::lejar($l), $st['baris']), 'ringkasan' => [
            'baki_awal' => Present::wang($st['baki_awal']), 'jumlah_debit' => Present::wang($st['jumlah_debit']),
            'jumlah_kredit' => Present::wang($st['jumlah_kredit']), 'baki_akhir' => Present::wang($st['baki_akhir']),
        ], 'pelarasan' => PelarasanLejar::query()->where('kontrak_id', $kontrak->id)->orderByDesc('id')->get()->map(fn ($p) => Present::pelarasan($p))->all()]);
    }

    /** SRS-PJK-11: tenant statement of account, PDF or Excel. */
    public function statement(Request $request, Kontrak $kontrak): Response
    {
        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'pdf';
        $from = $request->query('dari') ? (string) $request->query('dari') : null;
        $to = $request->query('hingga') ? (string) $request->query('hingga') : null;
        $st = $this->lejar->statement($kontrak, $from, $to);
        $rows = array_map(fn ($l) => [$l->tarikh_transaksi->format('d/m/Y'), $l->no_transaksi, Kod::JENIS_LEJAR[$l->jenis] ?? $l->jenis, (string) $l->keterangan, $l->no_resit ?? '', Wang::rm($l->debit), Wang::rm($l->kredit), Wang::rm($l->baki_berjalan)], $st['baris']);
        $this->audit->record('eksport', 'pjk', Kontrak::SUBJECT, $kontrak->id, meta: ['klasifikasi' => 'sulit', 'laporan' => 'penyata_akaun', 'format' => $format]);
        $name = 'penyata-'.str_replace('/', '-', (string) $kontrak->no_kontrak).'.'.$format;
        $head = ['Tarikh', 'No. transaksi', 'Jenis', 'Keterangan', 'No. resit', 'Debit', 'Kredit', 'Baki'];
        if ($format === 'xlsx') {
            return Dokumen::download(Dokumen::xlsx('Penyata', $head, $rows), $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
        $pdf = Dokumen::pdf(__('pjk::pjk.penyata.tajuk', ['no' => $kontrak->no_kontrak]), [
            ['jadual' => [
                [__('pjk::pjk.penyata.penyewa'), (string) $kontrak->penyewa],
                [__('pjk::pjk.penyata.tempoh'), ($from ?? '—').' hingga '.($to ?? Carbon::today()->format('Y-m-d'))],
                [__('pjk::pjk.penyata.baki_awal'), Wang::rm($st['baki_awal'])],
                [__('pjk::pjk.penyata.baki_akhir'), Wang::rm($st['baki_akhir'])],
            ]],
            ['tajuk' => __('pjk::pjk.penyata.transaksi'), 'kepala' => $head, 'jadual' => $rows],
        ], null, true);

        return Dokumen::download($pdf, $name, 'application/pdf');
    }

    public function payments(Kontrak $kontrak): JsonResponse
    {
        return response()->json([
            'data' => Bayaran::query()->where('kontrak_id', $kontrak->id)->with('resit')->orderByDesc('id')->get()->map(fn ($b) => Present::bayaran($b))->all(),
            'gateway' => TransaksiGateway::query()->where('kontrak_id', $kontrak->id)->orderByDesc('masa_mula')->limit(50)->get()->map(fn ($t) => Present::gateway($t))->all(),
        ]);
    }

    public function deposits(Kontrak $kontrak): JsonResponse
    {
        return response()->json(['data' => DepositCagaran::query()->where('kontrak_id', $kontrak->id)->orderBy('id')->get()->map(fn ($d) => Present::deposit($d))->all()]);
    }

    public function amendments(Kontrak $kontrak): JsonResponse
    {
        return response()->json([
            'data' => PindaanKontrak::query()->where('kontrak_id', $kontrak->id)->orderByDesc('id')->get()->map(fn ($p) => Present::pindaan($p))->all(),
            'versi' => VersiKontrak::query()->where('kontrak_id', $kontrak->id)->orderByDesc('no_versi')->get()->map(fn (VersiKontrak $v) => [
                'id_versi' => $v->id_versi, 'no_versi' => $v->no_versi, 'jenis_pindaan' => $v->jenis_pindaan,
                'jenis_pindaan_nama' => Kod::JENIS_PINDAAN[$v->jenis_pindaan] ?? __('pjk::pjk.kontrak.versi_asal'),
                'tarikh_pindaan' => $v->getAttribute('tarikh_pindaan')?->format('Y-m-d'), 'id_pegawai' => $v->getAttribute('id_pegawai'),
                'justifikasi' => $v->getAttribute('justifikasi'), 'data_sebelum' => $v->data_sebelum, 'data_selepas' => $v->data_selepas,
                'id_dokumen_sebelum' => $v->getAttribute('id_dokumen_sebelum'), 'id_dokumen_selepas' => $v->getAttribute('id_dokumen_selepas'),
            ])->all(),
            'sejarah_status' => SejarahStatusKontrak::query()->where('kontrak_id', $kontrak->id)->with('pegawai:id,name')->orderByDesc('id')->get()->map(fn (SejarahStatusKontrak $s) => [
                'status_sebelum' => $s->getAttribute('status_sebelum'), 'status_selepas' => $s->getAttribute('status_selepas'),
                'status_nama' => Kod::STATUS_KONTRAK[(string) $s->getAttribute('status_selepas')] ?? $s->getAttribute('status_selepas'),
                'pegawai' => $s->pegawai?->name, 'catatan' => $s->getAttribute('catatan'), 'masa' => $s->getAttribute('created_at')?->toIso8601String(),
            ])->all(),
            'surat' => SuratDijana::query()->where('kontrak_id', $kontrak->id)->with('templat:id,tajuk,jenis_surat,versi')->orderByDesc('id')->get()->map(fn (SuratDijana $s) => [
                'id' => $s->id, 'no_rujukan_surat' => $s->no_rujukan_surat, 'tajuk' => $s->templat->tajuk, 'jenis_surat' => $s->templat->jenis_surat,
                'versi_templat' => $s->templat->versi, 'id_dokumen' => $s->id_dokumen, 'masa' => $s->getAttribute('created_at')?->toIso8601String(),
            ])->all(),
        ]);
    }

    /** SRS-PJK-07 rate review (valuation shown only with pjk.rahsia.lihat, audited). */
    public function rateReview(Request $request, Kontrak $kontrak, SemakanKadar $semakan): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $semakan->forContract($kontrak, $user)]);
    }

    /** SRS-PJK-05/07/10: renewal, rate review, amendment or termination request → WFE. */
    public function requestAmendment(Request $request, Kontrak $kontrak, PindaanService $pindaan): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $file = $request->file('dokumen');
        $input = $request->all();
        if (is_string($input['data'] ?? null)) {
            $input['data'] = json_decode($input['data'], true) ?: [];
        }
        $p = $pindaan->request($kontrak, $input, $file instanceof UploadedFile ? $file : null, $user);

        return response()->json(['data' => Present::pindaan($p), 'message' => __('pjk::pjk.mesej.dihantar_kelulusan')], 201);
    }

    /** SRS-PJK-10: upload the signed agreement (draft contracts) into DMS. */
    public function uploadAgreement(Request $request, Kontrak $kontrak, DocumentStore $documents): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Validator::make($request->all(), ['fail' => ['required', 'file']], [], ['fail' => 'fail perjanjian'])->validate();
        if ($kontrak->status !== 'draf' && $kontrak->status !== 'dalam_kelulusan') {
            throw ValidationException::withMessages(['fail' => __('pjk::pjk.ralat.dokumen_melalui_pindaan')]);
        }
        try {
            $id = $documents->store($request->file('fail'), Kontrak::SUBJECT, $kontrak->id, 'perjanjian_sewa', $user, 'sulit', ['no_kontrak' => $kontrak->no_kontrak]);
        } catch (DocumentRejected $e) {
            throw ValidationException::withMessages(['fail' => $e->getMessage()]);
        }
        $kontrak->forceFill(['id_dokumen_perjanjian' => $id])->save();

        return response()->json(['data' => Present::kontrak($kontrak), 'message' => __('pjk::pjk.mesej.dokumen_dimuat_naik')], 201);
    }

    /** Masked identity for quick reference in the header (never the full value). */
    public static function maskedTenant(?Penyewa $p): ?string
    {
        return $p ? Masker::ic($p->no_pengenalan) ?? Masker::tail($p->no_pendaftaran_syarikat) : null;
    }
}
