<?php

namespace App\Modules\Ntf\Services;

use App\Modules\Adm\Contracts\Parameters;

/**
 * MOD-NTF tunables, declared to MOD-ADM's Parameters (SRS-ADM-01) and read
 * with our own default so behaviour never depends on declaration order.
 */
class Settings
{
    /** @var array<string, array{0: string, 1: mixed, 2: string}> key => [type, default, Malay label] */
    public const DECLARATIONS = [
        'ntf.cubaan_maksimum' => ['int', 4, 'Notifikasi: bilangan cubaan penghantaran maksimum (termasuk cubaan pertama)'],
        'ntf.backoff_saat' => ['json', [60, 300, 900], 'Notifikasi: selang cuba semula (saat) yang menaik'],
        'ntf.tempoh_eskalasi_minit' => ['int', 240, 'Notifikasi: tempoh lalai sebelum notifikasi Kritikal belum dibaca dieskalasi (minit)'],
        'ntf.tempoh_pendiaman_minit' => ['int', 60, 'Notifikasi: tempoh pendiaman lalai bagi peristiwa berulang (minit)'],
        'ntf.peranan_penyelia' => ['string', 'admin', 'Notifikasi: peranan penyelia lalai bagi eskalasi'],
        'ntf.masa_ringkasan' => ['string', '08:00', 'Notifikasi: masa penghantaran ringkasan harian'],
        'ntf.masa_peringatan' => ['string', '07:00', 'Notifikasi: masa larian peringatan berjadual harian'],
        'ntf.medan_sensitif_global' => ['json', [
            'no_kp', 'no_kad_pengenalan', 'kad_pengenalan', 'nilai_penilaian', 'nilai_pasaran', 'nilai_setara',
            'no_hak_milik', 'kadar_sewa', 'deposit', 'no_akaun_bank', 'kata_laluan', 'perintah_mahkamah',
        ], 'Notifikasi: medan Sulit/Rahsia yang tidak boleh dimuatkan dalam kandungan'],
        'ntf.had_pengumuman_sejam' => ['int', 10, 'Notifikasi: had pengumuman manual sejam bagi setiap pentadbir'],
    ];

    public function __construct(private Parameters $parameters) {}

    public static function declare(Parameters $parameters): void
    {
        foreach (self::DECLARATIONS as $key => [$type, $default, $label]) {
            $parameters->declare($key, $type, $default, $label);
        }
    }

    public function get(string $key): mixed
    {
        $default = self::DECLARATIONS[$key][1] ?? null;

        return $this->parameters->get($key, $default) ?? $default;
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    /** @return list<int> */
    public function backoff(): array
    {
        $value = $this->get('ntf.backoff_saat');
        $value = is_string($value) ? json_decode($value, true) : $value;

        return array_values(array_map('intval', is_array($value) && $value !== [] ? $value : [60, 300, 900]));
    }

    /** @return list<string> */
    public function sensitiveFields(): array
    {
        $value = $this->get('ntf.medan_sensitif_global');
        $value = is_string($value) ? json_decode($value, true) : $value;

        return array_values(array_map('strval', is_array($value) ? $value : []));
    }
}
