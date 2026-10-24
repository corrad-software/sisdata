<?php

namespace App\Modules\Pjk\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Support\Kod;
use App\Modules\Pjk\Support\Wang;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Daily batch (`pjk:harian`): contract status refresh (SRS-PJK-02/03),
 * posting of due charges (SRS-PJK-12), late-payment penalties and arrears
 * notifications at the configured ageing thresholds.
 */
class Harian
{
    public function __construct(
        private KontrakService $kontrak,
        private JadualCajService $jadual,
        private Notifier $notifier,
        private Parameters $parameters,
    ) {}

    /** @return array<string, int> */
    public function run(?Carbon $today = null): array
    {
        $today ??= Carbon::today();
        $status = $this->kontrak->refreshStatuses($today);
        $issued = $this->jadual->issueDue($today);
        $penalties = $this->jadual->penalties($today);
        $notices = $this->arrears($today);

        return [...$status, 'caj_dikeluarkan' => $issued, 'penalti' => $penalties, 'notis_tunggakan' => $notices];
    }

    /** One arrears notice per contract each time its oldest debt crosses a threshold (pjk.ambang_tunggakan_hari). */
    public function arrears(Carbon $today): int
    {
        $thresholds = array_map('intval', (array) $this->parameters->get('pjk.ambang_tunggakan_hari', [30, 60, 90]));
        rsort($thresholds);
        $rows = DB::table('jadual_caj')->where('dikeluarkan', true)->whereIn('status_caj', ['belum_bayar', 'separa'])->whereDate('tarikh_akhir', '<', $today)
            ->groupBy('kontrak_id')->selectRaw('kontrak_id, min(tarikh_akhir) as tertua, sum(amaun - amaun_dibayar - amaun_dikecualikan)::text as baki')->get();
        $sent = 0;
        foreach ($rows as $r) {
            $age = (int) Carbon::parse($r->tertua)->diffInDays($today);
            $hit = null;
            foreach ($thresholds as $t) {
                if ($age >= $t) {
                    $hit = $t;
                    break;
                }
            }
            if ($hit === null || Wang::sen((string) $r->baki) <= 0) {
                continue;
            }
            $k = Kontrak::query()->withoutGlobalScope('access')->find($r->kontrak_id);
            if ($k === null || ! in_array($k->status, [...Kod::BERKUAT_KUASA, 'tamat_tempoh', 'ditamatkan'], true)) {
                continue;
            }
            $officer = $k->id_pendaftar ? User::query()->find($k->id_pendaftar) : null;
            try {
                $this->notifier->send('pjk.tunggakan', $officer ? [$officer] : [], [
                    'no_kontrak' => (string) $k->no_kontrak, 'kod_daerah' => (string) $k->kod_daerah,
                    'amaun' => Wang::rm((string) $r->baki), 'hari' => $hit,
                ], '/admin/pjk/kontrak/'.$k->id.'?tab=caj', 'pjk.tunggakan:'.$k->id.':'.$hit);
                $sent++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $sent;
    }
}
