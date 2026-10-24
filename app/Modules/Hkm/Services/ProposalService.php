<?php

namespace App\Modules\Hkm\Services;

use App\Models\User;
use App\Modules\Hkm\Contracts\TitleChangeProposals;
use App\Modules\Hkm\Models\CadanganPerubahan;
use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Models\PerubahanHakMilik;
use App\Modules\Hkm\Support\Kod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * SRS-HKM-18 (process step 7): external proposals land as drafts. Confirming
 * one creates a change request (ChangeService) — the master is only written
 * after two-stage approval. Rejecting keeps the draft for the record.
 */
class ProposalService implements TitleChangeProposals
{
    public function __construct(private ChangeService $changes) {}

    public function propose(array $proposal, ?User $sender = null): string
    {
        return $this->receive($proposal, $sender)->id;
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function receive(array $input, ?User $sender): CadanganPerubahan
    {
        $valid = Validator::make($input, [
            'sumber' => ['required', Rule::in(array_keys(Kod::SUMBER_CADANGAN))],
            'rujukan_luaran' => ['required', 'string', 'max:100'],
            'no_hak_milik' => ['required', 'string', 'max:50'],
            'kod_mukim' => ['nullable', 'string', 'size:6'],
            'jenis_perubahan' => ['required', Rule::in(array_keys(Kod::JENIS_PERUBAHAN))],
            'data' => ['required', 'array'],
            'keterangan' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        $existing = CadanganPerubahan::query()->where('sumber', $valid['sumber'])->where('rujukan_luaran', $valid['rujukan_luaran'])->first();
        if ($existing) {
            return $existing;
        }

        $match = HakMilik::query()->withoutGlobalScope('access')
            ->whereRaw('upper(no_hak_milik) = ?', [mb_strtoupper(trim($valid['no_hak_milik']))])
            ->when($valid['kod_mukim'] ?? null, fn ($q, $m) => $q->where('kod_mukim', $m))
            ->limit(2)->pluck('id');

        return CadanganPerubahan::query()->create([
            'hak_milik_id' => $match->count() === 1 ? $match->first() : null,
            'no_hak_milik' => trim($valid['no_hak_milik']),
            'kod_mukim' => $valid['kod_mukim'] ?? null,
            'sumber' => $valid['sumber'],
            'rujukan_luaran' => $valid['rujukan_luaran'],
            'jenis_perubahan' => $valid['jenis_perubahan'],
            'data_cadangan' => $valid['data'],
            'keterangan' => $valid['keterangan'] ?? null,
            'status' => CadanganPerubahan::DRAF,
            'id_penghantar' => $sender?->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $input  justifikasi, hak_milik_id? (when unmatched), data? (officer's corrections)
     *
     * @throws ValidationException
     */
    public function confirm(CadanganPerubahan $proposal, array $input, User $user): PerubahanHakMilik
    {
        $this->assertDraft($proposal);

        $titleId = $proposal->hak_milik_id ?? ($input['hak_milik_id'] ?? null);
        $title = $titleId ? HakMilik::query()->find($titleId) : null; // scoped to the officer
        if (! $title) {
            throw ValidationException::withMessages(['hak_milik_id' => __('hkm::hkm.ralat.cadangan_tiada_hak_milik')]);
        }

        return DB::transaction(function () use ($proposal, $input, $user, $title) {
            $change = $this->changes->request($title, [
                'jenis_perubahan' => $proposal->jenis_perubahan,
                'data' => is_array($input['data'] ?? null) ? $input['data'] : $proposal->data_cadangan,
                'justifikasi' => $input['justifikasi'] ?? null,
                'no_rujukan_dokumen' => $proposal->rujukan_luaran,
            ], $user, $proposal);

            $proposal->forceFill([
                'hak_milik_id' => $title->id,
                'status' => 'disahkan',
                'id_penyemak' => $user->id,
                'tarikh_semakan' => now(),
                'catatan_semakan' => $input['justifikasi'] ?? null,
                'id_perubahan' => $change->id,
            ])->save();

            return $change;
        });
    }

    /** @throws ValidationException */
    public function reject(CadanganPerubahan $proposal, string $note, User $user): CadanganPerubahan
    {
        $this->assertDraft($proposal);
        $proposal->forceFill(['status' => 'ditolak', 'id_penyemak' => $user->id, 'tarikh_semakan' => now(), 'catatan_semakan' => $note])->save();

        return $proposal;
    }

    private function assertDraft(CadanganPerubahan $proposal): void
    {
        if ($proposal->status !== CadanganPerubahan::DRAF) {
            throw ValidationException::withMessages(['status' => __('hkm::hkm.ralat.cadangan_bukan_draf')]);
        }
    }
}
