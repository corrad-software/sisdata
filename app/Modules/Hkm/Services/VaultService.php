<?php

namespace App\Modules\Hkm\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Hkm\Models\DaftarPetiBesi;
use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Models\PengeluaranDokumen;
use App\Modules\Hkm\Models\PetiBesi;
use App\Modules\Hkm\Support\Kod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * SRS-HKM-03..06: vault register — deposit, location, status, issue and
 * return of original documents. Issue/return are explicit audit actions
 * (SRS-HKM-22) on top of the automatic field-change audit.
 */
class VaultService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data  kod_peti_besi|peti_besi_id, jenis_dokumen_asal, no_fail, lokasi_rak, no_kotak_pengikat, catatan_lokasi, tarikh_masuk_simpanan
     *
     * @throws ValidationException
     */
    public function deposit(HakMilik $title, array $data, ?User $actor): DaftarPetiBesi
    {
        $valid = Validator::make($data, [
            'peti_besi_id' => ['required_without:kod_peti_besi', 'nullable', 'integer'],
            'kod_peti_besi' => ['required_without:peti_besi_id', 'nullable', 'string', 'max:30'],
            'jenis_dokumen_asal' => ['nullable', Rule::in(array_keys(Kod::JENIS_DOKUMEN_ASAL))],
            'no_fail' => ['nullable', 'string', 'max:50'],
            'lokasi_rak' => ['nullable', 'string', 'max:50'],
            'no_kotak_pengikat' => ['nullable', 'string', 'max:50'],
            'catatan_lokasi' => ['nullable', 'string', 'max:2000'],
            'tarikh_masuk_simpanan' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ], [], ['kod_peti_besi' => 'peti besi', 'peti_besi_id' => 'peti besi', 'no_fail' => 'nombor fail', 'lokasi_rak' => 'lokasi rak'])->validate();

        $vault = isset($valid['peti_besi_id'])
            ? PetiBesi::query()->find($valid['peti_besi_id'])
            : PetiBesi::query()->where('kod_peti_besi', $valid['kod_peti_besi'])->first();
        if (! $vault || $vault->status_peti_besi === 'tidak_aktif') {
            throw ValidationException::withMessages(['peti_besi_id' => __('hkm::hkm.ralat.peti_besi_tiada')]);
        }

        $jenis = $valid['jenis_dokumen_asal'] ?? 'dokumen_hak_milik_daftar';
        if (DaftarPetiBesi::query()->where('hak_milik_id', $title->id)->where('jenis_dokumen_asal', $jenis)->exists()) {
            throw ValidationException::withMessages(['jenis_dokumen_asal' => __('hkm::hkm.ralat.dokumen_sudah_didaftar')]);
        }
        if ($vault->kapasiti !== null && $vault->daftar()->count() >= $vault->kapasiti) {
            throw ValidationException::withMessages(['peti_besi_id' => __('hkm::hkm.ralat.peti_besi_penuh')]);
        }

        return DaftarPetiBesi::query()->create([
            'peti_besi_id' => $vault->id,
            'hak_milik_id' => $title->id,
            'lot_id' => $title->lot_id,
            'jenis_dokumen_asal' => $jenis,
            'no_fail' => $valid['no_fail'] ?? null,
            'lokasi_rak' => $valid['lokasi_rak'] ?? null,
            'no_kotak_pengikat' => $valid['no_kotak_pengikat'] ?? null,
            'catatan_lokasi' => $valid['catatan_lokasi'] ?? null,
            'status_dokumen_asal' => 'dalam_simpanan',
            'tarikh_masuk_simpanan' => $valid['tarikh_masuk_simpanan'] ?? today()->toDateString(),
            'id_pegawai_terima' => $actor?->id,
        ]);
    }

    /**
     * Custodian corrects location or sets a status the issue flow doesn't
     * (hilang, diganti, dipegang pihak ketiga, back to dalam simpanan).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(DaftarPetiBesi $entry, array $data): DaftarPetiBesi
    {
        $valid = Validator::make($data, [
            'peti_besi_id' => ['sometimes', 'integer', Rule::exists('peti_besi', 'id')],
            'no_fail' => ['sometimes', 'nullable', 'string', 'max:50'],
            'lokasi_rak' => ['sometimes', 'nullable', 'string', 'max:50'],
            'no_kotak_pengikat' => ['sometimes', 'nullable', 'string', 'max:50'],
            'catatan_lokasi' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status_dokumen_asal' => ['sometimes', Rule::in(['dalam_simpanan', 'dipegang_pihak_ketiga', 'hilang', 'diganti', 'dalam_proses_pt'])],
        ])->validate();

        if (isset($valid['status_dokumen_asal']) && $entry->pengeluaranTerbuka !== null && $valid['status_dokumen_asal'] === 'dalam_simpanan') {
            // Use the return flow so the issue record is closed properly.
            throw ValidationException::withMessages(['status_dokumen_asal' => __('hkm::hkm.ralat.guna_pemulangan')]);
        }

        $entry->fill($valid)->save();

        return $entry;
    }

    /**
     * SRS-HKM-06 issue (process step 4).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function issue(DaftarPetiBesi $entry, array $data, User $actor): PengeluaranDokumen
    {
        $valid = Validator::make($data, [
            'penerima_nama' => ['required', 'string', 'max:150'],
            'penerima_jawatan' => ['nullable', 'string', 'max:150'],
            'tujuan_pengeluaran' => ['required', 'string', 'max:2000'],
            'tarikh_jangka_pulang' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'status_semasa_dikeluarkan' => ['nullable', Rule::in(Kod::STATUS_KELUAR)],
        ], [], ['penerima_nama' => 'nama penerima', 'tujuan_pengeluaran' => 'tujuan pengeluaran', 'tarikh_jangka_pulang' => 'tarikh dijangka pulang'])->validate();

        if ($entry->status_dokumen_asal !== 'dalam_simpanan') {
            throw ValidationException::withMessages(['status_dokumen_asal' => __('hkm::hkm.ralat.dokumen_tiada_dalam_simpanan')]);
        }

        $status = $valid['status_semasa_dikeluarkan'] ?? 'dikeluarkan';

        $issue = DB::transaction(function () use ($entry, $valid, $actor, $status) {
            $issue = PengeluaranDokumen::query()->create([
                'daftar_peti_besi_id' => $entry->id,
                'tarikh_pengeluaran' => now(),
                'id_pegawai_keluar' => $actor->id,
                'penerima_nama' => $valid['penerima_nama'],
                'penerima_jawatan' => $valid['penerima_jawatan'] ?? null,
                'tujuan_pengeluaran' => $valid['tujuan_pengeluaran'],
                'status_semasa_dikeluarkan' => $status,
                'tarikh_jangka_pulang' => $valid['tarikh_jangka_pulang'],
                'status_pengeluaran' => PengeluaranDokumen::DIKELUARKAN,
            ]);
            $entry->forceFill(['status_dokumen_asal' => $status])->save();

            return $issue;
        });

        $this->audit->record('keluar_dokumen', 'hkm', 'pengeluaran_dokumen', $issue->id, reason: $valid['tujuan_pengeluaran'], meta: [
            'klasifikasi' => 'sulit',
            'hak_milik_id' => $entry->hak_milik_id,
            'penerima' => $valid['penerima_nama'],
            'tarikh_jangka_pulang' => $valid['tarikh_jangka_pulang'],
        ]);

        return $issue;
    }

    /**
     * SRS-HKM-06 return (process step 5).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function return(PengeluaranDokumen $issue, array $data, User $actor): PengeluaranDokumen
    {
        $valid = Validator::make($data, [
            'keadaan_dokumen_pulang' => ['required', Rule::in(array_keys(Kod::KEADAAN_PULANG))],
            'catatan_pulang' => ['nullable', 'string', 'max:2000'],
        ], [], ['keadaan_dokumen_pulang' => 'keadaan dokumen'])->validate();

        if ($issue->status_pengeluaran !== PengeluaranDokumen::DIKELUARKAN) {
            throw ValidationException::withMessages(['pengeluaran' => __('hkm::hkm.ralat.sudah_dipulangkan')]);
        }

        $late = $issue->lewat();
        DB::transaction(function () use ($issue, $valid, $actor) {
            $issue->forceFill([
                'tarikh_pulang_sebenar' => now(),
                'id_pegawai_terima_pulang' => $actor->id,
                'keadaan_dokumen_pulang' => $valid['keadaan_dokumen_pulang'],
                'catatan_pulang' => $valid['catatan_pulang'] ?? null,
                'status_pengeluaran' => PengeluaranDokumen::DIPULANGKAN,
            ])->save();
            $issue->daftar->forceFill(['status_dokumen_asal' => 'dalam_simpanan'])->save();
        });

        $this->audit->record('pulang_dokumen', 'hkm', 'pengeluaran_dokumen', $issue->id, meta: [
            'klasifikasi' => 'sulit',
            'hak_milik_id' => $issue->daftar->hak_milik_id,
            'keadaan' => $valid['keadaan_dokumen_pulang'],
            'lewat' => $late,
        ]);

        return $issue;
    }
}
