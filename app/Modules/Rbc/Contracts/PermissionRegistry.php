<?php

namespace App\Modules\Rbc\Contracts;

/**
 * Each module declares its permission catalogue once, from its service
 * provider's boot(). MOD-RBC shows these in the role editor and access matrix;
 * a permission exists only if declared (least privilege — SRS-RBC-09).
 *
 *     app(PermissionRegistry::class)->register('dms', 'Dokumen Digital', [
 *         'dms.dokumen.lihat' => 'Lihat dokumen',
 *         'dms.dokumen.cipta' => 'Muat naik dokumen',
 *     ]);
 */
interface PermissionRegistry
{
    /**
     * @param  array<string, string>  $permissions  key "<modul>.<fungsi>.<tindakan>" => Malay label
     */
    public function register(string $module, string $label, array $permissions): void;

    /**
     * @return array<string, array{label: string, permissions: array<string, string>}>
     */
    public function all(): array;
}
