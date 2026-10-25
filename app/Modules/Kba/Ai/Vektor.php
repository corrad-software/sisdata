<?php

namespace App\Modules\Kba\Ai;

final class Vektor
{
    /**
     * @param  list<float>  $v
     * @return list<float>
     */
    public static function normalise(array $v): array
    {
        $norm = sqrt(array_sum(array_map(fn (float $x) => $x * $x, $v)));

        return $norm > 0 ? array_map(fn (float $x) => $x / $norm, $v) : $v;
    }

    /** @param list<float> $v pgvector literal "[0.1,0.2,…]" */
    public static function literal(array $v): string
    {
        return '['.implode(',', array_map(fn (float $x) => rtrim(rtrim(sprintf('%.7F', $x), '0'), '.') ?: '0', $v)).']';
    }
}
