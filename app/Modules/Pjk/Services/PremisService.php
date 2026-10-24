<?php

namespace App\Modules\Pjk\Services;

use App\Models\User;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Pjk\Models\SejarahStatusPremis;
use App\Modules\Pjk\Support\Kod;
use App\Modules\Pjk\Support\Penomboran;
use App\Modules\Pjk\Support\Wang;
use App\Modules\Pro\Contracts\LotRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * SRS-PJK-03/04: premise register on top of MOD-PRO lots, its status and
 * history. The lot is resolved through LotRegistry (scoped to the user).
 */
class PremisService
{
    public function __construct(private LotRegistry $lots) {}

    /** @param array<string, mixed> $input */
    public function create(array $input, User $user): PremisAset
    {
        $data = $this->validate($input, null);
        $lot = $this->lots->find((string) $data['lot_id'], $user);
        if ($lot === null) {
            throw ValidationException::withMessages(['lot_id' => __('pjk::pjk.ralat.lot_tiada')]);
        }

        return DB::transaction(function () use ($data, $lot, $user) {
            $premis = PremisAset::query()->create([
                ...$data,
                'kod_premis' => $data['kod_premis'] ?? Penomboran::next('premis'),
                'kod_daerah' => $lot['kod_daerah'],
                'kod_mukim' => $lot['kod_mukim'],
                'bahagian' => $lot['bahagian_pemilik'],
                'status_premis' => $data['status_premis'] ?? 'kosong',
                'tarikh_status' => Carbon::today()->format('Y-m-d'),
                'kadar_tawaran' => isset($data['kadar_tawaran']) ? Wang::norm((string) $data['kadar_tawaran']) : null,
            ]);
            $this->history($premis, null, $premis->status_premis, $user->id, __('pjk::pjk.premis.didaftarkan'));

            return $premis;
        });
    }

    /** @param array<string, mixed> $input */
    public function update(PremisAset $premis, array $input, User $user): PremisAset
    {
        $data = $this->validate($input, $premis);
        unset($data['lot_id']);
        $status = $data['status_premis'] ?? null;
        unset($data['status_premis']);
        if (isset($data['kadar_tawaran'])) {
            $data['kadar_tawaran'] = Wang::norm((string) $data['kadar_tawaran']);
        }

        return DB::transaction(function () use ($premis, $data, $status, $user, $input) {
            $premis->fill($data)->save();
            if ($status !== null && $status !== $premis->status_premis) {
                if ($status === 'disewa' || $premis->status_premis === 'disewa') {
                    throw ValidationException::withMessages(['status_premis' => __('pjk::pjk.ralat.status_premis_automatik')]);
                }
                $this->setStatus($premis, $status, $user->id, (string) ($input['sebab'] ?? ''));
            }

            return $premis->refresh();
        });
    }

    /** SRS-PJK-03: change status with history (automatic on activation / termination). */
    public function setStatus(PremisAset $premis, string $status, ?int $userId, ?string $reason = null): void
    {
        if ($premis->status_premis === $status) {
            return;
        }
        $before = $premis->status_premis;
        $premis->forceFill(['status_premis' => $status, 'tarikh_status' => Carbon::today()->format('Y-m-d')])->save();
        $this->history($premis, $before, $status, $userId, $reason);
    }

    private function history(PremisAset $premis, ?string $before, string $after, ?int $userId, ?string $reason): void
    {
        SejarahStatusPremis::query()->create([
            'premis_id' => $premis->id, 'status_sebelum' => $before, 'status_selepas' => $after,
            'tarikh' => Carbon::today()->format('Y-m-d'), 'id_pegawai' => $userId, 'sebab' => $reason !== '' ? $reason : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validate(array $input, ?PremisAset $premis): array
    {
        $req = $premis === null ? 'required' : 'sometimes';

        return Validator::make($input, [
            'lot_id' => [$req, 'uuid'],
            'kod_premis' => ['nullable', 'string', 'max:40', Rule::unique('premis_aset', 'kod_premis')->ignore($premis?->id)],
            'nama_premis' => [$req, 'string', 'max:200'],
            'jenis_aset' => [$req, Rule::in(array_keys(Kod::JENIS_ASET))],
            'kategori_hartanah' => [$req, Rule::in(array_keys(Kod::KATEGORI_HARTANAH))],
            'saluran_pemerolehan' => ['nullable', Rule::in(['wakaf', 'belian'])],
            'keluasan' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'status_premis' => ['sometimes', Rule::in(array_keys(Kod::STATUS_PREMIS))],
            'kadar_tawaran' => ['nullable', 'decimal:0,2', 'min:0', 'max:99999999'],
            'papar_portal' => ['sometimes', 'boolean'],
            'keterangan_awam' => ['nullable', 'string', 'max:2000'],
        ], [], ['nama_premis' => 'nama premis', 'jenis_aset' => 'jenis aset', 'kategori_hartanah' => 'kategori hartanah', 'kadar_tawaran' => 'kadar tawaran'])->validate();
    }
}
