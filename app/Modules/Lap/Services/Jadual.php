<?php

namespace App\Modules\Lap\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\JobProgress;
use App\Modules\Lap\Models\DefinisiLaporan;
use App\Modules\Lap\Models\JadualPenjanaan;
use App\Modules\Lap\Models\KatalogLaporan;
use App\Modules\Ntf\Contracts\Notifier;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Runs due report schedules (SRS-LAP-06): each recipient's copy is generated
 * under that recipient's permissions and scope ("kebenaran penerima, bukan
 * pencipta"), stored privately and announced by e-mail / in-app through
 * MOD-NTF with a link — report content never travels in the notification
 * (SRS-NTF-19).
 */
class Jadual
{
    public function __construct(private Penjana $penjana, private Katalog $katalog, private Notifier $notifier, private JobProgress $progress) {}

    public function recipientMayOpen(User $user, ?string $definisi, ?string $kod): bool
    {
        if (! $user->is_active) {
            return false;
        }
        if ($definisi) {
            $d = DefinisiLaporan::query()->find($definisi);

            return $d !== null && $this->katalog->canOpenDefinition($user, $d);
        }
        $e = KatalogLaporan::query()->where('kod_laporan', (string) $kod)->first();

        return $e !== null && $this->katalog->canOpen($user, $e);
    }

    /** @return array{jadual: int, dihantar: int, ditolak: int, gagal: int} */
    public function runDue(): array
    {
        $totals = ['jadual' => 0, 'dihantar' => 0, 'ditolak' => 0, 'gagal' => 0];
        $due = JadualPenjanaan::query()->where('status_aktif', true)->whereNotNull('jalan_berikutnya')->where('jalan_berikutnya', '<=', Carbon::now())->orderBy('jalan_berikutnya')->get();
        foreach ($due as $j) {
            $r = $this->run($j);
            $totals['jadual']++;
            foreach (['dihantar', 'ditolak', 'gagal'] as $k) {
                $totals[$k] += $r[$k];
            }
        }

        return $totals;
    }

    /** @return array{dihantar: int, ditolak: int, gagal: int} */
    public function run(JadualPenjanaan $j, bool $advance = true): array
    {
        $this->katalog->sync();
        $source = $j->id_definisi ? ['definisi' => $j->id_definisi] : ['katalog' => (string) $j->kod_laporan];
        $out = ['dihantar' => 0, 'ditolak' => 0, 'gagal' => 0];
        $users = User::query()->whereIn('id', (array) $j->senarai_penerima)->get()->keyBy('id');
        foreach ((array) $j->senarai_penerima as $id) {
            $user = $users[$id] ?? null;
            if (! $user || ! $user->is_active) {
                $out['ditolak']++;

                continue;
            }
            $log = $this->penjana->queuedLog($user, $source, (array) $j->parameter, $j->format, 'berjadual', $j->nama, $j->id);
            $log = $this->penjana->toFile($log, $user);
            $this->progress->processed();
            if ($log->status !== 'berjaya') {
                $out[$log->status === 'ditolak' ? 'ditolak' : 'gagal']++;

                continue;
            }
            try {
                $this->notifier->send('lap.laporan_berjadual', $user, [
                    'tajuk' => $j->nama, 'kekerapan' => __('lap::lap.kekerapan.'.$j->kekerapan), 'tarikh' => Carbon::now()->format('d/m/Y H:i'),
                    'format' => strtoupper($j->format), 'bilangan_rekod' => $log->bilangan_rekod,
                ], '/admin/lap/log?id='.$log->id, 'lap.jadual.'.$log->id);
                $out['dihantar']++;
            } catch (Throwable $e) {
                report($e);
                $out['gagal']++;
            }
        }
        $j->jalan_terakhir = Carbon::now();
        $j->status_terakhir = $out['gagal'] > 0 ? 'gagal' : ($out['ditolak'] > 0 ? 'separa' : 'berjaya');
        if ($advance) {
            $j->jalan_berikutnya = $j->nextRun(Carbon::now());
        }
        $j->save();

        return $out;
    }
}
