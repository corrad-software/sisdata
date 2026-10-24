<?php

namespace App\Modules\Hkm\Services;

use App\Models\User;
use App\Modules\Hkm\Events\TitleRegistered;
use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Support\LotLookup;
use App\Modules\Hkm\Support\RefLookup;
use App\Modules\Hkm\Support\StatusTanah;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * SRS-HKM-01/02/21: registering a title for a lot (process step 1). The first
 * owner chronology row, first status history row and version 1 are written
 * in the same transaction. Later changes go through ChangeService (19).
 */
class TitleService
{
    public function __construct(
        private LotLookup $lots,
        private RefLookup $ref,
        private StatusTanah $statuses,
        private History $history,
        private VaultService $vault,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $data, ?HakMilik $existing = null): array
    {
        $validator = Validator::make($data, [
            'lot_id' => [$existing ? 'sometimes' : 'required', 'uuid'],
            'no_hak_milik' => ['required', 'string', 'max:50'],
            'jenis_hak_milik' => ['required', 'string', 'max:20'],
            'kategori_tanah' => ['nullable', 'string', 'max:30'],
            'syarat_nyata' => ['nullable', 'string', 'max:5000'],
            'keluasan' => ['nullable', 'numeric', 'gt:0', 'max:999999999999'],
            'unit_keluasan' => ['nullable', Rule::in(['m2', 'hektar', 'ekar', 'kaki_persegi'])],
            'pemilik_berdaftar' => ['required', 'string', 'max:255'],
            'tarikh_pendaftaran' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'tarikh_tamat_tempoh' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tarikh_pendaftaran'],
            'tempoh_pajakan_tahun' => ['nullable', 'integer', 'min:1', 'max:999'],
            'pejabat_pendaftaran' => ['nullable', 'string', 'max:150'],
            'no_perserahan' => ['nullable', 'string', 'max:50'],
            'tarikh_perserahan' => ['nullable', 'date_format:Y-m-d'],
            'status_semasa' => ['nullable', 'string', 'max:40'],
            'id_hak_milik_digantikan' => ['nullable', 'uuid'],
        ], [], self::attributes());

        $validator->after(function ($v) use ($data) {
            $jenis = (string) ($data['jenis_hak_milik'] ?? '');
            if ($jenis !== '' && ! $this->ref->validJenis($jenis)) {
                $v->errors()->add('jenis_hak_milik', __('hkm::hkm.ralat.jenis_tidak_sah'));
            }
            $kategori = $data['kategori_tanah'] ?? null;
            if ($kategori !== null && $kategori !== '' && ! $this->ref->validKategori((string) $kategori)) {
                $v->errors()->add('kategori_tanah', __('hkm::hkm.ralat.kategori_tidak_sah'));
            }
            $status = $data['status_semasa'] ?? null;
            if ($status !== null && $status !== '' && ! in_array($status, $this->statuses->activeCodes(), true)) {
                $v->errors()->add('status_semasa', __('hkm::hkm.ralat.status_tidak_sah'));
            }
            // A TOL is always for a fixed term: its expiry date is needed for renewal reminders (SRS-HKM-17).
            if ($jenis === 'TOL' && empty($data['tarikh_tamat_tempoh'])) {
                $v->errors()->add('tarikh_tamat_tempoh', __('hkm::hkm.ralat.tamat_wajib'));
            }
        });

        return $validator->validate();
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'lot_id' => 'lot', 'no_hak_milik' => 'nombor hak milik', 'jenis_hak_milik' => 'jenis hak milik',
            'kategori_tanah' => 'kategori tanah', 'syarat_nyata' => 'syarat nyata', 'keluasan' => 'keluasan',
            'pemilik_berdaftar' => 'pemilik berdaftar', 'tarikh_pendaftaran' => 'tarikh pendaftaran',
            'tarikh_tamat_tempoh' => 'tarikh tamat tempoh', 'pejabat_pendaftaran' => 'pejabat pendaftaran',
            'no_perserahan' => 'nombor perserahan', 'tarikh_perserahan' => 'tarikh perserahan', 'status_semasa' => 'status',
        ];
    }

    /**
     * @param  array<string, mixed>  $data  validated
     * @param  array<string, mixed>|null  $vault  deposit the original document now (SRS-HKM-03)
     * @param  array{sumber?: string, sebab?: string, id_pemerolehan?: string|null, sumber_sistem?: string|null, kunci_asal?: string|null, disahkan?: bool, pemilik_terdahulu?: list<string>}  $options
     *
     * @throws ValidationException
     */
    public function register(string $lotId, array $data, ?User $actor, ?array $vault = null, array $options = []): HakMilik
    {
        $lot = $this->lots->find($lotId, $actor);
        if ($lot === null) {
            throw ValidationException::withMessages(['lot_id' => __('hkm::hkm.ralat.lot_tiada')]);
        }

        $this->assertUnique((string) $data['no_hak_milik'], $lot['kod_mukim']);

        $title = DB::transaction(function () use ($lot, $data, $actor, $vault, $options) {
            $status = ($data['status_semasa'] ?? null) ?: 'aktif';
            $title = new HakMilik;
            $title->forceFill([
                'lot_id' => $lot['id'],
                'no_hak_milik' => trim((string) $data['no_hak_milik']),
                'jenis_hak_milik' => $data['jenis_hak_milik'],
                'kategori_tanah' => ($data['kategori_tanah'] ?? null) ?: $lot['kategori_guna_tanah'],
                'syarat_nyata' => $data['syarat_nyata'] ?? null,
                'keluasan' => $data['keluasan'] ?? $lot['keluasan'],
                'unit_keluasan' => ($data['unit_keluasan'] ?? null) ?: ($lot['unit_keluasan'] ?: 'm2'),
                'kod_mukim' => $lot['kod_mukim'],
                'kod_daerah' => $lot['kod_daerah'],
                'pemilik_berdaftar' => trim((string) $data['pemilik_berdaftar']),
                'tarikh_pendaftaran' => $data['tarikh_pendaftaran'] ?? null,
                'tarikh_tamat_tempoh' => $data['tarikh_tamat_tempoh'] ?? null,
                'tempoh_pajakan_tahun' => $data['tempoh_pajakan_tahun'] ?? null,
                'pejabat_pendaftaran' => $data['pejabat_pendaftaran'] ?? null,
                'no_perserahan' => $data['no_perserahan'] ?? null,
                'tarikh_perserahan' => $data['tarikh_perserahan'] ?? null,
                'status_semasa' => $status,
                'id_hak_milik_digantikan' => $data['id_hak_milik_digantikan'] ?? null,
                'klasifikasi' => in_array($lot['klasifikasi'], ['rahsia'], true) ? 'rahsia' : 'sulit',
                'versi_rekod' => 1,
                'id_pendaftar' => $actor?->id,
                'sumber_sistem' => $options['sumber_sistem'] ?? null,
                'kunci_asal' => $options['kunci_asal'] ?? null,
                'disahkan' => (bool) ($options['disahkan'] ?? false),
            ])->save();

            $sebab = $options['sebab'] ?? __('hkm::hkm.sejarah.pendaftaran');
            $this->history->status($title, null, $status, [
                'tarikh_berkuat_kuasa' => $data['tarikh_pendaftaran'] ?? today()->toDateString(),
                'sebab' => $sebab,
                'id_pegawai' => $actor?->id,
            ]);
            // Earliest recorded owners first (SRS-HKM-10), then the registered owner.
            $previous = null;
            foreach ($options['pemilik_terdahulu'] ?? [] as $earlier) {
                $this->history->owner($title, $previous, $earlier, ['sebab' => __('hkm::hkm.sejarah.pemilik_terdahulu'), 'sumber' => $options['sumber'] ?? 'pendaftaran', 'id_pegawai' => $actor?->id]);
                $previous = $earlier;
            }
            $this->history->owner($title, $previous, $title->pemilik_berdaftar, [
                'tarikh' => $data['tarikh_pendaftaran'] ?? null,
                'sebab' => $sebab,
                'sumber' => $options['sumber'] ?? 'pendaftaran',
                'id_pemerolehan' => $options['id_pemerolehan'] ?? null,
                'id_pegawai' => $actor?->id,
            ]);
            $this->history->version($title, 'pendaftaran', null, ['justifikasi' => $sebab, 'id_pengguna' => $actor?->id]);

            if ($vault !== null) {
                $this->vault->deposit($title, $vault, $actor);
            }

            return $title;
        });

        TitleRegistered::dispatch($title->id, $title->lot_id, $options['id_pemerolehan'] ?? null);

        return $title;
    }

    /** @throws ValidationException */
    public function assertUnique(string $noHakMilik, string $kodMukim, ?string $exceptId = null): void
    {
        $exists = HakMilik::query()->withoutGlobalScope('access')
            ->where('kod_mukim', $kodMukim)
            ->whereRaw('upper(no_hak_milik) = ?', [mb_strtoupper(trim($noHakMilik))])
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['no_hak_milik' => __('hkm::hkm.ralat.no_hak_milik_wujud')]);
        }
    }

    /** SRS-HKM-17: an officer other than the registrar confirms the title against the Land Office record. */
    public function verify(HakMilik $title, User $user): HakMilik
    {
        if ($title->id_pendaftar !== null && $title->id_pendaftar === $user->id) {
            throw ValidationException::withMessages(['hak_milik' => __('hkm::hkm.ralat.sod_pengesahan')]);
        }

        $before = $title->snapshot();
        DB::transaction(function () use ($title, $user, $before) {
            $title->forceFill(['disahkan' => true, 'disahkan_oleh' => $user->id, 'disahkan_pada' => now(), 'versi_rekod' => $title->versi_rekod + 1])->save();
            $this->history->version($title, 'pengesahan', $before, ['justifikasi' => __('hkm::hkm.sejarah.pengesahan'), 'id_pengguna' => $user->id]);
        });

        return $title;
    }
}
