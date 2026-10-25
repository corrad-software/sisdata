<?php

namespace App\Modules\Lap\Services;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;

/**
 * Turns a user's RBAC scope (AccessControl::scope()) into the session
 * settings the `laporan` views filter on (SRS-LAP-08 row-level security):
 *
 *   sisdata.skop_daerah / skop_mukim / skop_bahagian  '*' | 'a,b' | '' (nothing)
 *   sisdata.tahap_klasifikasi                         allowed levels, e.g. 'awam,dalaman,sulit'
 *
 * Levels beyond Dalaman need the dataset's explicit Sulit/Rahsia permission
 * (never granted by wildcard). A null user is the system (console analytics
 * with no recipient) and sees everything.
 */
class Skop
{
    public const DIMENSI = ['daerah', 'mukim', 'bahagian'];

    public const TAHAP = ['awam', 'dalaman', 'sulit', 'rahsia'];

    public function __construct(private AccessControl $access) {}

    /**
     * @param  array<string, string>  $tahap  level => permission (from SetData)
     * @return array<string, string> setting name => value
     */
    public function settings(?User $user, array $tahap = []): array
    {
        $out = [];
        foreach (self::DIMENSI as $type) {
            $out['sisdata.skop_'.$type] = $this->value($user ? $this->access->scope($user, $type) : null);
        }
        $out['sisdata.tahap_klasifikasi'] = implode(',', $this->levels($user, $tahap));

        return $out;
    }

    /** @return list<string> */
    public function levels(?User $user, array $tahap = []): array
    {
        if ($user === null) {
            return self::TAHAP;
        }
        $levels = ['awam', 'dalaman'];
        foreach (['sulit', 'rahsia'] as $level) {
            if (isset($tahap[$level]) && $this->access->allows($user, $tahap[$level])) {
                $levels[] = $level;
            }
        }
        $scope = $this->access->scope($user, AccessControl::SCOPE_KLASIFIKASI);
        if ($scope !== null) {
            $levels = array_values(array_intersect($levels, $scope));
        }

        return $levels;
    }

    /**
     * The user's scope per dimension, for report headers (null = unrestricted).
     *
     * @return array<string, list<string>|null>
     */
    public function scopes(?User $user): array
    {
        $out = [];
        foreach (self::DIMENSI as $type) {
            $s = $user ? $this->access->scope($user, $type) : null;
            if ($s !== null) {
                sort($s);
            }
            $out[$type] = $s;
        }

        return $out;
    }

    /** @param list<string>|null $values */
    private function value(?array $values): string
    {
        if ($values === null) {
            return '*';
        }
        // Codes never contain commas or '*'; anything else is dropped (deny).
        $clean = array_values(array_filter(array_map('strval', $values), fn ($v) => $v !== '' && $v !== '*' && ! str_contains($v, ',')));

        return implode(',', $clean);
    }
}
