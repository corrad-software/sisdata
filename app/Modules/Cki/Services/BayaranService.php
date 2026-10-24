<?php

namespace App\Modules\Cki\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Cki\Contracts\GrpPembayaran;
use App\Modules\Cki\Contracts\PenyediaCukaiBerpusat;
use App\Modules\Cki\Contracts\PenyediaTidakTersedia;
use App\Modules\Cki\Models\Cukai;
use App\Modules\Cki\Models\Kelulusan;
use App\Modules\Cki\Models\Liputan;
use App\Modules\Cki\Models\Penyelarasan;
use App\Modules\Cki\Models\Resit;
use App\Modules\Cki\Models\Transaksi;
use App\Modules\Cki\Support\Aliran;
use App\Modules\Cki\Support\Dokumen;
use App\Modules\Cki\Support\Kod;
use App\Modules\Cki\Support\Masker;
use App\Modules\Cki\Support\Penomboran;
use App\Modules\Cki\Support\Wang;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Payment of a tax bill (MAIS → Pejabat Tanah / PBT), SRS-CKI-11..15:
 *
 *   mohon   → payment request + approval record; MOD-WFE flow started with
 *             the level required by the value thresholds (SRS-CKI-14)
 *   (WFE)   → WorkflowCompleted lulus / tolak
 *   laksana → only after correct-level approval: FPX through the central
 *             API (official receipt returned), or EFT / bank draft through a
 *             GRP MAIS payment request (SRS-CKI-11); counter payments are
 *             recorded with rekod()
 *   rekod   → method-specific fields (SRS-CKI-12)
 *   resit   → electronic (API) or uploaded receipt (SRS-CKI-13)
 *   selesai → needs ≥ 1 receipt / proof; updates the bill, the tax ledger
 *             and creates the GRP reconciliation record (SRS-CKI-15)
 */
class BayaranService
{
    public const AKTIF = ['menunggu_kelulusan', 'diluluskan', 'dihantar_grp', 'dibayar'];

    public function __construct(
        private Parameters $parameters,
        private WorkflowEngine $workflow,
        private AuditLogger $audit,
        private Notifier $notifier,
        private BilService $bil,
        private PenyediaCukaiBerpusat $penyedia,
        private GrpPembayaran $grp,
        private Panggilan $panggilan,
    ) {}

    /** @return array{peringkat: string, tahap: int} required approval level for an amount under the current thresholds */
    public function peringkatUntuk(string $amaun): array
    {
        $had = $this->parameters->get('cki.had_kelulusan', Aliran::HAD_LALAI);
        $had = is_array($had) && $had !== [] ? $had : Aliran::HAD_LALAI;
        foreach ($had as $row) {
            $limit = $row['hingga'] ?? null;
            if ($limit === null || Wang::cmp($amaun, (string) $limit) <= 0) {
                $p = (string) $row['peringkat'];

                return ['peringkat' => $p, 'tahap' => Kod::PERINGKAT_TAHAP[$p] ?? 3];
            }
        }

        return ['peringkat' => 'pengurusan_atasan', 'tahap' => 3];
    }

    /** @param array{kaedah_bayaran: string, amaun?: string|null, catatan?: string|null} $data */
    public function mohon(Cukai $bil, array $data, User $user): Transaksi
    {
        if ($bil->dibayar()) {
            throw ValidationException::withMessages(['amaun' => __('cki::cki.ralat.bil_dibayar')]);
        }
        if (Transaksi::query()->withoutGlobalScope('access')->where('id_bil', $bil->id)->whereIn('status_transaksi', self::AKTIF)->exists()) {
            throw ValidationException::withMessages(['id_bil' => __('cki::cki.ralat.transaksi_aktif')]);
        }
        $amaun = Wang::norm($data['amaun'] ?? $bil->baki());
        if (Wang::cmp($amaun, '0') <= 0 || Wang::cmp($amaun, $bil->baki()) > 0) {
            throw ValidationException::withMessages(['amaun' => __('cki::cki.ralat.amaun_tidak_sah', ['baki' => Wang::rm($bil->baki())])]);
        }
        $level = $this->peringkatUntuk($amaun);

        $t = DB::transaction(function () use ($bil, $data, $user, $amaun, $level) {
            $t = Transaksi::query()->create([
                'no_permohonan' => Penomboran::next('permohonan'), 'id_bil' => $bil->id, 'kaedah_bayaran' => $data['kaedah_bayaran'], 'amaun' => $amaun,
                'status_transaksi' => 'menunggu_kelulusan', 'kod_daerah' => (string) $bil->kod_daerah, 'kod_mukim' => $bil->kod_mukim,
                'catatan' => $data['catatan'] ?? null, 'id_pemohon' => $user->id,
            ]);
            $k = Kelulusan::query()->create([
                'id_transaksi' => $t->id, 'id_bil' => $bil->id, 'amaun' => $amaun, 'peringkat_pelulus' => $level['peringkat'], 'tahap' => $level['tahap'],
                'id_pegawai_pemohon' => $user->id, 'status' => 'berjalan',
            ]);
            try {
                $k->id_aliran = $this->workflow->start(Aliran::BAYARAN, Transaksi::SUBJECT, $t->id, $user, [
                    'amaun' => (float) $amaun, 'tahap' => $level['tahap'], 'peringkat' => $level['peringkat'], 'jenis_cukai' => $bil->jenis,
                    'kaedah_bayaran' => $t->kaedah_bayaran, 'kod_daerah' => $bil->kod_daerah, 'pautan' => '/admin/cki/bil/'.$bil->id,
                ], __('cki::cki.aliran.tajuk', ['no' => $t->no_permohonan, 'amaun' => Wang::rm($amaun)]));
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages(['aliran' => __('cki::cki.ralat.aliran_tiada', ['aliran' => Aliran::BAYARAN])]);
            }
            $k->save();
            $t->id_kelulusan = $k->id;
            $t->save();
            $this->bil->kemaskiniStatus($bil);

            return $t;
        });

        // SRS-CKI-11: payment request document, kept in DMS with the bill.
        $pdf = Dokumen::pdf(__('cki::cki.dokumen.permohonan_tajuk'), [['jadual' => [
            [__('cki::cki.medan.no_permohonan'), $t->no_permohonan], [__('cki::cki.medan.no_bil'), (string) $bil->no_bil],
            [__('cki::cki.medan.jenis_cukai'), Kod::JENIS_CUKAI[$bil->jenis] ?? $bil->jenis], [__('cki::cki.medan.tahun'), (string) $bil->tahun_taksiran],
            [__('cki::cki.medan.agensi'), (string) $bil->kod_agensi], [__('cki::cki.medan.amaun'), Wang::rm($amaun)],
            [__('cki::cki.medan.kaedah'), Kod::KAEDAH_BAYARAN[$t->kaedah_bayaran] ?? $t->kaedah_bayaran],
            [__('cki::cki.medan.peringkat'), Kod::PERINGKAT[$level['peringkat']]], [__('cki::cki.medan.tarikh_akhir'), $bil->tarikh_akhir?->format('d/m/Y') ?? '—'],
        ]]]);
        $t->id_dokumen_permohonan = $this->bil->cubaSimpanDokumen($bil, Dokumen::file($pdf, str_replace('/', '-', $t->no_permohonan).'.pdf'), 'permohonan', $user, ['no_permohonan' => $t->no_permohonan]);
        $t->save();

        return $t;
    }

    public function onWorkflowCompleted(WorkflowCompleted $e): void
    {
        if ($e->flowCode !== Aliran::BAYARAN) {
            return;
        }
        $t = Transaksi::query()->withoutGlobalScope('access')->find($e->subjectId);
        if ($t === null || $t->status_transaksi !== 'menunggu_kelulusan') {
            return;
        }
        DB::transaction(function () use ($t, $e) {
            $k = Kelulusan::query()->find($t->id_kelulusan);
            $k?->forceFill(['status' => $e->outcome, 'id_pegawai_pelulus' => $e->decidedBy, 'tarikh_kelulusan' => now(), 'justifikasi' => $e->remarks])->save();
            $t->status_transaksi = $e->outcome === 'lulus' ? 'diluluskan' : ($e->outcome === 'tolak' ? 'ditolak' : 'dibatalkan');
            $t->save();
            $bil = Cukai::query()->withoutGlobalScope('access')->find($t->id_bil);
            if ($bil) {
                $this->bil->kemaskiniStatus($bil);
            }
        });
        if ($t->id_pemohon && ($u = User::query()->find($t->id_pemohon))) {
            $this->notifier->send('cki.keputusan_kelulusan', $u, [
                'no_permohonan' => $t->no_permohonan, 'keputusan' => Kod::STATUS_TRANSAKSI[$t->status_transaksi] ?? $t->status_transaksi, 'amaun' => Wang::rm($t->amaun),
            ], '/admin/cki/bil/'.$t->id_bil);
        }
    }

    /**
     * SRS-CKI-14 / kriteria 5: a payment is executed only after approval at
     * the right level. A refused attempt is recorded in the audit trail.
     */
    public function pastikanLulus(Transaksi $t, string $tindakan): Kelulusan
    {
        $k = $t->id_kelulusan ? Kelulusan::query()->find($t->id_kelulusan) : null;
        $required = $this->peringkatUntuk($t->amaun);
        $reason = match (true) {
            $k === null || $k->status !== 'lulus' => __('cki::cki.ralat.belum_lulus'),
            $k->tahap < $required['tahap'] => __('cki::cki.ralat.tahap_tidak_cukup', ['perlu' => Kod::PERINGKAT[$required['peringkat']], 'ada' => Kod::PERINGKAT[$k->peringkat_pelulus] ?? $k->peringkat_pelulus]),
            ! in_array($t->status_transaksi, ['diluluskan', 'dihantar_grp', 'dibayar'], true) => __('cki::cki.ralat.status_transaksi', ['status' => Kod::STATUS_TRANSAKSI[$t->status_transaksi] ?? $t->status_transaksi]),
            default => null,
        };
        if ($reason !== null) {
            $this->audit->record($tindakan, 'cki', Transaksi::SUBJECT, $t->id, status: 'gagal', reason: $reason, meta: [
                'amaun' => $t->amaun, 'peringkat_diperlukan' => $required['peringkat'], 'peringkat_diluluskan' => $k?->peringkat_pelulus, 'status_kelulusan' => $k?->status,
            ]);
            throw ValidationException::withMessages(['kelulusan' => $reason]);
        }

        /** @var Kelulusan $k */
        return $k;
    }

    /** Execute an approved payment: central API (FPX) or GRP MAIS request (EFT / bank draft). */
    public function laksana(Transaksi $t, User $user): Transaksi
    {
        $this->pastikanLulus($t, 'laksana_bayaran');
        if ($t->status_transaksi !== 'diluluskan') {
            throw ValidationException::withMessages(['status' => __('cki::cki.ralat.status_transaksi', ['status' => Kod::STATUS_TRANSAKSI[$t->status_transaksi]])]);
        }
        $bil = Cukai::query()->withoutGlobalScope('access')->findOrFail($t->id_bil);
        $agensi = Liputan::query()->where('kod_agensi', $bil->kod_agensi)->first();

        if ($t->kaedah_bayaran === 'fpx') {
            if (! $agensi || ! $agensi->perkhidmatan_bayaran || $agensi->dalamSandaran()) {
                throw ValidationException::withMessages(['kaedah_bayaran' => __('cki::cki.ralat.bayaran_api_tiada')]);
            }
            $no = (string) optional($bil->obligasi)->no_akaun_cukai;
            try {
                $r = $this->panggilan->call('bayar', $agensi->kod_agensi, fn () => $this->penyedia->bayar([
                    'kod_agensi' => $agensi->kod_agensi, 'no_akaun' => $no, 'no_bil' => (string) $bil->no_bil, 'amaun' => $t->amaun, 'rujukan' => $t->no_permohonan,
                ]), ['no_permohonan' => $t->no_permohonan, 'no_akaun' => Masker::akaun($no)], $bil->obligasi_id);
            } catch (PenyediaTidakTersedia $e) {
                app(LiputanService::class)->gagal($agensi, $e->getMessage(), $user);
                throw ValidationException::withMessages(['kaedah_bayaran' => __('cki::cki.ralat.api_gagal')]);
            }
            DB::transaction(function () use ($t, $bil, $r, $user) {
                $t->forceFill(['melalui_api' => true, 'rujukan_fpx' => $r['rujukan_transaksi'], 'rujukan_transaksi_api' => $r['rujukan_transaksi'],
                    'no_resit' => $r['no_resit'], 'tarikh_bayaran' => $r['tarikh'], 'status_transaksi' => 'dibayar'])->save();
                // SRS-CKI-15: official e-receipt saved to DMS automatically.
                $pdf = Dokumen::pdf(__('cki::cki.dokumen.resit_tajuk'), [['jadual' => [
                    [__('cki::cki.medan.no_resit'), $r['no_resit']], [__('cki::cki.medan.no_bil'), (string) $bil->no_bil], [__('cki::cki.medan.agensi'), (string) $bil->kod_agensi],
                    [__('cki::cki.medan.amaun'), Wang::rm($r['amaun'])], [__('cki::cki.medan.tarikh_bayar'), Carbon::parse($r['tarikh'])->format('d/m/Y')], [__('cki::cki.medan.rujukan'), $r['rujukan_transaksi']],
                ]]]);
                $doc = $this->bil->cubaSimpanDokumen($bil, Dokumen::file($pdf, $r['no_resit'].'.pdf'), 'resit', $user, ['no_resit' => $r['no_resit'], 'sumber' => 'api']);
                Resit::query()->create([
                    'id_transaksi' => $t->id, 'id_bil' => $bil->id, 'no_resit' => $r['no_resit'], 'no_bil_rujukan' => $bil->no_bil, 'kod_agensi' => $bil->kod_agensi,
                    'amaun' => Wang::norm($r['amaun']), 'tarikh_resit' => $r['tarikh'], 'sumber' => 'api', 'id_dokumen' => $doc, 'kod_daerah' => $bil->kod_daerah, 'id_pengguna' => $user->id,
                ]);
                $t->forceFill(['id_dokumen_resit' => $doc])->save();
            });

            return $this->selesai($t->refresh(), $user);
        }

        if (in_array($t->kaedah_bayaran, Kod::MELALUI_GRP, true)) {
            try {
                $ref = $this->panggilan->call('grp_hantar', null, fn () => $this->grp->hantarPermohonan([
                    'no_permohonan' => $t->no_permohonan, 'no_bil' => (string) $bil->no_bil, 'penerima' => $agensi->nama_agensi ?? (string) $bil->kod_agensi,
                    'amaun' => $t->amaun, 'kaedah' => $t->kaedah_bayaran, 'kod_vot' => (string) $this->parameters->get('cki.kod_vot_grp', 'B29000-CUKAI'),
                    'tarikh' => Carbon::today()->format('Y-m-d'), 'keterangan' => 'Bayaran '.(Kod::JENIS_CUKAI[$bil->jenis] ?? $bil->jenis).' '.$bil->tahun_taksiran,
                ]), ['no_permohonan' => $t->no_permohonan], $bil->obligasi_id, 'cki_grp');
            } catch (PenyediaTidakTersedia) {
                throw ValidationException::withMessages(['grp' => __('cki::cki.ralat.grp_gagal')]);
            }
            $t->forceFill(['rujukan_grp' => $ref, 'status_grp' => 'menunggu', 'status_transaksi' => 'dihantar_grp'])->save();
            $this->semakGrp($t);

            return $t->refresh();
        }

        // Counter payments: approved — the officer pays at the counter and records it (rekod()).
        return $t;
    }

    /** SRS-CKI-11: receive the payment status of a GRP request. */
    public function semakGrp(Transaksi $t): Transaksi
    {
        if ($t->rujukan_grp === null || $t->status_transaksi !== 'dihantar_grp') {
            return $t;
        }
        try {
            $s = $this->panggilan->call('grp_status', null, fn () => $this->grp->status((string) $t->rujukan_grp), ['no_permohonan' => $t->no_permohonan], null, 'cki_grp');
        } catch (PenyediaTidakTersedia) {
            return $t;
        }
        if ($s === null) {
            return $t;
        }
        $t->status_grp = $s['status'];
        if ($s['status'] === 'dibayar') {
            $t->tarikh_bayaran = $s['tarikh_bayar'] ? Carbon::parse($s['tarikh_bayar']) : Carbon::today();
            if ($t->kaedah_bayaran === 'eft') {
                $t->no_rujukan_eft ??= $s['no_rujukan_bayaran'];
            } else {
                $t->no_bank_draf ??= $s['no_rujukan_bayaran'];
            }
            $t->status_transaksi = 'dibayar';
        } elseif ($s['status'] === 'ditolak') {
            $t->status_transaksi = 'ditolak';
        }
        $t->save();

        return $t;
    }

    /**
     * SRS-CKI-12: record how the payment was made, with the fields each method requires.
     *
     * @param  array<string, mixed>  $data
     */
    public function rekod(Transaksi $t, array $data, User $user): Transaksi
    {
        $this->pastikanLulus($t, 'rekod_bayaran');
        $required = Kod::MEDAN_KAEDAH[$t->kaedah_bayaran];
        if ($t->kaedah_bayaran === 'bank_draf') {
            $required = ['no_bank_draf', 'bank_penerbit'];
        }
        $errors = [];
        foreach ($required as $f) {
            if (trim((string) ($data[$f] ?? $t->getAttribute($f) ?? '')) === '') {
                $errors[$f] = __('cki::cki.ralat.medan_wajib_kaedah', ['medan' => __('cki::cki.medan.'.$f), 'kaedah' => Kod::KAEDAH_BAYARAN[$t->kaedah_bayaran]]);
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        foreach (['no_rujukan_eft', 'no_bank_draf', 'bank_penerbit', 'rujukan_fpx', 'nama_kaunter', 'no_resit'] as $f) {
            if (array_key_exists($f, $data) && $data[$f] !== null && $data[$f] !== '') {
                $t->setAttribute($f, (string) $data[$f]);
            }
        }
        $t->tarikh_bayaran = Carbon::parse((string) ($data['tarikh_bayaran'] ?? Carbon::today()->format('Y-m-d')));
        $t->status_transaksi = 'dibayar';
        $t->save();

        return $t;
    }

    /**
     * SRS-CKI-13/16: attach a receipt or proof of payment (DMS) and register it.
     *
     * @param  array{no_resit: string, amaun?: string|null, tarikh_resit?: string|null}  $data
     */
    public function resit(Cukai $bil, ?Transaksi $t, UploadedFile $file, array $data, User $user): Resit
    {
        $doc = $this->bil->simpanDokumen($bil, $file, 'resit', $user, ['no_resit' => $data['no_resit'], 'sumber' => 'manual']);

        return DB::transaction(function () use ($bil, $t, $data, $user, $doc) {
            $r = Resit::query()->create([
                'id_transaksi' => $t?->id, 'id_bil' => $bil->id, 'no_resit' => $data['no_resit'], 'no_bil_rujukan' => $bil->no_bil, 'kod_agensi' => $bil->kod_agensi,
                'amaun' => Wang::norm($data['amaun'] ?? ($t->amaun ?? $bil->baki())), 'tarikh_resit' => $data['tarikh_resit'] ?? Carbon::today()->format('Y-m-d'),
                'sumber' => 'manual', 'id_dokumen' => $doc, 'kod_daerah' => $bil->kod_daerah, 'id_pengguna' => $user->id,
            ]);
            if ($t !== null) {
                $t->no_resit ??= $r->no_resit;
                $t->id_dokumen_resit ??= $doc;
                $t->save();
            }

            return $r;
        });
    }

    /** SRS-CKI-13/15: complete a paid transaction — never without a receipt or proof of payment. */
    public function selesai(Transaksi $t, User $user): Transaksi
    {
        if ($t->status_transaksi !== 'dibayar') {
            throw ValidationException::withMessages(['status' => __('cki::cki.ralat.status_transaksi', ['status' => Kod::STATUS_TRANSAKSI[$t->status_transaksi] ?? $t->status_transaksi])]);
        }
        if (! Resit::query()->where('id_transaksi', $t->id)->exists()) {
            $this->audit->record('selesai_bayaran', 'cki', Transaksi::SUBJECT, $t->id, status: 'gagal', reason: __('cki::cki.ralat.tiada_resit'));
            throw ValidationException::withMessages(['resit' => __('cki::cki.ralat.tiada_resit')]);
        }

        return DB::transaction(function () use ($t, $user) {
            $bil = Cukai::query()->withoutGlobalScope('access')->lockForUpdate()->findOrFail($t->id_bil);
            $t->status_transaksi = 'selesai';
            $t->save();
            $bil->amaun_dibayar = Wang::add($bil->amaun_dibayar, $t->amaun);
            $bil->tarikh_bayar = $t->tarikh_bayaran ?? Carbon::today();
            $bil->kaedah_bayaran = $t->kaedah_bayaran;
            $bil->no_resit = $t->no_resit ?? $bil->no_resit;
            $this->bil->lejar($bil, 'bayaran', '0', $t->amaun, $t->no_permohonan, __('cki::cki.lejar.bayaran', ['kaedah' => Kod::KAEDAH_BAYARAN[$t->kaedah_bayaran]]), $user, $bil->tarikh_bayar);
            $this->bil->kemaskiniStatus($bil);
            Penyelarasan::query()->create([
                'id_transaksi' => $t->id, 'id_bil' => $bil->id, 'jenis' => 'penyelarasan', 'rujukan_grp' => $t->rujukan_grp, 'amaun_sistem' => $t->amaun,
                'status_padanan' => 'menunggu', 'kod_daerah' => $t->kod_daerah,
            ]);

            return $t;
        });
    }
}
