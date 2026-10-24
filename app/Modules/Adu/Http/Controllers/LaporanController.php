<?php

namespace App\Modules\Adu\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Adu\Gis\AduanMapLayer;
use App\Modules\Adu\Services\Analitik;
use App\Modules\Adu\Support\Eksport;
use App\Modules\Adu\Support\Lalai;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Pro\Contracts\LotRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** SRS-ADU-16/25/26/27: dashboard, reports, analytics, map, export. */
class LaporanController extends Controller
{
    public const LAPORAN = ['ringkasan', 'prestasi_sla', 'tertangguh', 'berulang', 'kawasan_panas', 'trend', 'bukan_mais', 'resolusi', 'saluran', 'audit_capaian'];

    public function __construct(private Analitik $analitik, private LotRegistry $lots, private AuditLogger $audit) {}

    public function ringkasan(Request $request): JsonResponse
    {
        $f = $this->filters($request);

        return response()->json(['data' => $this->analitik->ringkasan($f) + [
            'trend' => $this->analitik->trend($f, 12),
            'kawasan_panas' => array_slice($this->analitik->kawasanPanas($f, 10), 0, 10),
        ]]);
    }

    public function show(Request $request, string $laporan): JsonResponse
    {
        abort_unless(in_array($laporan, self::LAPORAN, true), 404);
        if ($laporan === 'audit_capaian') {
            abort_unless($request->user()->can('adu.audit_capaian.lihat'), 403);
        }

        return response()->json(['data' => $this->data($laporan, $this->filters($request), (string) $request->query('ikut', 'kategori'))]);
    }

    /** Map points for the module's own map (same features as the GIS layer). */
    public function peta(Request $request, AduanMapLayer $layer): JsonResponse
    {
        $request->validate(['bbox' => ['nullable', 'regex:/^-?[\d.]+,-?[\d.]+,-?[\d.]+,-?[\d.]+$/']]);
        $bbox = $request->filled('bbox') ? array_map('floatval', explode(',', (string) $request->query('bbox'))) : null;

        return response()->json($layer->features($request->user(), $bbox, $request->only(['kod_daerah', 'kod_mukim', 'kategori', 'status']), 3000) + ['legenda' => $layer->legend()]);
    }

    public function eksport(Request $request, string $laporan): Response
    {
        abort_unless(in_array($laporan, self::LAPORAN, true) && $laporan !== 'ringkasan', 404);
        if ($laporan === 'audit_capaian') {
            abort_unless($request->user()->can('adu.audit_capaian.lihat'), 403);
        }
        $request->validate(['format' => ['nullable', Rule::in(['csv', 'xlsx', 'pdf'])]]);
        $format = (string) $request->query('format', 'xlsx');
        $f = $this->filters($request);
        $ikut = (string) $request->query('ikut', 'kategori');
        $rows = $this->data($laporan, $f, $ikut);
        [$cols, $map] = $this->columns($laporan);
        $baris = array_map(fn (array $r) => array_map(fn ($k) => is_bool($r[$k] ?? null) ? (($r[$k] ?? false) ? 'Ya' : 'Tidak') : ($r[$k] ?? null), $map), $rows);

        $this->audit->record('eksport', 'adu', 'aduan', null, meta: ['laporan' => $laporan, 'format' => $format, 'penapis' => $f, 'bilangan' => count($baris), 'klasifikasi' => $laporan === 'audit_capaian' ? 'sulit' : 'dalaman']);
        $title = (string) __('adu::adu.laporan.'.$laporan);
        $footer = (string) __('adu::adu.laporan.kaki', ['pengguna' => $request->user()->name, 'masa' => now()->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i')]);

        return Eksport::download($format, 'aduan-'.$laporan.'-'.now()->format('Ymd-His'), $title, [['tajuk' => $title, 'lajur' => $cols, 'baris' => $baris]], $footer);
    }

    /**
     * @param  array<string, mixed>  $f
     * @return list<array<string, mixed>>
     */
    private function data(string $laporan, array $f, string $ikut): array
    {
        return match ($laporan) {
            'ringkasan' => [$this->analitik->ringkasan($f)],
            'prestasi_sla' => $this->analitik->prestasiSla($f, $ikut),
            'tertangguh' => $this->analitik->tertangguh($f),
            'berulang' => $this->withLots($this->analitik->berulang($f)),
            'kawasan_panas' => $this->analitik->kawasanPanas($f, 200),
            'trend' => $this->analitik->trend($f, 24),
            'bukan_mais' => $this->analitik->bukanMais($f),
            'resolusi' => $this->analitik->resolusi($f),
            'saluran' => $this->analitik->pecahan($f, 'aduan.saluran_penerimaan', fn ($k) => Lalai::SALURAN[$k] ?? $k),
            'audit_capaian' => $this->analitik->auditCapaian($f),
            default => [],
        };
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function withLots(array $rows): array
    {
        $lots = $this->lots->summaries(array_column($rows, 'lot_id'), request()->user());

        return array_map(fn ($r) => $r + ['no_lot' => $lots[$r['lot_id']]['no_lot'] ?? null, 'pautan_lot' => $this->lots->profileLink($r['lot_id'])], $rows);
    }

    /** @return array{0: list<string>, 1: list<string>} */
    private function columns(string $laporan): array
    {
        return match ($laporan) {
            'prestasi_sla' => [['Kumpulan', 'Jumlah', 'Dipenuhi', 'Tidak dipenuhi', 'Terbuka melepasi SLA', '% dipenuhi', '% respons dalam SLA', 'Purata hari selesai'], ['label', 'jumlah', 'dipenuhi', 'tidak_dipenuhi', 'melepasi_terbuka', 'peratus_dipenuhi', 'peratus_respons_dalam_sla', 'purata_hari_selesai']],
            'tertangguh' => [['No. rujukan', 'Kategori', 'Daerah', 'Status', 'Pegawai', 'Sasaran selesai', 'Peringkat eskalasi'], ['no_rujukan_aduan', 'kategori', 'daerah', 'status', 'pegawai', 'masa_sasaran_selesai', 'peringkat_eskalasi']],
            'berulang' => [['No. lot', 'Bilangan aduan', 'Terbuka', 'Kategori utama', 'Aduan terakhir'], ['no_lot', 'bilangan', 'terbuka', 'kategori', 'terakhir']],
            'kawasan_panas' => [['Mukim', 'Daerah', 'Jumlah', 'Terbuka', 'Kategori utama'], ['mukim', 'daerah', 'jumlah', 'terbuka', 'kategori_utama']],
            'trend' => [['Bulan', 'Diterima', 'Ditutup'], ['bulan', 'diterima', 'ditutup']],
            'bukan_mais' => [['No. rujukan', 'Tarikh', 'Daerah', 'Kategori', 'Status', 'Agensi disyorkan', 'Jawapan dihantar'], ['no_rujukan_aduan', 'tarikh_terima', 'daerah', 'kategori', 'status', 'agensi', 'jawapan_dihantar']],
            'resolusi' => [['Kategori resolusi', 'Jumlah'], ['label', 'jumlah']],
            'saluran' => [['Saluran', 'Jumlah', 'Aktif', 'Ditutup'], ['label', 'jumlah', 'aktif', 'ditutup']],
            'audit_capaian' => [['No. rujukan', 'Tarikh/masa', 'Pegawai', 'Medan', 'Tujuan', 'Asas capaian', 'Alamat IP'], ['no_rujukan_aduan', 'tarikh_masa', 'pegawai', 'medan', 'tujuan', 'asas', 'alamat_ip']],
            default => [[], []],
        };
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $request->validate([
            'dari' => ['nullable', 'date'],
            'hingga' => ['nullable', 'date', 'after_or_equal:dari'],
            'kod_daerah' => ['nullable', 'string', 'size:4'],
            'kod_mukim' => ['nullable', 'string', 'size:6'],
            'kategori' => ['nullable', 'string', 'max:20'],
            'saluran' => ['nullable', Rule::in(array_keys(Lalai::SALURAN))],
            'ikut' => ['nullable', Rule::in(['kategori', 'daerah', 'pegawai'])],
        ]);

        return array_filter($request->only(Analitik::PENAPIS), fn ($v) => $v !== null && $v !== '');
    }
}
