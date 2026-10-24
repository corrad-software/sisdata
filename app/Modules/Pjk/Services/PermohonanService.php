<?php

namespace App\Modules\Pjk\Services;

use App\Models\User;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\PermohonanSewaan;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Pjk\Support\Kod;
use App\Modules\Pjk\Support\Penomboran;
use App\Modules\Pjk\Support\Wang;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * SRS-PJK-06 (process steps 2–4): applications from the public portal (two-way
 * API, idempotent on rujukan_portal) and from the counter join the same path:
 * review → contract draft → the same WFE approval → activation; the portal can
 * read the status back.
 */
class PermohonanService
{
    public function __construct(private PenyewaService $penyewa, private KontrakService $kontrak) {}

    /** @param array<string, mixed> $input */
    public function create(array $input, User $user, string $source): PermohonanSewaan
    {
        if ($source === 'portal' && ! empty($input['rujukan_portal'])) {
            $existing = PermohonanSewaan::query()->withoutGlobalScope('access')->where('rujukan_portal', $input['rujukan_portal'])->first();
            if ($existing !== null) {
                return $existing;
            }
        }
        $data = Validator::make($input, [
            'rujukan_portal' => [$source === 'portal' ? 'required' : 'nullable', 'string', 'max:80'],
            'kod_premis' => ['required_without:premis_id', 'nullable', 'string', 'max:40'],
            'premis_id' => ['required_without:kod_premis', 'nullable', 'integer'],
            'penyewa_id' => ['nullable', 'integer'],
            'pemohon' => ['required_without:penyewa_id', 'nullable', 'array'],
            'tarikh_mula_dicadang' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'tempoh_bulan' => ['nullable', 'integer', 'min:1', 'max:360'],
            'kadar_dicadang' => ['nullable', 'decimal:0,2', 'gt:0', 'max:99999999'],
            'tujuan' => ['nullable', 'string', 'max:500'],
        ], [], ['kod_premis' => 'premis', 'pemohon' => 'maklumat pemohon', 'tarikh_mula_dicadang' => 'tarikh mula dicadang'])->validate();

        $q = PremisAset::query()->withoutGlobalScope('access');
        $premis = ! empty($data['premis_id']) ? $q->find($data['premis_id']) : $q->where('kod_premis', $data['kod_premis'])->first();
        if ($premis === null || ($source === 'portal' && ! $premis->papar_portal)) {
            throw ValidationException::withMessages(['kod_premis' => __('pjk::pjk.ralat.premis_tiada')]);
        }
        if ($premis->status_premis !== 'kosong') {
            throw ValidationException::withMessages(['kod_premis' => __('pjk::pjk.ralat.premis_tidak_kosong')]);
        }
        $pemohon = null;
        if (! empty($data['pemohon'])) {
            $pemohon = $this->penyewa->validate($data['pemohon'], null, false); // validated now, tenant created at contract stage
        }

        return DB::transaction(fn () => PermohonanSewaan::query()->create([
            'no_permohonan' => Penomboran::next('permohonan'),
            'sumber' => $source,
            'rujukan_portal' => $data['rujukan_portal'] ?? null,
            'premis_id' => $premis->id,
            'penyewa_id' => $data['penyewa_id'] ?? null,
            'maklumat_pemohon' => $pemohon,
            'tarikh_mula_dicadang' => $data['tarikh_mula_dicadang'] ?? null,
            'tempoh_bulan' => (int) ($data['tempoh_bulan'] ?? 24),
            'kadar_dicadang' => isset($data['kadar_dicadang']) ? Wang::norm((string) $data['kadar_dicadang']) : $premis->kadar_tawaran,
            'tujuan' => $data['tujuan'] ?? null,
            'status' => 'diterima',
            'kod_daerah' => $premis->kod_daerah,
            'id_pegawai' => $source === 'kaunter' ? $user->id : null,
        ]));
    }

    public function reject(PermohonanSewaan $p, string $note, User $user): PermohonanSewaan
    {
        if (! in_array($p->status, ['diterima', 'dalam_semakan'], true)) {
            throw ValidationException::withMessages(['status' => __('pjk::pjk.ralat.permohonan_status')]);
        }
        $p->forceFill(['status' => 'tolak', 'catatan' => $note, 'id_pegawai' => $user->id])->save();

        return $p;
    }

    /**
     * Prepare the contract draft from an application (and optionally submit it to WFE).
     *
     * @param  array<string, mixed>  $input  kadar, deposit, tarikh_mula, tarikh_tamat, kitaran_caj, jenis_perjanjian, terma, hantar
     */
    public function toContract(PermohonanSewaan $p, array $input, User $user): Kontrak
    {
        if (! in_array($p->status, ['diterima', 'dalam_semakan'], true) || $p->kontrak_id !== null) {
            throw ValidationException::withMessages(['status' => __('pjk::pjk.ralat.permohonan_status')]);
        }

        return DB::transaction(function () use ($p, $input, $user) {
            $penyewa = $p->penyewa_id !== null ? $p->penyewa : $this->penyewa->findOrCreate((array) $p->maklumat_pemohon, $user);
            $start = $input['tarikh_mula'] ?? ($p->tarikh_mula_dicadang?->format('Y-m-d') ?? Carbon::today()->addDays(14)->format('Y-m-d'));
            $kontrak = $this->kontrak->create([
                'premis_id' => $p->premis_id,
                'penyewa_id' => $penyewa?->id,
                'tarikh_mula' => $start,
                'tarikh_tamat' => $input['tarikh_tamat'] ?? Carbon::parse($start)->addMonthsNoOverflow($p->tempoh_bulan)->subDay()->format('Y-m-d'),
                'kadar' => $input['kadar'] ?? $p->kadar_dicadang,
                'deposit' => $input['deposit'] ?? ($p->kadar_dicadang !== null ? Wang::dec(Wang::sen($p->kadar_dicadang) * 2) : '0.00'),
                'kitaran_caj' => $input['kitaran_caj'] ?? 'bulanan',
                'jenis_perjanjian' => $input['jenis_perjanjian'] ?? 'sewaan',
                'terma' => $input['terma'] ?? null,
                'sumber' => $p->sumber,
                'permohonan_id' => $p->id,
            ], $user);
            $p->forceFill(['status' => 'dalam_semakan', 'kontrak_id' => $kontrak->id, 'penyewa_id' => $penyewa?->id, 'id_pegawai' => $user->id])->save();
            if (! empty($input['hantar'])) {
                $this->kontrak->submit($kontrak, $user);
            }

            return $kontrak;
        });
    }

    /** @return array{no_permohonan: string, rujukan_portal: string|null, status: string, status_nama: string, catatan: string|null, dikemas_kini: string} */
    public function statusFor(PermohonanSewaan $p): array
    {
        return [
            'no_permohonan' => $p->no_permohonan, 'rujukan_portal' => $p->rujukan_portal, 'status' => $p->status,
            'status_nama' => Kod::STATUS_PERMOHONAN[$p->status] ?? $p->status,
            'catatan' => $p->status === 'tolak' ? $p->catatan : null, 'dikemas_kini' => $p->updated_at?->toIso8601String() ?? '',
        ];
    }
}
