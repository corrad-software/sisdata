<?php

namespace App\Modules\Kes\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Cki\Contracts\GrpPembayaran;
use App\Modules\Cki\Contracts\PenyediaTidakTersedia;
use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Kes\Models\KosGuaman;
use App\Modules\Kes\Support\Kod;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * SRS-KES-15 + integration "GRP MAIS bagi pengesahan bayaran fi peguam dan
 * kos mahkamah": cost items by category, cumulative total per case
 * (bcmath), payment approval through MOD-WFE (`kes.bayaran_kos`), then the
 * approved request goes to GRP MAIS (Cki\Contracts\GrpPembayaran, mock) and
 * its payment status is verified from GRP.
 */
class KosService
{
    public const FLOW = 'kes.bayaran_kos';

    public const SUBJECT = 'kos_guaman';

    public function __construct(private KesService $kes, private WorkflowEngine $workflow, private GrpPembayaran $grp, private Parameters $parameters) {}

    /** @param array<string, mixed> $d validated */
    public function tambah(KesMahkamah $kes, array $d, User $by, bool $sintetik = false): KosGuaman
    {
        $k = new KosGuaman;
        $k->forceFill([
            'kes_mahkamah_id' => $kes->id, 'kategori_kos' => $d['kategori_kos'], 'keterangan' => $d['keterangan'] ?? null,
            'amaun' => Wang::normal((string) $d['amaun']), 'tarikh' => $d['tarikh'], 'no_resit' => $d['no_resit'] ?? null,
            'id_dokumen' => $d['id_dokumen'] ?? null, 'kes_panel_peguam_id' => $d['kes_panel_peguam_id'] ?? null,
            'status_bayaran' => $d['status_bayaran'] ?? 'belum_dibayar', 'id_pengguna' => $by->id, 'sintetik' => $sintetik,
        ])->save();
        $this->kes->kiraKos($kes);

        return $k;
    }

    /** @param array<string, mixed> $d validated */
    public function kemasKini(KesMahkamah $kes, KosGuaman $k, array $d): KosGuaman
    {
        if (! in_array($k->status_bayaran, ['belum_dibayar', 'ditolak'], true)) {
            throw ValidationException::withMessages(['status_bayaran' => __('kes::kes.ralat.kos_dikunci')]);
        }
        if (isset($d['amaun'])) {
            $d['amaun'] = Wang::normal((string) $d['amaun']);
        }
        $k->fill(array_intersect_key($d, array_flip(['kategori_kos', 'keterangan', 'amaun', 'tarikh', 'no_resit', 'id_dokumen', 'kes_panel_peguam_id'])))->save();
        $this->kes->kiraKos($kes);

        return $k;
    }

    public function mohonBayaran(KesMahkamah $kes, KosGuaman $k, User $by): string
    {
        if (! in_array($k->status_bayaran, ['belum_dibayar', 'ditolak'], true)) {
            throw ValidationException::withMessages(['status_bayaran' => __('kes::kes.ralat.kos_dikunci')]);
        }
        try {
            $id = $this->workflow->start(self::FLOW, self::SUBJECT, $k->id, $by, [
                'kod_daerah' => $kes->kod_daerah, 'jenis' => $k->kategori_kos, 'nilai' => (float) $k->amaun, 'pautan' => '/admin/kes/kes/'.$kes->id.'?tab=kos',
            ], 'Bayaran '.(Kod::KATEGORI_KOS[$k->kategori_kos] ?? $k->kategori_kos).' — '.$kes->no_rujukan);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['aliran' => __('kes::kes.ralat.tiada_aliran')]);
        }
        $k->forceFill(['status_bayaran' => 'menunggu_kelulusan', 'id_instans_aliran' => $id])->save();
        $this->kes->kiraKos($kes);

        return $id;
    }

    /** WorkflowCompleted for kes.bayaran_kos. */
    public function selesaiKelulusan(KosGuaman $k, string $outcome): void
    {
        if ($outcome !== 'lulus') {
            $k->forceFill(['status_bayaran' => $outcome === 'tolak' ? 'ditolak' : 'belum_dibayar'])->save();
            if ($k->kes !== null) {
                $this->kes->kiraKos($k->kes);
            }

            return;
        }
        $k->forceFill(['status_bayaran' => 'diluluskan'])->save();
        $this->hantarGrp($k);
    }

    /** Send an approved item to GRP MAIS; on GRP outage it stays "diluluskan" to be resent. */
    public function hantarGrp(KosGuaman $k): bool
    {
        if ($k->status_bayaran !== 'diluluskan') {
            return false;
        }
        $kes = $k->kes()->first();
        try {
            $ref = $this->grp->hantarPermohonan([
                'no_permohonan' => 'KOS-'.$k->id, 'no_bil' => (string) ($k->no_resit ?? 'KOS-'.$k->id),
                'penerima' => (string) ($k->panel()->value('nama_firma') ?? 'Penerima kos guaman'), 'amaun' => $k->amaun, 'kaedah' => 'eft',
                'kod_vot' => (string) $this->parameters->get('kes.kod_vot_grp', 'B-PERUNDANGAN'), 'tarikh' => now()->toDateString(),
                'keterangan' => 'Kos guaman '.($kes->no_rujukan ?? ''),
            ]);
        } catch (PenyediaTidakTersedia) {
            return false;
        }
        $k->forceFill(['status_bayaran' => 'dihantar_grp', 'rujukan_grp' => $ref])->save();

        return true;
    }

    /** Verify the payment status from GRP MAIS. */
    public function semakGrp(KosGuaman $k): string
    {
        if ($k->status_bayaran === 'diluluskan') {
            if (! $this->hantarGrp($k)) {
                throw ValidationException::withMessages(['grp' => __('kes::kes.ralat.grp_tiada')]);
            }
        }
        if ($k->status_bayaran !== 'dihantar_grp' || $k->rujukan_grp === null) {
            return $k->status_bayaran;
        }
        try {
            $s = $this->grp->status($k->rujukan_grp);
        } catch (PenyediaTidakTersedia) {
            throw ValidationException::withMessages(['grp' => __('kes::kes.ralat.grp_tiada')]);
        }
        if ($s !== null && $s['status'] === 'dibayar') {
            $k->forceFill(['status_bayaran' => 'dibayar', 'tarikh_bayar' => $s['tarikh_bayar'] ?? now()->toDateString()])->save();
        } elseif ($s !== null && $s['status'] === 'ditolak') {
            $k->forceFill(['status_bayaran' => 'ditolak'])->save();
            if ($k->kes !== null) {
                $this->kes->kiraKos($k->kes);
            }
        }

        return $k->status_bayaran;
    }
}
