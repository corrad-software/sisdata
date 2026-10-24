<?php

namespace App\Modules\Dms\Services;

use App\Modules\Adm\Contracts\Parameters;

/**
 * Typed access to the MOD-DMS parameters that administrators change in
 * MOD-ADM without a release (SRS-DMS-03/05/06/15/25, SRS-ADM-01).
 */
class DmsSettings
{
    public const DEFAULT_WHITELIST = ['pdf', 'jpg', 'jpeg', 'png', 'tiff', 'docx', 'xlsx', 'pptx', 'dwg', 'shp', 'kml', 'kmz', 'geojson'];

    /** key => [type, default, label, critical] */
    public const PARAMETERS = [
        'dms.senarai_putih' => ['json', self::DEFAULT_WHITELIST, 'DMS: Senarai putih jenis fail', true],
        'dms.had_saiz_pejabat_mb' => ['int', 25, 'DMS: Had saiz fail pejabat & imej (MB)', false],
        'dms.had_saiz_spatial_mb' => ['int', 250, 'DMS: Had saiz fail spatial (MB)', false],
        'dms.had_transaksi_mb' => ['int', 500, 'DMS: Had saiz kumulatif setiap transaksi muat naik (MB)', false],
        'dms.had_fail_kelompok' => ['int', 50, 'DMS: Bilangan maksimum fail setiap muat naik berkelompok', false],
        'dms.kuota_gb' => ['int', 500, 'DMS: Kuota storan dokumen (GB)', false],
        'dms.kuota_amaran_peratus' => ['int', 80, 'DMS: Amaran kuota storan pada peratus', false],
        'dms.amaran_luput_hari' => ['int', 30, 'DMS: Tempoh amaran sebelum tarikh luput (hari)', false],
        'dms.pautan_minit' => ['int', 5, 'DMS: Tempoh sah pautan muat turun (minit)', false],
        'dms.ocr_bahasa' => ['string', 'msa+eng', 'DMS: Bahasa OCR (Tesseract)', false],
        'dms.imbasan_wajib' => ['bool', true, 'DMS: Tolak fail jika antivirus tidak tersedia', true],
    ];

    public function __construct(private Parameters $parameters) {}

    public static function declareAll(Parameters $parameters): void
    {
        foreach (self::PARAMETERS as $key => [$type, $default, $label, $critical]) {
            $parameters->declare($key, $type, $default, $label, $critical);
        }
    }

    /** @return list<string> lower-case canonical type codes */
    public function whitelist(): array
    {
        $value = $this->get('dms.senarai_putih');
        if (is_string($value)) {
            $value = json_decode($value, true) ?? preg_split('/[\s,]+/', $value);
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($v) => strtolower(trim((string) $v, " .\t\n")),
            is_array($value) ? $value : self::DEFAULT_WHITELIST,
        ))));
    }

    public function sizeLimitMb(string $group): int
    {
        return $group === 'spatial' ? $this->int('dms.had_saiz_spatial_mb') : $this->int('dms.had_saiz_pejabat_mb');
    }

    public function transactionLimitMb(): int
    {
        return $this->int('dms.had_transaksi_mb');
    }

    public function batchLimit(): int
    {
        return max(20, $this->int('dms.had_fail_kelompok'));
    }

    public function quotaBytes(): int
    {
        return $this->int('dms.kuota_gb') * 1024 ** 3;
    }

    public function quotaWarnPercent(): int
    {
        return $this->int('dms.kuota_amaran_peratus');
    }

    public function expiryWarnDays(): int
    {
        return $this->int('dms.amaran_luput_hari');
    }

    public function linkMinutes(): int
    {
        return max(1, $this->int('dms.pautan_minit'));
    }

    public function ocrLanguages(): string
    {
        $value = (string) $this->get('dms.ocr_bahasa');

        return preg_match('/^[a-z_]+(\+[a-z_]+)*$/', $value) ? $value : 'msa+eng';
    }

    public function scanRequired(): bool
    {
        return filter_var($this->get('dms.imbasan_wajib'), FILTER_VALIDATE_BOOL);
    }

    private function int(string $key): int
    {
        return (int) $this->get($key);
    }

    private function get(string $key): mixed
    {
        return $this->parameters->get($key, self::PARAMETERS[$key][1]);
    }
}
