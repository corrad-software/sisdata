<?php

namespace App\Modules\Dms\Http\Resources;

use App\Models\User;
use App\Modules\Dms\Contracts\RecordLinks;
use App\Modules\Dms\Models\Dokumen;
use App\Modules\Dms\Models\DokumenVersi;
use App\Modules\Dms\Services\Access\DocumentAccess;
use App\Modules\Dms\Services\Upload\FileTypes;

/** JSON shapes for the DMS API (kept in one place so the frontend types match). */
class DokumenPresenter
{
    public function __construct(private RecordLinks $links, private DocumentAccess $access) {}

    /** @return array<string, mixed> */
    public function summary(Dokumen $doc): array
    {
        return [
            'id' => $doc->id,
            'no_rujukan' => $doc->no_rujukan,
            'tajuk' => $doc->tajuk,
            'nama_fail' => $doc->nama_fail,
            'sambungan' => $doc->sambungan,
            'jenis_mime' => $doc->jenis_mime,
            'saiz_bait' => $doc->saiz_bait,
            'kategori' => ['id' => $doc->kategori->id, 'kod' => $doc->kategori->kod, 'nama' => $doc->kategori->nama],
            'modul_sumber' => $doc->modul_sumber,
            'jenis_entiti_induk' => $doc->jenis_entiti_induk,
            'id_rekod_induk' => $doc->id_rekod_induk,
            'klasifikasi' => $doc->klasifikasi_keselamatan,
            'status' => $doc->status,
            'versi_semasa' => $doc->versi_semasa,
            'tahun' => $doc->tahun,
            'kod_daerah' => $doc->kod_daerah,
            'kod_mukim' => $doc->kod_mukim,
            'tarikh_dokumen' => $doc->tarikh_dokumen?->toDateString(),
            'tarikh_luput' => $doc->tarikh_luput?->toDateString(),
            'kata_kunci' => $doc->kata_kunci,
            'status_indeks' => $doc->status_indeks,
            'pratonton' => $doc->status === 'dilupus' ? 'tiada' : FileTypes::previewable($doc->sambungan),
            'petikan' => $doc->getAttribute('petikan') ?: null,
            'dicipta_pada' => $doc->created_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function detail(Dokumen $doc, User $user): array
    {
        $doc->loadMissing(['kategori', 'pencipta:id,name', 'pemilikProses:id,name']);

        $parent = null;
        if ($doc->jenis_entiti_induk !== null && $doc->id_rekod_induk !== null) {
            $resolved = $this->links->resolve($doc->jenis_entiti_induk, $doc->id_rekod_induk, $user);
            $parent = [
                'jenis' => $doc->jenis_entiti_induk,
                'id' => $doc->id_rekod_induk,
                'label' => $resolved['label'],
                'pautan' => $doc->pautan_induk ?? $resolved['link'],
            ];
        }

        $owner = $doc->id_pencipta === $user->id;

        return [
            ...$this->summary($doc),
            'metadata' => (object) $doc->metadata,
            'cincangan_fail' => $doc->cincangan_fail,
            'kaedah_indeks' => $doc->kaedah_indeks,
            'tarikh_arkib' => $doc->tarikh_arkib?->toIso8601String(),
            'tarikh_lupus' => $doc->tarikh_lupus?->toIso8601String(),
            'pencipta' => $doc->pencipta ? ['id' => $doc->pencipta->id, 'nama' => $doc->pencipta->name] : null,
            'pemilik_proses' => $doc->pemilikProses ? ['id' => $doc->pemilikProses->id, 'nama' => $doc->pemilikProses->name] : null,
            'induk' => $parent,
            'tanda_air' => $doc->isTerhad(),
            'boleh' => [
                'kemas_kini' => $doc->status !== 'dilupus' && $this->access->allows($user, 'dms.dokumen.kemas_kini'),
                'versi_baharu' => in_array($doc->status, ['aktif', 'tamat'], true) && $this->access->allows($user, 'dms.dokumen.cipta'),
                'padam' => in_array($doc->status, ['aktif', 'tamat'], true) && ($this->access->allows($user, 'dms.dokumen.padam')),
                'arkib' => in_array($doc->status, ['aktif', 'tamat'], true) && $this->access->allows($user, 'dms.arkib.kemas_kini'),
                'mohon_lupus' => $doc->status === 'diarkib' && $this->access->allows($user, 'dms.pelupusan.cipta'),
                'urus_kebenaran' => $doc->isTerhad() && ($owner || $this->access->allows($user, 'dms.kebenaran.kemas_kini')),
                'muat_turun' => $doc->status !== 'dilupus',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function version(DokumenVersi $version): array
    {
        return [
            'id' => $version->id,
            'no_versi' => $version->no_versi,
            'nama_fail' => $version->nama_fail,
            'jenis_mime' => $version->jenis_mime,
            'saiz_bait' => $version->saiz_bait,
            'cincangan_fail' => $version->cincangan_fail,
            'algoritma' => $version->algoritma,
            'catatan' => $version->catatan,
            'dimuat_naik_oleh' => $version->pemuatNaik ? ['id' => $version->pemuatNaik->id, 'nama' => $version->pemuatNaik->name] : null,
            'dimuat_naik_pada' => $version->created_at->toIso8601String(),
            'tersedia' => $version->kunci_storan !== null,
            'metadata' => $version->metadata_snapshot,
        ];
    }
}
