<?php

namespace App\Modules\Ref\Services;

use App\Modules\Ref\Models\RefModel;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Version control for reference codes (SRS-DAT-05). A changed code never
 * overwrites its old values: the current row is closed (sah_hingga = date)
 * and a new row with versi + 1 starts on that date. Unchanged input is a
 * no-op, so seeders and imports are idempotent.
 *
 * sah_hingga is exclusive: a version is valid while sah_dari <= D < sah_hingga.
 */
class RefVersioning
{
    public const CREATED = 'baharu';

    public const VERSIONED = 'versi_baharu';

    public const UNCHANGED = 'tiada_perubahan';

    public const RETIRED = 'ditamatkan';

    /**
     * @param  class-string<RefModel>  $model
     * @param  array<string, mixed>  $attributes  must include kod
     * @return array{0: string, 1: RefModel}
     */
    public function upsert(string $model, array $attributes, ?CarbonInterface $date = null): array
    {
        $date ??= now();
        $kod = (string) $attributes['kod'];
        unset($attributes['kod'], $attributes['id'], $attributes['versi'], $attributes['sah_dari'], $attributes['sah_hingga']);

        return DB::transaction(function () use ($model, $kod, $attributes, $date) {
            /** @var RefModel|null $current */
            $current = $model::query()->where('kod', $kod)->whereNull('sah_hingga')->lockForUpdate()->first();

            if ($current) {
                $probe = clone $current;
                $probe->fill(array_intersect_key($attributes, array_flip($current->versionedAttributes())));
                if (! $probe->isDirty()) {
                    return [self::UNCHANGED, $current];
                }

                $current->forceFill(['sah_hingga' => $date->toDateString()])->save();

                // a partial change carries every other value forward into the new version
                $attributes = [
                    ...Arr::except($current->attributesToArray(), ['id', 'kod', 'versi', 'sah_dari', 'sah_hingga', 'created_at', 'updated_at']),
                    ...$attributes,
                ];
            }

            $versi = (int) $model::query()->where('kod', $kod)->max('versi') + 1;

            /** @var RefModel $row */
            $row = $model::query()->create([
                ...$attributes,
                'kod' => $kod,
                'versi' => $versi,
                'sah_dari' => $date->toDateString(),
                'sah_hingga' => null,
            ]);

            return [$current ? self::VERSIONED : self::CREATED, $row];
        });
    }

    /**
     * Close the current version of a code (it no longer exists in the source).
     *
     * @param  class-string<RefModel>  $model
     */
    public function retire(string $model, string $kod, ?CarbonInterface $date = null): bool
    {
        return $model::query()->where('kod', $kod)->whereNull('sah_hingga')
            ->update(['sah_hingga' => ($date ?? now())->toDateString(), 'updated_at' => now()]) > 0;
    }
}
