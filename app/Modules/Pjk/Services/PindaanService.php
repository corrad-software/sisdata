<?php

namespace App\Modules\Pjk\Services;

use App\Models\User;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Pjk\Models\DepositCagaran;
use App\Modules\Pjk\Models\JadualCaj;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\PindaanKontrak;
use App\Modules\Pjk\Support\Aliran;
use App\Modules\Pjk\Support\Kod;
use App\Modules\Pjk\Support\Penomboran;
use App\Modules\Pjk\Support\Wang;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * SRS-PJK-05/07/10: renewal (with rate review), rate change, term amendment
 * and termination — each a request approved in WFE `pjk.pindaan_kontrak`
 * and applied as a new contract version (with DMS documents before/after)
 * only when WFE reports lulus.
 */
class PindaanService
{
    public function __construct(
        private WorkflowEngine $workflow,
        private KontrakService $kontrak,
        private JadualCajService $jadual,
        private SemakanKadar $semakan,
        private DocumentStore $documents,
    ) {}

    /** @param array<string, mixed> $input */
    public function request(Kontrak $kontrak, array $input, ?UploadedFile $document, User $user): PindaanKontrak
    {
        $kind = (string) ($input['jenis_pindaan'] ?? '');
        if (! isset(Kod::JENIS_PINDAAN[$kind])) {
            throw ValidationException::withMessages(['jenis_pindaan' => __('pjk::pjk.ralat.jenis_pindaan')]);
        }
        $allowed = $kind === 'pembaharuan' ? [...Kod::BERKUAT_KUASA, 'tamat_tempoh'] : Kod::BERKUAT_KUASA;
        if (! in_array($kontrak->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => __('pjk::pjk.ralat.status_tidak_benar_pindaan')]);
        }
        if (PindaanKontrak::query()->where('kontrak_id', $kontrak->id)->where('status', 'menunggu')->exists()) {
            throw ValidationException::withMessages(['pindaan' => __('pjk::pjk.ralat.pindaan_menunggu')]);
        }
        $justifikasi = Validator::make($input, ['justifikasi' => ['required', 'string', 'min:5', 'max:5000']], [], ['justifikasi' => 'justifikasi'])->validate()['justifikasi'];
        $data = $this->payload($kontrak, $kind, (array) ($input['data'] ?? []));

        $docId = null;
        if ($document !== null) {
            try {
                $docId = $this->documents->store($document, Kontrak::SUBJECT, $kontrak->id, 'perjanjian_sewa', $user, 'sulit', ['no_kontrak' => $kontrak->no_kontrak, 'jenis_pindaan' => $kind]);
            } catch (DocumentRejected $e) {
                throw ValidationException::withMessages(['dokumen' => $e->getMessage()]);
            }
        }
        $review = in_array($kind, ['pembaharuan', 'semakan_kadar'], true) ? $this->semakan->forContract($kontrak, $user, false) : null;
        $newRate = $data['kadar_baharu'] ?? $kontrak->kadar;
        $old = Wang::sen($kontrak->kadar);
        $pct = $old > 0 ? round((Wang::sen((string) $newRate) - $old) * 100 / $old, 2) : 0.0;

        return DB::transaction(function () use ($kontrak, $kind, $data, $justifikasi, $docId, $review, $user, $newRate, $pct) {
            $p = PindaanKontrak::query()->create([
                'kontrak_id' => $kontrak->id, 'jenis_pindaan' => $kind, 'data_cadangan' => $data, 'data_semasa' => $kontrak->snapshot(),
                'justifikasi' => $justifikasi, 'semakan_kadar' => $review, 'id_dokumen_selepas' => $docId, 'status' => 'menunggu', 'id_pemohon' => $user->id,
            ]);
            $instance = Aliran::start($this->workflow, Aliran::PINDAAN, $p->id, $user, [
                'jenis_pindaan' => $kind,
                'nilai_kontrak' => (float) $kontrak->nilai_kontrak,
                'kadar_baharu' => (float) $newRate,
                'peratus_perubahan_kadar' => $pct,
                'kategori_hartanah' => $kontrak->kategori_hartanah,
                'bahagian' => $kontrak->bahagian,
                'kod_daerah' => $kontrak->kod_daerah,
                'pautan' => '/admin/pjk/kontrak/'.$kontrak->id.'?tab=pindaan',
            ], __('pjk::pjk.aliran.tajuk_pindaan', ['jenis' => Kod::JENIS_PINDAAN[$kind], 'no' => $kontrak->no_kontrak]));
            $p->forceFill(['id_instans_aliran' => $instance])->save();

            return $p;
        });
    }

    /** WFE listener for `pjk.pindaan_kontrak`. */
    public function onWorkflowCompleted(WorkflowCompleted $event): void
    {
        if ($event->flowCode !== Aliran::PINDAAN) {
            return;
        }
        $p = PindaanKontrak::query()->find($event->subjectId);
        if ($p === null || $p->status !== 'menunggu') {
            return;
        }
        $kontrak = Kontrak::query()->withoutGlobalScope('access')->findOrFail($p->kontrak_id);

        DB::transaction(function () use ($p, $kontrak, $event) {
            $p->forceFill([
                'status' => $event->outcome === 'lulus' ? 'lulus' : ($event->outcome === 'ditarik_balik' ? 'ditarik_balik' : 'tolak'),
                'id_pelulus' => $event->decidedBy, 'tarikh_keputusan' => now(), 'ulasan' => $event->remarks,
            ])->save();
            if ($event->outcome === 'lulus') {
                $this->apply($p, $kontrak, $event->decidedBy);
            }
        });

        $pemohon = $p->id_pemohon ? User::query()->find($p->id_pemohon) : null;
        $this->kontrak->notify('pjk.keputusan_kelulusan', $pemohon ? [$pemohon] : [], [
            'perkara' => Kod::JENIS_PINDAAN[$p->jenis_pindaan], 'no_kontrak' => (string) $kontrak->no_kontrak,
            'keputusan' => __('pjk::pjk.keputusan.'.$event->outcome),
        ], '/admin/pjk/kontrak/'.$kontrak->id.'?tab=pindaan');
    }

    public function apply(PindaanKontrak $p, Kontrak $kontrak, ?int $userId): void
    {
        $d = $p->data_cadangan;
        $before = $kontrak->snapshot();
        $docBefore = $kontrak->id_dokumen_perjanjian;

        switch ($p->jenis_pindaan) {
            case 'pembaharuan':
                $baru = $this->renew($kontrak, $d, $userId, $p);
                $p->forceFill(['id_kontrak_baharu' => $baru->id])->save();
                $this->kontrak->version($kontrak, 'pembaharuan', $userId, $p->justifikasi, $before, $kontrak->snapshot(), $docBefore, $p->id_dokumen_selepas, $p->id);

                return;

            case 'semakan_kadar':
                $from = Carbon::parse($d['tarikh_berkuat_kuasa']);
                $kontrak->forceFill([
                    'kadar' => $d['kadar_baharu'],
                    'nilai_kontrak' => $this->kontrak->value($d['kadar_baharu'], (string) $kontrak->tarikh_mula?->format('Y-m-d'), (string) $kontrak->tarikh_tamat?->format('Y-m-d')),
                ])->save();
                $months = Kod::BULAN_KITARAN[$kontrak->kitaran_caj] ?? 1;
                JadualCaj::query()->where('kontrak_id', $kontrak->id)->where('kod_caj', 'sewa')->where('dikeluarkan', false)
                    ->where('status_caj', 'dijadualkan')->whereDate('tarikh_caj', '>=', $from)->get()
                    ->each(function (JadualCaj $c) use ($d, $months) {
                        if (Wang::sen($c->amaun_dibayar) === 0) {
                            $c->forceFill(['amaun' => Wang::dec(Wang::sen($d['kadar_baharu']) * $months)])->save();
                        }
                    });
                break;

            case 'pindaan_terma':
                $kontrak->forceFill(array_filter([
                    'terma' => $d['terma'] ?? null,
                    'hari_tempoh_bayar' => $d['hari_tempoh_bayar'] ?? null,
                ], fn ($v) => $v !== null))->save();
                break;

            case 'penamatan':
                $kontrak->forceFill(['tarikh_penamatan' => $d['tarikh_penamatan'], 'sebab_penamatan' => $d['sebab_penamatan']])->save();
                $this->jadual->cancelFrom($kontrak, Carbon::parse($d['tarikh_penamatan']));
                $this->kontrak->transition($kontrak, 'ditamatkan', $userId, $d['sebab_penamatan']);
                $this->kontrak->releasePremis($kontrak, $userId, __('pjk::pjk.premis.kosong_tamat', ['no' => $kontrak->no_kontrak]));
                $this->kontrak->syncESewa($kontrak);
                break;
        }

        if ($p->id_dokumen_selepas !== null) {
            $kontrak->forceFill(['id_dokumen_perjanjian' => $p->id_dokumen_selepas])->save();
        }
        $this->kontrak->version($kontrak, $p->jenis_pindaan, $userId, $p->justifikasi, $before, $kontrak->refresh()->snapshot(), $docBefore, $p->id_dokumen_selepas, $p->id);
    }

    /** @param array<string, mixed> $d */
    private function renew(Kontrak $old, array $d, ?int $userId, PindaanKontrak $p): Kontrak
    {
        $from = Carbon::parse($old->tarikh_tamat)->addDay()->format('Y-m-d');
        $rate = $d['kadar_baharu'] ?? $old->kadar;
        $new = $old->replicate(['no_kontrak', 'id_instans_aliran', 'id_versi_semasa', 'no_versi', 'tarikh_penamatan', 'sebab_penamatan', 'sintetik', 'permohonan_id']);
        $new->fill([
            'no_kontrak' => Penomboran::next('kontrak'),
            'tarikh_mula' => $from,
            'tarikh_tamat' => $d['tarikh_tamat_baharu'],
            'kadar' => $rate,
            'terma' => $d['terma'] ?? $old->terma,
            'nilai_kontrak' => $this->kontrak->value((string) $rate, $from, $d['tarikh_tamat_baharu']),
            'id_kontrak_asal' => $old->id,
            'status' => 'draf',
            'no_versi' => 0,
            'id_dokumen_perjanjian' => $p->id_dokumen_selepas ?? $old->id_dokumen_perjanjian,
            'id_pendaftar' => $p->id_pemohon,
        ]);
        $new->save();
        // The deposit carries over to the renewed agreement (no new deposit charge).
        DepositCagaran::query()->where('kontrak_id', $old->id)->whereIn('status', ['belum_diterima', 'dipegang'])->update(['kontrak_id' => $new->id]);
        $this->kontrak->transition($old, 'diperbaharui', $userId, __('pjk::pjk.kontrak.diperbaharui_oleh', ['no' => $new->no_kontrak]));
        $this->kontrak->activate($new, $userId, __('pjk::pjk.kontrak.pembaharuan_daripada', ['no' => $old->no_kontrak]));

        return $new;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(Kontrak $kontrak, string $kind, array $data): array
    {
        $end = $kontrak->tarikh_tamat?->format('Y-m-d');
        $rules = match ($kind) {
            'pembaharuan' => [
                'tarikh_tamat_baharu' => ['required', 'date_format:Y-m-d', 'after:'.$end],
                'kadar_baharu' => ['nullable', 'decimal:0,2', 'gt:0', 'max:99999999'],
                'terma' => ['nullable', 'string', 'max:10000'],
            ],
            'semakan_kadar' => [
                'kadar_baharu' => ['required', 'decimal:0,2', 'gt:0', 'max:99999999'],
                'tarikh_berkuat_kuasa' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$kontrak->tarikh_mula?->format('Y-m-d'), 'before_or_equal:'.$end],
            ],
            'pindaan_terma' => [
                'terma' => ['required_without:hari_tempoh_bayar', 'nullable', 'string', 'max:10000'],
                'hari_tempoh_bayar' => ['nullable', 'integer', 'min:0', 'max:90'],
            ],
            default => [
                'tarikh_penamatan' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$kontrak->tarikh_mula?->format('Y-m-d'), 'before_or_equal:'.$end],
                'sebab_penamatan' => ['required', 'string', 'min:5', 'max:2000'],
            ],
        };
        $valid = Validator::make($data, $rules, [], [
            'tarikh_tamat_baharu' => 'tarikh tamat baharu', 'kadar_baharu' => 'kadar baharu', 'tarikh_berkuat_kuasa' => 'tarikh berkuat kuasa',
            'tarikh_penamatan' => 'tarikh penamatan', 'sebab_penamatan' => 'sebab penamatan', 'terma' => 'terma',
        ])->validate();
        if (isset($valid['kadar_baharu'])) {
            $valid['kadar_baharu'] = Wang::norm((string) $valid['kadar_baharu']);
        }

        return $valid;
    }
}
