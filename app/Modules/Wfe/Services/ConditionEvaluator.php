<?php

namespace App\Modules\Wfe\Services;

/**
 * SRS-WFE-02: branch conditions on start-context values.
 *
 * A deliberately small, data-only expression model — never eval(). A
 * condition is {medan, operator, nilai}; `medan` is a dot path into the
 * context (e.g. "nilai", "lot.kod_daerah"); operators are a closed list.
 * Numeric operands compare numerically, everything else as strings.
 */
class ConditionEvaluator
{
    /** operator => Malay label (shown in the editors) */
    public const OPERATORS = [
        'sama' => 'sama dengan',
        'tidak_sama' => 'tidak sama dengan',
        'lebih' => 'lebih daripada',
        'lebih_sama' => 'lebih atau sama dengan',
        'kurang' => 'kurang daripada',
        'kurang_sama' => 'kurang atau sama dengan',
        'dalam' => 'salah satu daripada',
        'tiada_dalam' => 'bukan salah satu daripada',
        'mengandungi' => 'mengandungi',
        'wujud' => 'ada nilai',
        'tiada' => 'tiada nilai',
    ];

    /**
     * @param  iterable<array{medan: string, operator: string, nilai?: mixed}|object>  $conditions
     * @param  array<string, mixed>  $context
     * @param  string  $logic  semua (AND) | mana_mana (OR)
     */
    public function matchesAll(iterable $conditions, array $context, string $logic = 'semua'): bool
    {
        $results = [];
        foreach ($conditions as $c) {
            $c = is_object($c) ? ['medan' => $c->medan, 'operator' => $c->operator, 'nilai' => $c->nilai] : $c;
            $results[] = $this->matches($c['medan'], $c['operator'], $c['nilai'] ?? null, $context);
        }

        if ($results === []) {
            return true;
        }

        return $logic === 'mana_mana' ? in_array(true, $results, true) : ! in_array(false, $results, true);
    }

    /** @param array<string, mixed> $context */
    public function matches(string $field, string $operator, mixed $expected, array $context): bool
    {
        $exists = $this->has($context, $field);
        $actual = $exists ? data_get($context, $field) : null;

        return match ($operator) {
            'wujud' => $exists && $actual !== null && $actual !== '',
            'tiada' => ! $exists || $actual === null || $actual === '',
            'sama' => $exists && $this->compare($actual, $expected) === 0,
            'tidak_sama' => ! $exists || $this->compare($actual, $expected) !== 0,
            'lebih' => $exists && $this->compare($actual, $expected) > 0,
            'lebih_sama' => $exists && $this->compare($actual, $expected) >= 0,
            'kurang' => $exists && $this->compare($actual, $expected) < 0,
            'kurang_sama' => $exists && $this->compare($actual, $expected) <= 0,
            'dalam' => $exists && $this->inList($actual, $expected),
            'tiada_dalam' => ! $exists || ! $this->inList($actual, $expected),
            'mengandungi' => $exists && $this->contains($actual, $expected),
            default => throw new \InvalidArgumentException("Unknown operator {$operator}"),
        };
    }

    /** @param array<string, mixed> $context */
    private function has(array $context, string $field): bool
    {
        $cursor = $context;
        foreach (explode('.', $field) as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return false;
            }
            $cursor = $cursor[$segment];
        }

        return true;
    }

    private function compare(mixed $a, mixed $b): int
    {
        if (is_bool($a) || is_bool($b)) {
            return $this->bool($a) <=> $this->bool($b);
        }
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a <=> (float) $b;
        }
        if (! is_scalar($a) || ! is_scalar($b)) {
            return $a === $b ? 0 : 1;
        }

        return strcasecmp((string) $a, (string) $b) <=> 0;
    }

    private function bool(mixed $v): bool
    {
        return is_string($v) ? in_array(strtolower($v), ['1', 'true', 'ya'], true) : (bool) $v;
    }

    private function inList(mixed $actual, mixed $list): bool
    {
        $list = is_array($list) ? $list : array_map('trim', explode(',', (string) $list));
        foreach ($list as $item) {
            if ($this->compare($actual, $item) === 0) {
                return true;
            }
        }

        return false;
    }

    private function contains(mixed $actual, mixed $needle): bool
    {
        if (is_array($actual)) {
            return $this->inList($needle, $actual);
        }

        return is_scalar($actual) && is_scalar($needle) && $needle !== ''
            && str_contains(mb_strtolower((string) $actual), mb_strtolower((string) $needle));
    }
}
