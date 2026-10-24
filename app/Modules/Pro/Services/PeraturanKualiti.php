<?php

namespace App\Modules\Pro\Services;

use App\Modules\Pro\Models\LotTanah;
use App\Modules\Pro\Models\PeraturanKualitiData;
use App\Modules\Pro\Support\NoLot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Data-quality rule engine (SRS-PRO-13, SRS-DAT-01). The rules live in
 * peraturan_kualiti_data so MAIS can tune them; the same engine checks the
 * lot form, the API and every import row, so the three can never disagree.
 *
 * Severity "ralat" blocks the save; "amaran" is reported (and routed to the
 * steward) but does not block.
 */
class PeraturanKualiti
{
    /** @var array<string, list<PeraturanKualitiData>> */
    private array $rules = [];

    public function __construct(private RujukanInduk $ref) {}

    /**
     * Fill derived values and canonical forms before validation: canonical
     * lot number, daerah/negeri from the mukim, default unit/status/classification.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareLot(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_string($v)) {
                $data[$k] = trim($v) === '' ? null : trim($v);
            }
        }

        if (isset($data['no_lot']) && is_string($data['no_lot'])) {
            $data['no_lot'] = NoLot::canonical($data['no_lot']) ?? $data['no_lot'];
        }
        if (! empty($data['kod_mukim']) && empty($data['kod_daerah'])) {
            $data['kod_daerah'] = $this->ref->mukim((string) $data['kod_mukim'])?->daerah_kod;
        }
        if (! empty($data['kod_daerah'])) {
            $negeri = $this->ref->daerah((string) $data['kod_daerah'])?->negeri_kod;
            if ($negeri !== null) {
                $data['kod_negeri'] = $negeri;
            }
        }
        if (isset($data['kategori_guna_tanah']) && is_string($data['kategori_guna_tanah'])) {
            $data['kategori_guna_tanah'] = strtoupper($data['kategori_guna_tanah']);
        }
        foreach (['bahagian_pemilik', 'klasifikasi', 'status_lot', 'unit_keluasan'] as $lower) {
            if (isset($data[$lower]) && is_string($data[$lower])) {
                $data[$lower] = strtolower($data[$lower]);
            }
        }
        if (($data['unit_keluasan'] ?? null) === 'meter_persegi') {
            $data['unit_keluasan'] = 'm2';
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data  full record after the change (prepared)
     * @return list<array{medan: string, label: string, kod: string, mesej: string, tahap: string, nilai: mixed}>
     */
    public function check(string $entity, array $data, ?string $ignoreId = null): array
    {
        $issues = [];

        foreach ($this->rulesFor($entity) as $rule) {
            $field = $rule->medan;
            $value = $data[$field] ?? null;
            $empty = $value === null || $value === '' || $value === [];
            $expr = (array) $rule->ungkapan_peraturan;

            // digabung / arkib are set by the system (merge, archive), never typed in.
            if ($field === 'status_lot' && in_array($value, ['digabung', 'arkib'], true)) {
                continue;
            }

            $failed = match ($rule->jenis_peraturan) {
                'wajib', 'lengkap' => $empty,
                'format' => ! $empty && is_scalar($value) && ! preg_match('/'.str_replace('/', '\/', (string) ($expr['regex'] ?? '.*')).'/u', (string) $value),
                'julat' => ! $empty && $this->outOfRange($value, $expr),
                'senarai' => ! $empty && ! in_array((string) $value, array_map('strval', (array) ($expr['nilai'] ?? [])), true),
                'panjang' => ! $empty && is_scalar($value) && mb_strlen((string) $value) > (int) ($expr['max'] ?? PHP_INT_MAX),
                'rujukan' => ! $empty && ! $this->referenceOk($field, (string) $value, (string) ($expr['senarai'] ?? ''), $data),
                'unik' => ! $empty && $entity === 'lot_tanah' && $this->lotTaken((string) $value, $data, $ignoreId),
                default => false,
            };

            // Type checks are implicit: a non-numeric area is always an error.
            if (! $failed && $rule->jenis_peraturan === 'julat' && ! $empty && ! is_numeric($value)) {
                $failed = true;
            }

            if ($failed) {
                $issues[] = [
                    'medan' => $field,
                    'label' => $this->label($field),
                    'kod' => $rule->jenis_peraturan,
                    'mesej' => $rule->jenis_peraturan === 'julat' && ! is_numeric($value)
                        ? __('pro::pro.kualiti.bukan_nombor', ['medan' => $this->label($field)])
                        : $rule->mesej_ralat,
                    'tahap' => $rule->tahap_keterukan,
                    'nilai' => is_scalar($value) ? $value : null,
                ];
            }
        }

        return $issues;
    }

    /**
     * @param  list<array{medan: string, label: string, kod: string, mesej: string, tahap: string, nilai: mixed}>  $issues
     * @return list<array{medan: string, label: string, kod: string, mesej: string, tahap: string, nilai: mixed}>
     */
    public static function errors(array $issues): array
    {
        return array_values(array_filter($issues, fn ($i) => $i['tahap'] === 'ralat'));
    }

    /**
     * @param  list<array{medan: string, label: string, kod: string, mesej: string, tahap: string, nilai: mixed}>  $issues
     */
    public static function throwIfErrors(array $issues): void
    {
        $errors = [];
        foreach (self::errors($issues) as $issue) {
            $errors[$issue['medan']][] = $issue['label'].': '.$issue['mesej'];
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function label(string $field): string
    {
        $key = 'pro::pro.medan.'.$field;
        $label = __($key);

        return $label === $key ? $field : (string) $label;
    }

    /** @return list<PeraturanKualitiData> */
    public function rulesFor(string $entity): array
    {
        return $this->rules[$entity] ??= PeraturanKualitiData::query()
            ->where('entiti', $entity)->where('status_aktif', true)->orderBy('id')->get()->all();
    }

    public function flush(): void
    {
        $this->rules = [];
    }

    /** @param array<string, mixed> $expr */
    private function outOfRange(mixed $value, array $expr): bool
    {
        if (! is_numeric($value)) {
            return true;
        }
        $n = (float) $value;

        return (isset($expr['min']) && $n < (float) $expr['min']) || (isset($expr['max']) && $n > (float) $expr['max']);
    }

    /** @param array<string, mixed> $data */
    private function referenceOk(string $field, string $value, string $list, array $data): bool
    {
        if (! $this->ref->exists($list, $value)) {
            return false;
        }
        // The daerah must be the mukim's daerah.
        if ($field === 'kod_daerah' && ! empty($data['kod_mukim'])) {
            $mukim = $this->ref->mukim((string) $data['kod_mukim']);

            return $mukim === null || $mukim->daerah_kod === $value;
        }

        return true;
    }

    /** @param array<string, mixed> $data */
    private function lotTaken(string $noLot, array $data, ?string $ignoreId): bool
    {
        if (empty($data['kod_mukim'])) {
            return false;
        }

        return DB::table('lot_tanah')->whereNull('deleted_at')
            ->where('kod_mukim', (string) $data['kod_mukim'])
            ->where(fn ($q) => $q->where('no_lot', $noLot)->orWhere('no_lot_normal', NoLot::key($noLot)))
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    /**
     * Completeness / warning issues for an existing lot (used by the routing
     * to stewards and the data-quality report).
     *
     * @return list<array{medan: string, label: string, kod: string, mesej: string, tahap: string, nilai: mixed}>
     */
    public function checkLot(LotTanah $lot): array
    {
        return $this->check('lot_tanah', $lot->only(LotTanah::EDITABLE), $lot->id);
    }
}
