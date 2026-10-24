<?php

namespace App\Modules\Dsh\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Dsh\Contracts\KpiDrillDown;
use App\Modules\Dsh\Services\Aras;
use App\Modules\Dsh\Services\Baca;
use App\Modules\Dsh\Services\Eksport;
use App\Modules\Dsh\Services\KpiService;
use App\Modules\Dsh\Services\Penapis;
use App\Modules\Dsh\Services\Ringkasan;
use App\Modules\Dsh\Services\Skop;
use App\Modules\Dsh\Services\WidgetKatalog;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Executive dashboard (SRS-DSH-01..14): level resolution, widgets + KPI
 * values under the user's scope, filters, drill-down and export.
 */
class DashboardController extends Controller
{
    public function __construct(
        private Aras $aras,
        private KpiService $kpis,
        private WidgetKatalog $widgets,
        private Skop $skop,
        private AuditLogger $audit,
    ) {}

    private function resolveAras(Request $request, User $user): string
    {
        $aras = $request->query('aras');
        if (is_string($aras) && $aras !== '') {
            abort_unless(in_array($aras, Aras::ALL, true), 422, __('dsh::dsh.aras_tidak_sah'));
            abort_unless($this->aras->allows($user, $aras), 403, __('dsh::dsh.aras_tiada_kebenaran'));

            return $aras;
        }
        $default = $this->aras->default($user);
        abort_if($default === null, 403, __('dsh::dsh.aras_tiada_kebenaran'));

        return $default;
    }

    /** GET /dashboard — the level's layout with every visible widget's KPI computed. */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $aras = $this->resolveAras($request, $user);
        $filters = Penapis::fromRequest($request);
        $fresh = $request->boolean('segar');

        $layout = $this->widgets->layout($user, $aras);
        $keys = array_values(array_filter(array_map(fn ($w) => $w['tersembunyi'] ? null : $w['kpi'], $layout)));
        $kpis = $keys === [] ? [] : $this->kpis->compute($user, $filters, $keys, $fresh);
        if (! empty($filters['modul'])) {
            $shown = array_column($kpis, 'key');
            $layout = array_values(array_filter($layout, fn ($w) => $w['kpi'] === null || in_array($w['kpi'], $shown, true)));
        }

        $times = array_filter(array_column($kpis, 'dikira_pada'));
        $sources = array_filter(array_column($kpis, 'data_terakhir'));
        $this->audit->record('lihat', 'dsh', 'dashboard', $aras, meta: ['penapis' => $filters, 'bil_kpi' => count($kpis)]);

        return response()->json(['data' => [
            'aras' => $aras,
            'aras_tersedia' => $this->aras->available($user),
            'aras_lalai' => $this->aras->default($user),
            'varian' => $this->aras->variant($user),
            'skop' => $this->scopeLabels($user),
            'penapis' => $filters,
            'terhad' => $this->skop->outsideDistrict($user, $filters['kod_daerah'] ?? null),
            'widget' => $layout,
            'kpi' => $kpis,
            'dikira_pada' => $times === [] ? Carbon::now()->toIso8601String() : min($times),
            'data_terakhir' => $sources === [] ? null : max($sources),
            'ttl_saat' => $this->kpis->ttl(),
            'kebenaran' => [
                'eksport' => $user->can('dsh.dashboard.eksport'),
                'ai' => $user->can('dsh.ai.lihat'),
                'langganan' => $user->can('dsh.langganan.cipta'),
                'peta' => $user->can('gis.peta.lihat'),
                'tetapan' => $user->can('dsh.widget.kemas_kini'),
            ],
        ]]);
    }

    /** GET /kpi — selected KPIs (by key and/or modul), e.g. the tax / lease / monitoring dashboards. */
    public function kpi(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $request->validate(['kunci' => ['nullable', 'string', 'max:2000'], 'domain' => ['nullable', 'string', 'max:20']]);
        $filters = Penapis::fromRequest($request);
        $keys = $request->filled('kunci') ? array_values(array_filter(explode(',', (string) $request->query('kunci')))) : null;
        $results = $this->kpis->compute($user, $filters, $keys, $request->boolean('segar'));
        if ($request->filled('domain')) {
            $results = array_values(array_filter($results, fn ($r) => $r['domain'] === $request->query('domain')));
        }
        $times = array_filter(array_column($results, 'dikira_pada'));

        return response()->json(['data' => $results, 'dikira_pada' => $times === [] ? Carbon::now()->toIso8601String() : min($times), 'katalog' => $this->kpis->catalogue($user)]);
    }

    /** GET /kpi/{key}/senarai — drill-down: the records behind a KPI value or slice (SRS-DSH-11). */
    public function drill(Request $request, string $key): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate(['pecahan' => ['nullable', 'string', 'max:120'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:5', 'max:100']]);
        $provider = $this->kpis->find($user, $key);
        abort_unless($provider instanceof KpiDrillDown, 404, __('dsh::dsh.kpi_tiada'));
        $filters = Penapis::fromRequest($request);
        $page = (int) ($data['page'] ?? 1);
        $perPage = (int) ($data['per_page'] ?? 25);
        $records = $provider->records($user, $filters, $data['pecahan'] ?? null, $page, $perPage);
        $this->audit->record('lihat', 'dsh', 'kpi_senarai', $key, meta: ['penapis' => $filters, 'pecahan' => $data['pecahan'] ?? null]);

        return response()->json(['data' => $records['baris'], 'lajur' => $records['lajur'], 'meta' => [
            'kpi' => $key, 'label' => $provider->label(), 'pecahan' => $data['pecahan'] ?? null, 'penapis' => $filters,
            'jumlah' => $records['jumlah'], 'page' => $page, 'per_page' => $perPage, 'last_page' => max(1, (int) ceil($records['jumlah'] / $perPage)),
            'dijana_pada' => Carbon::now()->toIso8601String(),
        ]]);
    }

    /** GET /penapis/pilihan — filter options within the user's scope. */
    public function options(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $scope = $this->skop->scopes($user);
        $daerah = Baca::db()->table('ref_daerah')->where('aktif', true)->orderBy('susunan')->orderBy('nama')
            ->when($scope['daerah'] !== null, fn ($q) => $q->whereIn('kod', $scope['daerah']))->get(['kod', 'nama']);
        $mukim = Baca::db()->table('ref_mukim')->where('aktif', true)->orderBy('nama')
            ->when($scope['daerah'] !== null, fn ($q) => $q->whereIn('daerah_kod', $scope['daerah']))
            ->when($scope['mukim'] !== null, fn ($q) => $q->whereIn('kod', $scope['mukim']))->get(['kod', 'nama', 'daerah_kod']);
        $kategori = Baca::db()->table('ref_kategori_guna_tanah')->where('aktif', true)->orderBy('susunan')->orderBy('nama')->get(['kod', 'nama']);
        $bahagian = collect(['hartanah' => 'Hartanah', 'wakaf' => 'Wakaf', 'baitulmal' => 'Baitulmal'])
            ->filter(fn ($v, $k) => $scope['bahagian'] === null || in_array($k, $scope['bahagian'], true))
            ->map(fn ($v, $k) => ['kod' => $k, 'nama' => $v])->values();
        $modul = collect($this->kpis->catalogue($user))->pluck('modul')->unique()->values()
            ->map(fn ($m) => ['kod' => $m, 'nama' => __('dsh::dsh.modul.'.$m) === 'dsh::dsh.modul.'.$m ? strtoupper($m) : __('dsh::dsh.modul.'.$m)]);
        $year = Carbon::today()->year;

        return response()->json(['data' => [
            'daerah' => $daerah, 'mukim' => $mukim, 'kategori' => $kategori, 'bahagian' => $bahagian, 'modul' => $modul,
            'tahun' => range($year, $year - 9),
            'status' => collect(['aktif' => 'Aktif', 'draf' => 'Draf', 'tidak_aktif' => 'Tidak aktif'])->map(fn ($v, $k) => ['kod' => $k, 'nama' => $v])->values(),
        ]]);
    }

    /** GET /eksport — the current dashboard as PDF / Excel (SRS-DSH-14), audited with its filters. */
    public function export(Request $request, Eksport $eksport, Ringkasan $ringkasan): Response
    {
        /** @var User $user */
        $user = $request->user();
        $request->validate(['format' => ['required', 'in:pdf,xlsx']]);
        $aras = $this->resolveAras($request, $user);
        $filters = Penapis::fromRequest($request);
        $kpis = $ringkasan->compute($user, $aras, $filters);
        $format = (string) $request->query('format');
        $this->audit->record('eksport', 'dsh', 'dashboard', $aras, meta: ['format' => $format, 'penapis' => $filters, 'bil_kpi' => count($kpis)]);

        return $eksport->download($format, $user, $aras, $kpis, $filters);
    }

    /** @return array<string, list<array{kod: string, nama: string}>|null> */
    private function scopeLabels(User $user): array
    {
        $scope = $this->skop->scopes($user);
        $out = [];
        foreach (['daerah' => 'ref_daerah', 'mukim' => 'ref_mukim'] as $type => $table) {
            $out[$type] = $scope[$type] === null ? null : Baca::db()->table($table)->whereIn('kod', $scope[$type])->orderBy('nama')->get(['kod', 'nama'])
                ->map(fn ($r) => ['kod' => (string) $r->kod, 'nama' => (string) $r->nama])->all();
        }
        $out['bahagian'] = $scope['bahagian'] === null ? null : array_map(fn ($b) => ['kod' => $b, 'nama' => ucfirst($b)], $scope['bahagian']);

        return $out;
    }

    /** GET /portal — internal-portal quick links the user may use (SRS-DSH-19). */
    public function portal(Request $request, AccessControl $access): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $links = [];
        foreach (self::PORTAL as [$label, $href, $permission, $kumpulan]) {
            if ($permission === null || $access->allows($user, $permission)) {
                $links[] = ['label' => $label, 'href' => $href, 'kumpulan' => $kumpulan];
            }
        }

        return response()->json(['data' => $links]);
    }

    /** Internal portal entries: [label, frontend path, permission, group]. */
    private const PORTAL = [
        ['Dashboard Eksekutif', '/admin', 'dsh.dashboard.lihat', 'Dashboard'],
        ['Inbox Tugasan', '/admin/dsh/inbox', 'dsh.dashboard.lihat', 'Tugasan'],
        ['Papan Tugasan Workflow', '/admin/wfe/tugasan', 'wfe.tugasan.lihat', 'Tugasan'],
        ['Carian Dokumen & Rujukan', '/admin/dsh/carian', null, 'Carian'],
        ['Lot Tanah & Profil 360°', '/admin/pro/lot', 'pro.lot.lihat', 'Kemasukan & Semakan Data'],
        ['Hak Milik', '/admin/hkm/hak-milik', 'hkm.hak_milik.lihat', 'Kemasukan & Semakan Data'],
        ['Pemerolehan', '/admin/per', 'per.kes.lihat', 'Kemasukan & Semakan Data'],
        ['Pajakan & Sewaan', '/admin/pjk', 'pjk.kontrak.lihat', 'Kemasukan & Semakan Data'],
        ['Cukai Tanah', '/admin/cki', 'cki.bil.lihat', 'Kemasukan & Semakan Data'],
        ['Aduan', '/admin/adu', 'adu.aduan.lihat', 'Kemasukan & Semakan Data'],
        ['Dokumen Digital', '/admin/dms', 'dms.dokumen.lihat', 'Kemasukan & Semakan Data'],
        ['Peta GIS', '/admin/gis', 'gis.peta.lihat', 'Peta'],
        ['Dashboard Cukai', '/admin/dsh/tema/cukai', 'cki.laporan.lihat', 'Laporan'],
        ['Dashboard Pajakan', '/admin/dsh/tema/pajakan', 'pjk.laporan.lihat', 'Laporan'],
        ['Dashboard Pemantauan Tanah', '/admin/dsh/tema/pemantauan', 'dsh.dashboard.lihat', 'Laporan'],
        ['Bantuan', '/admin/dsh/bantuan', null, 'Bantuan'],
    ];
}
