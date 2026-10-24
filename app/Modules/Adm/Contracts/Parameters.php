<?php

namespace App\Modules\Adm\Contracts;

/**
 * System parameters admins change without a deploy (SRS-ADM-01): session
 * timeout, lockout threshold, file size limits, retention periods… Modules
 * declare their parameters with a default; reads are cached. Changing a
 * parameter flagged critical requires a second administrator's approval
 * (SRS-ADM-18) and every change is audited with before/after (SRS-ADM-19).
 */
interface Parameters
{
    /**
     * @param  string  $key  "<modul>.<nama>", e.g. "rbc.sesi_tamat_minit"
     * @param  string  $type  int | bool | string | json
     */
    public function declare(string $key, string $type, mixed $default, string $label, bool $critical = false): void;

    public function get(string $key, mixed $default = null): mixed;
}
