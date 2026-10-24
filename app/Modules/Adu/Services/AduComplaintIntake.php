<?php

namespace App\Modules\Adu\Services;

use App\Models\User;
use App\Modules\Adu\Contracts\ComplaintIntake;
use App\Modules\Adu\Support\Lalai;
use App\Modules\Adu\Support\Rujukan;
use InvalidArgumentException;

/**
 * ComplaintIntake for other modules (MOD-PTN field findings, MOD-PWS portal,
 * the central complaints adapter, MOD-TKT). Same queue, triage, SLA and
 * routing as internal intake; idempotent on (channel, sourceRef) so a retry
 * returns the existing complaint id instead of a second complaint.
 */
class AduComplaintIntake implements ComplaintIntake
{
    public function __construct(private AduanService $service, private Rujukan $ref) {}

    public function open(array $data, string $channel, ?User $reporter = null, ?string $sourceRef = null): string
    {
        if (! array_key_exists($channel, Lalai::SALURAN)) {
            throw new InvalidArgumentException("Saluran aduan tidak dikenali: {$channel}");
        }
        $kategori = $data['kategori'];
        if ($kategori !== '' && ! $this->ref->kategoriSah($kategori)) {
            $kategori = '';
        }
        if (trim($data['keterangan']) === '') {
            throw new InvalidArgumentException('Keterangan aduan diperlukan.');
        }

        $contact = $data['pelapor_hubungan'] ?? null;
        $pengadu = null;
        if (! empty($data['pelapor_nama']) || ! empty($contact)) {
            $pengadu = [
                'nama' => $data['pelapor_nama'] ?? null,
                'emel' => is_string($contact) && str_contains($contact, '@') ? $contact : null,
                'no_telefon' => is_string($contact) && ! str_contains($contact, '@') ? $contact : null,
            ];
        }

        $aduan = $this->service->terima([
            'kategori' => $kategori !== '' ? $kategori : null,
            'keterangan' => $data['keterangan'],
            'lot_id' => $data['lot_id'] ?? null,
            'kod_daerah' => $data['kod_daerah'] ?? null,
            'kod_mukim' => $data['kod_mukim'] ?? null,
            'lat' => $data['lat'] ?? null,
            'lng' => $data['lng'] ?? null,
            'tahap_keutamaan' => array_key_exists((string) ($data['keutamaan'] ?? ''), Lalai::KEUTAMAAN) ? $data['keutamaan'] : 'sederhana',
        ], $channel, $reporter, $sourceRef, $pengadu, $pengadu === null);

        return $aduan->id;
    }
}
