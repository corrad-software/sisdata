<?php

namespace App\Modules\Lap\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Lap\Support\SetData;
use App\Modules\Lap\Support\Wang;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * The reporting query engine (SRS-LAP-02/03/08/11/12). Takes a report
 * definition — dataset, fields, filters, grouping, aggregates, sort, period,
 * comparison — validates it against the dataset whitelist and runs it on the
 * reporting connection against a `laporan` view inside a read-only
 * transaction whose SET LOCAL settings carry the user's RBAC scope, so
 * row-level security is enforced by the database itself. Statement timeout
 * and row caps protect the transactional system (security controls).
 *
 * Definition (all keys optional except `set`):
 *   set        dataset key (SetData)
 *   medan      record-mode columns
 *   kumpulan   group keys, "medan" or "tarikh_medan:bulan|suku|tahun"
 *   hierarki   drill-down levels (one group key per level, SRS-LAP-12)
 *   agregat    [{fungsi: bilangan|jumlah|purata|min|maks|bilangan_benar|peratus_benar, medan?}]
 *   penapis    [{medan, op, nilai}]
 *   tempoh     {medan?, dari?, hingga?, pratetap?}
 *   susunan    [{medan, arah: asc|desc}]
 *   bandingan  null | "tahun_lepas" (year-on-year)
 *   carta      bar | garis | pai | jadual
 */
class Enjin
{
    public const OPS = ['sama', 'tidak_sama', 'dalam', 'lebih', 'kurang', 'lebih_sama', 'kurang_sama', 'antara', 'mengandungi', 'kosong', 'tidak_kosong', 'benar', 'palsu'];

    public const FUNGSI = ['bilangan', 'jumlah', 'purata', 'min', 'maks', 'bilangan_benar', 'peratus_benar'];

    public const BUTIRAN = ['hari', 'bulan', 'suku', 'tahun'];

    public const PRATETAP = ['hari_ini', '30_hari', 'bulan_ini', 'suku_ini', 'tahun_ini', 'tahun_lepas', '12_bulan'];

    public const CARTA = ['bar', 'garis', 'pai', 'jadual'];

    private const OP_SQL = ['sama' => '=', 'tidak_sama' => '<>', 'lebih' => '>', 'kurang' => '<', 'lebih_sama' => '>=', 'kurang_sama' => '<='];

    public function __construct(private Skop $skop, private AccessControl $access, private Parameters $parameters) {}

    // ---------------------------------------------------------------- validation

    /**
     * Normalise and validate a definition for $user (null = system).
     *
     * @param  array<string, mixed>  $def
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function normalise(array $def, ?User $user = null): array
    {
        $set = SetData::get((string) ($def['set'] ?? ''));
        if ($set === null) {
            throw ValidationException::withMessages(['set' => __('lap::lap.ralat.set_tidak_sah')]);
        }
        $fields = $this->fields($set, $user);
        $hidden = [];
        $field = function (string $key, string $path) use ($fields, $set, &$hidden): ?string {
            $base = explode(':', $key, 2)[0];
            if (! isset($set['medan'][$base])) {
                throw ValidationException::withMessages([$path => __('lap::lap.ralat.medan_tidak_sah', ['medan' => $key])]);
            }
            if (! isset($fields[$base])) {
                $hidden[] = $set['medan'][$base]['label'];

                return null;
            }

            return $base;
        };

        $out = ['set' => (string) $def['set']];

        $out['medan'] = [];
        foreach (array_values((array) ($def['medan'] ?? [])) as $i => $m) {
            if (($f = $field((string) $m, "medan.{$i}")) !== null) {
                $out['medan'][] = $f;
            }
        }

        $groupKey = function (string $key, string $path) use ($field, $set): ?string {
            [$base, $grain] = array_pad(explode(':', $key, 2), 2, null);
            if ($field($base, $path) === null) {
                return null;
            }
            $type = $set['medan'][$base]['jenis'];
            if ($type === 'tarikh') {
                $grain ??= 'bulan';
                if (! in_array($grain, self::BUTIRAN, true)) {
                    throw ValidationException::withMessages([$path => __('lap::lap.ralat.butiran_tidak_sah')]);
                }

                return "{$base}:{$grain}";
            }
            if (in_array($type, SetData::JENIS_NOMBOR, true) || $grain !== null) {
                throw ValidationException::withMessages([$path => __('lap::lap.ralat.kumpulan_tidak_sah', ['medan' => $key])]);
            }

            return $base;
        };
        foreach (['kumpulan', 'hierarki'] as $k) {
            $out[$k] = [];
            foreach (array_values((array) ($def[$k] ?? [])) as $i => $g) {
                if (($g = $groupKey((string) $g, "{$k}.{$i}")) !== null && ! in_array($g, $out[$k], true)) {
                    $out[$k][] = $g;
                }
            }
            if (count($out[$k]) > 3) {
                throw ValidationException::withMessages([$k => __('lap::lap.ralat.kumpulan_maksimum')]);
            }
        }

        $out['agregat'] = [];
        foreach (array_values((array) ($def['agregat'] ?? [])) as $i => $a) {
            $fn = (string) (is_array($a) ? ($a['fungsi'] ?? '') : $a);
            if (! in_array($fn, self::FUNGSI, true)) {
                throw ValidationException::withMessages(["agregat.{$i}" => __('lap::lap.ralat.fungsi_tidak_sah')]);
            }
            if ($fn === 'bilangan') {
                $out['agregat'][] = ['fungsi' => 'bilangan'];

                continue;
            }
            $m = is_array($a) ? (string) ($a['medan'] ?? '') : '';
            if (($m = $field($m, "agregat.{$i}.medan")) === null) {
                continue;
            }
            $type = $set['medan'][$m]['jenis'];
            $ok = match ($fn) {
                'jumlah', 'purata' => in_array($type, SetData::JENIS_NOMBOR, true),
                'min', 'maks' => in_array($type, [...SetData::JENIS_NOMBOR, 'tarikh'], true),
                default => $type === 'bool',
            };
            if (! $ok) {
                throw ValidationException::withMessages(["agregat.{$i}" => __('lap::lap.ralat.fungsi_medan', ['medan' => $m])]);
            }
            $out['agregat'][] = ['fungsi' => $fn, 'medan' => $m];
        }
        if (($out['kumpulan'] !== [] || $out['hierarki'] !== []) && $out['agregat'] === []) {
            $out['agregat'][] = ['fungsi' => 'bilangan'];
        }

        $out['penapis'] = [];
        foreach (array_values((array) ($def['penapis'] ?? [])) as $i => $p) {
            if (! is_array($p) || ($m = $field((string) ($p['medan'] ?? ''), "penapis.{$i}.medan")) === null) {
                continue;
            }
            $out['penapis'][] = $this->filter($set['medan'][$m], $m, (string) ($p['op'] ?? 'sama'), $p['nilai'] ?? null, "penapis.{$i}");
        }

        $out['tempoh'] = null;
        if (! empty($def['tempoh']) && is_array($def['tempoh'])) {
            $out['tempoh'] = $this->period($set, (array) $def['tempoh']);
        }

        $out['susunan'] = [];
        foreach (array_values((array) ($def['susunan'] ?? [])) as $s) {
            if (is_array($s) && preg_match('/^[a-z0-9_:]+$/', (string) ($s['medan'] ?? ''))) {
                $out['susunan'][] = ['medan' => (string) $s['medan'], 'arah' => ($s['arah'] ?? 'asc') === 'desc' ? 'desc' : 'asc'];
            }
        }

        $out['bandingan'] = ($def['bandingan'] ?? null) === 'tahun_lepas' ? 'tahun_lepas' : null;
        $out['carta'] = in_array($def['carta'] ?? null, self::CARTA, true) ? $def['carta'] : 'bar';
        $out['had'] = max(1, min((int) ($def['had'] ?? $this->maxRows()), $this->maxRows()));
        $out['medan_disembunyikan'] = array_values(array_unique($hidden));

        return $out;
    }

    /** @return array<string, array<string, mixed>> fields $user may see */
    public function fields(array $set, ?User $user): array
    {
        return array_filter($set['medan'], fn ($f) => $user === null || empty($f['kebenaran']) || $this->access->allows($user, $f['kebenaran']));
    }

    /** @return array{medan: string, op: string, nilai: mixed} */
    private function filter(array $meta, string $field, string $op, mixed $value, string $path): array
    {
        if (! in_array($op, self::OPS, true)) {
            throw ValidationException::withMessages([$path.'.op' => __('lap::lap.ralat.op_tidak_sah')]);
        }
        $type = $meta['jenis'];
        if (in_array($op, ['benar', 'palsu'], true) && $type !== 'bool') {
            throw ValidationException::withMessages([$path.'.op' => __('lap::lap.ralat.op_tidak_sah')]);
        }
        $check = function (mixed $v) use ($type, $path): string {
            if (is_array($v) || is_object($v)) {
                throw ValidationException::withMessages([$path.'.nilai' => __('lap::lap.ralat.nilai_tidak_sah')]);
            }
            $v = trim((string) $v);
            $ok = match ($type) {
                'nombor', 'wang' => (bool) preg_match('/^-?\d+(\.\d+)?$/', $v),
                'tarikh' => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v),
                'bool' => in_array($v, ['true', 'false', '1', '0'], true),
                default => mb_strlen($v) <= 200,
            };
            if (! $ok) {
                throw ValidationException::withMessages([$path.'.nilai' => __('lap::lap.ralat.nilai_tidak_sah')]);
            }

            return $type === 'bool' ? (in_array($v, ['true', '1'], true) ? 'true' : 'false') : $v;
        };

        $value = match ($op) {
            'kosong', 'tidak_kosong', 'benar', 'palsu' => null,
            'dalam' => array_values(array_map($check, array_slice((array) $value, 0, 100))),
            'antara' => array_map($check, array_values(array_slice((array) $value, 0, 2))),
            default => $check($value),
        };
        if ($op === 'antara' && count((array) $value) !== 2) {
            throw ValidationException::withMessages([$path.'.nilai' => __('lap::lap.ralat.nilai_tidak_sah')]);
        }
        if ($op === 'dalam' && $value === []) {
            throw ValidationException::withMessages([$path.'.nilai' => __('lap::lap.ralat.nilai_tidak_sah')]);
        }

        return ['medan' => $field, 'op' => $op, 'nilai' => $value];
    }

    /** @return array{medan: string, dari: string, hingga: string, pratetap: string|null}|null */
    private function period(array $set, array $p): ?array
    {
        $field = (string) ($p['medan'] ?? $set['tarikh'] ?? '');
        if ($field === '' || ($set['medan'][$field]['jenis'] ?? null) !== 'tarikh') {
            throw ValidationException::withMessages(['tempoh.medan' => __('lap::lap.ralat.tempoh_medan')]);
        }
        $preset = $p['pratetap'] ?? null;
        if ($preset !== null && $preset !== '') {
            if (! in_array($preset, self::PRATETAP, true)) {
                throw ValidationException::withMessages(['tempoh.pratetap' => __('lap::lap.ralat.tempoh_tidak_sah')]);
            }
            [$from, $to] = $this->preset((string) $preset);

            return ['medan' => $field, 'dari' => $from, 'hingga' => $to, 'pratetap' => (string) $preset];
        }
        $from = (string) ($p['dari'] ?? '');
        $to = (string) ($p['hingga'] ?? '');
        if ($from === '' && $to === '') {
            return null;
        }
        foreach (['dari' => $from, 'hingga' => $to] as $k => $v) {
            if ($v !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                throw ValidationException::withMessages(["tempoh.{$k}" => __('lap::lap.ralat.tempoh_tidak_sah')]);
            }
        }
        $from = $from ?: '1900-01-01';
        $to = $to ?: '2999-12-31';
        if ($from > $to) {
            throw ValidationException::withMessages(['tempoh.hingga' => __('lap::lap.ralat.tempoh_terbalik')]);
        }

        return ['medan' => $field, 'dari' => $from, 'hingga' => $to, 'pratetap' => null];
    }

    /** @return array{0: string, 1: string} */
    public function preset(string $preset): array
    {
        $t = Carbon::today();

        return match ($preset) {
            'hari_ini' => [$t->toDateString(), $t->toDateString()],
            '30_hari' => [$t->copy()->subDays(29)->toDateString(), $t->toDateString()],
            'bulan_ini' => [$t->copy()->startOfMonth()->toDateString(), $t->copy()->endOfMonth()->toDateString()],
            'suku_ini' => [$t->copy()->firstOfQuarter()->toDateString(), $t->copy()->lastOfQuarter()->toDateString()],
            'tahun_lepas' => [$t->copy()->subYear()->startOfYear()->toDateString(), $t->copy()->subYear()->endOfYear()->toDateString()],
            '12_bulan' => [$t->copy()->subMonths(11)->startOfMonth()->toDateString(), $t->copy()->endOfMonth()->toDateString()],
            default => [$t->copy()->startOfYear()->toDateString(), $t->copy()->endOfYear()->toDateString()],
        };
    }

    // ---------------------------------------------------------------- execution

    /**
     * Run a normalised definition.
     *
     * @param  array<string, mixed>  $def  output of normalise()
     * @param  array{halaman?: int, per_halaman?: int, drill?: array<string, mixed>, semua?: bool}  $opts
     * @return array<string, mixed>
     */
    public function run(?User $user, array $def, array $opts = []): array
    {
        $set = SetData::get($def['set']);
        $settings = $this->skop->settings($user, $set['tahap'] ?? []);
        $ttl = (int) $this->parameters->get('lap.cache_saat', 300);
        $key = 'lap:hasil:'.sha1((string) json_encode([$def, $opts, $settings, $user ? $this->access->permissions($user) : '*']));

        $compute = fn () => $this->execute($user, $set, $def, $opts, $settings);
        if ($ttl <= 0) {
            return $compute() + ['dari_cache' => false];
        }
        $hit = true;
        $result = Cache::remember($key, $ttl, function () use ($compute, &$hit) {
            $hit = false;

            return $compute();
        });

        return $result + ['dari_cache' => $hit];
    }

    /** @return array<string, mixed> */
    private function execute(?User $user, array $set, array $def, array $opts, array $settings): array
    {
        $started = microtime(true);
        $view = 'laporan.'.$set['paparan'];
        $drill = $this->drill($set, $def, (array) ($opts['drill'] ?? []));
        $groups = $this->effectiveGroups($def, $drill);
        [$where, $bindings] = $this->where($set, $def, $def['tempoh'], $drill['penapis']);

        $result = $this->transaction($settings, function ($db) use ($set, $def, $opts, $view, $groups, $where, $bindings) {
            $meta = $db->selectOne('SELECT count(*) AS n, max(dikemas_kini) AS t'.($this->hasClassification($set) ? ", max(CASE klasifikasi WHEN 'rahsia' THEN 4 WHEN 'sulit' THEN 3 WHEN 'dalaman' THEN 2 ELSE 1 END) AS k" : '')." FROM {$view} WHERE {$where}", $bindings);
            $asOf = $db->selectOne('SELECT CASE WHEN pg_is_in_recovery() THEN pg_last_xact_replay_timestamp() ELSE now() END AS t');

            if ($groups !== []) {
                $rows = $this->grouped($db, $set, $def, $groups, $view, $where, $bindings);
                if ($def['bandingan'] === 'tahun_lepas' && $def['tempoh'] !== null) {
                    $prev = $def['tempoh'];
                    $prev['dari'] = Carbon::parse($prev['dari'])->subYear()->toDateString();
                    $prev['hingga'] = Carbon::parse($prev['hingga'])->subYear()->toDateString();
                    [$pw, $pb] = $this->where($set, $def, $prev, $this->drill($set, $def, (array) ($opts['drill'] ?? []))['penapis']);
                    $rows = $this->compare($rows, $this->grouped($db, $set, $def, $groups, $view, $pw, $pb, true), $def, $groups);
                }
                $total = count($rows);
                $mode = 'kumpulan';
            } else {
                [$rows, $total] = $this->records($db, $set, $def, $view, $where, $bindings, $opts, (int) $meta->n);
                $mode = 'rekod';
            }

            return compact('meta', 'asOf', 'rows', 'total', 'mode');
        });

        $level = ['', 'awam', 'dalaman', 'sulit', 'rahsia'][(int) ($result['meta']->k ?? 2)] ?? 'dalaman';
        $columns = $this->columns($set, $def, $groups, $result['mode']);

        return [
            'mod' => $result['mode'],
            'set' => $def['set'],
            'lajur' => $columns,
            'baris' => $result['rows'],
            'jumlah_baris' => $result['total'],
            'bilangan_rekod' => (int) $result['meta']->n,
            'jumlah_keseluruhan' => $result['mode'] === 'kumpulan' ? $this->totals($columns, $result['rows']) : null,
            'kumpulan' => $groups,
            'aras_drill' => $drill['aras'],
            'drill' => $drill['nilai'],
            'boleh_drill' => $result['mode'] === 'kumpulan',
            'meta' => [
                'data_dikemas_kini' => $result['meta']->t ? Carbon::parse((string) $result['meta']->t)->toIso8601String() : null,
                'data_setakat' => $result['asOf']->t ? Carbon::parse((string) $result['asOf']->t)->toIso8601String() : null,
                'dijana_pada' => Carbon::now()->toIso8601String(),
                'tempoh' => $def['tempoh'],
                'bandingan' => $def['bandingan'],
                'penapis_teks' => $this->describe($set, $def, $drill),
                'skop' => $this->skop->scopes($user),
                'tahap_klasifikasi' => explode(',', $settings['sisdata.tahap_klasifikasi']),
                'klasifikasi_tertinggi' => $level,
                'medan_disembunyikan' => $def['medan_disembunyikan'] ?? [],
                'replika' => Baca::replica(),
                'tempoh_ms' => (int) round((microtime(true) - $started) * 1000),
                'dipotong' => $result['mode'] === 'kumpulan' && count($result['rows']) >= $def['had'],
            ],
        ];
    }

    /**
     * Ungrouped aggregates of a normalised definition under $user's scope.
     *
     * @return array<string, mixed> aggregate key => value
     */
    public function aggregate(?User $user, array $def): array
    {
        $set = SetData::get($def['set']);
        [$where, $b] = $this->where($set, $def, $def['tempoh']);
        $aggs = $def['agregat'] ?: [['fungsi' => 'bilangan']];
        $select = [];
        foreach ($aggs as $j => $a) {
            $select[] = $this->aggExpr($a)." AS a{$j}";
        }
        $row = (array) $this->transaction($this->skop->settings($user, $set['tahap'] ?? []), fn ($db) => $db->selectOne(
            'SELECT '.implode(', ', $select).' FROM laporan.'.$set['paparan']." WHERE {$where}", $b));
        $out = [];
        foreach ($aggs as $j => $a) {
            $out[self::aggKey($a)] = $this->aggValue($set, $a, $row["a{$j}"] ?? null);
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $settings
     */
    public function transaction(array $settings, callable $fn): mixed
    {
        $db = Baca::db();

        return $db->transaction(function () use ($db, $settings, $fn) {
            if ($db->transactionLevel() === 1) {
                $db->statement('SET TRANSACTION READ ONLY');
            }
            $sql = [];
            $b = [];
            foreach ($settings as $name => $value) {
                $sql[] = 'set_config(?, ?, true)';
                array_push($b, $name, $value);
            }
            $sql[] = "set_config('statement_timeout', ?, true)";
            $b[] = (string) (max(1, (int) $this->parameters->get('lap.had_masa_saat', 30)) * 1000);
            $db->select('SELECT '.implode(', ', $sql), $b);

            return $fn($db);
        });
    }

    /** @return array{penapis: list<array{medan: string, op: string, nilai: mixed}>, aras: int, nilai: array<string, mixed>} */
    private function drill(array $set, array $def, array $drill): array
    {
        $allowed = $def['hierarki'] !== [] ? $def['hierarki'] : $def['kumpulan'];
        $filters = [];
        $values = [];
        foreach ($allowed as $g) {
            if (! array_key_exists($g, $drill)) {
                if ($def['hierarki'] !== []) {
                    break;
                }

                continue;
            }
            $v = $drill[$g];
            if (is_array($v)) {
                continue;
            }
            [$base, $grain] = array_pad(explode(':', $g, 2), 2, null);
            $values[$g] = $v;
            if ($v === null || $v === '') {
                $filters[] = ['medan' => $base, 'op' => 'kosong', 'nilai' => null];
            } elseif ($grain !== null) {
                $range = $this->grainRange($grain, (string) $v);
                if ($range !== null) {
                    $filters[] = ['medan' => $base, 'op' => 'antara', 'nilai' => $range];
                }
            } else {
                $filters[] = $this->filter($set['medan'][$base], $base, 'sama', is_bool($v) ? ($v ? 'true' : 'false') : $v, 'drill');
            }
        }

        return ['penapis' => $filters, 'aras' => count($values), 'nilai' => $values];
    }

    /** @return array{0: string, 1: string}|null */
    private function grainRange(string $grain, string $v): ?array
    {
        if ($grain === 'tahun' && preg_match('/^\d{4}$/', $v)) {
            return ["{$v}-01-01", "{$v}-12-31"];
        }
        if ($grain === 'bulan' && preg_match('/^\d{4}-\d{2}$/', $v)) {
            $d = Carbon::parse("{$v}-01");

            return [$d->toDateString(), $d->copy()->endOfMonth()->toDateString()];
        }
        if ($grain === 'suku' && preg_match('/^(\d{4})-S([1-4])$/', $v, $m)) {
            $d = Carbon::create((int) $m[1], ((int) $m[2] - 1) * 3 + 1, 1);

            return [$d->toDateString(), $d->copy()->addMonths(2)->endOfMonth()->toDateString()];
        }
        if ($grain === 'hari' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return [$v, $v];
        }

        return null;
    }

    /** @return list<string> */
    private function effectiveGroups(array $def, array $drill): array
    {
        if ($def['hierarki'] !== []) {
            return isset($def['hierarki'][$drill['aras']]) ? [$def['hierarki'][$drill['aras']]] : [];
        }

        return $drill['nilai'] === [] ? $def['kumpulan'] : [];
    }

    /**
     * @param  list<array{medan: string, op: string, nilai: mixed}>  $extra
     * @return array{0: string, 1: list<mixed>}
     */
    private function where(array $set, array $def, ?array $period, array $extra = []): array
    {
        $sql = ['TRUE'];
        $b = [];
        foreach ([...$def['penapis'], ...$extra] as $p) {
            $col = $this->col($p['medan']);
            $isDate = ($set['medan'][$p['medan']]['jenis'] ?? null) === 'tarikh';
            switch ($p['op']) {
                case 'kosong':
                    $sql[] = "{$col} IS NULL";
                    break;
                case 'tidak_kosong':
                    $sql[] = "{$col} IS NOT NULL";
                    break;
                case 'benar':
                    $sql[] = "{$col} IS TRUE";
                    break;
                case 'palsu':
                    $sql[] = "{$col} IS NOT TRUE";
                    break;
                case 'dalam':
                    $sql[] = "{$col}::text IN (".implode(',', array_fill(0, count($p['nilai']), '?')).')';
                    array_push($b, ...$p['nilai']);
                    break;
                case 'antara':
                    $sql[] = $isDate ? "{$col} >= ?::date AND {$col} < (?::date + 1)" : "{$col} BETWEEN ? AND ?";
                    array_push($b, ...$p['nilai']);
                    break;
                case 'mengandungi':
                    $sql[] = "{$col}::text ILIKE ?";
                    $b[] = '%'.addcslashes((string) $p['nilai'], '%_\\').'%';
                    break;
                default:
                    $type = $set['medan'][$p['medan']]['jenis'] ?? 'teks';
                    $cast = match ($type) {
                        'bool' => '::boolean', 'tarikh' => '::date', 'nombor', 'wang' => '::numeric', default => '',
                    };
                    $lhs = in_array($type, ['teks', 'kod'], true) ? "{$col}::text" : $col;
                    $sql[] = "{$lhs} ".self::OP_SQL[$p['op']]." ?{$cast}";
                    $b[] = $p['nilai'];
            }
        }
        if ($period !== null) {
            $col = $this->col($period['medan']);
            $sql[] = "{$col} >= ?::date AND {$col} < (?::date + 1)";
            array_push($b, $period['dari'], $period['hingga']);
        }

        return [implode(' AND ', $sql), $b];
    }

    private function col(string $field): string
    {
        // Whitelisted keys only reach here; quote anyway.
        return '"'.str_replace('"', '', $field).'"';
    }

    private function groupExpr(string $key): string
    {
        [$base, $grain] = array_pad(explode(':', $key, 2), 2, null);
        $col = $this->col($base);

        return match ($grain) {
            'hari' => "to_char({$col}, 'YYYY-MM-DD')",
            'bulan' => "to_char({$col}, 'YYYY-MM')",
            'suku' => "to_char({$col}, 'YYYY') || '-S' || to_char({$col}, 'Q')",
            'tahun' => "to_char({$col}, 'YYYY')",
            default => $col,
        };
    }

    public static function aggKey(array $a): string
    {
        return match ($a['fungsi']) {
            'bilangan' => 'bilangan',
            'bilangan_benar' => 'bilangan_'.$a['medan'],
            'peratus_benar' => 'peratus_'.$a['medan'],
            default => $a['fungsi'].'_'.$a['medan'],
        };
    }

    private function aggExpr(array $a): string
    {
        $c = isset($a['medan']) ? $this->col($a['medan']) : '';

        return match ($a['fungsi']) {
            'bilangan' => 'count(*)',
            'jumlah' => "coalesce(sum({$c}), 0)",
            'purata' => "round(avg({$c})::numeric, 2)",
            'min' => "min({$c})",
            'maks' => "max({$c})",
            'bilangan_benar' => "count(*) FILTER (WHERE {$c} IS TRUE)",
            'peratus_benar' => "round(100.0 * count(*) FILTER (WHERE {$c} IS TRUE) / nullif(count(*), 0), 2)",
            default => 'count(*)',
        };
    }

    /** @return list<array<string, mixed>> */
    private function grouped($db, array $set, array $def, array $groups, string $view, string $where, array $b, bool $all = false): array
    {
        $select = [];
        $groupBy = [];
        foreach ($groups as $i => $g) {
            $select[] = $this->groupExpr($g)." AS g{$i}";
            $groupBy[] = $this->groupExpr($g);
            $base = explode(':', $g, 2)[0];
            if (! empty($set['medan'][$base]['nama'])) {
                $select[] = 'max('.$this->col($set['medan'][$base]['nama']).") AS n{$i}";
            }
        }
        foreach ($def['agregat'] as $j => $a) {
            $select[] = $this->aggExpr($a)." AS a{$j}";
        }
        $order = [];
        foreach ($def['susunan'] as $s) {
            $gi = array_search($s['medan'], $groups, true);
            if ($gi !== false) {
                $order[] = 'g'.$gi.' '.$s['arah'].' NULLS LAST';

                continue;
            }
            foreach ($def['agregat'] as $j => $a) {
                if (self::aggKey($a) === $s['medan']) {
                    $order[] = "a{$j} {$s['arah']} NULLS LAST";
                }
            }
        }
        if ($order === []) {
            $first = explode(':', $groups[0], 2);
            $order[] = isset($first[1]) ? 'g0 asc' : 'a0 desc NULLS LAST';
        }
        $order[] = 'g0 asc NULLS LAST';
        $limit = $all ? 100000 : $def['had'];
        $rows = $db->select('SELECT '.implode(', ', $select)." FROM {$view} WHERE {$where} GROUP BY ".implode(', ', $groupBy).' ORDER BY '.implode(', ', $order).' LIMIT '.(int) $limit, $b);

        $out = [];
        foreach ($rows as $r) {
            $r = (array) $r;
            $row = ['_drill' => [], '_label' => []];
            foreach ($groups as $i => $g) {
                $v = $r["g{$i}"];
                $row['_drill'][$g] = $v;
                $row[$g] = $v;
                $row['_label'][$g] = $r["n{$i}"] ?? null;
            }
            foreach ($def['agregat'] as $j => $a) {
                $row[self::aggKey($a)] = $this->aggValue($set, $a, $r["a{$j}"]);
            }
            $out[] = $row;
        }

        return $out;
    }

    private function aggValue(array $set, array $a, mixed $v): mixed
    {
        if (in_array($a['fungsi'], ['bilangan', 'bilangan_benar'], true)) {
            return (int) $v;
        }
        if ($v === null) {
            return null;
        }
        $type = $set['medan'][$a['medan']]['jenis'] ?? 'nombor';
        if ($type === 'tarikh') {
            return substr((string) $v, 0, 10);
        }

        return Wang::nilai($v);
    }

    /**
     * Year-on-year: add <agg>__lepas, <agg>__beza and <agg>__peratus (bcmath).
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $previous
     * @return list<array<string, mixed>>
     */
    private function compare(array $rows, array $previous, array $def, array $groups): array
    {
        $keyOf = function (array $row) use ($groups): string {
            $parts = [];
            foreach ($groups as $g) {
                $v = (string) ($row[$g] ?? '');
                // A previous-year date bucket lines up with this year's: shift +1 year.
                $parts[] = $v;
            }

            return implode('|', $parts);
        };
        $shift = function (array $row) use ($groups): array {
            foreach ($groups as $g) {
                $grain = explode(':', $g, 2)[1] ?? null;
                if ($grain !== null && is_string($row[$g] ?? null) && preg_match('/^(\d{4})(.*)$/', $row[$g], $m)) {
                    $row[$g] = ((int) $m[1] + 1).$m[2];
                }
            }

            return $row;
        };
        $prev = [];
        foreach ($previous as $p) {
            $prev[$keyOf($shift($p))] = $p;
        }
        foreach ($rows as &$row) {
            $p = $prev[$keyOf($row)] ?? null;
            foreach ($def['agregat'] as $a) {
                if (in_array($a['fungsi'], ['min', 'maks'], true)) {
                    continue;
                }
                $k = self::aggKey($a);
                $before = $p[$k] ?? 0;
                $row[$k.'__lepas'] = is_int($row[$k]) ? (int) $before : Wang::nilai($before);
                $row[$k.'__beza'] = is_int($row[$k]) ? (int) $row[$k] - (int) $before : Wang::tolak($row[$k], $before);
                $row[$k.'__peratus'] = Wang::peratusPerubahan($row[$k] ?? 0, $before);
            }
        }

        return $rows;
    }

    /** @return array{0: list<array<string, mixed>>, 1: int} */
    private function records($db, array $set, array $def, string $view, string $where, array $b, array $opts, int $total): array
    {
        $fields = $def['medan'] !== [] ? $def['medan'] : array_keys(array_filter($this->visibleDefaults($set), fn ($f) => true));
        $extra = ['id'];
        if (preg_match_all('/\{([a-z_]+)\}/', (string) ($set['pautan'] ?? ''), $m)) {
            array_push($extra, ...$m[1]);
        }
        $cols = array_values(array_unique([...$fields, ...$extra]));
        foreach ($fields as $f) {
            if (! empty($set['medan'][$f]['nama'])) {
                $cols[] = $set['medan'][$f]['nama'];
            }
        }
        $cols = array_values(array_unique($cols));

        $order = [];
        foreach ($def['susunan'] as $s) {
            if (isset($set['medan'][$s['medan']])) {
                $order[] = $this->col($s['medan']).' '.$s['arah'].' NULLS LAST';
            }
        }
        if ($order === []) {
            $order[] = $set['tarikh'] ? $this->col($set['tarikh']).' desc NULLS LAST' : $this->col($fields[0]).' asc';
        }
        $order[] = '"id" asc';

        $all = (bool) ($opts['semua'] ?? false);
        $per = $all ? $this->maxExport() : max(1, min((int) ($opts['per_halaman'] ?? 50), 500));
        $page = $all ? 1 : max(1, (int) ($opts['halaman'] ?? 1));
        $rows = $db->select('SELECT '.implode(', ', array_map(fn ($c) => $this->col($c), $cols))." FROM {$view} WHERE {$where} ORDER BY ".implode(', ', $order).' LIMIT ? OFFSET ?', [...$b, $per, ($page - 1) * $per]);

        $out = [];
        foreach ($rows as $r) {
            $r = (array) $r;
            $row = ['_id' => (string) $r['id'], '_pautan' => $this->link((string) ($set['pautan'] ?? ''), $r), '_label' => []];
            foreach ($fields as $f) {
                $row[$f] = $this->cell($set['medan'][$f]['jenis'], $r[$f] ?? null);
                if (! empty($set['medan'][$f]['nama'])) {
                    $row['_label'][$f] = $r[$set['medan'][$f]['nama']] ?? null;
                }
            }
            $out[] = $row;
        }

        return [$out, $total];
    }

    /** @return array<string, array<string, mixed>> */
    private function visibleDefaults(array $set): array
    {
        return array_slice(array_filter($set['medan'], fn ($f) => empty($f['kebenaran'])), 0, 8, true);
    }

    private function cell(string $type, mixed $v): mixed
    {
        if ($v === null) {
            return null;
        }

        return match ($type) {
            'wang' => Wang::nilai($v),
            'nombor' => is_numeric($v) && ! str_contains((string) $v, '.') ? (int) $v : Wang::nilai($v),
            'tarikh' => substr((string) $v, 0, 10),
            'bool' => (bool) $v,
            default => (string) $v,
        };
    }

    private function link(string $template, array $row): ?string
    {
        if ($template === '') {
            return null;
        }
        if ($template === '{pautan}') {
            $p = (string) ($row['pautan'] ?? '');

            return str_starts_with($p, '/') && ! str_starts_with($p, '//') ? $p : null;
        }
        $missing = false;
        $url = preg_replace_callback('/\{([a-z_]+)\}/', function ($m) use ($row, &$missing) {
            $v = $row[$m[1]] ?? null;
            if ($v === null || $v === '') {
                $missing = true;
            }

            return rawurlencode((string) $v);
        }, $template);

        return $missing ? null : $url;
    }

    /** @return list<array{kunci: string, label: string, jenis: string}> */
    private function columns(array $set, array $def, array $groups, string $mode): array
    {
        $cols = [];
        if ($mode === 'kumpulan') {
            foreach ($groups as $g) {
                [$base, $grain] = array_pad(explode(':', $g, 2), 2, null);
                $label = $set['medan'][$base]['label'].($grain ? ' ('.__('lap::lap.butiran.'.$grain).')' : '');
                $cols[] = ['kunci' => $g, 'label' => $label, 'jenis' => $grain ? 'tempoh' : $set['medan'][$base]['jenis'], 'kumpulan' => true];
            }
            foreach ($def['agregat'] as $a) {
                $k = self::aggKey($a);
                $type = match ($a['fungsi']) {
                    'bilangan', 'bilangan_benar' => 'bilangan',
                    'peratus_benar' => 'peratus',
                    default => $set['medan'][$a['medan']]['jenis'],
                };
                $label = $a['fungsi'] === 'bilangan' ? __('lap::lap.fungsi.bilangan') : __('lap::lap.fungsi.'.$a['fungsi']).' '.mb_strtolower(mb_substr($set['medan'][$a['medan']]['label'], 0, 1)).mb_substr($set['medan'][$a['medan']]['label'], 1);
                $cols[] = ['kunci' => $k, 'label' => $label, 'jenis' => $type];
                if ($def['bandingan'] === 'tahun_lepas' && $def['tempoh'] !== null && ! in_array($a['fungsi'], ['min', 'maks'], true)) {
                    $cols[] = ['kunci' => $k.'__lepas', 'label' => $label.' — '.__('lap::lap.bandingan.lepas'), 'jenis' => $type];
                    $cols[] = ['kunci' => $k.'__beza', 'label' => __('lap::lap.bandingan.beza'), 'jenis' => $type];
                    $cols[] = ['kunci' => $k.'__peratus', 'label' => __('lap::lap.bandingan.peratus'), 'jenis' => 'peratus'];
                }
            }

            return $cols;
        }
        $fields = $def['medan'] !== [] ? $def['medan'] : array_keys($this->visibleDefaults($set));
        foreach ($fields as $f) {
            $cols[] = ['kunci' => $f, 'label' => $set['medan'][$f]['label'], 'jenis' => $set['medan'][$f]['jenis']];
        }

        return $cols;
    }

    /**
     * Grand totals of additive columns, with bcmath.
     *
     * @param  list<array{kunci: string, label: string, jenis: string}>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int|string>
     */
    private function totals(array $columns, array $rows): array
    {
        $out = [];
        foreach ($columns as $c) {
            $k = $c['kunci'];
            $additive = $c['jenis'] === 'bilangan' || str_starts_with($k, 'jumlah_');
            if (! $additive || str_ends_with($k, '__peratus')) {
                continue;
            }
            $values = array_column($rows, $k);
            $out[$k] = $c['jenis'] === 'bilangan' ? array_sum(array_map('intval', $values)) : Wang::jumlah($values);
        }

        return $out;
    }

    /** @return list<string> human-readable filters (SRS-LAP-11) */
    public function describe(array $set, array $def, array $drill = ['penapis' => []]): array
    {
        $out = [];
        foreach ([...$def['penapis'], ...($drill['penapis'] ?? [])] as $p) {
            $label = $set['medan'][$p['medan']]['label'] ?? $p['medan'];
            $v = $p['nilai'];
            $v = is_array($v) ? implode($p['op'] === 'antara' ? ' – ' : ', ', $v) : (string) $v;
            $out[] = trim($label.' '.__('lap::lap.op.'.$p['op']).' '.$v);
        }
        if ($def['tempoh'] !== null) {
            $t = $def['tempoh'];
            $out[] = __('lap::lap.tempoh_teks', [
                'medan' => $set['medan'][$t['medan']]['label'] ?? $t['medan'],
                'dari' => Carbon::parse($t['dari'])->format('d/m/Y'),
                'hingga' => Carbon::parse($t['hingga'])->format('d/m/Y'),
            ]);
        }

        return $out;
    }

    private function hasClassification(array $set): bool
    {
        return isset($set['medan']['klasifikasi']);
    }

    public function maxRows(): int
    {
        return max(1, (int) $this->parameters->get('lap.had_baris', 5000));
    }

    public function maxExport(): int
    {
        return max(1, (int) $this->parameters->get('lap.had_eksport', 50000));
    }
}
