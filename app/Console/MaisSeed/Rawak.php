<?php

namespace App\Console\MaisSeed;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Deterministic random source per property: the same property key always
 * yields the same synthetic lot number, title, tenants, amounts… so a
 * re-run (or a run on another database) produces the same demo story.
 */
class Rawak
{
    private Randomizer $r;

    public function __construct(string $key)
    {
        $this->r = new Randomizer(new Mt19937(crc32('mais_awam:'.$key) & 0x7FFFFFFF));
    }

    /** @phpstan-impure */
    public function int(int $min, int $max): int
    {
        return $this->r->getInt($min, $max);
    }

    /** @phpstan-impure */
    public function float(float $min, float $max): float
    {
        return $this->r->getFloat($min, $max);
    }

    /** @phpstan-impure */
    public function chance(int $percent): bool
    {
        return $this->r->getInt(1, 100) <= $percent;
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $list
     * @return T
     *
     * @phpstan-impure
     */
    public function pick(array $list): mixed
    {
        $list = array_values($list);

        return $list[$this->r->getInt(0, count($list) - 1)];
    }

    /**
     * @param  array<int|string, int>  $weights
     *
     * @phpstan-impure
     */
    public function weighted(array $weights): string
    {
        $n = $this->r->getInt(1, max(1, array_sum($weights)));
        foreach ($weights as $value => $weight) {
            $n -= $weight;
            if ($n <= 0) {
                return (string) $value;
            }
        }

        return (string) array_key_last($weights);
    }
}
