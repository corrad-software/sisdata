<?php

namespace App\Modules\Tsu\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Pro\Contracts\LotRegistry;
use App\Modules\Tsu\Events\UrusanDitutup;
use App\Modules\Tsu\Models\HasilUkur;
use App\Modules\Tsu\Models\KosUrusan;
use App\Modules\Tsu\Models\LantikanJurukur;
use App\Modules\Tsu\Models\PemindahanRekod;
use App\Modules\Tsu\Models\TemplatProses;
use App\Modules\Tsu\Models\UrusanDokumen;
use App\Modules\Tsu\Models\UrusanKeputusan;
use App\Modules\Tsu\Models\UrusanLot;
use App\Modules\Tsu\Models\UrusanPeringkat;
use App\Modules\Tsu\Models\UrusanTanah;
use App\Modules\Tsu\Support\AliranTsu;
use App\Modules\Tsu\Support\Lalai;
use App\Modules\Tsu\Support\Makluman;
use App\Modules\Tsu\Support\Penomboran;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Urusan lifecycle (SRS-TSU-01..07, 24): register on lots from the active
 * template version, track stages with target dates, block a stage until its
 * mandatory documents and preconditions are met, hand approval stages to
 * MOD-WFE, apply the stage effects, close and lock.
 */
class UrusanService
{
    public function __construct(
        private LotRegistry $lots,
        private WorkflowEngine $workflow,
        private Parameters $parameters,
        private AuditLogger $audit,
        private Makluman $makluman,
        private KesanUrusan $kesan,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated
     */
    public function create(array $data, User $user, bool $sintetik = false): UrusanTanah
    {
        $jenis = (string) $data['jenis_urusan'];
        $lotIds = array_values(array_unique(array_map('strval', (array) $data['lot_ids'])));
        if ($jenis === 'penyatuan' && count($lotIds) < 2) {
            throw ValidationException::withMessages(['lot_ids' => [__('tsu::tsu.urusan.penyatuan_dua_lot')]]);
        }
        $lots = [];
        foreach ($lotIds as $id) {
            $lot = $this->lots->find($id, $user);
            if ($lot === null) {
                throw ValidationException::withMessages(['lot_ids' => [__('tsu::tsu.urusan.lot_tiada')]]);
            }
            if (in_array($lot['status_lot'], ['arkib', 'digabung'], true)) {
                throw ValidationException::withMessages(['lot_ids' => [__('tsu::tsu.urusan.lot_arkib', ['no_lot' => $lot['no_lot']])]]);
            }
            $lots[] = $lot;
        }
        $open = UrusanLot::query()->whereIn('lot_id', $lotIds)
            ->whereHas('urusan', fn ($q) => $q->withoutGlobalScope('access')->where('jenis_urusan', $jenis)->whereIn('status', Lalai::URUSAN_TERBUKA))->exists();
        if ($open) {
            throw ValidationException::withMessages(['lot_ids' => [__('tsu::tsu.urusan.urusan_wujud')]]);
        }
        if ($jenis === 'pelupusan' && empty($data['kaedah_pelupusan'])) {
            throw ValidationException::withMessages(['kaedah_pelupusan' => [__('validation.required', ['attribute' => 'kaedah pelupusan'])]]);
        }
        $templat = TemplatProses::query()->with('peringkat')->where('jenis_urusan', $jenis)->where('status_aktif', true)->first();
        if ($templat === null || $templat->peringkat->isEmpty()) {
            throw ValidationException::withMessages(['jenis_urusan' => [__('tsu::tsu.urusan.templat_tiada')]]);
        }

        $first = $lots[0];
        $mula = Carbon::parse($data['tarikh_mula'] ?? today()->toDateString())->startOfDay();
        $jumlahHari = (int) $templat->peringkat->sum('tempoh_sasaran_hari');
        $klasifikasi = $jenis === 'pelupusan' && in_array($first['klasifikasi'], ['awam', 'dalaman'], true) ? 'sulit' : $first['klasifikasi'];

        $urusan = DB::transaction(function () use ($data, $user, $jenis, $templat, $first, $lots, $mula, $jumlahHari, $klasifikasi, $sintetik) {
            $u = new UrusanTanah;
            $u->forceFill([
                'jenis_urusan' => $jenis, 'templat_id' => $templat->id, 'no_rujukan_mais' => Penomboran::urusan($jenis),
                'no_rujukan_etanah' => $data['no_rujukan_etanah'] ?? null, 'pejabat_tanah' => $data['pejabat_tanah'] ?? null,
                'kod_daerah' => $first['kod_daerah'], 'kod_mukim' => $first['kod_mukim'], 'bahagian_pemilik' => $first['bahagian_pemilik'],
                'klasifikasi' => $klasifikasi, 'tajuk' => $data['tajuk'] ?? Lalai::JENIS_URUSAN[$jenis].' — '.implode(', ', array_column($lots, 'no_lot')),
                'keterangan' => $data['keterangan'] ?? null, 'butiran' => (array) ($data['butiran'] ?? []),
                'kaedah_pelupusan' => $jenis === 'pelupusan' ? $data['kaedah_pelupusan'] : null,
                'justifikasi' => $data['justifikasi'] ?? null, 'status' => 'aktif', 'peringkat_semasa' => 1,
                'tarikh_mula' => $mula->toDateString(), 'tarikh_sasaran_siap' => $mula->copy()->addDays($jumlahHari)->toDateString(),
                'id_pegawai_bertanggungjawab' => $data['id_pegawai_bertanggungjawab'] ?? $user->id, 'id_pendaftar' => $user->id, 'sintetik' => $sintetik,
            ])->save();

            $peranan = in_array($jenis, Lalai::JENIS_LOT_BAHARU, true) ? 'asal' : 'terlibat';
            foreach ($lots as $lot) {
                UrusanLot::query()->create(['urusan_id' => $u->id, 'lot_id' => $lot['id'], 'peranan_lot' => $peranan, 'tarikh_paut' => now()]);
            }
            foreach ($templat->peringkat as $tp) {
                $isFirst = $tp->no_urutan === $templat->peringkat->first()->no_urutan;
                UrusanPeringkat::query()->create([
                    'urusan_id' => $u->id, 'no_urutan' => $tp->no_urutan, 'kod' => $tp->kod, 'nama_peringkat' => $tp->nama_peringkat,
                    'tindakan_diperlukan' => $tp->tindakan_diperlukan, 'pihak_bertanggungjawab' => $tp->pihak_bertanggungjawab,
                    'tempoh_sasaran_hari' => $tp->tempoh_sasaran_hari, 'senarai_dokumen_wajib' => $tp->senarai_dokumen_wajib,
                    'senarai_kategori_kos' => $tp->senarai_kategori_kos, 'perlu_kelulusan' => $tp->perlu_kelulusan, 'kesan' => $tp->kesan,
                    'status' => $isFirst ? 'aktif' : 'belum_mula',
                    'tarikh_mula' => $isFirst ? $mula->toDateString() : null,
                    'tarikh_sasaran' => $isFirst ? $mula->copy()->addDays($tp->tempoh_sasaran_hari)->toDateString() : null,
                ]);
            }
            $u->forceFill(['peringkat_semasa' => $templat->peringkat->first()->no_urutan])->save();

            return $u;
        });

        if (! $sintetik) {
            $this->makluman->hantar('tsu.urusan_baharu', $urusan->pegawai, ['no_rujukan' => $urusan->no_rujukan_mais, 'jenis' => $urusan->jenisLabel(), 'daerah' => $urusan->kod_daerah], $this->link($urusan));
        }

        return $urusan->refresh();
    }

    /**
     * Complete the current stage (SRS-TSU-03): checks mandatory documents and
     * the stage's preconditions; approval stages go to MOD-WFE instead.
     */
    public function completeStage(UrusanTanah $urusan, User $user, ?string $catatan = null): UrusanPeringkat
    {
        $this->assertOpen($urusan);
        $stage = $this->current($urusan);
        if ($stage->status !== 'aktif') {
            throw new HttpException(409, __('tsu::tsu.peringkat.bukan_aktif'));
        }
        $missing = $this->missingDocuments($urusan, $stage);
        if ($missing !== []) {
            throw ValidationException::withMessages(['dokumen' => [__('tsu::tsu.peringkat.dokumen_wajib', ['senarai' => implode(', ', $missing)])]]);
        }
        $this->checkPreconditions($urusan, $stage);

        if ($stage->perlu_kelulusan) {
            return $this->requestApproval($urusan, $stage, $user, $catatan);
        }

        return DB::transaction(function () use ($urusan, $stage, $user, $catatan) {
            $this->kesan->apply($urusan, $stage, $user);
            $this->finish($urusan, $stage, $user, $catatan);

            return $stage->refresh();
        });
    }

    private function requestApproval(UrusanTanah $urusan, UrusanPeringkat $stage, User $user, ?string $catatan): UrusanPeringkat
    {
        $flow = $urusan->jenis_urusan === 'pelupusan' ? AliranTsu::PELUPUSAN : ($urusan->templat->aliran_kelulusan ?: AliranTsu::URUSAN);
        if ($urusan->jenis_urusan === 'pelupusan') {
            // SRS-TSU-05: written justification + current valuation before the highest approval flow starts.
            if (trim((string) $urusan->justifikasi) === '') {
                throw ValidationException::withMessages(['justifikasi' => [__('tsu::tsu.pelupusan.justifikasi_wajib')]]);
            }
            if ($this->latestValuation($urusan) === null) {
                throw ValidationException::withMessages(['dokumen' => [__('tsu::tsu.pelupusan.penilaian_wajib')]]);
            }
        }
        try {
            $instance = $this->workflow->start($flow, AliranTsu::SUBJEK_PERINGKAT, $stage->id, $user, [
                'kod_daerah' => $urusan->kod_daerah, 'jenis_urusan' => $urusan->jenis_urusan, 'bahagian_pemilik' => $urusan->bahagian_pemilik,
                'kaedah_pelupusan' => $urusan->kaedah_pelupusan, 'pautan' => $this->link($urusan),
            ], $urusan->jenisLabel().' '.$urusan->no_rujukan_mais.' — '.$stage->nama_peringkat);
        } catch (InvalidArgumentException) {
            throw new HttpException(422, __('tsu::tsu.aliran_tiada', ['kod' => $flow]));
        }
        DB::transaction(function () use ($urusan, $stage, $instance, $catatan) {
            $stage->forceFill(['status' => 'menunggu_kelulusan', 'id_aliran_kerja' => $instance, 'catatan' => $catatan ?? $stage->catatan])->save();
            $urusan->forceFill(['status' => 'menunggu_kelulusan', 'id_aliran_kerja' => $instance])->save();
        });

        return $stage->refresh();
    }

    /**
     * MOD-WFE outcome for an approval stage. For disposal the approval is only
     * recorded when the highest stage was decided with a written justification
     * (MFA is enforced by MOD-WFE on that stage) and a current valuation is
     * still attached (SRS-TSU-05, acceptance #6).
     */
    public function onApproval(UrusanPeringkat $stage, string $outcome, ?int $decidedBy, ?string $remarks, string $instanceId): void
    {
        $urusan = UrusanTanah::query()->withoutGlobalScope('access')->with('templat')->find($stage->urusan_id);
        if ($urusan === null || $stage->status !== 'menunggu_kelulusan' || $urusan->dikunci) {
            return;
        }
        $user = $decidedBy ? User::query()->find($decidedBy) : null;

        if ($outcome === 'lulus' && $urusan->jenis_urusan === 'pelupusan') {
            $problem = $this->disposalApprovalProblem($urusan, $instanceId);
            if ($problem !== null) {
                DB::transaction(function () use ($urusan, $stage, $instanceId, $decidedBy, $problem) {
                    UrusanKeputusan::query()->create(['urusan_id' => $urusan->id, 'peringkat_id' => $stage->id, 'konteks' => 'urusan', 'jenis_keputusan' => 'tidak_sah', 'justifikasi' => $problem, 'id_pelulus' => $decidedBy, 'id_aliran' => $instanceId]);
                    $stage->forceFill(['status' => 'aktif', 'catatan' => $problem])->save();
                    $urusan->forceFill(['status' => 'aktif'])->save();
                });
                $this->makluman->hantar('tsu.keputusan_kelulusan', $urusan->pegawai, ['no_rujukan' => $urusan->no_rujukan_mais, 'peringkat' => $stage->nama_peringkat, 'keputusan' => 'Tidak direkodkan — '.$problem], $this->link($urusan));

                return;
            }
        }

        DB::transaction(function () use ($urusan, $stage, $outcome, $decidedBy, $remarks, $instanceId, $user) {
            UrusanKeputusan::query()->create([
                'urusan_id' => $urusan->id, 'peringkat_id' => $stage->id, 'konteks' => 'urusan', 'jenis_keputusan' => $outcome,
                'justifikasi' => $remarks, 'id_pelulus' => $decidedBy, 'id_aliran' => $instanceId,
                'indikator_mfa' => $this->mfaRecorded($urusan->id, $instanceId),
                'id_dokumen_kelulusan' => $urusan->jenis_urusan === 'pelupusan' ? $this->latestValuation($urusan)?->id_dokumen_dms : null,
            ]);
            if ($outcome === 'lulus') {
                $actor = $user ?? User::query()->findOrFail($urusan->id_pendaftar);
                $this->kesan->apply($urusan, $stage, $actor);
                $this->finish($urusan, $stage, $actor, $remarks);
            } elseif ($outcome === 'tolak') {
                $stage->forceFill(['status' => 'ditolak', 'tarikh_selesai' => today(), 'catatan' => $remarks])->save();
                $urusan->forceFill(['status' => 'ditolak'])->save();
            } else { // ditarik_balik / dibatalkan → back to the officer
                $stage->forceFill(['status' => 'aktif', 'id_aliran_kerja' => null])->save();
                $urusan->forceFill(['status' => 'aktif'])->save();
            }
        });

        $this->makluman->hantar('tsu.keputusan_kelulusan', $urusan->pegawai, ['no_rujukan' => $urusan->no_rujukan_mais, 'peringkat' => $stage->nama_peringkat, 'keputusan' => (string) __('tsu::tsu.keputusan.'.$outcome)], $this->link($urusan));
    }

    public function disposalApprovalProblem(UrusanTanah $urusan, string $instanceId): ?string
    {
        $top = UrusanKeputusan::query()->where('urusan_id', $urusan->id)->where('id_aliran', $instanceId)
            ->where('kod_peringkat_wfe', AliranTsu::PERINGKAT_TERTINGGI)->where('jenis_keputusan', 'lulus')->latest('id')->first();
        if ($top === null) {
            return (string) __('tsu::tsu.pelupusan.tiada_aliran_tertinggi');
        }
        if (! $top->indikator_mfa) {
            return (string) __('tsu::tsu.pelupusan.mfa_wajib');
        }
        if (trim((string) $top->justifikasi) === '') {
            return (string) __('tsu::tsu.pelupusan.justifikasi_pelulus_wajib');
        }
        if ($this->latestValuation($urusan) === null) {
            return (string) __('tsu::tsu.pelupusan.penilaian_wajib');
        }

        return null;
    }

    private function mfaRecorded(string $urusanId, string $instanceId): bool
    {
        return UrusanKeputusan::query()->where('urusan_id', $urusanId)->where('id_aliran', $instanceId)->where('indikator_mfa', true)->exists();
    }

    /** Valid (not expired) valuation report: a laporan_penilaian document + nilai_penilaian recorded. */
    public function latestValuation(UrusanTanah $urusan): ?UrusanDokumen
    {
        if ($urusan->nilai_penilaian === null) {
            return null;
        }
        $months = (int) $this->parameters->get('tsu.tempoh_sah_penilaian_bulan', 12);
        $doc = UrusanDokumen::query()->where('urusan_id', $urusan->id)->where('kategori_dokumen', 'laporan_penilaian')
            ->orderByDesc('tarikh_dokumen')->orderByDesc('id')->first();
        if ($doc === null) {
            return null;
        }
        $expiry = $doc->tarikh_luput ?? ($doc->tarikh_dokumen ?? $doc->tarikh_muat_naik)->copy()->addMonths($months);

        return $expiry->gte(today()) ? $doc : null;
    }

    private function finish(UrusanTanah $urusan, UrusanPeringkat $stage, User $user, ?string $catatan): void
    {
        $stage->forceFill(['status' => 'selesai', 'tarikh_selesai' => today(), 'id_pegawai_selesai' => $user->id, 'catatan' => $catatan ?? $stage->catatan])->save();
        $next = UrusanPeringkat::query()->where('urusan_id', $urusan->id)->where('no_urutan', '>', $stage->no_urutan)->where('status', 'belum_mula')->orderBy('no_urutan')->first();
        if ($next !== null) {
            $next->forceFill(['status' => 'aktif', 'tarikh_mula' => today(), 'tarikh_sasaran' => today()->addDays($next->tempoh_sasaran_hari)])->save();
            $urusan->forceFill(['peringkat_semasa' => $next->no_urutan, 'status' => 'aktif'])->save();
        } else {
            $urusan->forceFill(['status' => 'selesai', 'tarikh_siap' => today()])->save();
        }
    }

    /** SRS-TSU-24: close and lock — no change afterwards (DB trigger). */
    public function close(UrusanTanah $urusan, User $user, ?string $catatan = null): UrusanTanah
    {
        if ($urusan->dikunci) {
            throw new HttpException(409, __('tsu::tsu.urusan.dikunci'));
        }
        if (! in_array($urusan->status, ['selesai', 'ditolak', 'dibatalkan'], true)) {
            throw ValidationException::withMessages(['status' => [__('tsu::tsu.urusan.belum_selesai')]]);
        }
        if (PemindahanRekod::query()->where('urusan_id', $urusan->id)->whereIn('status_pemindahan', ['draf', 'menunggu_pengesahan'])->exists()) {
            throw ValidationException::withMessages(['status' => [__('tsu::tsu.pemindahan.tertunda')]]);
        }
        DB::transaction(function () use ($urusan, $user, $catatan) {
            UrusanKeputusan::query()->create(['urusan_id' => $urusan->id, 'konteks' => 'urusan', 'jenis_keputusan' => 'tutup', 'justifikasi' => $catatan, 'id_pelulus' => $user->id]);
            $urusan->forceFill(['status' => 'ditutup', 'tarikh_tutup' => now(), 'dikunci' => true, 'tarikh_siap' => $urusan->tarikh_siap ?? today()])->save();
        });
        event(new UrusanDitutup($urusan->id, $urusan->jenis_urusan, $urusan->lot()->pluck('lot_id')->all()));

        return $urusan->refresh();
    }

    public function cancel(UrusanTanah $urusan, User $user, string $sebab): UrusanTanah
    {
        $this->assertOpen($urusan);
        if ($this->workflow->activeInstanceFor(AliranTsu::SUBJEK_PERINGKAT, (string) $this->current($urusan)->id) !== null) {
            throw ValidationException::withMessages(['status' => [__('tsu::tsu.urusan.aliran_berjalan')]]);
        }
        DB::transaction(function () use ($urusan, $user, $sebab) {
            UrusanKeputusan::query()->create(['urusan_id' => $urusan->id, 'konteks' => 'urusan', 'jenis_keputusan' => 'batal', 'justifikasi' => $sebab, 'id_pelulus' => $user->id]);
            UrusanPeringkat::query()->where('urusan_id', $urusan->id)->whereIn('status', ['aktif', 'belum_mula'])->update(['status' => 'dilangkau', 'updated_at' => now()]);
            $urusan->forceFill(['status' => 'dibatalkan'])->save();
        });

        return $urusan->refresh();
    }

    /** @return list<string> labels of missing mandatory documents */
    public function missingDocuments(UrusanTanah $urusan, UrusanPeringkat $stage): array
    {
        $have = UrusanDokumen::query()->where('urusan_id', $urusan->id)->get()->filter(fn (UrusanDokumen $d) => $d->sah())->pluck('kategori_dokumen')->all();
        $missing = [];
        foreach ($stage->senarai_dokumen_wajib as $kategori) {
            if (! in_array($kategori, $have, true)) {
                $missing[] = Lalai::KATEGORI_DOKUMEN[$kategori][0] ?? $kategori;
            }
        }

        return $missing;
    }

    /** @throws ValidationException */
    public function checkPreconditions(UrusanTanah $urusan, UrusanPeringkat $stage): void
    {
        $fail = fn (string $key, array $r = []) => throw ValidationException::withMessages(['peringkat' => [__('tsu::tsu.syarat.'.$key, $r)]]);
        match ($stage->kesan) {
            'perlu_lantikan' => LantikanJurukur::query()->where('urusan_id', $urusan->id)->whereIn('status', ['aktif', 'siap'])->exists() ?: $fail('lantikan'),
            'perlu_hasil_ukur_diterima' => HasilUkur::query()->where('urusan_id', $urusan->id)->where('status_pengesahan', 'diterima')->exists() ?: $fail('hasil_ukur'),
            'perlu_rujukan_etanah' => $urusan->no_rujukan_etanah ? true : $fail('rujukan_etanah'),
            'perlu_rujukan_ptd' => $urusan->no_rujukan_ptd ? true : $fail('rujukan_ptd'),
            'perlu_kos_dibayar' => ! KosUrusan::query()->where('peringkat_id', $stage->id)->whereNotIn('status_bayaran', ['dibayar', 'dibatalkan'])->exists() ?: $fail('kos'),
            'perlu_penilaian' => $this->latestValuation($urusan) !== null ?: $fail('penilaian'),
            'perlu_pemindahan_selesai' => ! PemindahanRekod::query()->where('urusan_id', $urusan->id)->whereIn('status_pemindahan', ['draf', 'menunggu_pengesahan'])->exists() ?: $fail('pemindahan'),
            'cipta_lot_baharu' => $this->kesan->siapUntukLotBaharu($urusan) ?: $fail('lot_baharu'),
            default => true,
        };
    }

    public function current(UrusanTanah $urusan): UrusanPeringkat
    {
        return UrusanPeringkat::query()->where('urusan_id', $urusan->id)->where('no_urutan', $urusan->peringkat_semasa)->firstOrFail();
    }

    public function assertOpen(UrusanTanah $urusan): void
    {
        if ($urusan->dikunci) {
            throw new HttpException(409, __('tsu::tsu.urusan.dikunci'));
        }
        if (! $urusan->terbuka()) {
            throw new HttpException(409, __('tsu::tsu.urusan.tidak_terbuka'));
        }
    }

    public function link(UrusanTanah $urusan): string
    {
        return '/admin/tsu/urusan/'.$urusan->id;
    }

    /** Lots' ids linked to an urusan. @return list<string> */
    public function lotIds(UrusanTanah $urusan, ?string $peranan = null): array
    {
        return UrusanLot::query()->where('urusan_id', $urusan->id)->when($peranan, fn ($q, $p) => $q->where('peranan_lot', $p))->orderBy('id')->pluck('lot_id')->map(fn ($v) => (string) $v)->all();
    }

    public function auditView(UrusanTanah $urusan): void
    {
        if (in_array($urusan->klasifikasi, ['sulit', 'rahsia'], true)) {
            $this->audit->record('lihat', 'tsu', 'urusan_tanah', $urusan->id, meta: ['klasifikasi' => $urusan->klasifikasi]);
        }
    }
}
