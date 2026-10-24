<?php

namespace App\Modules\Dms\Contracts;

use App\Models\User;

/**
 * SRS-DMS-16/17: two-way drill-down between a document and its parent record.
 * Each module that attaches documents registers how its records are opened in
 * the frontend, from its provider's boot():
 *
 *     app(RecordLinks::class)->register('lot_tanah', 'Lot Tanah', '/admin/pro/lot/{id}', 'pro.lot.lihat');
 *
 * The document detail then shows the parent with a deep link — only when the
 * user holds $permission (the target page still enforces its own access).
 * A caller may instead pass metadata['pautan_induk'] at store-time.
 */
interface RecordLinks
{
    /**
     * @param  string  $ownerType  table name used as ownerType in DocumentStore::store()
     * @param  string  $pathTemplate  frontend path with an {id} placeholder
     */
    public function register(string $ownerType, string $label, string $pathTemplate, ?string $permission = null): void;

    /**
     * @return array{label: string, link: string|null}
     */
    public function resolve(string $ownerType, string $ownerId, User $user): array;
}
