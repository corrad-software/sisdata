<?php

namespace App\Modules\Tsu\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Integrations;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Tsu\Contracts\PejabatTanahGateway;
use App\Modules\Tsu\Contracts\PejabatTanahTidakTersedia;
use App\Modules\Tsu\Models\KemaskiniStatusPtd;
use App\Modules\Tsu\Models\UrusanTanah;
use App\Modules\Tsu\Support\Lalai;
use App\Modules\Tsu\Support\Makluman;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * SRS-TSU-22 / SRS-INT-10: Land Office status through the central API for
 * covered districts (scheduled sync), structured manual updates with an
 * automatic follow-up date elsewhere or while the provider is down. Every
 * exchange is logged in MOD-ADM's integration log (SRS-INT-06).
 */
class PtdService
{
    public const INTEGRASI = 'tsu_pejabat_tanah';

    public function __construct(private PejabatTanahGateway $gateway, private Integrations $integrations, private Parameters $parameters, private Makluman $makluman, private UrusanService $urusan) {}

    public function liputanApi(UrusanTanah $u): bool
    {
        return $this->gateway->covers($u->kod_daerah);
    }

    /** Submit to the Land Office through the provider; returns the PTD reference. */
    public function submit(UrusanTanah $u, User $user): string
    {
        $this->urusan->assertOpen($u);
        if ($u->no_rujukan_ptd) {
            throw ValidationException::withMessages(['no_rujukan_ptd' => [__('tsu::tsu.ptd.sudah_dihantar')]]);
        }
        $start = microtime(true);
        try {
            $ref = $this->gateway->submit([
                'no_rujukan_mais' => $u->no_rujukan_mais, 'jenis_urusan' => $u->jenis_urusan, 'kod_daerah' => $u->kod_daerah, 'kod_mukim' => $u->kod_mukim,
                'no_lot' => DB::table('urusan_lot')->join('lot_tanah', 'lot_tanah.id', '=', 'urusan_lot.lot_id')->where('urusan_id', $u->id)->pluck('lot_tanah.no_lot')->all(),
            ]);
            $this->integrations->log(self::INTEGRASI, true, 200, (int) ((microtime(true) - $start) * 1000), null, 'hantar', 1, ['urusan' => $u->no_rujukan_mais]);
        } catch (PejabatTanahTidakTersedia $e) {
            $this->integrations->log(self::INTEGRASI, false, 503, (int) ((microtime(true) - $start) * 1000), $e->getMessage(), 'hantar');
            throw ValidationException::withMessages(['no_rujukan_ptd' => [__('tsu::tsu.ptd.guna_manual')]]);
        }
        $this->record($u, 'api', 'diterima', today()->toDateString(), 'Permohonan dihantar melalui penyedia integrasi berpusat.', $ref, null, $user);

        return $ref;
    }

    /** Pull the current status for one urusan (covered districts). Returns true when it changed. */
    public function sync(UrusanTanah $u): bool
    {
        if (! $u->no_rujukan_ptd || ! $u->terbuka() || ! $this->gateway->covers($u->kod_daerah)) {
            return false;
        }
        $start = microtime(true);
        try {
            $s = $this->gateway->status($u->no_rujukan_ptd, $u->kod_daerah);
            $this->integrations->log(self::INTEGRASI, true, 200, (int) ((microtime(true) - $start) * 1000), null, 'semak_status', 1);
        } catch (PejabatTanahTidakTersedia $e) {
            $this->integrations->log(self::INTEGRASI, false, 503, (int) ((microtime(true) - $start) * 1000), $e->getMessage(), 'semak_status');

            return false;
        }
        if ($s === null || $s['status'] === $u->status_ptd) {
            return false;
        }
        $this->record($u, 'api', $s['status'], $s['tarikh'], $s['catatan'] ?? null, null, null, null);

        return true;
    }

    /**
     * Structured manual update (outside API coverage or while it is down).
     *
     * @param  array{status_ptd: string, tarikh_status: string, catatan?: string|null, rujukan_ptd?: string|null, tarikh_susulan?: string|null}  $data
     */
    public function manual(UrusanTanah $u, array $data, User $user): KemaskiniStatusPtd
    {
        $this->urusan->assertOpen($u);
        if ($this->gateway->covers($u->kod_daerah) && $u->no_rujukan_ptd) {
            try {
                $this->gateway->status($u->no_rujukan_ptd, $u->kod_daerah);
                throw ValidationException::withMessages(['status_ptd' => [__('tsu::tsu.ptd.dalam_liputan')]]);
            } catch (PejabatTanahTidakTersedia) {
                // provider down → manual update allowed (SRS-INT-10)
            }
        }
        $susulan = $data['tarikh_susulan'] ?? today()->addDays((int) $this->parameters->get('tsu.hari_susulan_manual', 14))->toDateString();

        return $this->record($u, 'manual', $data['status_ptd'], $data['tarikh_status'], $data['catatan'] ?? null, $data['rujukan_ptd'] ?? null, $susulan, $user);
    }

    private function record(UrusanTanah $u, string $sumber, string $status, string $tarikh, ?string $catatan, ?string $rujukan, ?string $susulan, ?User $user): KemaskiniStatusPtd
    {
        $old = $u->status_ptd;
        $row = DB::transaction(function () use ($u, $sumber, $status, $tarikh, $catatan, $rujukan, $susulan, $user) {
            $row = KemaskiniStatusPtd::query()->create([
                'urusan_id' => $u->id, 'sumber' => $sumber, 'status_ptd' => $status, 'tarikh_status' => $tarikh, 'catatan' => $catatan,
                'rujukan_ptd' => $rujukan ?? $u->no_rujukan_ptd, 'tarikh_susulan' => $susulan, 'id_pengguna' => $user?->id,
            ]);
            $u->forceFill([
                'status_ptd' => $status, 'sumber_status_ptd' => $sumber, 'tarikh_status_ptd' => Carbon::parse($tarikh),
                'tarikh_susulan_ptd' => $susulan, 'no_rujukan_ptd' => $rujukan ?? $u->no_rujukan_ptd,
            ])->save();

            return $row;
        });
        if ($old !== $status) {
            try {
                $this->makluman->hantar('tsu.status_ptd_berubah', $u->pegawai, ['no_rujukan' => $u->no_rujukan_mais, 'status' => Lalai::STATUS_PTD[$status] ?? $status, 'sumber' => $sumber === 'api' ? 'API berpusat' : 'kemas kini manual'], '/admin/tsu/urusan/'.$u->id);
            } catch (Throwable) {
            }
        }

        return $row;
    }

    /** Scheduled sync of every open urusan in covered districts. */
    public function syncAll(): int
    {
        $n = 0;
        UrusanTanah::query()->withoutGlobalScope('access')->whereIn('status', Lalai::URUSAN_TERBUKA)->whereNotNull('no_rujukan_ptd')->orderBy('id')
            ->each(function (UrusanTanah $u) use (&$n) {
                $n += $this->sync($u) ? 1 : 0;
            });

        return $n;
    }
}
