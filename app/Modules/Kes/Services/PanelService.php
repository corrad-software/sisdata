<?php

namespace App\Modules\Kes\Services;

use App\Models\User;
use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Kes\Models\PanelPeguam;
use App\Modules\Kes\Models\Peguam;
use App\Modules\Kes\Support\Akses;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Panel law firms representing MAIS (SRS-KES-04; category "panel peguam luar
 * MAIS — capaian terhad kepada kes yang ditugaskan, tertakluk kelulusan
 * MAIS"): an appointment starts `kes.lantikan_panel` in MOD-WFE and becomes
 * active (and visible to the firm's user) only when approved.
 */
class PanelService
{
    public const FLOW = 'kes.lantikan_panel';

    public function __construct(private WorkflowEngine $workflow, private Akses $akses) {}

    /** @param array<string, mixed> $d validated */
    public function lantik(KesMahkamah $kes, PanelPeguam $panel, array $d, User $by, bool $sintetik = false): Peguam
    {
        if ($panel->status !== 'aktif') {
            throw ValidationException::withMessages(['kes_panel_peguam_id' => __('kes::kes.ralat.panel_tidak_aktif')]);
        }
        if (Peguam::query()->where('kes_mahkamah_id', $kes->id)->where('kes_panel_peguam_id', $panel->id)->whereIn('status_lantikan', ['aktif', 'menunggu_kelulusan'])->exists()) {
            throw ValidationException::withMessages(['kes_panel_peguam_id' => __('kes::kes.ralat.panel_sudah')]);
        }
        $p = new Peguam;
        $p->forceFill([
            'kes_mahkamah_id' => $kes->id, 'kes_pihak_id' => $d['kes_pihak_id'] ?? null, 'kes_panel_peguam_id' => $panel->id,
            'nama_peguam' => (string) ($d['nama_peguam'] ?? $panel->pegawai_penghubung ?? $panel->nama_firma), 'nama_firma' => $panel->nama_firma,
            'pegawai_penghubung' => $panel->pegawai_penghubung, 'no_telefon' => $panel->no_telefon, 'emel' => $panel->emel,
            'indikator_panel_mais' => true, 'status_lantikan' => $sintetik ? 'aktif' : 'menunggu_kelulusan', 'sintetik' => $sintetik,
        ])->save();
        if ($sintetik) {
            return $p;
        }
        try {
            $id = $this->workflow->start(self::FLOW, 'kes_peguam', $p->id, $by, [
                'kod_daerah' => $kes->kod_daerah, 'jenis' => $kes->jenis, 'peranan_mais' => $kes->peranan_mais, 'pautan' => '/admin/kes/kes/'.$kes->id.'?tab=pihak',
            ], 'Lantikan panel '.$panel->nama_firma.' — '.$kes->no_rujukan);
        } catch (InvalidArgumentException) {
            $p->forceFill(['status_lantikan' => 'ditolak'])->save();
            throw ValidationException::withMessages(['aliran' => __('kes::kes.ralat.tiada_aliran')]);
        }
        $p->forceFill(['id_instans_aliran' => $id])->save();

        return $p;
    }

    public function selesai(Peguam $p, string $outcome): void
    {
        $p->forceFill(['status_lantikan' => $outcome === 'lulus' ? 'aktif' : 'ditolak'])->save();
        $this->akses->lupa();
    }

    public function tamat(Peguam $p): void
    {
        $p->forceFill(['status_lantikan' => 'tamat'])->save();
        $this->akses->lupa();
    }
}
