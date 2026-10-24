<?php

namespace App\Modules\Pro\Sections;

use App\Models\User;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Pro\Contracts\ProfileSection;
use App\Modules\Pro\Services\LotQuery;
use Throwable;

/** Tab "Dokumen & Jejak": documents attached to the lot through MOD-DMS (SRS-GEN-04). */
class DokumenSection implements ProfileSection
{
    public function __construct(private LotQuery $lots, private DocumentStore $documents) {}

    public function key(): string
    {
        return 'dokumen';
    }

    public function tab(): string
    {
        return 'dokumen';
    }

    public function label(): string
    {
        return (string) __('pro::pro.seksyen.dokumen');
    }

    /** @return list<array{id: string, name: string, category: string, version: int, classification: string, uploaded_at: string}> */
    private function docs(string $lotId, User $user): array
    {
        if ($this->lots->find($user, $lotId) === null) {
            return [];
        }
        try {
            return $this->documents->forOwner('lot_tanah', $lotId, $user);
        } catch (Throwable) {
            return [];
        }
    }

    public function forLot(string $lotId, User $user): ?array
    {
        if ($this->lots->find($user, $lotId) === null) {
            return null;
        }

        return [
            'items' => array_map(fn ($d) => [
                'id' => $d['id'], 'label' => $d['name'], 'nilai' => $d['category'], 'versi' => $d['version'],
                'klasifikasi' => $d['classification'], 'tarikh' => $d['uploaded_at'], 'link' => '/admin/dms/'.$d['id'],
            ], $this->docs($lotId, $user)),
            'link' => '/admin/dms?jenis_entiti_induk=lot_tanah&id_rekod_induk='.$lotId,
        ];
    }

    public function timeline(string $lotId, User $user): array
    {
        return array_map(fn ($d) => [
            'date' => $d['uploaded_at'],
            'type' => 'dokumen',
            'title' => (string) __('pro::pro.garis_masa.dokumen', ['nama' => $d['name']]),
            'link' => '/admin/dms/'.$d['id'],
        ], $this->docs($lotId, $user));
    }
}
