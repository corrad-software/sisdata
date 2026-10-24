<?php

namespace App\Modules\Hkm\Services;

use App\Models\User;
use App\Modules\Hkm\Contracts\TitleRegistry;
use App\Modules\Hkm\Events\TitleRegistered;
use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Support\Akses;
use App\Modules\Pro\Contracts\LotRegistry;
use Illuminate\Support\Facades\DB;

/**
 * TitleRegistry for MOD-PER (SRS-PER-21/22): an acquisition that completes
 * creates the title (or updates the same no_hak_milik in the same mukim) and
 * optionally deposits the original document in the vault register.
 */
class HkmTitleRegistry implements TitleRegistry
{
    public function __construct(private TitleService $titles, private VaultService $vault, private History $history, private LotRegistry $lots, private Akses $akses) {}

    public function registerFromAcquisition(string $lotId, array $data, User $actor, ?array $vault = null, ?string $sourceRef = null): string
    {
        $lot = $this->lots->find($lotId);
        $existing = $lot === null ? null : HakMilik::query()->withoutGlobalScope('access')
            ->where('kod_mukim', $lot['kod_mukim'])
            ->whereRaw('upper(no_hak_milik) = ?', [mb_strtoupper(trim((string) $data['no_hak_milik']))])
            ->first();

        if ($existing === null) {
            $valid = $this->titles->validate(['lot_id' => $lotId] + $data);

            return $this->titles->register($lotId, $valid, $actor, $vault, [
                'sebab' => __('hkm::hkm.sejarah.pemerolehan', ['rujukan' => $sourceRef ?? '-']),
                'sumber' => 'pemerolehan',
                'id_pemerolehan' => $sourceRef,
            ])->id;
        }

        // Acquisition of an already-registered title: the completed case is the
        // approval (MOD-PER runs its own WFE flow), so record the owner change
        // and version directly, with the case as the reference.
        DB::transaction(function () use ($existing, $data, $actor, $vault, $sourceRef) {
            $before = $existing->snapshot();
            $owner = trim((string) ($data['pemilik_berdaftar'] ?? $existing->pemilik_berdaftar));
            if ($owner !== $existing->pemilik_berdaftar) {
                $this->history->owner($existing, $existing->pemilik_berdaftar, $owner, [
                    'tarikh' => $data['tarikh_pendaftaran'] ?? today()->toDateString(),
                    'sebab' => __('hkm::hkm.sejarah.pemerolehan', ['rujukan' => $sourceRef ?? '-']),
                    'sumber' => 'pemerolehan',
                    'id_pemerolehan' => $sourceRef,
                    'id_pegawai' => $actor->id,
                ]);
            }
            $existing->forceFill([
                'pemilik_berdaftar' => $owner,
                'jenis_hak_milik' => $data['jenis_hak_milik'],
                'tarikh_tamat_tempoh' => array_key_exists('tarikh_tamat_tempoh', $data) ? $data['tarikh_tamat_tempoh'] : $existing->tarikh_tamat_tempoh,
                'pejabat_pendaftaran' => $data['pejabat_pendaftaran'] ?? $existing->pejabat_pendaftaran,
                'versi_rekod' => $existing->versi_rekod + 1,
            ])->save();
            $this->history->version($existing, 'pemerolehan', $before, ['justifikasi' => $sourceRef, 'id_pengguna' => $actor->id]);

            if ($vault !== null && ! $existing->daftarPetiBesi()->exists()) {
                $this->vault->deposit($existing, $vault, $actor);
            }
        });

        TitleRegistered::dispatch($existing->id, $existing->lot_id, $sourceRef);

        return $existing->id;
    }

    public function forLot(string $lotId, User $user): array
    {
        return $this->akses->titles($user)
            ->where('lot_id', $lotId)
            ->orderByDesc('tarikh_pendaftaran')
            ->get(['id', 'no_hak_milik', 'jenis_hak_milik', 'status_semasa', 'tarikh_tamat_tempoh'])
            ->map(fn (HakMilik $h) => [
                'id' => $h->id,
                'no_hak_milik' => $h->no_hak_milik,
                'jenis_hak_milik' => $h->jenis_hak_milik,
                'status_semasa' => $h->status_semasa,
                'tarikh_tamat_tempoh' => $h->tarikh_tamat_tempoh?->format('Y-m-d'),
            ])->values()->all();
    }
}
