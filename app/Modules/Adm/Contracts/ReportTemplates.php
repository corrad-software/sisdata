<?php

namespace App\Modules\Adm\Contracts;

/**
 * Admin-managed report templates (SRS-ADM-03): content with {{field}}
 * placeholders, output format and versions. Notification templates belong to
 * MOD-NTF (SRS-NTF-03), not here.
 */
interface ReportTemplates
{
    /**
     * Active version rendered with $data (values are HTML-escaped), or null if
     * no active template has that code.
     *
     * @param  array<string, scalar|null>  $data
     * @return array{format: string, versi: int, kandungan: string}|null
     */
    public function render(string $code, array $data): ?array;
}
