<?php

namespace App\Modules\Tsu\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Tsu\Models\HasilUkur;
use App\Modules\Tsu\Models\KosUrusan;
use App\Modules\Tsu\Models\LantikanJurukur;
use App\Modules\Tsu\Models\UrusanDokumen;
use App\Modules\Tsu\Models\UrusanKeputusan;
use App\Modules\Tsu\Models\UrusanLot;
use App\Modules\Tsu\Models\UrusanPeringkat;
use App\Modules\Tsu\Models\UrusanTanah;
use App\Modules\Tsu\Support\FailUkur;
use App\Modules\Tsu\Support\Makluman;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * SRS-TSU-16..18: import survey results, validate the CRS, compute the area
 * on the WGS84 spheroid, compare with the documented area against the
 * configurable tolerance, side-by-side comparison with the original boundary,
 * and the GIS Specialist's accept / reject decision. Acceptance records the
 * surveyor's fee as a cost item (invoice + payment, SRS-TSU-13).
 */
class HasilUkurService
{
    /** Malaysia extent (lng/lat) — catches a wrong declared CRS. */
    private const BBOX = [99.0, 0.5, 120.0, 7.6];

    public function __construct(private Parameters $parameters, private DocumentStore $documents, private Makluman $makluman, private UrusanService $urusan) {}

    /**
     * @param  array{format_fail: string, sistem_koordinat: int, keluasan_dokumen?: string|null, lot_id?: string|null, no_lot_cadangan?: string|null, lantikan_id?: string|null, geojson?: string|null}  $data
     * @return list<HasilUkur>
     */
    public function import(UrusanTanah $urusan, array $data, ?UploadedFile $file, User $user): array
    {
        $this->urusan->assertOpen($urusan);
        $format = $data['format_fail'];
        $read = FailUkur::baca($format, $file ?? (string) ($data['geojson'] ?? ''), (int) $data['sistem_koordinat']);
        if (! empty($data['lot_id']) && ! UrusanLot::query()->where('urusan_id', $urusan->id)->where('lot_id', $data['lot_id'])->exists()) {
            throw ValidationException::withMessages(['lot_id' => [__('tsu::tsu.ukur.lot_bukan_urusan')]]);
        }
        if (! empty($data['lantikan_id']) && ! LantikanJurukur::query()->where('urusan_id', $urusan->id)->where('id', $data['lantikan_id'])->exists()) {
            throw ValidationException::withMessages(['lantikan_id' => [__('tsu::tsu.ukur.lantikan_bukan_urusan')]]);
        }
        $single = count($read['features']) === 1;
        $tolerance = (string) $this->parameters->get('tsu.toleransi_keluasan_peratus', 5);

        $docId = null;
        if ($file !== null) {
            try {
                $docId = $this->documents->store($file, 'urusan_tanah', $urusan->id, 'fail_gis', $user, 'dalaman', ['pautan_induk' => '/admin/tsu/urusan/'.$urusan->id]);
            } catch (DocumentRejected $e) {
                report($e); // e.g. DXF/ZIP not on the DMS whitelist — the geometry itself is still kept in hasil_ukur
            }
        }

        $rows = DB::transaction(function () use ($urusan, $data, $read, $single, $tolerance, $docId, $user, $format) {
            $out = [];
            foreach ($read['features'] as $i => $f) {
                $g = $this->geometry($f['geometry'], $read['srid']);
                $props = $f['properties'];
                $declared = ($single ? ($data['keluasan_dokumen'] ?? null) : null) ?? $props['keluasan'] ?? $props['KELUASAN'] ?? $props['luas'] ?? null;
                $declared = is_numeric($declared) ? number_format((float) $declared, 4, '.', '') : null;
                $noLot = ($single ? ($data['no_lot_cadangan'] ?? null) : null) ?? $props['no_lot'] ?? $props['NO_LOT'] ?? $props['lapisan'] ?? null;
                $sisihan = $declared !== null && (float) $declared > 0 ? round(abs($g['m2'] - (float) $declared) / (float) $declared * 100, 2) : null;
                $h = new HasilUkur;
                $h->forceFill([
                    'urusan_id' => $urusan->id, 'lantikan_id' => $data['lantikan_id'] ?? null,
                    'lot_id' => $single ? ($data['lot_id'] ?? null) : null, 'no_lot_cadangan' => $noLot !== null ? mb_substr((string) $noLot, 0, 50) : null,
                    'format_fail' => $format, 'sistem_koordinat' => (int) $data['sistem_koordinat'], 'keluasan_dikira' => round($g['m2'], 4),
                    'keluasan_dokumen' => $declared, 'sisihan_peratus' => $sisihan, 'toleransi_peratus' => $tolerance,
                    'dalam_toleransi' => $sisihan === null ? null : $sisihan <= (float) $tolerance,
                    'status_pengesahan' => 'menunggu', 'id_pemuat_naik' => $user->id, 'id_dokumen_fail' => $docId,
                    'geometri' => DB::raw("ST_GeomFromEWKB(decode('".$g['hex']."', 'hex'))"),
                ])->save();
                $out[] = $h->refresh();
                unset($i);
            }

            return $out;
        });

        $this->makluman->hantar('tsu.hasil_ukur_untuk_pengesahan', [], ['no_rujukan' => $urusan->no_rujukan_mais, 'bilangan' => count($rows)], '/admin/tsu/urusan/'.$urusan->id.'?tab=ukur');

        return $rows;
    }

    /**
     * GeoJSON geometry in $srid → valid WGS84 MultiPolygon (HEXEWKB) + geodesic area.
     *
     * @param  array<string, mixed>  $geometry
     * @return array{hex: string, m2: float}
     */
    public function geometry(array $geometry, int $srid): array
    {
        try {
            $row = DB::transaction(fn () => DB::selectOne(
                "WITH g AS (SELECT ST_Multi(ST_CollectionExtract(ST_MakeValid(ST_Transform(ST_Force2D(ST_SetSRID(ST_GeomFromGeoJSON(?), {$srid})), 4326)), 3)) AS geom)
                 SELECT ST_AsHEXEWKB(geom) AS hex, ST_IsEmpty(geom) AS kosong, ST_Area(geom::geography) AS m2, ST_XMin(geom) x0, ST_YMin(geom) y0, ST_XMax(geom) x1, ST_YMax(geom) y1 FROM g",
                [json_encode($geometry)],
            ));
        } catch (Throwable) {
            throw ValidationException::withMessages(['fail' => [__('tsu::tsu.ukur.geometri_tidak_sah')]]);
        }
        if ($row === null || $row->kosong || $row->hex === null) {
            throw ValidationException::withMessages(['fail' => [__('tsu::tsu.ukur.tiada_poligon')]]);
        }
        [$minX, $minY, $maxX, $maxY] = self::BBOX;
        if ($row->x0 < $minX || $row->y0 < $minY || $row->x1 > $maxX || $row->y1 > $maxY) {
            throw ValidationException::withMessages(['sistem_koordinat' => [__('tsu::tsu.ukur.luar_malaysia', ['srid' => $srid])]]);
        }

        return ['hex' => (string) $row->hex, 'm2' => (float) $row->m2];
    }

    /**
     * SRS-TSU-17 side-by-side: new geometry vs original boundary (the re-surveyed
     * lot, or the union of the urusan's original lots).
     *
     * @return array<string, mixed>
     */
    public function compare(HasilUkur $h): array
    {
        $new = DB::selectOne('SELECT ST_AsGeoJSON(geometri, 7) AS g FROM hasil_ukur WHERE id = ?', [$h->id]);
        $lotIds = $h->lot_id ? [$h->lot_id] : UrusanLot::query()->where('urusan_id', $h->urusan_id)->where('peranan_lot', '!=', 'baharu')->pluck('lot_id')->all();
        $orig = null;
        $origArea = null;
        if ($lotIds !== []) {
            $place = implode(',', array_fill(0, count($lotIds), '?'));
            $o = DB::selectOne("SELECT ST_AsGeoJSON(ST_Union(geometri), 7) AS g, SUM(ST_Area(geometri::geography)) AS m2 FROM data_spatial WHERE semasa AND lot_id IN ({$place})", $lotIds);
            $orig = $o?->g ? json_decode((string) $o->g, true) : null;
            $origArea = $o?->m2 !== null ? round((float) $o->m2, 4) : null;
        }
        $overlap = $orig !== null ? DB::selectOne(
            'SELECT ST_Area(ST_Intersection(h.geometri, o.g)::geography) AS m2 FROM hasil_ukur h, (SELECT ST_Union(geometri) AS g FROM data_spatial WHERE semasa AND lot_id IN ('.implode(',', array_fill(0, count($lotIds), '?')).')) o WHERE h.id = ?',
            [...$lotIds, $h->id],
        ) : null;

        return [
            'baharu' => ['type' => 'Feature', 'geometry' => json_decode((string) $new->g, true), 'properties' => ['jenis' => 'baharu', 'keluasan' => (float) $h->keluasan_dikira]],
            'asal' => $orig ? ['type' => 'Feature', 'geometry' => $orig, 'properties' => ['jenis' => 'asal', 'keluasan' => $origArea]] : null,
            'keluasan_dikira' => (string) $h->keluasan_dikira,
            'keluasan_dokumen' => $h->keluasan_dokumen !== null ? (string) $h->keluasan_dokumen : null,
            'keluasan_asal_dikira' => $origArea,
            'sisihan_peratus' => $h->sisihan_peratus !== null ? (float) $h->sisihan_peratus : null,
            'toleransi_peratus' => (float) $h->toleransi_peratus,
            'dalam_toleransi' => $h->dalam_toleransi,
            'peratus_bertindih_asal' => $overlap && $origArea ? round((float) $overlap->m2 / $origArea * 100, 2) : null,
        ];
    }

    /**
     * Accept / reject (GIS Specialist). The uploader cannot verify their own
     * import; accepting a result outside the tolerance needs a justification.
     */
    public function verify(HasilUkur $h, string $status, User $user, ?string $catatan, ?string $pelanAkuiDocId = null): HasilUkur
    {
        $urusan = $h->urusan;
        $this->urusan->assertOpen($urusan);
        if ($h->status_pengesahan !== 'menunggu') {
            throw ValidationException::withMessages(['status_pengesahan' => [__('tsu::tsu.ukur.sudah_disahkan')]]);
        }
        if ($h->id_pemuat_naik === $user->id) {
            throw ValidationException::withMessages(['status_pengesahan' => [__('tsu::tsu.ukur.pengesah_sama')]]);
        }
        if ($status === 'diterima' && $h->dalam_toleransi === false && trim((string) $catatan) === '') {
            throw ValidationException::withMessages(['catatan' => [__('tsu::tsu.ukur.luar_toleransi_justifikasi')]]);
        }
        if ($pelanAkuiDocId !== null && ! UrusanDokumen::query()->where('urusan_id', $urusan->id)->where('id_dokumen_dms', $pelanAkuiDocId)->exists()) {
            throw ValidationException::withMessages(['id_dokumen_pelan_akui' => [__('tsu::tsu.ukur.pelan_bukan_urusan')]]);
        }

        DB::transaction(function () use ($h, $status, $user, $catatan, $pelanAkuiDocId, $urusan) {
            $h->forceFill(['status_pengesahan' => $status, 'id_pegawai_sah' => $user->id, 'tarikh_sah' => now(), 'catatan_pengesahan' => $catatan, 'id_dokumen_pelan_akui' => $pelanAkuiDocId])->save();
            UrusanKeputusan::query()->create(['urusan_id' => $urusan->id, 'konteks' => 'hasil_ukur', 'jenis_keputusan' => $status, 'justifikasi' => $catatan, 'id_pelulus' => $user->id, 'id_dokumen_kelulusan' => $pelanAkuiDocId]);
            if ($status === 'diterima' && $h->lantikan_id) {
                $l = LantikanJurukur::query()->findOrFail($h->lantikan_id);
                $l->forceFill(['status' => 'siap', 'tarikh_siap' => $l->tarikh_siap ?? today(), 'peratus_siap_semasa' => 100])->save();
                $exists = KosUrusan::query()->where('urusan_id', $urusan->id)->where('kategori_kos', 'upah_ukur')->where('rujukan_grp', 'lantikan:'.$l->id)->exists();
                if (! $exists) {
                    $stage = UrusanPeringkat::query()->where('urusan_id', $urusan->id)->whereJsonContains('senarai_kategori_kos', 'upah_ukur')->orderBy('no_urutan')->first();
                    KosUrusan::query()->create([
                        'urusan_id' => $urusan->id, 'peringkat_id' => $stage?->id, 'kategori_kos' => 'upah_ukur', 'amaun' => $l->nilai_kontrak,
                        'keterangan' => 'Upah ukur — '.$l->jurukur->nama_firma.' ('.$l->no_surat_setuju_terima.')', 'status_bayaran' => 'belum_bayar',
                        'tarikh_akhir_bayaran' => today()->addDays(30), 'rujukan_grp' => 'lantikan:'.$l->id, 'id_pencipta' => $user->id,
                    ]);
                }
            }
        });

        return $h->refresh();
    }
}
