<?php

namespace App\Modules\Kba\Services;

use App\Models\User;
use App\Modules\Kba\Models\PemetaanKebenaran;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Support\Facades\Schema;

/**
 * Retrieval-time permission filter (SRS-KBA-07/08). Produces ONE SQL
 * predicate over kba_segmen (alias "s") that every retrieval leg — vector,
 * full-text, trigram, viewer, export — puts in its WHERE clause, so passages a
 * user may not open are never ranked, never put in a model's context and
 * never returned.
 *
 *  - no user, or a user without kba.soalan.lihat → Awam segments with no role list only;
 *  - KBA-held segments: classification ∈ the user's levels (awam, dalaman, +sulit /
 *    +rahsia via kba.sulit.lihat / kba.rahsia.lihat or kba_pemetaan_kebenaran),
 *    ∩ the user's RBC klasifikasi scope; role list empty or shared; district in scope;
 *  - segments of DMS documents: MOD-DMS's own visibility rule, evaluated live
 *    against its tables (read-only) — uploader, explicit grant, or
 *    level + district scope with dms.sulit.lihat / dms.rahsia.lihat.
 */
class Capaian
{
    public const BACA = 'kba.soalan.lihat';

    public const SULIT = 'kba.sulit.lihat';

    public const RAHSIA = 'kba.rahsia.lihat';

    public const TAHAP = ['awam' => 0, 'dalaman' => 1, 'sulit' => 2, 'rahsia' => 3];

    private static ?bool $dmsAda = null;

    public function __construct(private AccessControl $access) {}

    /**
     * @return array{sql: string, bindings: list<mixed>}
     */
    public function predicate(?User $user, string $alias = 's'): array
    {
        $a = $alias;
        if ($user === null || ! $this->access->allows($user, self::BACA)) {
            return ['sql' => "({$a}.klasifikasi = 'awam' AND {$a}.senarai_peranan_dibenarkan = '[]'::jsonb AND {$a}.id_dokumen_dms IS NULL)", 'bindings' => []];
        }

        $roles = $this->access->roles($user);
        $daerah = $this->access->scope($user, AccessControl::SCOPE_DAERAH);
        $levels = $this->levels($user);
        $sql = [];
        $bindings = [];

        // Role list (applies to every segment, incl. DMS-backed ones).
        $roleSql = "({$a}.senarai_peranan_dibenarkan = '[]'::jsonb".($roles !== [] ? " OR jsonb_exists_any({$a}.senarai_peranan_dibenarkan, ?::text[])" : '').')';
        if ($roles !== []) {
            $bindings[] = self::pgArray($roles);
        }

        // KBA-held segments.
        $kba = ["{$a}.id_dokumen_dms IS NULL"];
        $kba[] = $levels === [] ? 'false' : "{$a}.klasifikasi IN (".self::marks($levels).')';
        array_push($bindings, ...$levels);
        if ($daerah !== null) {
            $kba[] = $daerah === [] ? "{$a}.kod_daerah IS NULL" : "({$a}.kod_daerah IS NULL OR {$a}.kod_daerah IN (".self::marks($daerah).'))';
            array_push($bindings, ...$daerah);
        }
        $sql[] = '('.implode(' AND ', $kba).')';

        // DMS-backed segments: DMS decides.
        $dms = $this->dmsPredicate($user, $roles, $daerah, $a);
        if ($dms !== null) {
            $sql[] = $dms['sql'];
            array_push($bindings, ...$dms['bindings']);
        }

        return ['sql' => '('.$roleSql.' AND ('.implode(' OR ', $sql).'))', 'bindings' => $bindings];
    }

    /**
     * Classification levels $user may read in KBA-held content.
     *
     * @return list<string>
     */
    public function levels(?User $user): array
    {
        if ($user === null || ! $this->access->allows($user, self::BACA)) {
            return ['awam'];
        }
        $roles = $this->access->roles($user);
        $mapped = $roles === [] ? [] : PemetaanKebenaran::query()
            ->whereIn('peranan', $roles)->where('jenis_capaian', 'baca')
            ->whereDate('tarikh_berkuat_kuasa', '<=', now()->toDateString())
            ->pluck('klasifikasi')->all();

        $levels = ['awam', 'dalaman'];
        if ($this->access->allows($user, self::SULIT) || in_array('sulit', $mapped, true)) {
            $levels[] = 'sulit';
        }
        if ($this->access->allows($user, self::RAHSIA) || in_array('rahsia', $mapped, true)) {
            $levels[] = 'rahsia';
        }
        $scope = $this->access->scope($user, AccessControl::SCOPE_KLASIFIKASI);

        return $scope === null ? $levels : array_values(array_intersect($levels, $scope));
    }

    /**
     * @param  list<string>  $roles
     * @param  list<string>|null  $daerah
     * @return array{sql: string, bindings: list<mixed>}|null
     */
    private function dmsPredicate(User $user, array $roles, ?array $daerah, string $a): ?array
    {
        if (! $this->access->allows($user, 'dms.dokumen.lihat') || ! self::dmsAda()) {
            return null;
        }
        $levels = ['awam', 'dalaman'];
        if ($this->access->allows($user, 'dms.sulit.lihat')) {
            $levels[] = 'sulit';
        }
        if ($this->access->allows($user, 'dms.rahsia.lihat')) {
            $levels[] = 'rahsia';
        }
        $scope = $this->access->scope($user, AccessControl::SCOPE_KLASIFIKASI);
        if ($scope !== null) {
            $levels = array_values(array_intersect($levels, $scope));
        }

        $bindings = [$user->id, (string) $user->id];
        $grantRoles = '';
        if ($roles !== []) {
            $grantRoles = " OR (k.jenis_penerima = 'peranan' AND k.penerima = ANY(?::text[]))";
            $bindings[] = self::pgArray($roles);
        }
        $scoped = $levels === [] ? 'false' : 'd.klasifikasi_keselamatan IN ('.self::marks($levels).')';
        array_push($bindings, ...$levels);
        if ($daerah !== null) {
            $scoped .= $daerah === [] ? ' AND d.kod_daerah IS NULL' : ' AND (d.kod_daerah IS NULL OR d.kod_daerah IN ('.self::marks($daerah).'))';
            array_push($bindings, ...$daerah);
        }

        return [
            'sql' => "({$a}.id_dokumen_dms IS NOT NULL AND EXISTS (SELECT 1 FROM dokumen d WHERE d.id = {$a}.id_dokumen_dms AND d.deleted_at IS NULL AND d.status <> 'dilupus' AND (
                d.id_pencipta = ?
                OR EXISTS (SELECT 1 FROM dokumen_kebenaran k WHERE k.dokumen_id = d.id AND (k.tamat_pada IS NULL OR k.tamat_pada > now())
                    AND ((k.jenis_penerima = 'pengguna' AND k.penerima = ?){$grantRoles}))
                OR ({$scoped}))))",
            'bindings' => $bindings,
        ];
    }

    public static function dmsAda(): bool
    {
        return self::$dmsAda ??= Schema::hasTable('dokumen') && Schema::hasTable('dokumen_kebenaran');
    }

    public static function flush(): void
    {
        self::$dmsAda = null;
    }

    /** Higher of two classifications. */
    public static function max(string $a, string $b): string
    {
        return (self::TAHAP[$b] ?? 1) > (self::TAHAP[$a] ?? 1) ? $b : $a;
    }

    /** @param list<string> $values */
    public static function pgArray(array $values): string
    {
        return '{'.implode(',', array_map(fn (string $v) => '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $v).'"', $values)).'}';
    }

    /** @param list<mixed> $values */
    private static function marks(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }
}
