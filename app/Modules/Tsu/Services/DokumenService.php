<?php

namespace App\Modules\Tsu\Services;

use App\Models\User;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Tsu\Models\UrusanDokumen;
use App\Modules\Tsu\Models\UrusanPeringkat;
use App\Modules\Tsu\Models\UrusanTanah;
use App\Modules\Tsu\Support\Lalai;
use App\Modules\Tsu\Support\Wang;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * SRS-TSU-25 (SRS-GEN-04): urusan documents are stored ONLY in MOD-DMS
 * (versioning, timestamps, access trail); MOD-TSU keeps the link with stage,
 * category, document date and expiry (for completeness checks, the disposal
 * valuation rule and expiry reminders). Valuation reports are Rahsia.
 */
class DokumenService
{
    public function __construct(private DocumentStore $documents) {}

    /** @param array{kategori_dokumen: string, peringkat_id?: int|null, tarikh_dokumen?: string|null, tarikh_luput?: string|null, nilai_penilaian?: string|null} $data */
    public function upload(UrusanTanah $urusan, UploadedFile $file, array $data, User $user): UrusanDokumen
    {
        if ($urusan->dikunci) {
            throw new HttpException(409, __('tsu::tsu.urusan.dikunci'));
        }
        $kategori = $data['kategori_dokumen'];
        if (! empty($data['peringkat_id']) && ! UrusanPeringkat::query()->where('urusan_id', $urusan->id)->where('id', $data['peringkat_id'])->exists()) {
            throw ValidationException::withMessages(['peringkat_id' => [__('tsu::tsu.kos.peringkat_bukan_urusan')]]);
        }
        if ($kategori === 'laporan_penilaian') {
            if (! $user->can('tsu.rahsia.kemas_kini')) {
                throw new HttpException(403, __('tsu::tsu.dokumen.penilaian_rahsia'));
            }
            if (empty($data['nilai_penilaian']) || empty($data['tarikh_dokumen'])) {
                throw ValidationException::withMessages(['nilai_penilaian' => [__('tsu::tsu.dokumen.nilai_wajib')]]);
            }
        }
        $classification = $kategori === 'laporan_penilaian' ? 'rahsia' : $urusan->klasifikasi;
        $docId = $this->documents->store($file, 'urusan_tanah', $urusan->id, Lalai::KATEGORI_DOKUMEN[$kategori][1], $user, $classification, [
            'pautan_induk' => '/admin/tsu/urusan/'.$urusan->id, 'no_rujukan_urusan' => $urusan->no_rujukan_mais, 'kategori_tsu' => $kategori,
        ]);

        return DB::transaction(function () use ($urusan, $file, $data, $user, $docId, $kategori) {
            $d = new UrusanDokumen;
            $d->forceFill([
                'urusan_id' => $urusan->id, 'peringkat_id' => $data['peringkat_id'] ?? UrusanPeringkat::query()->where('urusan_id', $urusan->id)->where('no_urutan', $urusan->peringkat_semasa)->value('id'),
                'id_dokumen_dms' => $docId, 'kategori_dokumen' => $kategori, 'nama_fail' => mb_substr($file->getClientOriginalName(), 0, 255),
                'tarikh_dokumen' => $data['tarikh_dokumen'] ?? null, 'tarikh_luput' => $data['tarikh_luput'] ?? null, 'tarikh_muat_naik' => now(), 'id_pemuat_naik' => $user->id,
            ])->save();
            if ($kategori === 'laporan_penilaian') {
                $urusan->forceFill(['nilai_penilaian' => Wang::norm((string) $data['nilai_penilaian'])])->save();
            }

            return $d;
        });
    }
}
