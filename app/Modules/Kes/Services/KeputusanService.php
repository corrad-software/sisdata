<?php

namespace App\Modules\Kes\Services;

use App\Models\User;
use App\Modules\Hkm\Contracts\TitleChangeProposals;
use App\Modules\Kes\Models\Keputusan;
use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Kes\Models\Kronologi;
use App\Modules\Kes\Models\PengemaskinianRekod;
use App\Modules\Kes\Models\Penyelesaian;
use App\Modules\Kes\Models\Rayuan;
use App\Modules\Kes\Models\TindakanSusulan;
use App\Modules\Kes\Support\Kod;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Aliran proses 9–10: decisions (SRS-KES-16), follow-up actions as MOD-WFE
 * tasks (SRS-KES-17), title / land-status updates proposed to MOD-HKM with
 * a cross-reference to the case number and the order document
 * (SRS-KES-18), appeals and out-of-court settlements (each approved in WFE).
 */
class KeputusanService
{
    public const FLOW_TINDAKAN = 'kes.tindakan_susulan';

    public const FLOW_RAYUAN = 'kes.rayuan';

    public const FLOW_PENYELESAIAN = 'kes.penyelesaian';

    public function __construct(
        private KesService $kes,
        private Rujukan $ref,
        private WorkflowEngine $workflow,
        private TitleChangeProposals $titles,
        private Notifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $d  validated; `tindakan` (list of jenis) overrides the defaults per decision type
     */
    public function rekod(KesMahkamah $kes, array $d, User $by, bool $sintetik = false): Keputusan
    {
        $k = DB::transaction(function () use ($kes, $d, $by, $sintetik) {
            $k = new Keputusan;
            $k->forceFill([
                'kes_mahkamah_id' => $kes->id, 'jenis_keputusan' => $d['jenis_keputusan'], 'tarikh_keputusan' => $d['tarikh_keputusan'],
                'peringkat_mahkamah' => $d['peringkat_mahkamah'] ?? $kes->peringkat_mahkamah, 'ringkasan_perintah' => $d['ringkasan_perintah'] ?? null,
                'implikasi' => $d['implikasi'] ?? null, 'id_dokumen_penghakiman' => $d['id_dokumen_penghakiman'] ?? null, 'id_pengguna' => $by->id, 'sintetik' => $sintetik,
            ])->save();
            $kes->forceFill(['tarikh_keputusan' => $d['tarikh_keputusan']])->save();

            return $k;
        });
        if (isset($this->ref->status()['keputusan'])) {
            $this->kes->tukarStatus($kes, 'keputusan', $by, 'Keputusan: '.Kod::JENIS_KEPUTUSAN[$k->jenis_keputusan]);
        }
        $jenis = array_key_exists('tindakan', $d) ? (array) $d['tindakan'] : (Kod::SUSULAN_LALAI[$k->jenis_keputusan] ?? []);
        $lots = $this->kes->lotIds($kes);
        foreach (array_unique($jenis) as $j) {
            $this->tindakan($kes, [
                'jenis_tindakan' => $j, 'kes_keputusan_id' => $k->id,
                'keterangan' => Kod::JENIS_TINDAKAN[$j].' — keputusan '.mb_strtolower(Kod::JENIS_KEPUTUSAN[$k->jenis_keputusan]).' bertarikh '.$k->tarikh_keputusan->format('d/m/Y'),
                'tarikh_sasaran' => $k->tarikh_keputusan->copy()->addDays($j === 'pemfailan_rayuan' ? 30 : 60)->toDateString(),
                'id_lot_terjejas' => $j === 'pengemaskinian_hak_milik' && count($lots) === 1 ? $lots[0] : null,
            ], $by, $sintetik);
        }

        return $k;
    }

    /**
     * SRS-KES-17: one follow-up action = one MOD-WFE task.
     *
     * @param  array<string, mixed>  $d
     */
    public function tindakan(KesMahkamah $kes, array $d, User $by, bool $sintetik = false): TindakanSusulan
    {
        $t = new TindakanSusulan;
        $t->forceFill([
            'kes_mahkamah_id' => $kes->id, 'kes_keputusan_id' => $d['kes_keputusan_id'] ?? null, 'jenis_tindakan' => $d['jenis_tindakan'],
            'keterangan' => $d['keterangan'] ?? null, 'tarikh_sasaran' => $d['tarikh_sasaran'] ?? null, 'status' => 'dibuka',
            'id_lot_terjejas' => $d['id_lot_terjejas'] ?? null, 'id_pegawai' => $d['id_pegawai'] ?? $kes->id_pegawai_bertanggungjawab, 'sintetik' => $sintetik,
        ])->save();
        if ($sintetik) {
            return $t;
        }
        try {
            $id = $this->workflow->start(self::FLOW_TINDAKAN, 'kes_tindakan_susulan', $t->id, $by, [
                'kod_daerah' => $kes->kod_daerah, 'jenis' => $t->jenis_tindakan, 'pautan' => '/admin/kes/kes/'.$kes->id.'?tab=keputusan',
            ], Kod::JENIS_TINDAKAN[$t->jenis_tindakan].' — '.$kes->no_rujukan);
            $t->forceFill(['id_tugasan_workflow' => $id, 'status' => 'dalam_tindakan'])->save();
        } catch (InvalidArgumentException) {
            // No active flow configured: the action stays "dibuka" and is shown for manual follow-up.
        }
        if ($t->pegawai !== null) {
            $this->notifier->send('kes.tindakan_susulan', $t->pegawai, [
                'no_rujukan' => $kes->no_rujukan, 'tindakan' => Kod::JENIS_TINDAKAN[$t->jenis_tindakan], 'tarikh_sasaran' => $t->tarikh_sasaran?->format('d/m/Y'),
            ], '/admin/kes/kes/'.$kes->id.'?tab=keputusan', 'kes_tindakan:'.$t->id);
        }

        return $t;
    }

    public function selesaiTindakan(TindakanSusulan $t, string $outcome): void
    {
        $t->forceFill($outcome === 'lulus' ? ['status' => 'selesai', 'tarikh_selesai' => now()] : ['status' => $outcome === 'tolak' ? 'dibuka' : 'dibatalkan', 'id_tugasan_workflow' => null])->save();
    }

    /**
     * SRS-KES-18: propose a title / land-status change to MOD-HKM on the
     * strength of the court order; recorded here with the cross-reference.
     *
     * @param  array{no_hak_milik: string, kod_mukim?: string|null, jenis_perubahan: string, data: array<string, mixed>, id_dokumen_perintah: string, lot_id?: string|null, ringkasan?: string|null}  $d
     */
    public function kemasKiniRekod(TindakanSusulan $t, array $d, User $by): PengemaskinianRekod
    {
        $kes = $t->kes;
        if ($kes === null || $t->jenis_tindakan !== 'pengemaskinian_hak_milik' || $t->status === 'dibatalkan') {
            throw ValidationException::withMessages(['tindakan' => __('kes::kes.ralat.tindakan_bukan_hak_milik')]);
        }
        if (! DB::table('kes_dokumen')->where('kes_mahkamah_id', $kes->id)->where('id_dokumen_dms', $d['id_dokumen_perintah'])->exists()) {
            throw ValidationException::withMessages(['id_dokumen_perintah' => __('kes::kes.ralat.dokumen_perintah')]);
        }
        $data = $d['data'];
        if ($d['jenis_perubahan'] === 'sekatan') {
            $data['id_kes_mahkamah'] ??= $kes->id;
            $data['id_dokumen_rujukan'] ??= $d['id_dokumen_perintah'];
        }
        $ringkasan = trim(($d['ringkasan'] ?? '') !== '' ? (string) $d['ringkasan'] : 'Pengemaskinian '.$d['jenis_perubahan'].' hak milik '.$d['no_hak_milik']);
        $rujukanSilang = "Berdasarkan perintah mahkamah — No. kes {$kes->no_kes} ({$kes->no_rujukan}); dokumen perintah {$d['id_dokumen_perintah']}.";
        $idCadangan = $this->titles->propose([
            'sumber' => 'mod_kes', 'rujukan_luaran' => 'kes_tindakan:'.$t->id.':'.substr(sha1(json_encode([$d['no_hak_milik'], $d['jenis_perubahan'], $data]) ?: ''), 0, 12),
            'no_hak_milik' => $d['no_hak_milik'], 'kod_mukim' => $d['kod_mukim'] ?? null, 'jenis_perubahan' => $d['jenis_perubahan'],
            'data' => $data, 'keterangan' => $ringkasan.'. '.$rujukanSilang,
        ], $by);
        $r = PengemaskinianRekod::query()->create([
            'kes_mahkamah_id' => $kes->id, 'kes_tindakan_susulan_id' => $t->id, 'lot_id' => $d['lot_id'] ?? $t->id_lot_terjejas,
            'jenis_rekod' => $d['jenis_perubahan'] === 'status' ? 'status_tanah' : 'hak_milik', 'no_hak_milik' => $d['no_hak_milik'],
            'jenis_perubahan' => $d['jenis_perubahan'], 'ringkasan_perubahan' => $ringkasan, 'no_kes_mahkamah' => $kes->no_kes,
            'id_dokumen_perintah' => $d['id_dokumen_perintah'], 'id_cadangan_hkm' => $idCadangan, 'id_pengguna' => $by->id,
        ]);
        Kronologi::query()->create(['kes_mahkamah_id' => $kes->id, 'tarikh' => now(), 'jenis' => 'sistem', 'keterangan' => 'Cadangan pengemaskinian rekod hak milik '.$d['no_hak_milik'].' dihantar ke MOD-HKM', 'sumber' => 'hkm', 'id_pengguna' => $by->id]);

        return $r;
    }

    /** @param array<string, mixed> $d validated */
    public function rayuan(KesMahkamah $kes, array $d, User $by, bool $sintetik = false): Rayuan
    {
        $r = new Rayuan;
        $mais = ($d['pihak_merayu'] ?? 'mais') === 'mais';
        $r->forceFill([
            'kes_mahkamah_id' => $kes->id, 'kes_keputusan_id' => $d['kes_keputusan_id'] ?? null, 'pihak_merayu' => $d['pihak_merayu'] ?? 'mais',
            'mahkamah_rayuan' => $d['mahkamah_rayuan'] ?? null, 'peringkat_mahkamah' => $d['peringkat_mahkamah'] ?? null, 'no_kes_rayuan' => $d['no_kes_rayuan'] ?? null,
            'tarikh_had_failan' => $d['tarikh_had_failan'] ?? null, 'tarikh_failan' => $mais ? null : ($d['tarikh_failan'] ?? now()->toDateString()),
            'alasan' => $d['alasan'] ?? null, 'status' => $mais ? 'cadangan' : 'difailkan', 'id_pengguna' => $by->id, 'sintetik' => $sintetik,
        ])->save();
        if ($r->tarikh_had_failan !== null) {
            app(ProsidingService::class)->tarikhPenting($kes, ['jenis' => 'had_rayuan', 'tarikh' => $r->tarikh_had_failan->toDateString(), 'keterangan' => 'Had pemfailan rayuan'], $by, $sintetik);
        }
        if (! $mais) {
            $this->kes->tukarStatus($kes, 'rayuan', $by, 'Rayuan difailkan oleh pihak lawan');

            return $r;
        }
        if (! $sintetik) {
            $this->mulaAliran($r, self::FLOW_RAYUAN, 'kes_rayuan', $kes, $by, 'Kelulusan rayuan — '.$kes->no_rujukan);
        }

        return $r;
    }

    public function selesaiRayuan(Rayuan $r, string $outcome, ?User $by): void
    {
        if ($outcome === 'lulus') {
            $r->forceFill(['status' => 'difailkan', 'tarikh_failan' => $r->tarikh_failan ?? now()->toDateString()])->save();
            if ($r->kes !== null) {
                $this->kes->tukarStatus($r->kes, 'rayuan', $by, 'Rayuan diluluskan untuk difailkan');
            }
        } else {
            $r->forceFill(['status' => $outcome === 'tolak' ? 'ditolak' : 'cadangan'])->save();
        }
    }

    /** @param array<string, mixed> $d validated */
    public function putusRayuan(Rayuan $r, array $d, User $by): void
    {
        $r->forceFill(['status' => 'diputuskan', 'keputusan_rayuan' => $d['keputusan_rayuan'], 'no_kes_rayuan' => $d['no_kes_rayuan'] ?? $r->no_kes_rayuan])->save();
        if ($r->kes !== null && ! empty($d['rekod_keputusan'])) {
            $this->rekod($r->kes, ['jenis_keputusan' => $d['keputusan_rayuan'], 'tarikh_keputusan' => $d['tarikh_keputusan'] ?? now()->toDateString(), 'peringkat_mahkamah' => $r->peringkat_mahkamah, 'ringkasan_perintah' => $d['ringkasan_perintah'] ?? 'Keputusan rayuan'], $by);
        }
    }

    /** @param array<string, mixed> $d validated */
    public function penyelesaian(KesMahkamah $kes, array $d, User $by, bool $sintetik = false): Penyelesaian
    {
        $p = new Penyelesaian;
        $p->forceFill([
            'kes_mahkamah_id' => $kes->id, 'terma' => $d['terma'], 'nilai_penyelesaian' => isset($d['nilai_penyelesaian']) && $d['nilai_penyelesaian'] !== '' ? Wang::normal((string) $d['nilai_penyelesaian']) : null,
            'tarikh_cadangan' => $d['tarikh_cadangan'] ?? now()->toDateString(), 'status' => 'cadangan', 'id_pengguna' => $by->id, 'sintetik' => $sintetik,
        ])->save();
        if (! $sintetik) {
            $this->mulaAliran($p, self::FLOW_PENYELESAIAN, 'kes_penyelesaian', $kes, $by, 'Kelulusan penyelesaian luar mahkamah — '.$kes->no_rujukan, (float) ($p->nilai_penyelesaian ?? 0));
        }

        return $p;
    }

    public function selesaiPenyelesaian(Penyelesaian $p, string $outcome): void
    {
        $p->forceFill(['status' => $outcome === 'lulus' ? 'diluluskan' : ($outcome === 'tolak' ? 'ditolak' : 'cadangan')])->save();
    }

    /** Seal an approved settlement: it becomes the case's decision (penyelesaian luar mahkamah) with its follow-ups. */
    public function meterai(Penyelesaian $p, string $idDokumen, string $tarikh, User $by): Keputusan
    {
        if ($p->status !== 'diluluskan' || $p->kes === null) {
            throw ValidationException::withMessages(['status' => __('kes::kes.ralat.penyelesaian_belum_lulus')]);
        }
        $p->forceFill(['status' => 'dimeterai', 'tarikh_persetujuan' => $tarikh, 'id_dokumen_perjanjian' => $idDokumen])->save();

        return $this->rekod($p->kes, [
            'jenis_keputusan' => 'penyelesaian_luar_mahkamah', 'tarikh_keputusan' => $tarikh, 'ringkasan_perintah' => mb_substr($p->terma, 0, 2000),
            'implikasi' => $p->nilai_penyelesaian === null ? null : 'Nilai penyelesaian '.Wang::papar($p->nilai_penyelesaian), 'id_dokumen_penghakiman' => $idDokumen,
        ], $by);
    }

    private function mulaAliran(Rayuan|Penyelesaian $rekod, string $flow, string $subject, KesMahkamah $kes, User $by, string $tajuk, float $nilai = 0): void
    {
        try {
            $id = $this->workflow->start($flow, $subject, $rekod->id, $by, [
                'kod_daerah' => $kes->kod_daerah, 'jenis' => $kes->jenis, 'peranan_mais' => $kes->peranan_mais, 'nilai' => $nilai, 'pautan' => '/admin/kes/kes/'.$kes->id.'?tab=keputusan',
            ], $tajuk);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['aliran' => __('kes::kes.ralat.tiada_aliran')]);
        }
        $rekod->forceFill(['status' => 'menunggu_kelulusan', 'id_instans_aliran' => $id])->save();
    }
}
