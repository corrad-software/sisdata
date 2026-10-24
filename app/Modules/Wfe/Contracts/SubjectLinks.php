<?php

namespace App\Modules\Wfe\Contracts;

/**
 * Drill-down from the workflow dashboard to the source record (SRS-WFE-15).
 * A starting module either passes `pautan` in start()'s context, or
 * registers a frontend path pattern for its subject type once:
 *
 *     app(SubjectLinks::class)->register('kontrak_pajakan', '/admin/pjk/kontrak/{id}');
 *
 * `{id}` is replaced with the subject id. The target page enforces its own
 * access control.
 */
interface SubjectLinks
{
    public function register(string $subjectType, string $pathPattern): void;

    public function linkFor(string $subjectType, string|int $subjectId): ?string;
}
