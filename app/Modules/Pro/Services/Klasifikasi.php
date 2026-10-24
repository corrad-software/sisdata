<?php

namespace App\Modules\Pro\Services;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Classification enforcement (SRS-PRO-14, SRS §4.2) on top of RBC scoping:
 *  - record level: a lot classified Sulit/Rahsia is visible only with
 *    pro.sulit.lihat / pro.rahsia.lihat (never granted by wildcard rules);
 *  - field level: fields the data dictionary classifies Sulit/Rahsia are
 *    masked for users without that permission — in the UI, API and exports.
 */
class Klasifikasi
{
    public const PERMISSION = ['sulit' => 'pro.sulit.lihat', 'rahsia' => 'pro.rahsia.lihat'];

    /** @var array<string, array<string, string>> */
    private array $fieldLevels = [];

    public function __construct(private AccessControl $access) {}

    /** @return list<string> classification levels $user may see */
    public function levels(User $user): array
    {
        $levels = ['awam', 'dalaman'];
        foreach (self::PERMISSION as $level => $permission) {
            if ($this->access->allows($user, $permission)) {
                $levels[] = $level;
            }
        }

        return $levels;
    }

    public function canSee(User $user, string $level): bool
    {
        return in_array($level, $this->levels($user), true);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function restrict(Builder $query, User $user, string $column = 'klasifikasi'): Builder
    {
        return $query->whereIn($query->getModel()->qualifyColumn($column), $this->levels($user));
    }

    /** @return array<string, string> field => classification, for fields above Dalaman */
    public function sensitiveFields(string $table): array
    {
        return $this->fieldLevels[$table] ??= DB::table('kamus_data')
            ->where('nama_jadual', $table)->whereNotNull('nama_medan')
            ->whereIn('klasifikasi', ['sulit', 'rahsia'])
            ->pluck('klasifikasi', 'nama_medan')->map(fn ($v) => (string) $v)->all();
    }

    /** @return list<string> fields of $table that $user may not see */
    public function maskedFields(string $table, User $user): array
    {
        $levels = $this->levels($user);

        return array_keys(array_filter($this->sensitiveFields($table), fn ($level) => ! in_array($level, $levels, true)));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function mask(string $table, array $row, User $user): array
    {
        $masked = [];
        foreach ($this->maskedFields($table, $user) as $field) {
            if (array_key_exists($field, $row)) {
                $row[$field] = null;
                $masked[] = $field;
            }
        }
        $row['medan_disamarkan'] = $masked;

        return $row;
    }

    public function flush(): void
    {
        $this->fieldLevels = [];
    }
}
