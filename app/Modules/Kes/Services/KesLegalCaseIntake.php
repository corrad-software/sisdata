<?php

namespace App\Modules\Kes\Services;

use App\Models\User;
use App\Modules\Kes\Contracts\LegalCases;
use App\Modules\Ptn\Contracts\LegalCaseIntake;

/**
 * SRS-PTN-18 → MOD-KES: a monitoring finding escalated to legal action opens
 * a case (status "didaftar") linked to the lot, idempotent on sourceRef.
 * Replaces MOD-PTN's "not available" stub.
 */
class KesLegalCaseIntake implements LegalCaseIntake
{
    /** PTN issue category → case type. */
    private const JENIS = ['pencerobohan' => 'pencerobohan', 'sempadan' => 'sengketa_pemilikan', 'binaan_haram' => 'pencerobohan'];

    public function __construct(private LegalCases $cases) {}

    public function available(): bool
    {
        return true;
    }

    public function open(array $data, User $by, string $sourceRef): string
    {
        return $this->cases->open([
            'tajuk' => $data['tajuk'],
            'keterangan' => $data['keterangan'],
            'jenis' => self::JENIS[$data['kategori']] ?? null,
            'lot_ids' => [$data['lot_id']],
            'dokumen' => array_map(fn (string $id) => ['id_dokumen_dms' => $id], $data['rujukan_dokumen'] ?? []),
            'kronologi' => [['tarikh' => now()->toDateTimeString(), 'keterangan' => 'Dinaikkan daripada penemuan pemantauan tanah (MOD-PTN), kategori '.$data['kategori'].': '.$data['keterangan']]],
            'sumber' => 'ptn',
        ], $by, $sourceRef);
    }
}
