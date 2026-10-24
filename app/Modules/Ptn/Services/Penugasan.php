<?php

namespace App\Modules\Ptn\Services;

use App\Models\User;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Ntf\Contracts\RecipientDirectory;
use App\Modules\Ptn\Models\JadualPemantauan;
use App\Modules\Ptn\Support\Rujukan;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Process step 2: visit tasks assigned to field officers by district
 * (manually or round-robin by current workload). Eligible = active user with
 * ptn.lapangan.kemas_kini whose daerah scope covers the lot.
 */
class Penugasan
{
    public function __construct(private RecipientDirectory $directory, private AccessControl $access, private Notifier $notifier, private Rujukan $ref) {}

    /** @return list<User> */
    public function pegawaiLayak(?string $kodDaerah): array
    {
        return array_values(array_filter($this->directory->resolve(RecipientDirectory::KEBENARAN, 'ptn.lapangan.kemas_kini'), function (User $u) use ($kodDaerah) {
            $scope = $this->access->scope($u, 'daerah');

            return $u->is_active && ($kodDaerah === null || $scope === null || in_array($kodDaerah, $scope, true));
        }));
    }

    public function tugaskan(JadualPemantauan $jadual, User $pegawai, User $by): JadualPemantauan
    {
        if (! $jadual->terbuka()) {
            throw ValidationException::withMessages(['jadual' => __('ptn::ptn.jadual_tutup')]);
        }
        if (! collect($this->pegawaiLayak($jadual->kod_daerah))->contains(fn (User $u) => $u->id === $pegawai->id)) {
            throw ValidationException::withMessages(['id_pegawai' => __('ptn::ptn.pegawai_tidak_layak')]);
        }
        $jadual->forceFill([
            'id_pegawai_bertugas' => $pegawai->id,
            'status_pelaksanaan' => $jadual->status_pelaksanaan === 'dirancang' ? 'ditugaskan' : $jadual->status_pelaksanaan,
            'versi' => $jadual->versi + 1,
        ])->save();
        $this->maklum($jadual, $pegawai);

        return $jadual;
    }

    /**
     * Round-robin among eligible officers of each district, least loaded first.
     *
     * @param  array{kod_daerah?: string|null, tahun?: int|null, hingga?: string|null}  $f
     */
    public function agih(array $f, User $by): int
    {
        $rows = JadualPemantauan::query()->whereNull('id_pegawai_bertugas')->where('status_pelaksanaan', 'dirancang')
            ->when($f['kod_daerah'] ?? null, fn ($q, $v) => $q->where('kod_daerah', $v))
            ->when($f['tahun'] ?? null, fn ($q, $v) => $q->where('tahun', $v))
            ->when($f['hingga'] ?? null, fn ($q, $v) => $q->where('tarikh_sasaran', '<=', $v))
            ->orderBy('tarikh_sasaran')->limit(2000)->get();
        $count = 0;
        foreach ($rows->groupBy('kod_daerah') as $daerah => $tasks) {
            $officers = $this->pegawaiLayak((string) $daerah);
            if ($officers === []) {
                continue;
            }
            $load = DB::table('jadual_pemantauan')->whereIn('id_pegawai_bertugas', array_map(fn ($u) => $u->id, $officers))
                ->whereIn('status_pelaksanaan', JadualPemantauan::TERBUKA)->groupBy('id_pegawai_bertugas')
                ->selectRaw('id_pegawai_bertugas, COUNT(*) AS n')->get()->mapWithKeys(fn ($r) => [$r->id_pegawai_bertugas => $r->n])->all();
            $assigned = [];
            foreach ($tasks as $task) {
                usort($officers, fn (User $a, User $b) => ((int) ($load[$a->id] ?? 0)) <=> ((int) ($load[$b->id] ?? 0)) ?: $a->id <=> $b->id);
                $officer = $officers[0];
                $task->forceFill(['id_pegawai_bertugas' => $officer->id, 'status_pelaksanaan' => 'ditugaskan', 'versi' => $task->versi + 1])->save();
                $load[$officer->id] = (int) ($load[$officer->id] ?? 0) + 1;
                $assigned[$officer->id][] = $task;
                $count++;
            }
            foreach ($assigned as $id => $list) {
                $officer = collect($officers)->first(fn (User $u) => $u->id === $id);
                if ($officer) {
                    $this->notifier->send('ptn.tugasan_baharu', $officer, ['bilangan' => count($list), 'no_lot' => '—', 'tarikh_sasaran' => $list[0]->tarikh_sasaran->format('d/m/Y'), 'daerah' => (string) $this->ref->namaDaerah((string) $daerah)], '/admin/ptn/lapangan', 'ptn.agih:'.$id.':'.now()->toDateString());
                }
            }
        }

        return $count;
    }

    private function maklum(JadualPemantauan $jadual, User $pegawai): void
    {
        $lot = DB::table('lot_tanah')->where('id', $jadual->lot_id)->value('no_lot');
        $this->notifier->send('ptn.tugasan_baharu', $pegawai, [
            'bilangan' => 1, 'no_lot' => (string) $lot, 'tarikh_sasaran' => $jadual->tarikh_sasaran->format('d/m/Y'), 'daerah' => (string) $this->ref->namaDaerah($jadual->kod_daerah),
        ], '/admin/ptn/lapangan', 'ptn.tugas:'.$jadual->id.':'.$jadual->versi);
    }
}
