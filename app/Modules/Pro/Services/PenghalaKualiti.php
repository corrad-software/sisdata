<?php

namespace App\Modules\Pro\Services;

use App\Models\User;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Pro\Models\StewardshipData;
use App\Modules\Pro\Models\TugasanKualitiData;
use Illuminate\Support\Collection;
use Throwable;

/**
 * SRS-PRO-12: records failing data-quality control go to the responsible
 * data steward as a task (tugasan_kualiti_data) plus an NTF notification;
 * the steward records the corrective action taken.
 *
 * Steward resolution, most specific first: the lot's own id_data_steward →
 * a register entry for the entity limited to the lot's bahagian AND daerah →
 * bahagian only → daerah only → the entity's general entry.
 */
class PenghalaKualiti
{
    public function __construct(private Notifier $notifier) {}

    /** @return array{stewardship: StewardshipData|null, user: User|null} */
    public function stewardFor(string $entity, ?LotTanah $lot = null): array
    {
        $entries = StewardshipData::query()->where('status', 'aktif')
            ->where(fn ($q) => $q->where('entiti_berkaitan', $entity)
                ->orWhere('entiti_berkaitan', 'like', $entity.',%')
                ->orWhere('entiti_berkaitan', 'like', '%,'.$entity)
                ->orWhere('entiti_berkaitan', 'like', '%,'.$entity.',%'))
            ->get();

        $ranked = $entries
            // Without a lot (e.g. an import session) every entry of the entity qualifies.
            ->filter(fn ($e) => $lot === null || (($e->bahagian_pemilik === null || $e->bahagian_pemilik === $lot->bahagian_pemilik)
                && ($e->kod_daerah === null || $e->kod_daerah === $lot->kod_daerah)))
            ->sortByDesc(fn ($e) => ($e->bahagian_pemilik !== null ? 2 : 0) + ($e->kod_daerah !== null ? 1 : 0) + ($e->id_data_steward !== null ? 4 : 0))
            ->values();

        /** @var StewardshipData|null $entry */
        $entry = $ranked->first();
        $user = null;
        if ($lot?->id_data_steward) {
            $user = User::query()->find($lot->id_data_steward);
        }
        if ($user === null && $entry?->id_data_steward) {
            $user = User::query()->find($entry->id_data_steward);
        }

        return ['stewardship' => $entry, 'user' => $user];
    }

    /**
     * @param  list<array{medan: string, label: string, kod: string, mesej: string, tahap: string, nilai?: mixed}>  $issues
     * @return Collection<int, TugasanKualitiData> tasks created now (already-open duplicates are skipped)
     */
    public function route(string $entity, string $recordId, array $issues, ?LotTanah $lot = null, string $source = 'semakan'): Collection
    {
        if ($issues === []) {
            return collect();
        }

        ['stewardship' => $entry, 'user' => $steward] = $this->stewardFor($entity, $lot);
        $created = collect();

        foreach ($issues as $issue) {
            $code = $issue['medan'].'.'.$issue['kod'];
            $exists = TugasanKualitiData::query()->where([
                'entiti' => $entity, 'id_rekod' => $recordId, 'kod_isu' => $code, 'status' => 'terbuka',
            ])->exists();
            if ($exists) {
                continue;
            }

            $created->push(TugasanKualitiData::query()->create([
                'entiti' => $entity,
                'id_rekod' => $recordId,
                'kod_isu' => $code,
                'keterangan' => $issue['label'].': '.$issue['mesej'],
                'tahap_keterukan' => $issue['tahap'],
                'id_stewardship' => $entry?->id,
                'id_data_steward' => $steward?->id,
                'kod_daerah' => $lot?->kod_daerah,
                'status' => 'terbuka',
                'sumber' => $source,
            ]));
        }

        if ($created->isNotEmpty() && $steward !== null) {
            try {
                $this->notifier->send('pro.kualiti_data_gagal', $steward, [
                    'rekod' => $lot !== null ? $lot->no_lot : $recordId,
                    'bilangan' => $created->count(),
                    'komponen' => $entry !== null ? $entry->komponen_data : $entity,
                ], '/admin/pro/kualiti?tab=tugasan', 'pro.kualiti:'.$entity.':'.$recordId);
            } catch (Throwable) {
                // notification problems never block data-quality routing
            }
        }

        return $created;
    }

    public function resolve(TugasanKualitiData $task, User $user, string $action, string $status = 'selesai'): TugasanKualitiData
    {
        $task->update([
            'status' => $status,
            'tindakan_pembetulan' => $action,
            'diselesaikan_oleh' => $user->id,
            'diselesaikan_pada' => now(),
        ]);

        return $task;
    }
}
