<?php

namespace App\Modules\Kes\Support;

use App\Models\User;
use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Ntf\Contracts\RecipientDirectory;

/**
 * Notification recipients (SRS-KES-10/11): the responsible case officer and
 * the Ketua Bahagian Perundangan — every active user holding `kes.kes.lulus`
 * (resolved through MOD-NTF's RecipientDirectory, i.e. MOD-RBC).
 */
class Penerima
{
    /** @var list<User>|null */
    private ?array $ketua = null;

    public function __construct(private RecipientDirectory $directory) {}

    /** @return list<User> */
    public function ketuaBahagian(): array
    {
        return $this->ketua ??= $this->directory->resolve(RecipientDirectory::KEBENARAN, 'kes.kes.lulus');
    }

    /** @return list<User> */
    public function pegawaiDanKetua(KesMahkamah $kes): array
    {
        $out = [];
        if ($kes->pegawai !== null && $kes->pegawai->is_active) {
            $out[$kes->pegawai->id] = $kes->pegawai;
        }
        foreach ($this->ketuaBahagian() as $u) {
            $out[$u->id] = $u;
        }

        return array_values($out);
    }
}
