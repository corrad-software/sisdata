<?php

namespace App\Modules\Rbc\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Rbc\Contracts\AccountStatus;
use App\Modules\Rbc\Exports\TabularExport;
use App\Modules\Rbc\Models\Peranan;
use App\Modules\Rbc\Services\PermissionCatalogue;
use App\Modules\Rbc\Services\SecurityPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * SRS-RBC-26 access reports: users by status / role / bahagian, inactive
 * and expired accounts, permissions per role, failed logins by user and IP.
 */
class ReportController extends Controller
{
    public function __construct(private SecurityPolicy $policy, private PermissionCatalogue $catalogue) {}

    public function summary(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->build($request)]);
    }

    public function export(Request $request, AuditLogger $audit): Response
    {
        $format = $request->query('format') === 'pdf' ? 'pdf' : 'xlsx';
        $report = $this->build($request);
        $status = fn (string $s) => __('rbc::rbc.status.'.$s);

        $sections = [
            [
                'tajuk' => __('rbc::rbc.laporan.mengikut_status'),
                'lajur' => [__('rbc::rbc.laporan.status'), __('rbc::rbc.laporan.bilangan')],
                'baris' => collect($report['mengikut_status'])->map(fn ($n, $s) => [$status($s), (string) $n])->values()->all(),
            ],
            [
                'tajuk' => __('rbc::rbc.laporan.mengikut_peranan'),
                'lajur' => [__('rbc::rbc.laporan.peranan'), __('rbc::rbc.laporan.aktif'), __('rbc::rbc.laporan.jumlah')],
                'baris' => collect($report['mengikut_peranan'])->map(fn ($r) => [$r['nama'], (string) $r['aktif'], (string) $r['jumlah']])->all(),
            ],
            [
                'tajuk' => __('rbc::rbc.laporan.mengikut_bahagian'),
                'lajur' => [__('rbc::rbc.laporan.bahagian'), __('rbc::rbc.laporan.aktif'), __('rbc::rbc.laporan.jumlah')],
                'baris' => collect($report['mengikut_bahagian'])->map(fn ($r) => [$r['bahagian'] ?? '-', (string) $r['aktif'], (string) $r['jumlah']])->all(),
            ],
            [
                'tajuk' => __('rbc::rbc.laporan.tidak_aktif'),
                'lajur' => [__('rbc::rbc.laporan.nama'), __('rbc::rbc.laporan.emel'), __('rbc::rbc.laporan.status'), __('rbc::rbc.laporan.log_masuk_akhir'), __('rbc::rbc.laporan.tamat_capaian')],
                'baris' => collect($report['pengguna_tidak_aktif'])->map(fn ($u) => [$u['name'], $u['email'], $status($u['status_akaun']), $u['tarikh_log_masuk_akhir'] ?? '-', $u['tarikh_tamat_capaian'] ?? '-'])->all(),
            ],
            [
                'tajuk' => __('rbc::rbc.laporan.kebenaran_peranan'),
                'lajur' => [__('rbc::rbc.laporan.peranan'), __('rbc::rbc.laporan.kebenaran'), __('rbc::rbc.laporan.label')],
                'baris' => collect($report['kebenaran_peranan'])->flatMap(fn ($r) => collect($r['kebenaran'])->map(fn ($k) => [$r['nama'], $k, $this->catalogue->flat()[$k] ?? '']))->values()->all(),
            ],
            [
                'tajuk' => __('rbc::rbc.laporan.gagal_pengguna'),
                'lajur' => [__('rbc::rbc.laporan.pengecam'), __('rbc::rbc.laporan.bilangan'), __('rbc::rbc.laporan.terakhir')],
                'baris' => collect($report['gagal_mengikut_pengguna'])->map(fn ($r) => [$r['pengecam'], (string) $r['bilangan'], $r['terakhir']])->all(),
            ],
            [
                'tajuk' => __('rbc::rbc.laporan.gagal_ip'),
                'lajur' => [__('rbc::rbc.laporan.alamat_ip'), __('rbc::rbc.laporan.bilangan'), __('rbc::rbc.laporan.terakhir')],
                'baris' => collect($report['gagal_mengikut_ip'])->map(fn ($r) => [$r['alamat_ip'], (string) $r['bilangan'], $r['terakhir']])->all(),
            ],
        ];

        $audit->record('eksport', 'rbc', 'laporan_capaian', null, meta: ['format' => $format, 'tempoh_hari' => $report['tempoh_hari']]);

        return TabularExport::download($format, 'laporan-capaian-'.now()->format('Ymd-His'), __('rbc::rbc.laporan.tajuk'), $sections,
            __('rbc::rbc.dijana_oleh', ['nama' => $request->user()?->name, 'tarikh' => now()->format('d/m/Y H:i')]));
    }

    /** @return array<string, mixed> */
    private function build(Request $request): array
    {
        $days = min(365, max(1, $request->integer('hari', 30)));
        $since = Carbon::now()->subDays($days);
        $inactiveDays = max(1, $this->policy->int('tidak_aktif_gantung_hari') ?: 90);

        $byStatus = array_fill_keys(AccountStatus::ALL, 0);
        foreach (DB::table('users')->select('status_akaun', DB::raw('count(*) as n'))->groupBy('status_akaun')->get() as $row) {
            $byStatus[$row->status_akaun] = (int) $row->n;
        }

        $byRole = Peranan::query()->orderBy('id')->get()->map(fn (Peranan $role) => [
            'id' => $role->id,
            'kod' => $role->kod,
            'nama' => $role->nama,
            'jumlah' => DB::table('pengguna_peranan')->where('peranan_id', $role->id)->count(),
            'aktif' => DB::table('pengguna_peranan')->join('users', 'users.id', '=', 'pengguna_peranan.user_id')
                ->where('peranan_id', $role->id)->where('users.status_akaun', AccountStatus::AKTIF)->count(),
        ])->all();

        $byDivision = DB::table('users')
            ->select('bahagian', DB::raw('count(*) as jumlah'), DB::raw("sum(case when status_akaun = 'aktif' then 1 else 0 end) as aktif"))
            ->groupBy('bahagian')->orderBy('bahagian')->get()
            ->map(fn ($r) => ['bahagian' => $r->bahagian, 'jumlah' => (int) $r->jumlah, 'aktif' => (int) $r->aktif])->all();

        $inactive = User::query()
            ->where(fn ($q) => $q->where('status_akaun', '!=', AccountStatus::AKTIF)
                ->orWhere('tarikh_log_masuk_akhir', '<', Carbon::now()->subDays($inactiveDays))
                ->orWhere(fn ($w) => $w->whereNull('tarikh_log_masuk_akhir')->where('created_at', '<', Carbon::now()->subDays($inactiveDays))))
            ->orderBy('name')->limit(500)->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'status_akaun' => $u->status_akaun,
                'tarikh_log_masuk_akhir' => $u->tarikh_log_masuk_akhir?->format('d/m/Y H:i'),
                'tarikh_tamat_capaian' => $u->tarikh_tamat_capaian?->format('d/m/Y'),
            ])->all();

        $permissionsPerRole = Peranan::query()->orderBy('id')->get()->map(fn (Peranan $role) => [
            'kod' => $role->kod,
            'nama' => $role->nama,
            'kebenaran' => $role->kebenaranKeys(),
        ])->all();

        $failedByUser = DB::table('log_log_masuk')->where('berjaya', false)->where('created_at', '>=', $since)
            ->select('pengecam', DB::raw('count(*) as bilangan'), DB::raw('max(created_at) as terakhir'))
            ->groupBy('pengecam')->orderByDesc('bilangan')->limit(100)->get()
            ->map(fn ($r) => ['pengecam' => $r->pengecam ?? '-', 'bilangan' => (int) $r->bilangan, 'terakhir' => Carbon::parse($r->terakhir)->format('d/m/Y H:i')])->all();

        $failedByIp = DB::table('log_log_masuk')->where('berjaya', false)->where('created_at', '>=', $since)
            ->select('alamat_ip', DB::raw('count(*) as bilangan'), DB::raw('max(created_at) as terakhir'))
            ->groupBy('alamat_ip')->orderByDesc('bilangan')->limit(100)->get()
            ->map(fn ($r) => ['alamat_ip' => $r->alamat_ip ?? '-', 'bilangan' => (int) $r->bilangan, 'terakhir' => Carbon::parse($r->terakhir)->format('d/m/Y H:i')])->all();

        return [
            'tempoh_hari' => $days,
            'had_tidak_aktif_hari' => $inactiveDays,
            'mengikut_status' => $byStatus,
            'mengikut_peranan' => $byRole,
            'mengikut_bahagian' => $byDivision,
            'pengguna_tidak_aktif' => $inactive,
            'kebenaran_peranan' => $permissionsPerRole,
            'gagal_mengikut_pengguna' => $failedByUser,
            'gagal_mengikut_ip' => $failedByIp,
        ];
    }
}
