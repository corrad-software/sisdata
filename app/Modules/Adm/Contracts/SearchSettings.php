<?php

namespace App\Modules\Adm\Contracts;

/**
 * Admin-configured search settings (SRS-ADM-20) a SearchProvider may honour:
 * which fields of its entity are searchable. Weights, synonyms and on/off are
 * applied centrally by MOD-ADM.
 */
interface SearchSettings
{
    /**
     * Fields the admin enabled for this entity; empty = provider's default set.
     *
     * @return list<string>
     */
    public function fields(string $entity): array;
}
