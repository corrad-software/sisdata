<?php

namespace App\Modules\Hkm\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditScope;
use App\Modules\Hkm\Models\CadanganPerubahan;
use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Models\PerubahanHakMilik;
use App\Modules\Hkm\Models\SekatanKepentingan;
use App\Modules\Hkm\Models\WarisPemilikBersama;
use App\Modules\Hkm\Support\Kod;
use App\Modules\Hkm\Support\StatusTanah;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * SRS-HKM-19 (process step 6): every change to a registered title — details,
 * land status, registered owner, encumbrances, heirs/co-owners — is a change
 * request that runs the WFE flow `hkm.perubahan_hak_milik` (two stages, both
 * perlu_mfa) and is applied as a new version only when WFE reports `lulus`.
 * HKM never decides approvals itself (SRS-GEN-03).
 */
class ChangeService
{
    public const FLOW = 'hkm.perubahan_hak_milik';

    public const SUBJECT = 'perubahan_hak_milik';

    public function __construct(
        private WorkflowEngine $workflow,
        private History $history,
        private TitleService $titles,
        private StatusTanah $statuses,
        private Notifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $input  jenis_perubahan, data, justifikasi, id_dokumen_rujukan?, no_rujukan_dokumen?
     *
     * @throws ValidationException
     */
    public function request(HakMilik $title, array $input, User $user, ?CadanganPerubahan $proposal = null): PerubahanHakMilik
    {
        $valid = Validator::make($input, [
            'jenis_perubahan' => ['required', Rule::in(array_keys(Kod::JENIS_PERUBAHAN))],
            'data' => ['required', 'array'],
            'justifikasi' => ['required', 'string', 'min:5', 'max:5000'],
            'id_dokumen_rujukan' => ['nullable', 'string', 'max:64'],
            'no_rujukan_dokumen' => ['nullable', 'string', 'max:100'],
        ], [], ['jenis_perubahan' => 'jenis perubahan', 'justifikasi' => 'justifikasi', 'data' => 'butiran perubahan'])->validate();

        $data = $this->validatePayload($title, $valid['jenis_perubahan'], $valid['data']);

        if (PerubahanHakMilik::query()->where('hak_milik_id', $title->id)->where('status', PerubahanHakMilik::MENUNGGU)->exists()) {
            throw ValidationException::withMessages(['hak_milik' => __('hkm::hkm.ralat.perubahan_menunggu')]);
        }

        return DB::transaction(function () use ($title, $valid, $data, $user, $proposal) {
            $change = PerubahanHakMilik::query()->create([
                'hak_milik_id' => $title->id,
                'jenis_perubahan' => $valid['jenis_perubahan'],
                'data_cadangan' => $data,
                'data_semasa' => $this->current($title, $valid['jenis_perubahan']),
                'justifikasi' => $valid['justifikasi'],
                'id_dokumen_rujukan' => $valid['id_dokumen_rujukan'] ?? null,
                'no_rujukan_dokumen' => $valid['no_rujukan_dokumen'] ?? null,
                'status' => PerubahanHakMilik::MENUNGGU,
                'id_pemohon' => $user->id,
                'cadangan_perubahan_id' => $proposal?->id,
            ]);

            try {
                $instance = $this->workflow->start(self::FLOW, self::SUBJECT, $change->id, $user, [
                    'kod_daerah' => $title->kod_daerah,
                    'kod_mukim' => $title->kod_mukim,
                    'jenis_perubahan' => $valid['jenis_perubahan'],
                    'jenis_hak_milik' => $title->jenis_hak_milik,
                    'pautan' => '/admin/hkm/perubahan/'.$change->id,
                ], __('hkm::hkm.aliran.tajuk', ['jenis' => Kod::JENIS_PERUBAHAN[$valid['jenis_perubahan']], 'no' => $title->no_hak_milik]));
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages(['aliran' => __('hkm::hkm.ralat.aliran_tiada')]);
            }

            $change->forceFill(['id_instans_aliran' => $instance])->save();

            return $change;
        });
    }

    /**
     * Validate and normalise the proposed data for one change kind.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validatePayload(HakMilik $title, string $kind, array $data): array
    {
        switch ($kind) {
            case 'butiran':
                $data = array_intersect_key($data, array_flip(Kod::MEDAN_BUTIRAN));
                if ($data === []) {
                    throw ValidationException::withMessages(['data' => __('hkm::hkm.ralat.tiada_perubahan')]);
                }
                $merged = array_merge($title->only(['no_hak_milik', 'jenis_hak_milik', 'pemilik_berdaftar']), [
                    'tarikh_pendaftaran' => $title->tarikh_pendaftaran?->format('Y-m-d'),
                    'tarikh_tamat_tempoh' => $title->tarikh_tamat_tempoh?->format('Y-m-d'),
                ], $data);
                $this->titles->validate($merged, $title);
                if (isset($data['no_hak_milik'])) {
                    $this->titles->assertUnique((string) $data['no_hak_milik'], $title->kod_mukim, $title->id);
                }

                return $data;

            case 'status':
                $valid = Validator::make($data, [
                    'status_selepas' => ['required', 'string', Rule::in($this->statuses->activeCodes()), Rule::notIn([$title->status_semasa])],
                    'tarikh_berkuat_kuasa' => ['required', 'date_format:Y-m-d'],
                ], ['status_selepas.not_in' => __('hkm::hkm.ralat.status_sama')], ['status_selepas' => 'status baharu', 'tarikh_berkuat_kuasa' => 'tarikh berkuat kuasa'])->validate();

                return $valid;

            case 'pemilik':
                return Validator::make($data, [
                    'pemilik_baharu' => ['required', 'string', 'max:255', Rule::notIn([$title->pemilik_berdaftar])],
                    'tarikh_pertukaran' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                    'sebab_pertukaran' => ['required', 'string', 'max:2000'],
                    'id_pemerolehan' => ['nullable', 'string', 'max:100'],
                ], [], ['pemilik_baharu' => 'pemilik baharu', 'tarikh_pertukaran' => 'tarikh pertukaran', 'sebab_pertukaran' => 'sebab pertukaran'])->validate();

            case 'sekatan':
                $valid = Validator::make($data, [
                    'tindakan' => ['required', Rule::in(['tambah', 'kemas_kini', 'lupus'])],
                    'id_sekatan' => ['required_unless:tindakan,tambah', 'nullable', 'integer'],
                    'jenis_sekatan' => ['required_if:tindakan,tambah', Rule::in(array_keys(Kod::JENIS_SEKATAN))],
                    'no_rujukan_sekatan' => ['nullable', 'string', 'max:100'],
                    'pihak_terlibat' => ['nullable', 'string', 'max:255'],
                    'keterangan' => ['nullable', 'string', 'max:5000'],
                    'tarikh_masuk' => ['nullable', 'date_format:Y-m-d'],
                    'tarikh_lupus' => ['required_if:tindakan,lupus', 'nullable', 'date_format:Y-m-d'],
                    'status_sekatan' => ['nullable', Rule::in(array_keys(Kod::STATUS_SEKATAN))],
                    'id_dokumen_rujukan' => ['nullable', 'string', 'max:64'],
                    'id_kes_mahkamah' => ['nullable', 'string', 'max:64'],
                ], [], ['jenis_sekatan' => 'jenis sekatan', 'tarikh_lupus' => 'tarikh lupus'])->validate();
                if (($valid['id_sekatan'] ?? null) !== null && ! SekatanKepentingan::query()->where('hak_milik_id', $title->id)->whereKey($valid['id_sekatan'])->exists()) {
                    throw ValidationException::withMessages(['data.id_sekatan' => __('hkm::hkm.ralat.rekod_tiada')]);
                }

                return $valid;

            case 'waris':
                $valid = Validator::make($data, [
                    'tindakan' => ['required', Rule::in(['tambah', 'kemas_kini', 'buang'])],
                    'id_waris' => ['required_unless:tindakan,tambah', 'nullable', 'integer'],
                    'nama' => ['required_if:tindakan,tambah', 'nullable', 'string', 'max:200'],
                    'no_pengenalan' => ['nullable', 'string', 'max:20', 'regex:/^[0-9A-Z\-]+$/'],
                    'hubungan' => ['required_if:tindakan,tambah', 'nullable', Rule::in(array_keys(Kod::HUBUNGAN))],
                    'pecahan_bahagian_pembilang' => ['nullable', 'integer', 'min:0', 'required_with:pecahan_bahagian_penyebut'],
                    'pecahan_bahagian_penyebut' => ['nullable', 'integer', 'min:1', 'required_with:pecahan_bahagian_pembilang', 'gte:pecahan_bahagian_pembilang'],
                    'sumber_pengesahan' => ['nullable', Rule::in(array_keys(Kod::SUMBER_PENGESAHAN))],
                    'no_kes_faraid' => ['nullable', 'string', 'max:60'],
                    'status_pengesahan' => ['nullable', Rule::in(array_keys(Kod::STATUS_PENGESAHAN_WARIS))],
                    'catatan' => ['nullable', 'string', 'max:2000'],
                ], [], ['nama' => 'nama', 'hubungan' => 'hubungan', 'pecahan_bahagian_penyebut' => 'penyebut pecahan'])->validate();
                if (($valid['id_waris'] ?? null) !== null && ! WarisPemilikBersama::query()->where('hak_milik_id', $title->id)->whereKey($valid['id_waris'])->exists()) {
                    throw ValidationException::withMessages(['data.id_waris' => __('hkm::hkm.ralat.rekod_tiada')]);
                }
                if (($valid['status_pengesahan'] ?? null) === 'menunggu_faraid' && empty($valid['no_kes_faraid'])) {
                    throw ValidationException::withMessages(['data.no_kes_faraid' => __('hkm::hkm.ralat.no_kes_faraid_wajib')]);
                }

                return $valid;
        }

        throw ValidationException::withMessages(['jenis_perubahan' => __('hkm::hkm.ralat.jenis_perubahan')]);
    }

    /** @return array<string, mixed> */
    private function current(HakMilik $title, string $kind): array
    {
        return match ($kind) {
            'sekatan' => ['sekatan' => $title->sekatan()->orderBy('id')->get(['id', ...SekatanKepentingan::MEDAN])->toArray()],
            'waris' => ['waris' => $title->waris()->orderBy('id')->get(['id', ...array_diff(WarisPemilikBersama::MEDAN, ['no_pengenalan'])])->toArray()],
            default => $title->snapshot(),
        };
    }

    /** WFE listener (SRS-WFE-20): apply the outcome of `hkm.perubahan_hak_milik`. */
    public function handle(WorkflowCompleted $event): void
    {
        if ($event->flowCode !== self::FLOW || $event->subjectType !== self::SUBJECT) {
            return;
        }

        $change = PerubahanHakMilik::query()->find($event->subjectId);
        if (! $change || $change->status !== PerubahanHakMilik::MENUNGGU) {
            return; // unknown or already handled — idempotent
        }

        if ($event->outcome === 'lulus') {
            try {
                AuditScope::withReason($change->justifikasi, fn () => $this->apply($change, $event->decidedBy));
                $change->forceFill(['status' => PerubahanHakMilik::DILULUSKAN])->save();
            } catch (Throwable $e) {
                report($e);
                $change->forceFill(['status' => 'gagal', 'catatan_keputusan' => $e instanceof ValidationException ? collect($e->errors())->flatten()->implode(' ') : __('hkm::hkm.ralat.gagal_laksana')])->save();
            }
        } else {
            $change->forceFill(['status' => match ($event->outcome) {
                'tolak' => 'ditolak',
                'ditarik_balik' => 'ditarik_balik',
                default => 'dibatalkan',
            }])->save();
        }

        $change->forceFill([
            'diputuskan_oleh' => $event->decidedBy,
            'tarikh_keputusan' => now(),
            'catatan_keputusan' => $change->catatan_keputusan ?? $event->remarks,
        ])->save();

        if ($change->pemohon) {
            $this->notifier->send('hkm.perubahan_keputusan', $change->pemohon, [
                'no_hak_milik' => $change->hakMilik->no_hak_milik,
                'jenis_perubahan' => Kod::JENIS_PERUBAHAN[$change->jenis_perubahan] ?? $change->jenis_perubahan,
                'keputusan' => Kod::STATUS_PERUBAHAN[$change->status] ?? $change->status,
            ], '/admin/hkm/perubahan/'.$change->id);
        }
    }

    /**
     * Apply an approved change as a new version.
     *
     * @throws ValidationException
     */
    public function apply(PerubahanHakMilik $change, ?int $approverId): HakMilik
    {
        return DB::transaction(function () use ($change, $approverId) {
            /** @var HakMilik $title */
            $title = HakMilik::query()->withoutGlobalScope('access')->lockForUpdate()->findOrFail($change->hak_milik_id);
            $data = $change->data_cadangan;
            $kind = $change->jenis_perubahan;
            $before = $title->snapshot() + $this->current($title, $kind);
            $meta = [
                'justifikasi' => $change->justifikasi,
                'id_dokumen_rujukan' => $change->id_dokumen_rujukan,
                'no_rujukan_dokumen' => $change->no_rujukan_dokumen,
                'id_perubahan' => $change->id,
                'id_pengguna' => $change->id_pemohon,
                'id_pelulus' => $approverId,
            ];

            // Re-check against the state at approval time (another change may have landed).
            $data = $this->validatePayload($title, $kind, $data);

            switch ($kind) {
                case 'butiran':
                    $title->fill($data);
                    break;
                case 'status':
                    $this->history->status($title, $title->status_semasa, $data['status_selepas'], [
                        'tarikh_berkuat_kuasa' => $data['tarikh_berkuat_kuasa'],
                        'sebab' => $change->justifikasi,
                        'id_dokumen_rujukan' => $change->id_dokumen_rujukan,
                        'no_rujukan_dokumen' => $change->no_rujukan_dokumen,
                        'id_pegawai' => $change->id_pemohon,
                        'id_pelulus' => $approverId,
                        'id_perubahan' => $change->id,
                    ]);
                    $title->status_semasa = $data['status_selepas'];
                    break;
                case 'pemilik':
                    $this->history->owner($title, $title->pemilik_berdaftar, $data['pemilik_baharu'], [
                        'tarikh' => $data['tarikh_pertukaran'],
                        'sebab' => $data['sebab_pertukaran'],
                        'sumber' => 'perubahan',
                        'id_pemerolehan' => $data['id_pemerolehan'] ?? null,
                        'id_dokumen_rujukan' => $change->id_dokumen_rujukan,
                        'id_pegawai' => $change->id_pemohon,
                        'id_perubahan' => $change->id,
                    ]);
                    $title->pemilik_berdaftar = $data['pemilik_baharu'];
                    break;
                case 'sekatan':
                    $this->applyEncumbrance($title, $data);
                    break;
                case 'waris':
                    $this->applyHeir($title, $data);
                    break;
            }

            $title->versi_rekod = $title->versi_rekod + 1;
            $title->save();
            $this->history->version($title, $kind, $before, $meta, in_array($kind, ['sekatan', 'waris'], true) ? $this->current($title, $kind) : []);

            return $title;
        });
    }

    /** @param array<string, mixed> $data */
    private function applyEncumbrance(HakMilik $title, array $data): void
    {
        $fields = array_intersect_key($data, array_flip(SekatanKepentingan::MEDAN));
        if ($data['tindakan'] === 'tambah') {
            SekatanKepentingan::query()->create(['hak_milik_id' => $title->id, 'status_sekatan' => 'aktif', ...$fields]);

            return;
        }

        $row = SekatanKepentingan::query()->where('hak_milik_id', $title->id)->findOrFail($data['id_sekatan']);
        if ($data['tindakan'] === 'lupus') {
            $fields['status_sekatan'] = ($data['status_sekatan'] ?? null) === 'ditarik_balik' ? 'ditarik_balik' : 'dilupuskan';
        }
        $row->fill($fields)->save();
    }

    /** @param array<string, mixed> $data */
    private function applyHeir(HakMilik $title, array $data): void
    {
        $fields = array_intersect_key($data, array_flip(WarisPemilikBersama::MEDAN));
        if ($data['tindakan'] === 'tambah') {
            WarisPemilikBersama::query()->create(['hak_milik_id' => $title->id, 'status_pengesahan' => 'belum_disahkan', ...$fields]);

            return;
        }

        $row = WarisPemilikBersama::query()->where('hak_milik_id', $title->id)->findOrFail($data['id_waris']);
        $data['tindakan'] === 'buang' ? $row->delete() : $row->fill($fields)->save();
    }
}
