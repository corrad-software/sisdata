<?php

namespace App\Modules\Cki\Models;

/**
 * Kawalan keselamatan: correction of a bill field with justification — a
 * separate, append-only record (bills are never edited silently or deleted).
 *
 * @property int $id
 * @property string $id_bil
 * @property string $medan
 * @property string|null $nilai_lama
 * @property string|null $nilai_baharu
 * @property string $justifikasi
 * @property int|null $id_pengguna
 */
class Pelarasan extends CkiModel
{
    public const UPDATED_AT = null;

    protected $table = 'pelarasan_cukai';
}
