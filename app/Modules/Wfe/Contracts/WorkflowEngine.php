<?php

namespace App\Modules\Wfe\Contracts;

use App\Models\User;

/**
 * Central approval engine (SRS-GEN-03). A module that needs approval starts
 * an instance of an admin-defined flow for its record and listens for
 * Events\WorkflowCompleted to apply the outcome (SRS-WFE-20). It never
 * implements approval steps itself.
 */
interface WorkflowEngine
{
    /**
     * Start the active version of flow $flowCode for a record.
     *
     * @param  string  $subjectType  e.g. "pajakan_kontrak" (table name)
     * @param  array<string, mixed>  $context  values branch conditions can test (nilai, kategori_tanah, kod_daerah…)
     * @return string workflow instance id
     *
     * @throws \InvalidArgumentException when no active flow has that code
     */
    public function start(string $flowCode, string $subjectType, string|int $subjectId, User $initiator, array $context = [], ?string $title = null): string;

    /** "berjalan", "lulus", "tolak", "ditarik_balik" or "dibatalkan". */
    public function status(string $instanceId): string;

    /** Current running instance for a record, if any. */
    public function activeInstanceFor(string $subjectType, string|int $subjectId): ?string;
}
