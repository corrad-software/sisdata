<?php

namespace App\Modules\Dsh\Contracts;

use App\Models\User;

/**
 * Dashboard insights and natural-language prompts (SRS-DSH-15..18). MUST run
 * inside MAIS's own environment: the default implementation is rule-based
 * (no model at all); an optional adapter may call a LOCAL model server
 * (e.g. Ollama on localhost / the MAIS VPC), never a public AI service
 * (SRS-DSH-18).
 *
 * Implementations only ever see KPI results that were already computed for
 * $user under that user's RBAC scope — they never query data themselves, so
 * a prompt cannot widen the scope (SRS-DSH-17).
 */
interface InsightGenerator
{
    /** Short engine id recorded in the prompt log, e.g. "peraturan", "ollama". */
    public function engine(): string;

    /**
     * Short observations on the KPIs shown (traffic-light status, trend
     * direction, biggest slice…), in Malay.
     *
     * @param  list<array<string, mixed>>  $kpis  KpiService results (key, label, nilai, unit, lampu, trend, pecahan…)
     * @return list<array{tahap: string, teks: string, kpi?: string}> tahap: baik | perhatian | kritikal | maklumat
     */
    public function insights(User $user, array $kpis): array;

    /**
     * Turn a Malay/English question into a structured dashboard query. The
     * caller executes it under $user's scope. `niat` is one of nilai | senarai
     * | trend | susun_atur | tidak_difahami.
     *
     * @param  list<array{key: string, label: string, domain: string}>  $catalogue  KPIs $user may see
     * @param  array<string, string>  $districts  kod => nama (all districts; the scope is applied later)
     * @return array{niat: string, kpi: list<string>, penapis: array<string, string|int>, visual?: string, susun_atur?: array{tindakan: string, kpi: list<string>}}
     */
    public function interpret(User $user, string $prompt, array $catalogue, array $districts): array;
}
