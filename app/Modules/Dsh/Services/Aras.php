<?php

namespace App\Modules\Dsh\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Rbc\Contracts\AccessControl;

/**
 * The three dashboard levels (SRS-DSH-01): Eksekutif (state-wide portfolio
 * KPIs), Pengurusan (division / district performance) and Operasi (tasks,
 * SLA, daily actions). Each level is a permission; the level shown first is
 * chosen automatically from the user's roles (admin-configurable mapping
 * `dsh.aras_peranan`, presentation only — data is always permission- and
 * scope-filtered), falling back to the highest level the user holds.
 * A user limited to some districts / a division never gets the state-wide
 * Eksekutif view: their variant is "daerah" or "bahagian".
 */
class Aras
{
    public const EKSEKUTIF = 'eksekutif';

    public const PENGURUSAN = 'pengurusan';

    public const OPERASI = 'operasi';

    public const PERMISSION = [
        self::EKSEKUTIF => 'dsh.eksekutif.lihat',
        self::PENGURUSAN => 'dsh.pengurusan.lihat',
        self::OPERASI => 'dsh.dashboard.lihat',
    ];

    public const ALL = [self::EKSEKUTIF, self::PENGURUSAN, self::OPERASI];

    /** Seeded default of `dsh.aras_peranan` (role code → level). */
    public const PEMETAAN_LALAI = [
        'pentadbir_sistem' => self::EKSEKUTIF,
        'pentadbir_aplikasi' => self::PENGURUSAN,
        'pelulus' => self::PENGURUSAN,
        'pengguna_paparan' => self::PENGURUSAN,
        'pegawai_operasi' => self::OPERASI,
    ];

    public function __construct(private AccessControl $access, private Parameters $parameters) {}

    /** @return list<string> levels the user may open, highest first */
    public function available(User $user): array
    {
        $out = [];
        foreach (self::ALL as $aras) {
            if (! $this->access->allows($user, self::PERMISSION[$aras])) {
                continue;
            }
            if ($aras === self::EKSEKUTIF && $this->variant($user) !== 'negeri') {
                continue;
            }
            $out[] = $aras;
        }

        return $out;
    }

    public function allows(User $user, string $aras): bool
    {
        return in_array($aras, $this->available($user), true);
    }

    /** The level shown automatically after login (SRS-DSH-01). */
    public function default(User $user): ?string
    {
        $available = $this->available($user);
        if ($available === []) {
            return null;
        }

        $map = $this->parameters->get('dsh.aras_peranan', self::PEMETAAN_LALAI);
        $map = is_string($map) ? (json_decode($map, true) ?: []) : (array) $map;
        $chosen = null;
        foreach ($this->access->roles($user) as $role) {
            $aras = $map[$role] ?? null;
            if (is_string($aras) && in_array($aras, $available, true)
                && ($chosen === null || array_search($aras, self::ALL, true) < array_search($chosen, self::ALL, true))) {
                $chosen = $aras;
            }
        }

        return $chosen ?? $available[0];
    }

    /** negeri (unrestricted) | daerah (district-scoped) | bahagian (division-scoped). */
    public function variant(User $user): string
    {
        if ($this->access->scope($user, 'daerah') !== null || $this->access->scope($user, 'mukim') !== null) {
            return 'daerah';
        }
        if ($this->access->scope($user, 'bahagian') !== null) {
            return 'bahagian';
        }

        return 'negeri';
    }
}
