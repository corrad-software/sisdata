<?php

namespace App\Modules\Aud\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * SRS-AUD-08/23 at the application layer: audit models can be read but never
 * saved or deleted through Eloquent — writes go only through AuditWriter, and
 * the database rejects UPDATE/DELETE anyway.
 */
abstract class ReadOnlyAuditModel extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    public function save(array $options = []): bool
    {
        throw new LogicException(__('aud::aud.hanya_baca'));
    }

    public function delete(): ?bool
    {
        throw new LogicException(__('aud::aud.hanya_baca'));
    }

    public function forceDelete(): ?bool
    {
        throw new LogicException(__('aud::aud.hanya_baca'));
    }
}
