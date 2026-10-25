<?php

namespace App\Modules\Lap\Services;

use App\Models\User;
use App\Modules\Lap\Models\InsightEksekutif;
use App\Modules\Lap\Support\SetData;
use App\Modules\Lap\Support\Wang;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Executive insight summary (SRS-LAP-20): main issues, risks and immediate
 * actions for management, composed by rules from scoped aggregates of the
 * standard datasets (no external AI — SRS-LAP-21). The scheduled one is
 * state-wide (system scope); a user may generate one for their own scope.
 */
class Insight
{
    public function __construct(private Enjin $enjin, private Skop $skop, private AccessControl $access) {}

    public function generate(?User $user, string $source = 'berjadual'): InsightEksekutif
    {
        $now = Carbon::now();
        $s = [];
        $s['tunggakan_sewa'] = $this->value($user, 'caj_sewaan', [['fungsi' => 'jumlah', 'medan' => 'baki']], [['medan' => 'tertunggak', 'op' => 'benar']], 'jumlah_baki');
        $s['tunggakan_cukai'] = $this->value($user, 'cukai', [['fungsi' => 'jumlah', 'medan' => 'baki']], [['medan' => 'tertunggak', 'op' => 'benar']], 'jumlah_baki');
        $s['tunggakan_lebih_setahun'] = $this->value($user, 'caj_sewaan', [['fungsi' => 'jumlah', 'medan' => 'baki']], [['medan' => 'tertunggak', 'op' => 'benar'], ['medan' => 'umur_tunggakan_hari', 'op' => 'lebih', 'nilai' => '365']], 'jumlah_baki');
        $s['lot_risiko_merah'] = $this->value($user, 'risiko', [['fungsi' => 'bilangan']], [['medan' => 'kod_warna', 'op' => 'sama', 'nilai' => 'merah']]);
        $s['peratus_sla'] = $this->value($user, 'tugasan', [['fungsi' => 'peratus_benar', 'medan' => 'dalam_sla']], [], 'peratus_dalam_sla', ['pratetap' => '30_hari']);
        $s['tugasan_lewat'] = $this->value($user, 'tugasan', [['fungsi' => 'bilangan_benar', 'medan' => 'lewat_terbuka']], [], 'bilangan_lewat_terbuka');
        $s['aduan_terbuka'] = $this->value($user, 'aduan', [['fungsi' => 'bilangan']], [['medan' => 'terbuka', 'op' => 'benar']]);
        $s['aduan_bulan_ini'] = $this->value($user, 'aduan', [['fungsi' => 'bilangan']], [], 'bilangan', ['pratetap' => 'bulan_ini']);
        $last = Carbon::today()->subMonthNoOverflow();
        $s['aduan_bulan_lepas'] = $this->value($user, 'aduan', [['fungsi' => 'bilangan']], [], 'bilangan', ['dari' => $last->copy()->startOfMonth()->toDateString(), 'hingga' => $last->copy()->endOfMonth()->toDateString()]);
        $s['kontrak_tamat_90'] = $this->value($user, 'pajakan', [['fungsi' => 'bilangan']], [['medan' => 'status', 'op' => 'dalam', 'nilai' => ['aktif', 'hampir_tamat']], ['medan' => 'baki_hari', 'op' => 'antara', 'nilai' => ['0', '90']]]);
        $s['kes_aktif'] = $this->value($user, 'kes', [['fungsi' => 'bilangan']], [['medan' => 'aktif', 'op' => 'benar']]);
        $s['isu_pencerobohan'] = $this->value($user, 'pemantauan', [['fungsi' => 'bilangan']], [['medan' => 'terbuka', 'op' => 'benar'], ['medan' => 'kategori_isu', 'op' => 'dalam', 'nilai' => ['pencerobohan', 'pendudukan_haram', 'binaan_haram']]]);
        $s['analitik_kritikal'] = $this->value($user, 'analitik', [['fungsi' => 'bilangan']], [['medan' => 'aras_risiko', 'op' => 'sama', 'nilai' => 'kritikal']]);

        $issues = [];
        $risks = [];
        $actions = [];
        $arrears = ($s['tunggakan_sewa'] !== null || $s['tunggakan_cukai'] !== null) ? Wang::jumlah([$s['tunggakan_sewa'] ?? 0, $s['tunggakan_cukai'] ?? 0]) : null;
        if ($arrears !== null && bccomp($arrears, '0', 2) > 0) {
            $issues[] = ['tahap' => 'kritikal', 'teks' => __('lap::lap.insight.tunggakan', ['jumlah' => Wang::papar($arrears)]), 'pautan' => '/admin/lap/laporan/KEW-02'];
            $actions[] = ['teks' => __('lap::lap.insight.tindakan_tunggakan'), 'pautan' => '/admin/lap/analitik?jenis=risiko_tunggakan'];
        }
        if ($s['tunggakan_lebih_setahun'] !== null && bccomp((string) $s['tunggakan_lebih_setahun'], '0', 2) > 0) {
            $risks[] = ['tahap' => 'tinggi', 'teks' => __('lap::lap.insight.tunggakan_lama', ['jumlah' => Wang::papar($s['tunggakan_lebih_setahun'])])];
        }
        if ((int) ($s['lot_risiko_merah'] ?? 0) > 0) {
            $risks[] = ['tahap' => 'kritikal', 'teks' => __('lap::lap.insight.risiko_merah', ['n' => (int) $s['lot_risiko_merah']]), 'pautan' => '/admin/lap/laporan/RIS-01'];
        }
        if ($s['peratus_sla'] !== null && (float) $s['peratus_sla'] < 90) {
            $issues[] = ['tahap' => 'perhatian', 'teks' => __('lap::lap.insight.sla', ['peratus' => $s['peratus_sla'], 'lewat' => (int) $s['tugasan_lewat']]), 'pautan' => '/admin/lap/laporan/OPS-01'];
            $actions[] = ['teks' => __('lap::lap.insight.tindakan_sla'), 'pautan' => '/admin/wfe/pemantauan'];
        }
        if ($s['aduan_bulan_ini'] !== null && (int) $s['aduan_bulan_lepas'] > 0 && (int) $s['aduan_bulan_ini'] > (int) $s['aduan_bulan_lepas'] * 1.2) {
            $issues[] = ['tahap' => 'perhatian', 'teks' => __('lap::lap.insight.aduan_naik', ['ini' => (int) $s['aduan_bulan_ini'], 'lepas' => (int) $s['aduan_bulan_lepas']]), 'pautan' => '/admin/lap/laporan/OPS-03'];
        }
        if ((int) ($s['kontrak_tamat_90'] ?? 0) > 0) {
            $risks[] = ['tahap' => 'tinggi', 'teks' => __('lap::lap.insight.kontrak_tamat', ['n' => (int) $s['kontrak_tamat_90']]), 'pautan' => '/admin/lap/laporan/RIS-02'];
            $actions[] = ['teks' => __('lap::lap.insight.tindakan_kontrak'), 'pautan' => '/admin/pjk/laporan?jenis=hampir_tamat'];
        }
        if ((int) ($s['isu_pencerobohan'] ?? 0) > 0) {
            $risks[] = ['tahap' => 'tinggi', 'teks' => __('lap::lap.insight.pencerobohan', ['n' => (int) $s['isu_pencerobohan']])];
            $actions[] = ['teks' => __('lap::lap.insight.tindakan_pencerobohan'), 'pautan' => '/admin/ptn/isu'];
        }
        if ((int) ($s['kes_aktif'] ?? 0) > 0) {
            $issues[] = ['tahap' => 'maklumat', 'teks' => __('lap::lap.insight.kes', ['n' => (int) $s['kes_aktif']]), 'pautan' => '/admin/lap/laporan/PEM-02'];
        }
        if ((int) ($s['analitik_kritikal'] ?? 0) > 0) {
            $actions[] = ['teks' => __('lap::lap.insight.tindakan_analitik', ['n' => (int) $s['analitik_kritikal']]), 'pautan' => '/admin/lap/analitik'];
        }
        if ($issues === [] && $risks === []) {
            $issues[] = ['tahap' => 'baik', 'teks' => __('lap::lap.insight.tiada_isu')];
        }

        return InsightEksekutif::query()->create([
            'tempoh' => $now->format('Y-m'), 'skop' => $user ? $this->skop->scopes($user) : null, 'isu_utama' => $issues, 'risiko' => $risks, 'tindakan_segera' => $actions,
            'statistik' => array_filter($s, fn ($v) => $v !== null), 'sumber' => $source, 'id_pengguna' => $user?->id, 'tarikh_jana' => $now,
        ]);
    }

    private function value(?User $user, string $set, array $agg, array $filters, string $key = 'bilangan', ?array $period = null): mixed
    {
        $meta = SetData::get($set);
        if ($meta === null || ($user && ! $this->access->allows($user, $meta['kebenaran']))) {
            return null;
        }
        $def = ['set' => $set, 'kumpulan' => [], 'agregat' => $agg, 'penapis' => $filters];
        if ($period !== null) {
            $def['tempoh'] = ['medan' => $meta['tarikh'], ...$period];
        }
        try {
            $res = $this->enjin->aggregate($user, $this->enjin->normalise($def, $user));
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        return $res[$key] ?? null;
    }
}
