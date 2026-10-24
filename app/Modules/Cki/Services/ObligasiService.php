<?php

namespace App\Modules\Cki\Services;

use App\Models\User;
use App\Modules\Cki\Models\Obligasi;
use App\Modules\Cki\Support\Kod;
use App\Modules\Pro\Contracts\LotRegistry;
use Illuminate\Validation\ValidationException;

/** SRS-CKI-01/06: the tax register — every obligation linked to a lot (and optionally a premise) in the master data. */
class ObligasiService
{
    public function __construct(private LotRegistry $lots, private LiputanService $liputan) {}

    /** @param array<string, mixed> $data */
    public function cipta(array $data, ?User $user, bool $sintetik = false): Obligasi
    {
        $lot = $this->lots->find((string) $data['lot_id'], $user);
        if ($lot === null) {
            throw ValidationException::withMessages(['lot_id' => __('cki::cki.ralat.lot_tiada')]);
        }
        $jenis = (string) $data['jenis_cukai'];
        $kodPbt = $data['kod_pbt'] ?? ($jenis === 'taksiran_pbt' ? $this->liputan->pbtLalai($lot['kod_daerah']) : null);
        $agensi = $this->liputan->agensiUntuk($jenis, $lot['kod_daerah'], $kodPbt);
        $kodAgensi = $agensi->kod_agensi ?? ($jenis === 'taksiran_pbt' ? (string) ($kodPbt ?? 'PBT-TIADA') : 'PTD-'.$lot['kod_daerah']);

        if (Obligasi::query()->withoutGlobalScope('access')->where('jenis_cukai', $jenis)->where('kod_agensi', $kodAgensi)->where('no_akaun_cukai', $data['no_akaun_cukai'])->exists()) {
            throw ValidationException::withMessages(['no_akaun_cukai' => __('cki::cki.ralat.akaun_wujud')]);
        }

        return Obligasi::query()->create([
            'lot_id' => $lot['id'],
            'id_premis' => $data['id_premis'] ?? null,
            'jenis_cukai' => $jenis,
            'pihak_pengeluar' => $data['pihak_pengeluar'] ?? Kod::PENGELUAR_LALAI[$jenis],
            'kod_pbt' => $kodPbt,
            'kod_ptd' => $jenis === 'taksiran_pbt' ? null : $lot['kod_daerah'],
            'kod_agensi' => $kodAgensi,
            'no_akaun_cukai' => (string) $data['no_akaun_cukai'],
            'status_liputan_api' => $agensi?->perkhidmatan_semakan ? 'dalam' : 'luar',
            'kod_daerah' => $lot['kod_daerah'],
            'kod_mukim' => $lot['kod_mukim'],
            'klasifikasi' => 'dalaman',
            'sintetik' => $sintetik,
            'catatan' => $data['catatan'] ?? null,
            'id_pencipta' => $user?->id,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function kemaskini(Obligasi $o, array $data): Obligasi
    {
        $o->fill(array_intersect_key($data, array_flip(['no_akaun_cukai', 'id_premis', 'catatan', 'aktif', 'pihak_pengeluar'])));
        $o->save();

        return $o;
    }
}
