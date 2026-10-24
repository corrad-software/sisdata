<?php

namespace App\Modules\Adu\Services;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Models\PeraturanTriaj;
use App\Modules\Adu\Support\LotSpatial;
use App\Modules\Adu\Support\Rujukan;
use App\Modules\Pro\Contracts\LotRegistry;
use Illuminate\Support\Facades\DB;

/**
 * SRS-ADU-09/10 automatic triage of every incoming complaint, whatever the
 * channel: (1) match the location to a MAIS lot (chosen lot, else the point
 * inside a lot polygon), (2) suggest a category / priority from keywords,
 * (3) look for repeat complaints on the same lot or nearby within the
 * configured window, and (4) flag "bukan tanah MAIS" with a drafted polite
 * reply + suggested agencies — for a Unit Aduan officer to confirm.
 */
class Triaj
{
    private const KEUTAMAAN_ARAS = ['rendah' => 1, 'sederhana' => 2, 'tinggi' => 3, 'kritikal' => 4];

    public function __construct(
        private LotSpatial $spatial,
        private LotRegistry $lots,
        private Rujukan $ref,
        private Parameters $parameters,
    ) {}

    /**
     * Mutates (does not save) the complaint and returns what was found, for the timeline.
     *
     * @return array<string, mixed>
     */
    public function jalankan(Aduan $aduan, ?float $lat, ?float $lng): array
    {
        $hasil = [];

        // (1) Lot match.
        if ($aduan->lot_id !== null && ($lot = $this->lots->find($aduan->lot_id)) !== null) {
            $aduan->kaedah_padanan_lot = 'pilihan';
            $aduan->kod_daerah = $lot['kod_daerah'];
            $aduan->kod_mukim = $lot['kod_mukim'];
            if ($lat === null && ($pusat = $this->spatial->pusatLot($aduan->lot_id))) {
                $aduan->setKoordinat($pusat['lat'], $pusat['lng']);
            }
        } elseif ($lat !== null && $lng !== null) {
            $aduan->lot_id = $this->spatial->lotAt($lat, $lng, (int) $this->parameters->get('adu.toleransi_padanan_meter', 0));
            if ($aduan->lot_id !== null && ($lot = $this->lots->find($aduan->lot_id)) !== null) {
                $aduan->kaedah_padanan_lot = 'koordinat';
                $aduan->kod_daerah = $lot['kod_daerah'];
                $aduan->kod_mukim = $lot['kod_mukim'];
            } else {
                $aduan->lot_id = null;
                $aduan->kaedah_padanan_lot = 'tiada';
                $kawasan = $this->ref->kawasanTitik($lat, $lng);
                $aduan->kod_daerah ??= $kawasan['kod_daerah'];
                $aduan->kod_mukim ??= $kawasan['kod_mukim'];
                // (4) SRS-ADU-10.
                $aduan->status_bukan_tanah_mais = true;
                $aduan->agensi_dicadang = $this->ref->agensiCadangan($aduan->kod_daerah);
            }
        }
        $hasil['lot_id'] = $aduan->lot_id;
        $hasil['kaedah_padanan_lot'] = $aduan->kaedah_padanan_lot;
        $hasil['bukan_tanah_mais'] = $aduan->status_bukan_tanah_mais;

        // (2) Keyword category / priority suggestion.
        [$kategori, $keutamaan, $kata] = $this->cadangKategori((string) $aduan->keterangan);
        if ($kategori !== null) {
            $aduan->kategori_dicadang = $kategori;
            if ($aduan->kategori === null || $aduan->kategori === '') {
                $aduan->kategori = $kategori;
            }
            if ($keutamaan !== null && $aduan->kategori === $kategori
                && (self::KEUTAMAAN_ARAS[$keutamaan] ?? 0) > (self::KEUTAMAAN_ARAS[$aduan->tahap_keutamaan] ?? 2)) {
                $aduan->tahap_keutamaan = $keutamaan;
            }
        }
        $aduan->kategori ??= 'LAIN';
        if ($aduan->status_bukan_tanah_mais) {
            $aduan->jawapan_automatik = $this->jawapanBukanMais($aduan);
        }
        $hasil['kategori_dicadang'] = $kategori;
        $hasil['kata_kunci'] = $kata;

        // (3) Repeat / duplicate check.
        $pendua = $this->cariPendua($aduan, $lat, $lng);
        if ($pendua !== null) {
            $aduan->id_aduan_pendua_dicadang = $pendua['id'];
            $aduan->skor_pendua = $pendua['skor'];
        }
        $hasil['pendua'] = $pendua;

        return $hasil;
    }

    /** @return array{0: string|null, 1: string|null, 2: list<string>} */
    public function cadangKategori(string $text): array
    {
        $haystack = ' '.mb_strtolower($text).' ';
        $best = [null, null, []];
        foreach (PeraturanTriaj::query()->where('jenis_peraturan', 'kata_kunci')->where('status_aktif', true)->orderBy('id')->get() as $rule) {
            $hits = array_values(array_filter($rule->kata_kunci ?? [], fn ($w) => $w !== '' && str_contains($haystack, mb_strtolower((string) $w))));
            if (count($hits) > count($best[2])) {
                $best = [$rule->kategori_cadangan, $rule->keutamaan_cadangan, $hits];
            }
        }

        return $best;
    }

    /**
     * Same category, same lot (or within the radius), within the window; text
     * similarity (pg_trgm) raises the score. Score 0–100.
     *
     * @return array{id: string, no_rujukan: string|null, skor: int}|null
     */
    public function cariPendua(Aduan $aduan, ?float $lat, ?float $lng): ?array
    {
        $rule = PeraturanTriaj::query()->where('jenis_peraturan', 'semakan_berulang')->where('status_aktif', true)->orderBy('id')->first();
        $days = $rule->tempoh_semakan_berulang ?? 30;
        $radius = $rule->jejari_meter ?? 100;
        if ($aduan->lot_id === null && ($lat === null || $lng === null)) {
            return null;
        }

        $bindings = [(string) $aduan->keterangan, $aduan->kategori, now()->subDays($days)];
        $where = [];
        if ($aduan->lot_id !== null) {
            $where[] = 'a.lot_id = ?';
            $bindings[] = $aduan->lot_id;
        }
        if ($lat !== null && $lng !== null) {
            $where[] = 'ST_DWithin(a.koordinat::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)';
            array_push($bindings, $lng, $lat, $radius);
        }
        $sql = 'SELECT a.id, a.no_rujukan_aduan, similarity(coalesce(a.keterangan, \'\'), ?) AS mirip
                FROM aduan a
                WHERE a.deleted_at IS NULL AND a.kategori = ? AND a.tarikh_terima >= ? AND a.status <> \'pendua\'
                  AND ('.implode(' OR ', $where).')';
        if ($aduan->exists) {
            $sql .= ' AND a.id <> ?';
            $bindings[] = $aduan->id;
        }
        $row = DB::selectOne($sql.' ORDER BY mirip DESC, a.tarikh_terima DESC LIMIT 1', $bindings);
        if (! $row) {
            return null;
        }

        return ['id' => (string) $row->id, 'no_rujukan' => $row->no_rujukan_aduan, 'skor' => (int) round(60 + 40 * (float) $row->mirip)];
    }

    private function jawapanBukanMais(Aduan $aduan): string
    {
        $agensi = implode('; ', array_map(fn ($a) => $a['nama'], $aduan->agensi_dicadang ?? []));

        return __('adu::adu.jawapan_bukan_mais', [
            'agensi' => $agensi !== '' ? $agensi : 'pihak berkuasa tempatan berkenaan',
            'kategori' => (string) $this->ref->namaKategori($aduan->kategori),
        ]);
    }
}
