<?php

namespace App\Modules\Per\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Aud\Contracts\AuditScope;
use App\Modules\Hkm\Contracts\TitleRegistry;
use App\Modules\Per\Contracts\WakafPortal;
use App\Modules\Per\Events\AcquisitionCompleted;
use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Per\Models\PeringkatPemerolehan;
use App\Modules\Per\Models\PihakBerkaitan;
use App\Modules\Per\Models\Saluran;
use App\Modules\Per\Support\ChannelCatalog;
use App\Modules\Per\Support\Sekatan;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The case lifecycle (SRS-PER-01..21): register, edit, advance through the
 * channel's stages with its guards, hand over to WFE for approval, close by
 * creating the title through MOD-HKM.
 */
class CaseService
{
    public function __construct(
        private ChannelFields $fields,
        private Checklist $checklist,
        private LotLinker $lots,
        private Payments5a $payments,
        private StageMover $mover,
        private ApprovalService $approvals,
        private AccessControl $access,
        private AuditLogger $audit,
    ) {}

    // ------------------------------------------------------------------ create / update

    /**
     * @param  array<string, mixed>  $data  validated request data
     */
    public function create(array $data, ?User $user, string $source = 'dalaman'): Pemerolehan
    {
        $publicSubmission = $source === 'portal_wakaf';
        if ($user === null && ! $publicSubmission && $source !== 'sintetik') {
            throw new HttpException(403, __('per::per.luar_skop'));
        }

        $channel = Saluran::query()->where('kod_saluran', $data['kod_saluran'])->where('status_aktif', true)->first();
        if ($channel === null) {
            throw ValidationException::withMessages(['kod_saluran' => __('per::per.saluran_tidak_aktif')]);
        }

        [$public, $personal] = $this->fields->validate($channel->kod_saluran, $data['butiran'] ?? []);
        $place = $publicSubmission && empty($data['no_lot']) && empty($data['lot_id'])
            ? ['lot_id' => null, 'no_lot_dijangka' => null, 'kod_mukim' => null, 'kod_daerah' => null]
            : $this->lots->resolve($data['lot_id'] ?? null, $data['kod_mukim'] ?? null, $data['no_lot'] ?? null, $publicSubmission ? null : $user);
        if ($user !== null && ! $publicSubmission) {
            $this->assertInScope($user, (string) $place['kod_daerah'], $channel->bahagian_bertanggungjawab);
        }

        return DB::transaction(function () use ($channel, $data, $user, $source, $public, $personal, $place, $publicSubmission) {
            $seq = (int) DB::selectOne('SELECT nextval(\'pemerolehan_no_kes_seq\') AS n')->n;

            $case = Pemerolehan::query()->create([
                'no_kes' => sprintf('PER/%s/%05d', now()->format('Y'), $seq),
                'id_saluran' => $channel->id,
                'tajuk' => $data['tajuk'],
                'lot_id' => $place['lot_id'],
                'no_lot_dijangka' => $place['no_lot_dijangka'],
                'kod_mukim' => $place['kod_mukim'],
                'kod_daerah' => $place['kod_daerah'],
                'tarikh_mula_kes' => $data['tarikh_mula_kes'] ?? now()->toDateString(),
                'status_pemerolehan' => $publicSubmission ? Pemerolehan::MENUNGGU_SEMAKAN : Pemerolehan::DALAM_PROSES,
                'kod_bahagian' => $channel->bahagian_bertanggungjawab,
                'id_pegawai_bertanggungjawab' => $data['id_pegawai_bertanggungjawab'] ?? ($publicSubmission ? null : $user?->id),
                'id_pendaftar' => $publicSubmission ? null : $user?->id,
                'nilai_pasaran' => $data['nilai_pasaran'] ?? null,
                'nilai_pemerolehan' => $data['nilai_pemerolehan'] ?? null,
                'sumber_dana' => $data['sumber_dana'] ?? null,
                'rujukan_penilaian_jpph' => $data['rujukan_penilaian_jpph'] ?? null,
                'butiran_saluran' => $public,
                'butiran_peribadi' => $personal ?: null,
                'tempoh_sasaran_hari' => $channel->tempoh_sasaran_hari,
                'sumber_rekod' => $source,
                'rujukan_luaran' => $data['rujukan_luaran'] ?? null,
                'catatan' => $data['catatan'] ?? null,
                'klasifikasi' => 'sulit',
            ]);

            $first = true;
            foreach ($channel->peringkat()->where('aktif', true)->get() as $stage) {
                $open = $first && ! $publicSubmission;
                $case->peringkat()->create([
                    'kod_peringkat' => $stage->kod_peringkat,
                    'nama_peringkat' => $stage->nama_peringkat,
                    'jenis' => $stage->jenis,
                    'susunan' => $stage->susunan,
                    'tempoh_sasaran_hari' => $stage->tempoh_sasaran_hari,
                    'status_peringkat' => $open ? PeringkatPemerolehan::SEMASA : PeringkatPemerolehan::BELUM,
                    'tarikh_masuk' => $open ? $case->tarikh_mula_kes->copy()->setTimeFrom(now()) : null,
                    'id_pegawai_bertanggungjawab' => $open ? $case->id_pegawai_bertanggungjawab : null,
                ]);
                if ($open) {
                    $case->forceFill(['peringkat_semasa' => $stage->kod_peringkat])->save();
                }
                $first = false;
            }

            foreach ($data['pihak'] ?? [] as $party) {
                $this->addParty($case, $party);
            }

            $this->checklist->refresh($case);

            return $case->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Pemerolehan $case, array $data, User $user): Pemerolehan
    {
        $this->assertOpen($case);

        $attributes = array_intersect_key($data, array_flip([
            'tajuk', 'catatan', 'nilai_pasaran', 'nilai_pemerolehan', 'sumber_dana', 'rujukan_penilaian_jpph', 'id_pegawai_bertanggungjawab', 'tarikh_pemerolehan',
        ]));

        if (array_key_exists('butiran', $data)) {
            [$public, $personal] = $this->fields->validate($case->kodSaluran(), (array) $data['butiran']);
            $existingPersonal = $case->butiran_peribadi ?? [];
            // Personal fields can be filled in, but only an officer cleared for Sulit data may change them.
            if (! $this->access->allows($user, 'per.sulit.lihat')) {
                foreach ($personal as $k => $v) {
                    if (($existingPersonal[$k] ?? null) !== null) {
                        unset($personal[$k]);
                    }
                }
            }
            $attributes['butiran_saluran'] = array_merge($case->butiran_saluran ?? [], $public);
            $attributes['butiran_peribadi'] = array_merge($existingPersonal, $personal) ?: null;
        }

        AuditScope::withReason($data['sebab'] ?? null ?: __('per::per.sebab_kemas_kini'), fn () => $case->forceFill($attributes)->save());

        return $case->refresh();
    }

    /** @param array<string, mixed> $party */
    public function addParty(Pemerolehan $case, array $party): PihakBerkaitan
    {
        $roles = ChannelCatalog::partyRoles()[$case->kodSaluran()] ?? [];
        if (! in_array($party['peranan_pihak'], $roles, true)) {
            throw ValidationException::withMessages(['peranan_pihak' => __('per::per.peranan_tidak_sah')]);
        }

        return $case->pihak()->create([
            'peranan_pihak' => $party['peranan_pihak'],
            'nama' => $party['nama'],
            'hubungan' => $party['hubungan'] ?? null,
            'jenis_dokumen_pengenalan' => $party['jenis_dokumen_pengenalan'] ?? null,
            'no_dokumen_pengenalan' => $party['no_dokumen_pengenalan'] ?? null,
            'bahagian_pusaka' => $party['bahagian_pusaka'] ?? null,
            'status_pengesahan' => $party['status_pengesahan'] ?? 'belum_disahkan',
            'sumber_pengesahan' => $party['sumber_pengesahan'] ?? 'manual',
            'catatan' => $party['catatan'] ?? null,
        ]);
    }

    // ------------------------------------------------------------------ progress

    /**
     * Move the case to its next stage (SRS-PER-17/18). Entering an approval
     * stage hands the case to WFE (SRS-PER-29); leaving it happens only on
     * the WFE outcome.
     */
    public function advance(Pemerolehan $case, User $user, ?string $note = null, ?int $nextOfficer = null): Pemerolehan
    {
        return DB::transaction(function () use ($case, $user, $note, $nextOfficer) {
            $case = Pemerolehan::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $case->load(['peringkat', 'saluran']);

            if ($case->status_pemerolehan === Pemerolehan::MENUNGGU_SEMAKAN) {
                throw new HttpException(409, __('per::per.perlu_semakan_awam'));
            }
            $this->assertOpen($case);

            $current = $case->peringkatSemasa();
            if ($current === null) {
                throw new HttpException(409, __('per::per.tiada_peringkat_semasa'));
            }

            if ($current->jenis === 'kelulusan') {
                if ($case->status_pemerolehan === Pemerolehan::MENUNGGU_KELULUSAN) {
                    throw new HttpException(409, __('per::per.sedang_menunggu_kelulusan'));
                }
                // Re-submission after rejection / withdrawal.
                $this->assertCanEnter($case, $current);
                $this->approvals->start($case, $user);

                return $case->refresh();
            }

            if ($current->jenis === 'simpanan') {
                throw new HttpException(409, __('per::per.guna_tutup_kes'));
            }

            $next = $case->peringkat->first(fn (PeringkatPemerolehan $s) => $s->susunan > $current->susunan);
            $this->assertCanLeave($case, $current);
            if ($next !== null) {
                $this->assertCanEnter($case, $next);
            }

            AuditScope::withReason($note ?: __('per::per.sebab_maju', ['peringkat' => $next->nama_peringkat ?? '-']), function () use ($case, $current, $next, $nextOfficer, $note) {
                $this->mover->move($case, $current, $next, $nextOfficer, $note);
            });

            if ($next !== null && $next->jenis === 'kelulusan') {
                $case->load('saluran', 'pendaftar');
                $this->approvals->start($case, $user);
            }

            return $case->refresh();
        });
    }

    public function assignOfficer(Pemerolehan $case, int $officerId): void
    {
        $this->assertOpen($case);
        $current = $case->load('peringkat')->peringkatSemasa();
        if ($current === null) {
            throw new HttpException(409, __('per::per.tiada_peringkat_semasa'));
        }
        $current->forceFill(['id_pegawai_bertanggungjawab' => $officerId, 'status_eskalasi' => $current->status_eskalasi])->save();
    }

    /** What still blocks the next step — for the UI (same checks as advance()). @return list<string> */
    public function blockers(Pemerolehan $case): array
    {
        $case->loadMissing(['peringkat', 'saluran']);
        $current = $case->peringkatSemasa();
        if ($current === null || in_array($case->status_pemerolehan, [Pemerolehan::SELESAI, Pemerolehan::DIBATALKAN, Pemerolehan::MENUNGGU_KELULUSAN], true)) {
            return [];
        }

        try {
            if ($current->jenis === 'simpanan') {
                $this->assertCanClose($case);
            } elseif ($current->jenis === 'kelulusan') {
                $this->assertCanEnter($case, $current);
            } else {
                $this->assertCanLeave($case, $current);
                $next = $case->peringkat->first(fn (PeringkatPemerolehan $s) => $s->susunan > $current->susunan);
                if ($next !== null) {
                    $this->assertCanEnter($case, $next);
                }
            }
        } catch (ValidationException $e) {
            return array_merge(...array_values($e->errors()));
        } catch (HttpException $e) {
            return [$e->getMessage()];
        }

        return [];
    }

    private function assertCanLeave(Pemerolehan $case, PeringkatPemerolehan $stage): void
    {
        $missing = $this->fields->missingFor($case->kodSaluran(), $stage->kod_peringkat, $case->butiran());
        if ($missing !== []) {
            throw new Sekatan(__('per::per.medan_belum_lengkap', ['peringkat' => $stage->nama_peringkat]), $missing);
        }

        // SRS-PER-03: only an approved MMKN decision lets a 5A case continue.
        if ($case->kodSaluran() === 'pemberimilikan_5a' && $stage->kod_peringkat === 'keputusan_mmkn'
            && ! in_array($case->butiran_saluran['jenis_keputusan_mmkn'] ?? null, ['lulus', 'lulus_bersyarat'], true)) {
            throw new Sekatan(__('per::per.mmkn_bukan_lulus'));
        }
    }

    private function assertCanEnter(Pemerolehan $case, PeringkatPemerolehan $stage): void
    {
        // SRS-PER-16: mandatory documents block progress (above all into approval).
        $missing = $this->checklist->missingBefore($case, $stage->kod_peringkat);
        if ($missing !== []) {
            throw new Sekatan(__($stage->jenis === 'kelulusan' ? 'per::per.dokumen_kurang_kelulusan' : 'per::per.dokumen_kurang', ['peringkat' => $stage->nama_peringkat]), $missing);
        }

        // SRS-PER-05: no title receipt until the 5A payment is confirmed in full.
        if ($case->kodSaluran() === 'pemberimilikan_5a' && $stage->kod_peringkat === 'penerimaan_hak_milik') {
            $summary = $this->payments->summary($case);
            if (! $summary['lengkap']) {
                throw new Sekatan(__('per::per.bayaran_belum_lengkap', ['baki' => $summary['baki_tertunggak']]));
            }
        }
    }

    // ------------------------------------------------------------------ public submissions, cancel

    public function acceptPublic(Pemerolehan $case, User $user, bool $accept, ?string $note = null): Pemerolehan
    {
        if ($case->status_pemerolehan !== Pemerolehan::MENUNGGU_SEMAKAN) {
            throw new HttpException(409, __('per::per.bukan_permohonan_awam'));
        }

        DB::transaction(function () use ($case, $user, $accept, $note) {
            if ($accept) {
                $first = $case->peringkat()->orderBy('susunan')->first();
                $case->forceFill([
                    'status_pemerolehan' => Pemerolehan::DALAM_PROSES,
                    'id_pendaftar' => $user->id,
                    'id_pegawai_bertanggungjawab' => $case->id_pegawai_bertanggungjawab ?? $user->id,
                ])->save();
                $this->mover->move($case, null, $first, $user->id);
            } else {
                AuditScope::withReason($note ?: __('per::per.sebab_tolak_awam'), fn () => $case->forceFill([
                    'status_pemerolehan' => Pemerolehan::DIBATALKAN, 'catatan' => trim(($case->catatan ?? '')."\n".$note),
                ])->save());
            }
            $this->audit->record($accept ? 'sah' : 'tolak', 'per', 'pemerolehan', $case->id, reason: $note, meta: [
                'no_kes' => $case->no_kes, 'sumber' => 'portal_wakaf', 'klasifikasi' => 'sulit',
            ]);
        });

        if ($case->rujukan_luaran !== null) {
            app(WakafPortal::class)->acknowledge($case->rujukan_luaran, $accept ? 'diterima' : 'ditolak', $case->no_kes);
        }

        return $case->refresh();
    }

    public function cancel(Pemerolehan $case, User $user, string $reason): Pemerolehan
    {
        $this->assertOpen($case);
        AuditScope::withReason($reason, fn () => $case->forceFill(['status_pemerolehan' => Pemerolehan::DIBATALKAN])->save());
        $this->audit->record('batal', 'per', 'pemerolehan', $case->id, reason: $reason, meta: ['no_kes' => $case->no_kes, 'klasifikasi' => 'sulit']);

        return $case->refresh();
    }

    // ------------------------------------------------------------------ close (SRS-PER-21/22)

    /**
     * @param  array{no_hak_milik: string, jenis_hak_milik: string, tarikh_pendaftaran?: string|null, tarikh_tamat_tempoh?: string|null, pejabat_pendaftaran?: string|null, keluasan?: string|null, kategori_tanah?: string|null, syarat_nyata?: string|null}  $title
     * @param  array{kod_peti_besi: string, no_fail?: string|null, lokasi_rak?: string|null, no_kotak_pengikat?: string|null, jenis_dokumen_asal?: string|null}  $vault
     */
    public function close(Pemerolehan $case, array $title, array $vault, User $user): Pemerolehan
    {
        $case->load(['peringkat', 'saluran']);
        $this->assertCanClose($case);

        $titleData = array_filter($title + ['pemilik_berdaftar' => 'Majlis Agama Islam Selangor'], fn ($v) => $v !== null && $v !== '');
        $vaultData = array_filter($vault, fn ($v) => $v !== null && $v !== '');

        $case = DB::transaction(function () use ($case, $titleData, $vaultData, $user) {
            try {
                /** @var array{no_hak_milik: string, jenis_hak_milik: string} $titleData */
                /** @var array{kod_peti_besi: string} $vaultData */
                $titleId = app(TitleRegistry::class)->registerFromAcquisition((string) $case->lot_id, $titleData, $user, $vaultData, 'pemerolehan:'.$case->id);
            } catch (RuntimeException $e) {
                throw new HttpException(503, __('per::per.hkm_tiada'), $e);
            }

            $current = $case->peringkatSemasa();
            $this->mover->move($case, $current, null, null, __('per::per.catatan_tutup'));

            $today = now()->startOfDay();
            $total = (int) $case->tarikh_mula_kes->copy()->startOfDay()->diffInDays($today);
            $case->forceFill([
                'status_pemerolehan' => Pemerolehan::SELESAI,
                'id_hak_milik_dijana' => $titleId,
                'butiran_hak_milik' => ['hak_milik' => $titleData, 'peti_besi' => $vaultData],
                'tarikh_selesai' => $today->toDateString(),
                'tarikh_pemerolehan' => $case->tarikh_pemerolehan ?? $today->toDateString(),
                'tempoh_keseluruhan_hari' => $total,
            ])->save();

            $this->audit->record('tutup', 'per', 'pemerolehan', $case->id, meta: [
                'no_kes' => $case->no_kes, 'id_hak_milik' => $titleId, 'kod_peti_besi' => $vaultData['kod_peti_besi'],
                'tempoh_keseluruhan_hari' => $total, 'tempoh_sasaran_hari' => $case->tempoh_sasaran_hari, 'klasifikasi' => 'sulit',
            ]);

            $event = new AcquisitionCompleted($case->id, $case->kodSaluran(), $case->lot_id, $titleId);
            DB::afterCommit(fn () => event($event));

            return $case;
        });

        $this->checklist->refresh($case);
        if ($case->sumber_rekod === 'portal_wakaf' && $case->rujukan_luaran !== null) {
            app(WakafPortal::class)->acknowledge($case->rujukan_luaran, 'selesai', $case->no_kes);
        }

        return $case->refresh();
    }

    private function assertCanClose(Pemerolehan $case): void
    {
        $current = $case->peringkatSemasa();
        if ($current === null || $current->jenis !== 'simpanan') {
            throw new HttpException(409, __('per::per.belum_peringkat_akhir'));
        }
        if ($case->status_pemerolehan !== Pemerolehan::DILULUSKAN) {
            throw new HttpException(409, __('per::per.belum_diluluskan'));
        }
        if ($case->lot_id === null) {
            throw new Sekatan(__('per::per.lot_belum_berdaftar', ['no_lot' => $case->no_lot_dijangka ?? '-']));
        }
        $missing = $this->checklist->missingBefore($case, ChannelCatalog::TUTUP);
        if ($missing !== []) {
            throw new Sekatan(__('per::per.dokumen_kurang_tutup'), $missing);
        }
    }

    // ------------------------------------------------------------------ helpers

    private function assertOpen(Pemerolehan $case): void
    {
        if (in_array($case->status_pemerolehan, [Pemerolehan::SELESAI, Pemerolehan::DIBATALKAN], true)) {
            throw new HttpException(409, __('per::per.kes_ditutup'));
        }
    }

    private function assertInScope(User $user, string $kodDaerah, string $bahagian): void
    {
        foreach (['daerah' => $kodDaerah, 'bahagian' => $bahagian] as $type => $value) {
            $allowed = $this->access->scope($user, $type);
            if ($allowed !== null && ! in_array($value, $allowed, true)) {
                throw new HttpException(403, __('per::per.luar_skop'));
            }
        }
    }
}
