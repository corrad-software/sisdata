<?php

namespace App\Modules\Pjk\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Pjk\Contracts\ESewa;
use App\Modules\Pjk\Models\DepositCagaran;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\Penyewa;
use App\Modules\Pjk\Models\PermohonanSewaan;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Pjk\Models\SejarahStatusKontrak;
use App\Modules\Pjk\Models\VersiKontrak;
use App\Modules\Pjk\Support\Aliran;
use App\Modules\Pjk\Support\Kod;
use App\Modules\Pjk\Support\Luaran;
use App\Modules\Pjk\Support\Penomboran;
use App\Modules\Pjk\Support\Wang;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * SRS-PJK-01/02/03/05: contract register and lifecycle
 * draf → dalam_kelulusan (WFE) → aktif → hampir_tamat → tamat_tempoh, plus
 * diperbaharui / ditamatkan via PindaanService. Activation (only on WFE
 * `lulus`) marks the premise "disewa", generates the charge schedule, the
 * deposit and version 1, and issues the first charges.
 */
class KontrakService
{
    public function __construct(
        private WorkflowEngine $workflow,
        private JadualCajService $jadual,
        private PremisService $premisService,
        private Parameters $parameters,
        private Notifier $notifier,
    ) {}

    /** @param array<string, mixed> $input */
    public function create(array $input, User $user): Kontrak
    {
        $data = $this->validate($input);
        $premis = PremisAset::query()->find($data['premis_id']);
        if ($premis === null) {
            throw ValidationException::withMessages(['premis_id' => __('pjk::pjk.ralat.premis_tiada')]);
        }
        $penyewa = Penyewa::query()->find($data['penyewa_id']);
        if ($penyewa === null) {
            throw ValidationException::withMessages(['penyewa_id' => __('pjk::pjk.ralat.penyewa_tiada')]);
        }
        $this->assertNoOverlap($premis, (string) $data['tarikh_mula'], (string) $data['tarikh_tamat'], null);

        return DB::transaction(function () use ($data, $premis, $penyewa, $user) {
            $kontrak = Kontrak::query()->create([
                'no_kontrak' => Penomboran::next('kontrak'),
                'lot_id' => $premis->lot_id,
                'premis_id' => $premis->id,
                'penyewa_id' => $penyewa->id,
                'penyewa' => $penyewa->nama,
                'jenis_perjanjian' => $data['jenis_perjanjian'] ?? 'sewaan',
                'kategori_hartanah' => $premis->kategori_hartanah,
                'tarikh_mula' => $data['tarikh_mula'],
                'tarikh_tamat' => $data['tarikh_tamat'],
                'kadar' => Wang::norm((string) $data['kadar']),
                'deposit' => Wang::norm((string) ($data['deposit'] ?? '0')),
                'terma' => $data['terma'] ?? null,
                'kitaran_caj' => $data['kitaran_caj'] ?? 'bulanan',
                'hari_tempoh_bayar' => (int) ($data['hari_tempoh_bayar'] ?? $this->parameters->get('pjk.hari_tempoh_bayar', 14)),
                'nilai_kontrak' => $this->value((string) $data['kadar'], (string) $data['tarikh_mula'], (string) $data['tarikh_tamat']),
                'status' => 'draf',
                'klasifikasi' => 'sulit',
                'kod_daerah' => $premis->kod_daerah,
                'kod_mukim' => $premis->kod_mukim,
                'bahagian' => $premis->bahagian,
                'tarikh_status' => Carbon::today()->format('Y-m-d'),
                'sumber' => $data['sumber'] ?? 'kaunter',
                'permohonan_id' => $data['permohonan_id'] ?? null,
                'id_pendaftar' => $user->id,
            ]);
            $this->history($kontrak, null, 'draf', $user->id, __('pjk::pjk.kontrak.didaftarkan'));

            return $kontrak;
        });
    }

    /** @param array<string, mixed> $input */
    public function update(Kontrak $kontrak, array $input, User $user): Kontrak
    {
        if ($kontrak->status !== 'draf') {
            throw ValidationException::withMessages(['status' => __('pjk::pjk.ralat.bukan_draf')]);
        }
        $data = $this->validate($input + [
            'premis_id' => $kontrak->premis_id, 'penyewa_id' => $kontrak->penyewa_id,
            'tarikh_mula' => $kontrak->tarikh_mula?->format('Y-m-d'), 'tarikh_tamat' => $kontrak->tarikh_tamat?->format('Y-m-d'), 'kadar' => $kontrak->kadar,
        ]);
        $premis = PremisAset::query()->findOrFail($data['premis_id']);
        $penyewa = Penyewa::query()->findOrFail($data['penyewa_id']);
        $this->assertNoOverlap($premis, (string) $data['tarikh_mula'], (string) $data['tarikh_tamat'], $kontrak->id);

        $kontrak->fill([
            'premis_id' => $premis->id, 'lot_id' => $premis->lot_id, 'kod_daerah' => $premis->kod_daerah, 'kod_mukim' => $premis->kod_mukim,
            'bahagian' => $premis->bahagian, 'kategori_hartanah' => $premis->kategori_hartanah,
            'penyewa_id' => $penyewa->id, 'penyewa' => $penyewa->nama,
            'jenis_perjanjian' => $data['jenis_perjanjian'] ?? $kontrak->jenis_perjanjian,
            'tarikh_mula' => $data['tarikh_mula'], 'tarikh_tamat' => $data['tarikh_tamat'],
            'kadar' => Wang::norm((string) $data['kadar']),
            'deposit' => array_key_exists('deposit', $data) ? Wang::norm((string) $data['deposit']) : $kontrak->deposit,
            'terma' => array_key_exists('terma', $data) ? $data['terma'] : $kontrak->terma,
            'kitaran_caj' => $data['kitaran_caj'] ?? $kontrak->kitaran_caj,
            'hari_tempoh_bayar' => (int) ($data['hari_tempoh_bayar'] ?? $kontrak->hari_tempoh_bayar),
            'nilai_kontrak' => $this->value((string) $data['kadar'], (string) $data['tarikh_mula'], (string) $data['tarikh_tamat']),
        ])->save();

        return $kontrak;
    }

    /** SRS-PJK-05: draf → dalam_kelulusan, layered approval in WFE by value and category. */
    public function submit(Kontrak $kontrak, User $user): Kontrak
    {
        if ($kontrak->status !== 'draf') {
            throw ValidationException::withMessages(['status' => __('pjk::pjk.ralat.bukan_draf')]);
        }

        return DB::transaction(function () use ($kontrak, $user) {
            $instance = Aliran::start($this->workflow, Aliran::KONTRAK, $kontrak->id, $user, [
                'nilai_kontrak' => (float) $kontrak->nilai_kontrak,
                'kadar' => (float) $kontrak->kadar,
                'kategori_hartanah' => $kontrak->kategori_hartanah,
                'jenis_perjanjian' => $kontrak->jenis_perjanjian,
                'bahagian' => $kontrak->bahagian,
                'kod_daerah' => $kontrak->kod_daerah,
                'sumber' => $kontrak->sumber,
                'pautan' => '/admin/pjk/kontrak/'.$kontrak->id,
            ], __('pjk::pjk.aliran.tajuk_kontrak', ['no' => $kontrak->no_kontrak]));
            $kontrak->forceFill(['id_instans_aliran' => $instance])->save();
            $this->transition($kontrak, 'dalam_kelulusan', $user->id, __('pjk::pjk.kontrak.dihantar'));
            if ($kontrak->permohonan_id !== null) {
                PermohonanSewaan::query()->withoutGlobalScope('access')->whereKey($kontrak->permohonan_id)->update(['status' => 'kontrak_disediakan']);
            }

            return $kontrak;
        });
    }

    /** WFE listener (SRS-WFE-20) for `pjk.kelulusan_kontrak`. */
    public function onWorkflowCompleted(WorkflowCompleted $event): void
    {
        if ($event->flowCode !== Aliran::KONTRAK) {
            return;
        }
        $kontrak = Kontrak::query()->withoutGlobalScope('access')->find($event->subjectId);
        if ($kontrak === null || $kontrak->status !== 'dalam_kelulusan') {
            return;
        }

        DB::transaction(function () use ($kontrak, $event) {
            if ($event->outcome === 'lulus') {
                $this->activate($kontrak, $event->decidedBy, $event->remarks);
            } else {
                $this->transition($kontrak, 'draf', $event->decidedBy, __('pjk::pjk.kontrak.dikembalikan', ['keputusan' => $event->outcome]).($event->remarks ? ' — '.$event->remarks : ''));
                if ($kontrak->permohonan_id !== null && $event->outcome === 'tolak') {
                    PermohonanSewaan::query()->withoutGlobalScope('access')->whereKey($kontrak->permohonan_id)->update(['status' => 'tolak', 'catatan' => $event->remarks]);
                }
            }
        });

        $pendaftar = $kontrak->id_pendaftar ? User::query()->find($kontrak->id_pendaftar) : null;
        $this->notify('pjk.keputusan_kelulusan', $pendaftar ? [$pendaftar] : [], [
            'perkara' => __('pjk::pjk.aliran.perkara_kontrak'), 'no_kontrak' => (string) $kontrak->no_kontrak,
            'keputusan' => __('pjk::pjk.keputusan.'.$event->outcome),
        ], '/admin/pjk/kontrak/'.$kontrak->id);
    }

    /** SRS-PJK-03/12/13: activation after approval. */
    public function activate(Kontrak $kontrak, ?int $userId, ?string $remarks = null, bool $issueDue = true, bool $sync = true, ?Carbon $depositOn = null): void
    {
        DB::transaction(function () use ($kontrak, $userId, $remarks, $issueDue, $depositOn) {
            $this->transition($kontrak, 'aktif', $userId, $remarks ?: __('pjk::pjk.kontrak.diaktifkan'));
            $premis = PremisAset::query()->withoutGlobalScope('access')->find($kontrak->premis_id);
            if ($premis !== null) {
                $this->premisService->setStatus($premis, 'disewa', $userId, __('pjk::pjk.premis.disewa', ['no' => $kontrak->no_kontrak]));
            }
            $this->version($kontrak, 'asal', $userId, null, null, $kontrak->snapshot(), null, $kontrak->id_dokumen_perjanjian);
            $this->jadual->generate($kontrak);

            $deposit = Wang::sen($kontrak->deposit);
            if ($deposit > 0 && ! DepositCagaran::query()->where('kontrak_id', $kontrak->id)->exists()) {
                $caj = $this->jadual->oneOff($kontrak, 'deposit', 'deposit', $deposit, __('pjk::pjk.caj.deposit'), $userId, $depositOn);
                DepositCagaran::query()->create(['kontrak_id' => $kontrak->id, 'jenis' => 'deposit_sewa', 'amaun' => Wang::dec($deposit), 'caj_id' => $caj->id, 'status' => 'belum_diterima']);
            }
            foreach (! $issueDue ? [] : $kontrak->caj()->where('kod_caj', 'sewa')->where('dikeluarkan', false)->whereDate('tarikh_caj', '<=', Carbon::today())->orderBy('tarikh_caj')->get() as $caj) {
                $this->jadual->issue($caj, $userId);
            }
            if ($kontrak->permohonan_id !== null) {
                PermohonanSewaan::query()->withoutGlobalScope('access')->whereKey($kontrak->permohonan_id)->update(['status' => 'lulus', 'kontrak_id' => $kontrak->id]);
            }
        });
        if ($sync) {
            $this->syncESewa($kontrak);
        }
    }

    /** SRS-PJK-02: one status transition with date and officer. */
    public function transition(Kontrak $kontrak, string $to, ?int $userId, ?string $note = null): void
    {
        $from = $kontrak->status;
        if ($from === $to) {
            return;
        }
        $kontrak->forceFill(['status' => $to, 'tarikh_status' => Carbon::today()->format('Y-m-d')])->save();
        $this->history($kontrak, $from, $to, $userId, $note);
    }

    /**
     * Daily (SRS-PJK-02/03): aktif → hampir_tamat within pjk.hari_hampir_tamat
     * days of the end date; → tamat_tempoh after it (premise back to kosong).
     *
     * @return array{hampir_tamat: int, tamat_tempoh: int}
     */
    public function refreshStatuses(?Carbon $today = null): array
    {
        $today ??= Carbon::today();
        $days = (int) $this->parameters->get('pjk.hari_hampir_tamat', 90);
        $near = 0;
        $ended = 0;
        Kontrak::query()->withoutGlobalScope('access')->where('status', 'aktif')
            ->whereDate('tarikh_tamat', '>=', $today)->whereDate('tarikh_tamat', '<=', $today->copy()->addDays($days))
            ->each(function (Kontrak $k) use (&$near) {
                $this->transition($k, 'hampir_tamat', null, __('pjk::pjk.kontrak.auto_hampir_tamat'));
                $near++;
            });
        Kontrak::query()->withoutGlobalScope('access')->whereIn('status', Kod::BERKUAT_KUASA)->whereDate('tarikh_tamat', '<', $today)
            ->each(function (Kontrak $k) use (&$ended) {
                DB::transaction(function () use ($k) {
                    $this->transition($k, 'tamat_tempoh', null, __('pjk::pjk.kontrak.auto_tamat'));
                    $this->releasePremis($k, null, __('pjk::pjk.premis.kosong_tamat', ['no' => $k->no_kontrak]));
                });
                $this->syncESewa($k);
                $ended++;
            });

        return ['hampir_tamat' => $near, 'tamat_tempoh' => $ended];
    }

    /** Premise back to kosong unless another running contract still holds it. */
    public function releasePremis(Kontrak $kontrak, ?int $userId, string $reason): void
    {
        $premis = PremisAset::query()->withoutGlobalScope('access')->find($kontrak->premis_id);
        if ($premis === null) {
            return;
        }
        $other = Kontrak::query()->withoutGlobalScope('access')->where('premis_id', $premis->id)->where('id', '!=', $kontrak->id)->whereIn('status', Kod::BERKUAT_KUASA)->exists();
        if (! $other && $premis->status_premis === 'disewa') {
            $this->premisService->setStatus($premis, 'kosong', $userId, $reason);
        }
    }

    /**
     * SRS-PJK-10: append a version.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    public function version(Kontrak $kontrak, string $kind, ?int $userId, ?string $justification, ?array $before, array $after, ?string $docBefore, ?string $docAfter, ?int $pindaanId = null): VersiKontrak
    {
        $no = (int) VersiKontrak::query()->where('kontrak_id', $kontrak->id)->max('no_versi') + 1;
        $v = VersiKontrak::query()->create([
            'kontrak_id' => $kontrak->id, 'no_kontrak' => (string) $kontrak->no_kontrak, 'no_versi' => $no, 'jenis_pindaan' => $kind,
            'tarikh_pindaan' => Carbon::today()->format('Y-m-d'), 'id_pegawai' => $userId, 'justifikasi' => $justification,
            'data_sebelum' => $before, 'data_selepas' => $after, 'id_dokumen_sebelum' => $docBefore, 'id_dokumen_selepas' => $docAfter, 'pindaan_id' => $pindaanId,
        ]);
        $kontrak->forceFill(['no_versi' => $no, 'id_versi_semasa' => $v->id_versi])->save();

        return $v;
    }

    public function syncESewa(Kontrak $kontrak): void
    {
        try {
            $premis = PremisAset::query()->withoutGlobalScope('access')->find($kontrak->premis_id);
            Luaran::call('pjk_esewa', 'sync_kontrak', fn () => app(ESewa::class)->syncContract([
                'no_kontrak' => (string) $kontrak->no_kontrak, 'status' => $kontrak->status,
                'tarikh_mula' => $kontrak->tarikh_mula?->format('Y-m-d'), 'tarikh_tamat' => $kontrak->tarikh_tamat?->format('Y-m-d'),
                'kadar' => $kontrak->kadar, 'kod_premis' => $premis?->kod_premis,
            ]), ['no_kontrak' => $kontrak->no_kontrak]);
        } catch (Throwable $e) {
            report($e); // e-Sewa sync is retried by reconciliation; never blocks the officer (SRS-INT-10)
        }
    }

    /**
     * @param  iterable<User>  $to
     * @param  array<string, scalar|null>  $data
     */
    public function notify(string $type, iterable $to, array $data, string $link): void
    {
        try {
            $this->notifier->send($type, $to, $data, $link);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function history(Kontrak $kontrak, ?string $from, string $to, ?int $userId, ?string $note): void
    {
        SejarahStatusKontrak::query()->create(['kontrak_id' => $kontrak->id, 'status_sebelum' => $from, 'status_selepas' => $to, 'id_pegawai' => $userId, 'catatan' => $note]);
    }

    /** Contract value = monthly rate × months (used for approval layers). */
    public function value(string $kadar, string $from, string $to): string
    {
        $months = max(1, (int) round(Carbon::parse($from)->diffInMonths(Carbon::parse($to)->addDay())));

        return Wang::dec(Wang::sen(Wang::norm($kadar)) * $months);
    }

    private function assertNoOverlap(PremisAset $premis, string $from, string $to, ?string $ignore): void
    {
        $overlap = Kontrak::query()->withoutGlobalScope('access')->where('premis_id', $premis->id)
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))
            ->whereIn('status', ['dalam_kelulusan', ...Kod::BERKUAT_KUASA])
            ->whereDate('tarikh_mula', '<=', $to)->whereDate('tarikh_tamat', '>=', $from)->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['premis_id' => __('pjk::pjk.ralat.premis_bertindih')]);
        }
        if (in_array($premis->status_premis, ['penyelenggaraan', 'penguatkuasaan', 'tidak_tersedia'], true)) {
            throw ValidationException::withMessages(['premis_id' => __('pjk::pjk.ralat.premis_tidak_tersedia')]);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validate(array $input): array
    {
        return Validator::make($input, [
            'premis_id' => ['required', 'integer'],
            'penyewa_id' => ['required', 'integer'],
            'jenis_perjanjian' => ['sometimes', Rule::in(array_keys(Kod::JENIS_PERJANJIAN))],
            'tarikh_mula' => ['required', 'date_format:Y-m-d'],
            'tarikh_tamat' => ['required', 'date_format:Y-m-d', 'after:tarikh_mula'],
            'kadar' => ['required', 'decimal:0,2', 'gt:0', 'max:99999999'],
            'deposit' => ['nullable', 'decimal:0,2', 'min:0', 'max:99999999'],
            'terma' => ['nullable', 'string', 'max:10000'],
            'kitaran_caj' => ['sometimes', Rule::in(array_keys(Kod::KITARAN_CAJ))],
            'hari_tempoh_bayar' => ['sometimes', 'integer', 'min:0', 'max:90'],
            'sumber' => ['sometimes', Rule::in(['kaunter', 'portal'])],
            'permohonan_id' => ['nullable', 'integer'],
        ], [], [
            'premis_id' => 'premis', 'penyewa_id' => 'penyewa', 'tarikh_mula' => 'tarikh mula', 'tarikh_tamat' => 'tarikh tamat',
            'kadar' => 'kadar sewaan', 'deposit' => 'deposit', 'kitaran_caj' => 'kitaran caj', 'hari_tempoh_bayar' => 'tempoh bayar',
        ])->validate();
    }
}
