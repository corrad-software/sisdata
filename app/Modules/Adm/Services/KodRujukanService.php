<?php

namespace App\Modules\Adm\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\ReferenceCodes;
use App\Modules\Adm\Models\KodRujukan;
use App\Modules\Adm\Models\KodRujukanSejarah;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * SRS-ADM-02 / SRS-DAT-05: generic, versioned reference codes. Every change
 * bumps `versi` and snapshots the previous state into adm_kod_rujukan_sejarah;
 * deactivation sets status + tarikh_tamat but never deletes, so records that
 * used the code keep a valid, labelled value.
 */
class KodRujukanService implements ReferenceCodes
{
    /** Types the SRS names; admins may add others (snake_case). */
    public const JENIS_LAZIM = ['daerah', 'mukim', 'pbt', 'kategori_guna_tanah', 'jenis_hak_milik', 'jenis_dokumen', 'kategori_aduan', 'jenis_urusan', 'status_transaksi', 'klasifikasi_data'];

    /** Hierarchy: kod_induk of a type must be an existing code of its parent type. */
    public const INDUK = ['mukim' => 'daerah', 'pbt' => 'daerah'];

    public function valid(string $type, string $code, ?DateTimeInterface $at = null): bool
    {
        $date = Carbon::instance($at ?? now())->toDateString();

        return KodRujukan::query()
            ->where('jenis_kod', $type)->where('kod', $code)
            ->whereDate('tarikh_mula', '<=', $date)
            ->where(fn ($q) => $q->whereNull('tarikh_tamat')->orWhereDate('tarikh_tamat', '>=', $date))
            ->exists();
    }

    public function label(string $type, string $code): ?string
    {
        return KodRujukan::query()->where('jenis_kod', $type)->where('kod', $code)->value('keterangan');
    }

    public function options(string $type, ?string $parent = null, bool $activeOnly = true): array
    {
        return KodRujukan::query()
            ->where('jenis_kod', $type)
            ->when($parent !== null, fn ($q) => $q->where('kod_induk', $parent))
            ->when($activeOnly, fn ($q) => $q->where('status', KodRujukan::AKTIF))
            ->orderBy('susunan')->orderBy('kod')
            ->get(['kod', 'keterangan', 'kod_induk'])
            ->map(fn (KodRujukan $k) => ['kod' => $k->kod, 'keterangan' => $k->keterangan, 'kod_induk' => $k->kod_induk])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $input, ?KodRujukan $existing = null): array
    {
        $jenis = $existing->jenis_kod ?? ($input['jenis_kod'] ?? null);

        $data = Validator::make($input, [
            'jenis_kod' => $existing ? ['prohibited'] : ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/'],
            'kod' => [
                $existing ? 'sometimes' : 'required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._\-\/]+$/',
                Rule::unique('adm_kod_rujukan', 'kod')->where('jenis_kod', $jenis)->ignore($existing?->id),
            ],
            'keterangan' => [$existing ? 'sometimes' : 'required', 'string', 'max:255'],
            'kod_induk' => ['nullable', 'string', 'max:60'],
            'susunan' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'tarikh_mula' => ['nullable', 'date'],
            'tarikh_tamat' => ['nullable', 'date', 'after_or_equal:tarikh_mula'],
            'justifikasi' => ['nullable', 'string', 'max:1000'],
        ], [], __('adm::adm.kod.attributes'))->validate();

        $parentType = self::INDUK[$jenis] ?? null;
        if (! empty($data['kod_induk']) && $parentType !== null
            && ! KodRujukan::query()->where('jenis_kod', $parentType)->where('kod', $data['kod_induk'])->exists()) {
            throw ValidationException::withMessages(['kod_induk' => __('adm::adm.kod.parent_missing', ['jenis' => $parentType])]);
        }

        return $data;
    }

    /** @param array<string, mixed> $data validated */
    public function create(array $data, ?User $by): KodRujukan
    {
        return KodRujukan::query()->create([
            'jenis_kod' => $data['jenis_kod'],
            'kod' => $data['kod'],
            'keterangan' => $data['keterangan'],
            'kod_induk' => $data['kod_induk'] ?? null,
            'susunan' => $data['susunan'] ?? 0,
            'status' => KodRujukan::AKTIF,
            'tarikh_mula' => $data['tarikh_mula'] ?? now()->toDateString(),
            'tarikh_tamat' => $data['tarikh_tamat'] ?? null,
            'versi' => 1,
            'justifikasi' => $data['justifikasi'] ?? null,
            'dikemas_kini_oleh' => $by?->id,
        ]);
    }

    /** @param array<string, mixed> $changes validated */
    public function update(KodRujukan $kod, array $changes, ?User $by): KodRujukan
    {
        return DB::transaction(function () use ($kod, $changes, $by) {
            $kod->fill(array_intersect_key($changes, array_flip([...KodRujukan::VERSIONED, 'justifikasi'])));
            if (! $kod->isDirty(KodRujukan::VERSIONED)) {
                return $kod;
            }

            $this->snapshot($kod, $by);
            $kod->versi = $kod->versi + 1;
            $kod->dikemas_kini_oleh = $by?->id;
            $kod->save();

            return $kod;
        });
    }

    public function deactivate(KodRujukan $kod, ?string $until, ?string $justification, ?User $by): KodRujukan
    {
        return $this->update($kod, [
            'status' => KodRujukan::TIDAK_AKTIF,
            'tarikh_tamat' => $until ?? now()->toDateString(),
            'justifikasi' => $justification,
        ], $by);
    }

    public function activate(KodRujukan $kod, ?string $justification, ?User $by): KodRujukan
    {
        return $this->update($kod, ['status' => KodRujukan::AKTIF, 'tarikh_tamat' => null, 'justifikasi' => $justification], $by);
    }

    /**
     * Bulk import (SRS-ADM-05, SRS-DAT-03 style): every row validated on its
     * own; the report lists accepted and rejected rows with reasons.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{diterima: int, dikemas_kini: int, ditolak: int, ralat: list<array{baris: int, kod: ?string, mesej: list<string>}>}
     */
    public function import(string $type, array $rows, bool $upsert, ?User $by): array
    {
        $report = ['diterima' => 0, 'dikemas_kini' => 0, 'ditolak' => 0, 'ralat' => []];

        foreach ($rows as $i => $row) {
            $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row);
            $row = array_filter($row, fn ($v) => $v !== '' && $v !== null);
            $existing = $upsert && isset($row['kod'])
                ? KodRujukan::query()->where('jenis_kod', $type)->where('kod', $row['kod'])->first()
                : null;

            try {
                if ($existing) {
                    $data = $this->validate(array_diff_key($row, ['jenis_kod' => true]), $existing);
                    $this->update($existing, $data, $by);
                    $report['dikemas_kini']++;
                } else {
                    $data = $this->validate(['jenis_kod' => $type] + $row);
                    $this->create($data, $by);
                    $report['diterima']++;
                }
            } catch (ValidationException $e) {
                $report['ditolak']++;
                $report['ralat'][] = [
                    'baris' => $i + 1,
                    'kod' => isset($row['kod']) ? (string) $row['kod'] : null,
                    'mesej' => array_values(array_merge(...array_values($e->errors()))),
                ];
            }
        }

        return $report;
    }

    private function snapshot(KodRujukan $kod, ?User $by): void
    {
        $original = [];
        foreach (KodRujukan::VERSIONED as $field) {
            $value = $kod->getOriginal($field);
            $original[$field] = $value instanceof DateTimeInterface ? Carbon::instance($value)->toDateString() : $value;
        }

        KodRujukanSejarah::query()->create([
            'id_kod_rujukan' => $kod->id,
            'versi' => $kod->getOriginal('versi'),
            'data' => $original + ['justifikasi' => $kod->getOriginal('justifikasi')],
            'diubah_oleh' => $by?->id,
        ]);
    }
}
