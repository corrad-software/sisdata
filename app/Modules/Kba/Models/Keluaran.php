<?php

namespace App\Modules\Kba\Models;

/**
 * A system release (SRS-KBA-12): recording one flags articles that refer to
 * an older system version for review.
 *
 * @property int $id
 * @property string $versi
 * @property string|null $catatan
 * @property int|null $id_pengguna
 * @property int $bilangan_artikel_ditanda
 */
class Keluaran extends KbaModel
{
    protected $table = 'kba_keluaran';
}
