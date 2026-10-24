<?php

namespace App\Modules\Hkm\Support;

use App\Modules\Adm\Contracts\Parameters;

/**
 * SRS-HKM-08: the land status list is configurable by MAIS through the ADM
 * parameter `hkm.senarai_status_tanah` (json list of {kod, nama, aktif?}).
 * Deactivated codes stay valid for history display but can't be chosen.
 */
class StatusTanah
{
    public const PARAMETER = 'hkm.senarai_status_tanah';

    /** Seeded default (SRS-HKM-08 minimum set + title lifecycle codes). */
    public const LALAI = [
        ['kod' => 'aktif', 'nama' => 'Aktif'],
        ['kod' => 'kosong', 'nama' => 'Kosong'],
        ['kod' => 'disewa', 'nama' => 'Disewa'],
        ['kod' => 'dipajak', 'nama' => 'Dipajak'],
        ['kod' => 'dalam_penyelenggaraan', 'nama' => 'Dalam penyelenggaraan'],
        ['kod' => 'tindakan_undang_undang', 'nama' => 'Dalam tindakan undang-undang'],
        ['kod' => 'proses_tukar_syarat', 'nama' => 'Dalam proses tukar syarat'],
        ['kod' => 'dilupuskan', 'nama' => 'Dilupuskan'],
        ['kod' => 'diganti', 'nama' => 'Diganti (hak milik baharu dikeluarkan)'],
    ];

    public function __construct(private Parameters $parameters) {}

    /** @return list<array{kod: string, nama: string, aktif: bool}> */
    public function all(): array
    {
        $raw = $this->parameters->get(self::PARAMETER, self::LALAI);
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (! is_array($raw) || $raw === []) {
            $raw = self::LALAI;
        }

        $list = [];
        foreach ($raw as $row) {
            if (is_array($row) && isset($row['kod'], $row['nama'])) {
                $list[] = ['kod' => (string) $row['kod'], 'nama' => (string) $row['nama'], 'aktif' => (bool) ($row['aktif'] ?? true)];
            }
        }

        return $list;
    }

    /** @return list<string> codes that may be set now */
    public function activeCodes(): array
    {
        return array_values(array_map(fn ($r) => $r['kod'], array_filter($this->all(), fn ($r) => $r['aktif'])));
    }

    public function label(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }
        foreach ($this->all() as $row) {
            if ($row['kod'] === $code) {
                return $row['nama'];
            }
        }

        return $code;
    }
}
