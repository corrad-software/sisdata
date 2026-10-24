<?php

namespace App\Modules\Hkm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Support\Eksport;
use App\Modules\Hkm\Support\Kod;
use App\Modules\Hkm\Support\RefLookup;
use App\Modules\Hkm\Support\StatusTanah;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * MOD-HKM reports ("Laporan yang boleh dijana"), as JSON or CSV/Excel/PDF.
 * Title-based reports only include titles in the user's scope.
 */
class LaporanController extends Controller
{
    public const JENIS = [
        'ringkasan' => 'Senarai hak milik mengikut daerah, jenis dan status',
        'hampir_tamat' => 'Hak milik hampir tamat tempoh (180/90/30 hari) dan yang telah tamat',
        'lot_tanpa_hak_milik' => 'Lot tanpa hak milik disahkan melebihi tempoh sasaran',
        'dokumen_belum_pulang' => 'Dokumen asal dikeluarkan dan belum dipulangkan',
        'dokumen_bermasalah' => 'Dokumen asal hilang, diganti atau dipegang pihak ketiga',
        'sekatan_aktif' => 'Hak milik dengan sekatan kepentingan atau bebanan aktif',
        'waris_belum_selesai' => 'Waris dan pemilik bersama yang belum selesai (termasuk menunggu faraid)',
        'permohonan_hmk' => 'Status permohonan hak milik kekal',
    ];

    public function __construct(private RefLookup $ref, private StatusTanah $statuses, private AuditLogger $audit, private AccessControl $access) {}

    public function show(Request $request, string $jenis): JsonResponse|Response
    {
        abort_unless(isset(self::JENIS[$jenis]), 404);
        $request->validate(['format' => ['nullable', Rule::in(['csv', 'xlsx', 'pdf'])], 'hari' => ['nullable', 'integer', 'min:1', 'max:3650']]);
        if (in_array($jenis, ['dokumen_belum_pulang', 'dokumen_bermasalah'], true)) {
            abort_unless($request->user()->can('hkm.peti_besi.lihat') || $request->user()->can('hkm.pemantauan.lihat'), 403);
        }

        [$lajur, $baris] = match ($jenis) {
            'ringkasan' => $this->reportRingkasan(),
            'hampir_tamat' => $this->reportHampirTamat($request),
            'lot_tanpa_hak_milik' => $this->reportLotTanpaHakMilik($request),
            'dokumen_belum_pulang' => $this->reportDokumenBelumPulang(),
            'dokumen_bermasalah' => $this->reportDokumenBermasalah(),
            'sekatan_aktif' => $this->reportSekatanAktif(),
            'waris_belum_selesai' => $this->reportWarisBelumSelesai(),
            default => $this->reportPermohonanHmk(),
        };

        if ($request->filled('format')) {
            abort_unless($request->user()->can('hkm.hak_milik.eksport'), 403);
            $this->audit->record('eksport', 'hkm', 'laporan', $jenis, meta: ['klasifikasi' => 'sulit', 'format' => $request->query('format'), 'bilangan' => count($baris)]);

            return Eksport::download((string) $request->query('format'), 'laporan-hkm-'.$jenis.'-'.now()->format('Ymd'), self::JENIS[$jenis],
                [['tajuk' => self::JENIS[$jenis].' ('.count($baris).')', 'lajur' => $lajur, 'baris' => $baris]],
                __('hkm::hkm.eksport.kaki', ['nama' => $request->user()->name, 'masa' => now()->format('d/m/Y H:i'), 'kriteria' => $jenis]));
        }

        return response()->json(['data' => ['jenis' => $jenis, 'tajuk' => self::JENIS[$jenis], 'lajur' => $lajur, 'baris' => $baris]]);
    }

    /** Ids of titles in the user's scope, as a sub-query.
     *
     * @return Builder<HakMilik> */
    private function scoped(): Builder
    {
        return HakMilik::query()->select('id');
    }

    /** @return array{0: list<string>, 1: list<list<scalar|null>>} */
    private function reportRingkasan(): array
    {
        $rows = HakMilik::query()->selectRaw('kod_daerah, jenis_hak_milik, status_semasa, count(*) as n')
            ->groupBy('kod_daerah', 'jenis_hak_milik', 'status_semasa')->orderBy('kod_daerah')->orderBy('jenis_hak_milik')->toBase()->get();

        return [['Daerah', 'Jenis hak milik', 'Status', 'Bilangan'], $rows->map(fn ($r) => [
            $this->ref->namaDaerah($r->kod_daerah), $this->ref->namaJenis($r->jenis_hak_milik), $this->statuses->label($r->status_semasa), (int) $r->n,
        ])->all()];
    }

    /** @return array{0: list<string>, 1: list<list<scalar|null>>} */
    private function reportHampirTamat(Request $request): array
    {
        $days = (int) $request->query('hari', 180);
        $rows = DB::table('vw_hak_milik_hampir_tamat_tempoh')->whereIn('id_hak_milik', $this->scoped())
            ->where('baki_hari', '<=', $days)->orderBy('baki_hari')->get();

        return [['No. hak milik', 'Jenis', 'Daerah', 'Tarikh tamat', 'Baki hari', 'Kategori'], $rows->map(fn ($r) => [
            $r->no_hak_milik, $this->ref->namaJenis($r->jenis_hak_milik), $this->ref->namaDaerah($r->kod_daerah), $r->tarikh_tamat_tempoh, (int) $r->baki_hari,
            $r->baki_hari < 0 ? 'Telah tamat' : ($r->baki_hari <= 30 ? '30 hari' : ($r->baki_hari <= 90 ? '90 hari' : '180 hari')),
        ])->all()];
    }

    /** @return array{0: list<string>, 1: list<list<scalar|null>>} */
    private function reportLotTanpaHakMilik(Request $request): array
    {
        $target = (int) $request->query('hari', 180);
        $query = DB::table('vw_lot_tanpa_hak_milik_disahkan')->where('umur_hari', '>=', $target)->orderByDesc('umur_hari');
        $daerah = $this->access->scope($request->user(), AccessControl::SCOPE_DAERAH);
        if ($daerah !== null) {
            $query->whereIn('kod_daerah', $daerah);
        }

        return [['No. lot', 'Mukim', 'Daerah', 'Umur rekod (hari)'], $query->limit(5000)->get()->map(fn ($r) => [
            $r->no_lot, $this->ref->namaMukim($r->kod_mukim), $this->ref->namaDaerah($r->kod_daerah), (int) $r->umur_hari,
        ])->all()];
    }

    /** @return array{0: list<string>, 1: list<list<scalar|null>>} */
    private function reportDokumenBelumPulang(): array
    {
        $rows = DB::table('vw_dokumen_asal_belum_dipulangkan')->whereIn('id_hak_milik', $this->scoped())->orderBy('tarikh_jangka_pulang')->get();

        return [['Peti besi', 'No. hak milik', 'Penerima', 'Tujuan', 'Dikeluarkan', 'Jangka pulang', 'Lewat (hari)'], $rows->map(fn ($r) => [
            $r->kod_peti_besi, $r->no_hak_milik, $r->penerima_nama, $r->tujuan_pengeluaran, substr((string) $r->tarikh_pengeluaran, 0, 10), $r->tarikh_jangka_pulang, $r->lewat ? (int) $r->hari_lewat : 0,
        ])->all()];
    }

    /** @return array{0: list<string>, 1: list<list<scalar|null>>} */
    private function reportDokumenBermasalah(): array
    {
        $rows = DB::table('vw_daftar_peti_besi_inventori')->whereIn('id_hak_milik', $this->scoped())
            ->whereIn('status_dokumen_asal', ['hilang', 'diganti', 'dipegang_pihak_ketiga'])->orderBy('status_dokumen_asal')->orderBy('no_hak_milik')->get();

        return [['No. hak milik', 'Peti besi', 'Rak', 'No. fail', 'Status'], $rows->map(fn ($r) => [
            $r->no_hak_milik, $r->kod_peti_besi, $r->lokasi_rak, $r->no_fail, Kod::STATUS_DOKUMEN[$r->status_dokumen_asal] ?? $r->status_dokumen_asal,
        ])->all()];
    }

    /** @return array{0: list<string>, 1: list<list<scalar|null>>} */
    private function reportSekatanAktif(): array
    {
        $rows = DB::table('sekatan_kepentingan as s')->join('hak_milik as h', 'h.id', '=', 's.hak_milik_id')
            ->whereIn('h.id', $this->scoped())->where('s.status_sekatan', 'aktif')
            ->orderBy('h.no_hak_milik')->get(['h.no_hak_milik', 'h.kod_daerah', 's.jenis_sekatan', 's.no_rujukan_sekatan', 's.pihak_terlibat', 's.tarikh_masuk']);

        return [['No. hak milik', 'Daerah', 'Jenis sekatan', 'No. rujukan', 'Pihak terlibat', 'Tarikh masuk'], $rows->map(fn ($r) => [
            $r->no_hak_milik, $this->ref->namaDaerah($r->kod_daerah), Kod::JENIS_SEKATAN[$r->jenis_sekatan] ?? $r->jenis_sekatan, $r->no_rujukan_sekatan, $r->pihak_terlibat, $r->tarikh_masuk,
        ])->all()];
    }

    /** @return array{0: list<string>, 1: list<list<scalar|null>>} */
    private function reportWarisBelumSelesai(): array
    {
        $rows = DB::table('waris_pemilik_bersama as w')->join('hak_milik as h', 'h.id', '=', 'w.hak_milik_id')
            ->whereIn('h.id', $this->scoped())->whereNull('w.deleted_at')
            ->whereIn('w.hubungan', Kod::HUBUNGAN_BERBAHAGI)->whereIn('w.status_pengesahan', Kod::WARIS_BELUM_SELESAI)
            ->orderBy('h.no_hak_milik')->get(['h.no_hak_milik', 'w.nama', 'w.hubungan', 'w.pecahan_bahagian_pembilang', 'w.pecahan_bahagian_penyebut', 'w.status_pengesahan', 'w.no_kes_faraid']);

        return [['No. hak milik', 'Nama', 'Hubungan', 'Bahagian', 'Status', 'No. kes faraid'], $rows->map(fn ($r) => [
            $r->no_hak_milik, $r->nama, Kod::HUBUNGAN[$r->hubungan] ?? $r->hubungan,
            $r->pecahan_bahagian_penyebut ? $r->pecahan_bahagian_pembilang.'/'.$r->pecahan_bahagian_penyebut : null,
            Kod::STATUS_PENGESAHAN_WARIS[$r->status_pengesahan] ?? $r->status_pengesahan, $r->no_kes_faraid,
        ])->all()];
    }

    /** @return array{0: list<string>, 1: list<list<scalar|null>>} */
    private function reportPermohonanHmk(): array
    {
        $rows = DB::table('permohonan_hak_milik_kekal as p')->join('hak_milik as h', 'h.id', '=', 'p.hak_milik_sementara_id')
            ->whereIn('h.id', $this->scoped())->orderByDesc('p.tarikh_permohonan')
            ->get(['p.no_permohonan', 'h.no_hak_milik', 'p.pejabat_tanah', 'p.peringkat_semasa', 'p.status_kelulusan', 'p.tarikh_permohonan', 'p.tempoh_proses_hari']);

        return [['No. permohonan', 'Hak milik sementara', 'Pejabat tanah', 'Peringkat', 'Status', 'Tarikh mohon', 'Tempoh (hari)'], $rows->map(fn ($r) => [
            $r->no_permohonan, $r->no_hak_milik, $r->pejabat_tanah, Kod::PERINGKAT_HMK[$r->peringkat_semasa] ?? $r->peringkat_semasa,
            Kod::STATUS_HMK[$r->status_kelulusan] ?? $r->status_kelulusan, $r->tarikh_permohonan,
            $r->tempoh_proses_hari ?? (int) now()->startOfDay()->diffInDays($r->tarikh_permohonan, true),
        ])->all()];
    }
}
