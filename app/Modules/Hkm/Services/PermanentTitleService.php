<?php

namespace App\Modules\Hkm\Services;

use App\Models\User;
use App\Modules\Hkm\Models\DaftarPetiBesi;
use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Models\PermohonanHakMilikKekal;
use App\Modules\Hkm\Support\Kod;
use App\Modules\Hkm\Support\RefLookup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * SRS-HKM-16 (process step 9): permanent-title applications — registration,
 * document checklist, Land Office stages, payment, and receipt of the new
 * title, which is registered and linked to the temporary title it replaces
 * (hak_milik.id_hak_milik_digantikan). The temporary title moves to status
 * "diganti" with a status-history row in the same transaction.
 */
class PermanentTitleService
{
    public function __construct(private TitleService $titles, private History $history, private RefLookup $ref) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(HakMilik $temporary, array $data, User $user): PermohonanHakMilikKekal
    {
        $valid = Validator::make($data, [
            'no_permohonan' => ['nullable', 'string', 'max:50', Rule::unique('permohonan_hak_milik_kekal', 'no_permohonan')],
            'tarikh_permohonan' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'pejabat_tanah' => ['required', 'string', 'max:150'],
            'catatan' => ['nullable', 'string', 'max:5000'],
        ], [], ['tarikh_permohonan' => 'tarikh permohonan', 'pejabat_tanah' => 'pejabat tanah', 'no_permohonan' => 'nombor permohonan'])->validate();

        $jenis = $this->ref->jenisHakMilik()[$temporary->jenis_hak_milik] ?? null;
        if (! $jenis || ! $jenis['sementara'] || ! $jenis['hak_milik']) {
            throw ValidationException::withMessages(['hak_milik_sementara_id' => __('hkm::hkm.ralat.bukan_sementara')]);
        }
        $open = PermohonanHakMilikKekal::query()->where('hak_milik_sementara_id', $temporary->id)->where('status_kelulusan', 'dalam_proses')->exists();
        if ($open || $temporary->status_semasa === 'diganti') {
            throw ValidationException::withMessages(['hak_milik_sementara_id' => __('hkm::hkm.ralat.permohonan_wujud')]);
        }

        return DB::transaction(function () use ($temporary, $valid, $user) {
            $application = PermohonanHakMilikKekal::query()->create([
                'hak_milik_sementara_id' => $temporary->id,
                'lot_id' => $temporary->lot_id,
                'no_permohonan' => $valid['no_permohonan'] ?? $this->nextNumber(),
                'tarikh_permohonan' => $valid['tarikh_permohonan'],
                'pejabat_tanah' => $valid['pejabat_tanah'],
                'senarai_semak_dokumen' => array_map(fn ($k, $label) => ['kod' => $k, 'label' => $label, 'lengkap' => false, 'id_dokumen' => null], array_keys(Kod::SENARAI_SEMAK_HMK), Kod::SENARAI_SEMAK_HMK),
                'peringkat_semasa' => 'pendaftaran',
                'status_kelulusan' => 'dalam_proses',
                'catatan' => $valid['catatan'] ?? null,
                'id_pegawai' => $user->id,
            ]);
            $application->peringkat()->create(['peringkat' => 'pendaftaran', 'status' => 'selesai', 'tarikh' => $valid['tarikh_permohonan'], 'id_pegawai' => $user->id]);

            return $application;
        });
    }

    private function nextNumber(): string
    {
        $prefix = 'HMK/'.now()->format('Y').'/';
        $last = PermohonanHakMilikKekal::query()->where('no_permohonan', 'like', $prefix.'%')->count();

        return $prefix.str_pad((string) ($last + 1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<array<string, mixed>>  $items  [{kod, lengkap, id_dokumen?}] or new items {kod, label, lengkap}
     *
     * @throws ValidationException
     */
    public function checklist(PermohonanHakMilikKekal $application, array $items): PermohonanHakMilikKekal
    {
        $this->assertOpen($application);
        $valid = Validator::make(['senarai' => $items], [
            'senarai' => ['required', 'array', 'min:1', 'max:50'],
            'senarai.*.kod' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', 'distinct'],
            'senarai.*.label' => ['nullable', 'string', 'max:200'],
            'senarai.*.lengkap' => ['required', 'boolean'],
            'senarai.*.id_dokumen' => ['nullable', 'string', 'max:64'],
        ])->validate();

        $current = collect($application->senarai_semak_dokumen)->keyBy('kod');
        $list = [];
        foreach ($valid['senarai'] as $item) {
            $label = $item['label'] ?? ($current[$item['kod']]['label'] ?? null);
            if ($label === null) {
                throw ValidationException::withMessages(['senarai' => __('hkm::hkm.ralat.label_semak_wajib')]);
            }
            $list[] = ['kod' => $item['kod'], 'label' => $label, 'lengkap' => (bool) $item['lengkap'], 'id_dokumen' => $item['id_dokumen'] ?? null];
        }
        $application->forceFill(['senarai_semak_dokumen' => $list])->save();

        return $application;
    }

    /**
     * Move to a stage (or record its rejection/cancellation).
     *
     * @param  array<string, mixed>  $data  peringkat, status (selesai|ditolak|dibatalkan), tarikh, catatan
     *
     * @throws ValidationException
     */
    public function advance(PermohonanHakMilikKekal $application, array $data, User $user): PermohonanHakMilikKekal
    {
        $this->assertOpen($application);
        $stages = array_keys(Kod::PERINGKAT_HMK);
        $valid = Validator::make($data, [
            'peringkat' => ['required', Rule::in(array_diff($stages, ['penerimaan', 'selesai']))],
            'status' => ['required', Rule::in(['dalam_proses', 'selesai', 'ditolak', 'dibatalkan'])],
            'tarikh' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'catatan' => ['nullable', 'string', 'max:5000'],
        ], [], ['peringkat' => 'peringkat', 'tarikh' => 'tarikh'])->validate();

        if (array_search($valid['peringkat'], $stages, true) < array_search($application->peringkat_semasa, $stages, true)) {
            throw ValidationException::withMessages(['peringkat' => __('hkm::hkm.ralat.peringkat_undur')]);
        }
        if (in_array($valid['status'], ['ditolak', 'dibatalkan'], true) && empty($valid['catatan'])) {
            throw ValidationException::withMessages(['catatan' => __('hkm::hkm.ralat.catatan_wajib')]);
        }

        DB::transaction(function () use ($application, $valid, $user) {
            $application->peringkat()->create(['peringkat' => $valid['peringkat'], 'status' => $valid['status'], 'tarikh' => $valid['tarikh'], 'catatan' => $valid['catatan'] ?? null, 'id_pegawai' => $user->id]);
            $application->forceFill([
                'peringkat_semasa' => $valid['peringkat'],
                'status_kelulusan' => match ($valid['status']) {
                    'ditolak' => 'ditolak',
                    'dibatalkan' => 'dibatalkan',
                    default => $valid['peringkat'] === 'kelulusan_pt' && $valid['status'] === 'selesai' ? 'lulus' : $application->status_kelulusan,
                },
            ])->save();
        });

        return $application;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function payment(PermohonanHakMilikKekal $application, array $data): PermohonanHakMilikKekal
    {
        $this->assertOpen($application);
        $valid = Validator::make($data, [
            'jumlah_bayaran' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'tarikh_bayaran' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'no_resit' => ['required', 'string', 'max:60'],
        ], [], ['jumlah_bayaran' => 'jumlah bayaran', 'tarikh_bayaran' => 'tarikh bayaran', 'no_resit' => 'nombor resit'])->validate();

        $application->forceFill($valid)->save();

        return $application;
    }

    /**
     * Receive the new permanent title and re-register it (SRS-HKM-16, acceptance #7).
     *
     * @param  array<string, mixed>  $data  no_hak_milik, jenis_hak_milik, tarikh_pendaftaran, tarikh_terima, + optional title fields, peti_besi (deposit)
     *
     * @throws ValidationException
     */
    public function receive(PermohonanHakMilikKekal $application, array $data, User $user): PermohonanHakMilikKekal
    {
        $this->assertOpen($application);
        if (! $application->semakLengkap()) {
            throw ValidationException::withMessages(['senarai_semak_dokumen' => __('hkm::hkm.ralat.semak_tidak_lengkap')]);
        }
        if ($application->no_resit === null) {
            throw ValidationException::withMessages(['no_resit' => __('hkm::hkm.ralat.bayaran_belum')]);
        }

        $tarikhTerima = Validator::make($data, ['tarikh_terima' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']], [], ['tarikh_terima' => 'tarikh terima'])->validate()['tarikh_terima'];

        /** @var HakMilik $temporary */
        $temporary = HakMilik::query()->withoutGlobalScope('access')->findOrFail($application->hak_milik_sementara_id);
        $fields = array_merge([
            'kategori_tanah' => $temporary->kategori_tanah,
            'syarat_nyata' => $temporary->syarat_nyata,
            'keluasan' => $temporary->keluasan,
            'unit_keluasan' => $temporary->unit_keluasan,
            'pemilik_berdaftar' => $temporary->pemilik_berdaftar,
            'pejabat_pendaftaran' => $application->pejabat_tanah,
        ], array_filter($data, fn ($v) => $v !== null && $v !== ''), ['lot_id' => $temporary->lot_id, 'id_hak_milik_digantikan' => $temporary->id, 'status_semasa' => $temporary->status_semasa === 'diganti' ? 'aktif' : $temporary->status_semasa]);
        $valid = $this->titles->validate($fields);
        if (($this->ref->jenisHakMilik()[$valid['jenis_hak_milik']]['sementara'] ?? true) === true) {
            throw ValidationException::withMessages(['jenis_hak_milik' => __('hkm::hkm.ralat.bukan_kekal')]);
        }
        $vault = is_array($data['peti_besi'] ?? null) ? $data['peti_besi'] : null;

        return DB::transaction(function () use ($application, $temporary, $valid, $vault, $user, $tarikhTerima) {
            $new = $this->titles->register($temporary->lot_id, $valid, $user, $vault, [
                'sebab' => __('hkm::hkm.sejarah.hak_milik_kekal', ['no' => $temporary->no_hak_milik]),
                'sumber' => 'hak_milik_kekal',
            ]);

            $before = $temporary->snapshot();
            $this->history->status($temporary, $temporary->status_semasa, 'diganti', [
                'tarikh_berkuat_kuasa' => $valid['tarikh_pendaftaran'] ?? $tarikhTerima,
                'sebab' => __('hkm::hkm.sejarah.diganti_oleh', ['no' => $new->no_hak_milik, 'permohonan' => $application->no_permohonan]),
                'no_rujukan_dokumen' => $application->no_permohonan,
                'id_pegawai' => $user->id,
            ]);
            $temporary->forceFill(['status_semasa' => 'diganti', 'versi_rekod' => $temporary->versi_rekod + 1])->save();
            $this->history->version($temporary, 'diganti', $before, ['justifikasi' => $application->no_permohonan, 'id_pengguna' => $user->id]);
            DaftarPetiBesi::query()->where('hak_milik_id', $temporary->id)->where('status_dokumen_asal', 'dalam_simpanan')
                ->get()->each(fn (DaftarPetiBesi $d) => $d->forceFill(['status_dokumen_asal' => 'diganti'])->save());

            $application->peringkat()->create(['peringkat' => 'penerimaan', 'status' => 'selesai', 'tarikh' => $tarikhTerima, 'catatan' => $new->no_hak_milik, 'id_pegawai' => $user->id]);
            $application->forceFill([
                'hak_milik_baharu_id' => $new->id,
                'tarikh_terima_hak_milik_baharu' => $tarikhTerima,
                'peringkat_semasa' => 'selesai',
                'status_kelulusan' => 'selesai',
                'tempoh_proses_hari' => (int) $application->tarikh_permohonan->diffInDays($tarikhTerima),
            ])->save();

            return $application;
        });
    }

    private function assertOpen(PermohonanHakMilikKekal $application): void
    {
        if (! in_array($application->status_kelulusan, ['dalam_proses', 'lulus'], true)) {
            throw ValidationException::withMessages(['status_kelulusan' => __('hkm::hkm.ralat.permohonan_ditutup')]);
        }
    }
}
