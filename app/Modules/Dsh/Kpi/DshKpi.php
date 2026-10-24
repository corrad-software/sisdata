<?php

namespace App\Modules\Dsh\Kpi;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Dsh\Contracts\KpiProvider;
use App\Modules\Dsh\Services\Baca;
use App\Modules\Dsh\Services\Skop;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Support\Carbon;

/**
 * Base of MOD-DSH's own KPIs for modules that do not register any yet
 * (PRO, HKM, PER, WFE, NTF, DMS, KES). As an analytics module MOD-DSH reads
 * their tables read-only through SQL on the read connection (Baca) — it never
 * writes them and never imports their PHP classes — with the user's RBAC
 * scope applied in the WHERE clause (Skop).
 */
abstract class DshKpi implements KpiProvider
{
    /** @var array<string, array<string, string>> */
    private static array $names = [];

    public function __construct(protected Skop $skop, protected AccessControl $access, protected Parameters $parameters) {}

    /**
     * Lot visibility + the dashboard filters that apply to lots.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    protected function lotWhere(User $user, array $filters, string $alias = 'l'): array
    {
        [$sql, $b] = $this->skop->lot($user, $alias);
        foreach (['kod_daerah' => 'kod_daerah', 'kod_mukim' => 'kod_mukim', 'bahagian' => 'bahagian_pemilik', 'kategori' => 'kategori_guna_tanah', 'status' => 'status_lot'] as $key => $column) {
            if (! empty($filters[$key])) {
                $sql .= " AND {$alias}.{$column} = ?";
                $b[] = (string) $filters[$key];
            }
        }

        return [$sql, $b];
    }

    /**
     * Scope + filters for a table carrying kod_daerah / kod_mukim / bahagian columns.
     *
     * @param  array<string, string|null>  $columns  scope type => column (daerah, mukim, bahagian, klasifikasi)
     * @return array{0: string, 1: list<mixed>}
     */
    protected function scopedWhere(User $user, array $filters, string $alias, array $columns): array
    {
        [$sql, $b] = $this->skop->where($user, $alias, $columns);
        foreach (['kod_daerah' => 'daerah', 'kod_mukim' => 'mukim', 'bahagian' => 'bahagian'] as $key => $type) {
            if (! empty($filters[$key]) && ! empty($columns[$type])) {
                $sql .= " AND {$alias}.{$columns[$type]} = ?";
                $b[] = (string) $filters[$key];
            }
        }

        return [$sql, $b];
    }

    /**
     * @param  list<mixed>  $bindings
     * @return list<object>
     */
    protected function select(string $sql, array $bindings = []): array
    {
        return Baca::db()->select($sql, $bindings);
    }

    protected function scalar(string $sql, array $bindings = []): mixed
    {
        $row = Baca::db()->selectOne($sql, $bindings);

        return $row ? array_values((array) $row)[0] : null;
    }

    /** @return array<string, string> kod => nama from a reference table */
    protected function names(string $table, string $code = 'kod', string $name = 'nama'): array
    {
        $key = "{$table}.{$code}.{$name}";
        if (! isset(self::$names[$key])) {
            $map = [];
            foreach ($this->select("SELECT {$code} AS k, {$name} AS n FROM {$table}") as $r) {
                $map[(string) $r->k] = (string) $r->n;
            }
            self::$names[$key] = $map;
        }

        return self::$names[$key];
    }

    public static function flushNames(): void
    {
        self::$names = [];
    }

    protected function year(array $filters): int
    {
        return (int) ($filters['tahun'] ?? Carbon::today()->year);
    }

    /** Today (+ days) as a SQL date literal — generated here, never user input. */
    protected function today(int $plusDays = 0): string
    {
        return "DATE '".Carbon::today()->addDays($plusDays)->toDateString()."'";
    }

    protected function iso(mixed $timestamp): ?string
    {
        return $timestamp ? Carbon::parse((string) $timestamp)->toIso8601String() : null;
    }

    /**
     * Group-by rows to `pecahan`.
     *
     * @param  list<object>  $rows  with ->k and ->n
     * @param  array<string, string>  $labels
     * @return list<array{label: string, nilai: float|int, kod: string}>
     */
    protected function slices(array $rows, array $labels, string $prefix, bool $float = false): array
    {
        $out = [];
        foreach ($rows as $r) {
            $k = $r->k === null ? 'tiada' : (string) $r->k;
            $out[] = [
                'label' => $labels[$k] ?? ($k === 'tiada' ? __('dsh::dsh.tiada_maklumat') : $k),
                'nilai' => $float ? round((float) $r->n, 2) : (int) $r->n,
                'kod' => $prefix.':'.$k,
            ];
        }

        return $out;
    }

    /**
     * Parse a drill-down slice "dimension:value" into [dimension, value].
     *
     * @return array{0: string|null, 1: string|null}
     */
    protected function slice(?string $slice): array
    {
        if ($slice === null || ! str_contains($slice, ':')) {
            return [null, null];
        }
        [$dim, $value] = explode(':', $slice, 2);

        return [$dim, $value === 'tiada' ? null : $value];
    }

    /**
     * Paginate a drill-down query.
     *
     * @param  list<mixed>  $bindings
     * @return array{0: list<object>, 1: int}
     */
    protected function page(string $from, array $bindings, string $columns, string $order, int $page, int $perPage): array
    {
        $total = (int) $this->scalar("SELECT count(*) {$from}", $bindings);
        $rows = $this->select("SELECT {$columns} {$from} ORDER BY {$order} LIMIT ? OFFSET ?", [...$bindings, $perPage, max(0, ($page - 1) * $perPage)]);

        return [$rows, $total];
    }
}
