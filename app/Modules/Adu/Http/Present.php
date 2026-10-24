<?php

namespace App\Modules\Adu\Http;

use App\Models\User;
use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Models\KronologiAduan;
use App\Modules\Adu\Services\SlaAduan;
use App\Modules\Adu\Support\Lalai;
use App\Modules\Adu\Support\Rujukan;
use App\Modules\Pro\Contracts\LotRegistry;

/** JSON shapes of MOD-ADU (labels in Malay). Never includes reporter identity. */
class Present
{
    public function __construct(private Rujukan $ref, private SlaAduan $sla, private LotRegistry $lots) {}

    /**
     * @param  array<string, array<string, mixed>>  $lots  lot summaries keyed by id (from LotRegistry::summaries)
     * @return array<string, mixed>
     */
    public function aduan(Aduan $a, array $lots = []): array
    {
        $lot = $a->lot_id !== null ? ($lots[$a->lot_id] ?? null) : null;

        return [
            'id' => $a->id,
            'no_rujukan_aduan' => $a->no_rujukan_aduan,
            'saluran_penerimaan' => $a->saluran_penerimaan,
            'saluran_nama' => Lalai::SALURAN[$a->saluran_penerimaan] ?? $a->saluran_penerimaan,
            'tarikh_terima' => $a->tarikh_terima?->toIso8601String(),
            'kategori' => $a->kategori,
            'kategori_nama' => $this->ref->namaKategori($a->kategori),
            'subkategori' => $a->subkategori,
            'subkategori_nama' => $this->ref->namaSubkategori($a->kategori, $a->subkategori),
            'kategori_dicadang' => $a->kategori_dicadang,
            'keterangan' => $a->keterangan,
            'lokasi_teks' => $a->lokasi_teks,
            'lot_id' => $a->lot_id,
            'no_lot' => $lot['no_lot'] ?? null,
            'kod_daerah' => $a->kod_daerah,
            'daerah' => $this->ref->namaDaerah($a->kod_daerah),
            'kod_mukim' => $a->kod_mukim,
            'mukim' => $this->ref->namaMukim($a->kod_mukim),
            'status' => $a->status,
            'status_nama' => Lalai::STATUS[$a->status] ?? $a->status,
            'tahap_keutamaan' => $a->tahap_keutamaan,
            'keutamaan_nama' => Lalai::KEUTAMAAN[$a->tahap_keutamaan] ?? $a->tahap_keutamaan,
            'status_tanpa_nama' => $a->status_tanpa_nama,
            'status_bukan_tanah_mais' => $a->status_bukan_tanah_mais,
            'id_aduan_pendua_dicadang' => $a->id_aduan_pendua_dicadang,
            'skor_pendua' => $a->skor_pendua,
            'id_aduan_induk' => $a->id_aduan_induk,
            'pegawai' => $a->pegawai ? ['id' => $a->pegawai->id, 'nama' => $a->pegawai->name] : null,
            'bilangan_buka_semula' => $a->bilangan_buka_semula,
            'tarikh_tutup' => $a->tarikh_tutup?->toIso8601String(),
            'sintetik' => $a->sintetik,
            'lat' => $a->getAttribute('lat') !== null ? (float) $a->getAttribute('lat') : null,
            'lng' => $a->getAttribute('lng') !== null ? (float) $a->getAttribute('lng') : null,
            'sla' => $a->terbuka() || $a->relationLoaded('slaSemasa') ? $this->sla->baki($a->slaSemasa) : null,
        ];
    }

    /** @return array<string, mixed> */
    public function kronologi(KronologiAduan $k): array
    {
        return [
            'id' => $k->id,
            'tarikh_masa' => $k->tarikh_masa->toIso8601String(),
            'jenis_tindakan' => $k->jenis_tindakan,
            'jenis_nama' => Lalai::JENIS_TINDAKAN[$k->jenis_tindakan] ?? $k->jenis_tindakan,
            'keterangan' => $k->keterangan_tindakan,
            'pegawai' => $k->pegawai?->name,
            'status_sebelum' => $k->status_sebelum,
            'status_selepas' => $k->status_selepas,
            'komunikasi_pengadu' => $k->komunikasi_pengadu,
            'id_dokumen' => $k->id_dokumen_berkaitan,
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function lotsFor(iterable $aduan, ?User $user): array
    {
        $ids = [];
        foreach ($aduan as $a) {
            if ($a->lot_id) {
                $ids[] = $a->lot_id;
            }
        }

        return $ids === [] ? [] : $this->lots->summaries(array_values(array_unique($ids)), $user);
    }
}
