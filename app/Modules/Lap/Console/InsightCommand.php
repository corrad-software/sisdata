<?php

namespace App\Modules\Lap\Console;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Lap\Models\InsightEksekutif;
use App\Modules\Lap\Services\Insight;
use App\Modules\Ntf\Contracts\Notifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * SRS-LAP-20: state-wide executive insight at the frequency MAIS sets
 * (lap.insight_kekerapan: mingguan | bulanan | suku_tahun), announced to the
 * management recipients configured for the NTF type.
 */
class InsightCommand extends Command
{
    protected $signature = 'lap:insight-eksekutif {--paksa : jana walaupun belum tiba masanya}';

    protected $description = 'Jana executive insight summary MOD-LAP mengikut kekerapan yang ditetapkan';

    public function handle(Insight $insight, Parameters $parameters, Notifier $notifier): int
    {
        $freq = (string) $parameters->get('lap.insight_kekerapan', 'bulanan');
        $last = InsightEksekutif::query()->whereNull('id_pengguna')->where('sumber', 'berjadual')->max('tarikh_jana');
        $due = match ($freq) {
            'mingguan' => Carbon::now()->subWeek(),
            'suku_tahun' => Carbon::now()->subMonthsNoOverflow(3),
            default => Carbon::now()->subMonthNoOverflow(),
        };
        if (! $this->option('paksa') && $last && Carbon::parse((string) $last)->gt($due)) {
            $this->line('Belum tiba masanya.');

            return self::SUCCESS;
        }
        $i = $insight->generate(null);
        try {
            $notifier->send('lap.insight_eksekutif', [], [
                'tempoh' => $i->tempoh, 'bil_isu' => count($i->isu_utama), 'bil_risiko' => count($i->risiko), 'bil_tindakan' => count($i->tindakan_segera),
            ], '/admin/lap/insight', 'lap.insight.'.$i->id);
        } catch (Throwable $e) {
            report($e);
        }
        $this->info("Insight #{$i->id} dijana.");

        return self::SUCCESS;
    }
}
