<?php

use App\Modules\Pro\Support\GovernanceDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SRS-DAT-07 / SRS-PRO-04: the named views and stored procedures of the
 * physical model, plus the seeded governance defaults (stewardship register,
 * quality rules, classification, curated dictionary, retention schedule).
 *
 *  - vw_profil_hartanah_360            one row per lot with spatial + satellite counts
 *  - vw_kualiti_data_rekod_tidak_lengkap  lots missing mandatory/expected fields
 *  - vw_lot_spatial_belum_disahkan     lots whose current geometry is missing or unverified
 *  - sp_kesan_duplikasi_lot(...)       candidate duplicate pairs with match metrics
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW vw_lot_spatial_belum_disahkan AS
            SELECT l.id AS lot_id, l.no_lot, l.kod_mukim, l.kod_daerah, l.bahagian_pemilik, l.id_data_steward,
                   ds.id AS id_spatial,
                   COALESCE(ds.status_pengesahan, 'tiada_geometri') AS status_pengesahan,
                   ds.sumber_data, ds.tarikh_perolehan_data
            FROM lot_tanah l
            LEFT JOIN data_spatial ds ON ds.lot_id = l.id AND ds.semasa
            WHERE l.deleted_at IS NULL AND l.status_lot <> 'arkib'
              AND (ds.id IS NULL OR ds.status_pengesahan IN ('belum_disahkan', 'bertindih'))
            SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW vw_kualiti_data_rekod_tidak_lengkap AS
            SELECT * FROM (
                SELECT l.id AS lot_id, l.no_lot, l.kod_mukim, l.kod_daerah, l.bahagian_pemilik, l.id_data_steward,
                       array_remove(ARRAY[
                           CASE WHEN l.keluasan IS NULL THEN 'keluasan' END,
                           CASE WHEN l.kategori_guna_tanah IS NULL THEN 'kategori_guna_tanah' END,
                           CASE WHEN l.bahagian_pemilik IS NULL THEN 'bahagian_pemilik' END,
                           CASE WHEN l.id_data_owner IS NULL THEN 'id_data_owner' END,
                           CASE WHEN l.id_data_steward IS NULL THEN 'id_data_steward' END,
                           CASE WHEN l.koordinat_pusat IS NULL THEN 'koordinat_pusat' END
                       ], NULL) AS medan_tidak_lengkap
                FROM lot_tanah l
                WHERE l.deleted_at IS NULL AND l.status_lot <> 'arkib'
            ) t
            WHERE cardinality(medan_tidak_lengkap) > 0
            SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW vw_profil_hartanah_360 AS
            SELECT l.id AS lot_id, l.no_lot, l.kod_negeri, l.kod_daerah, l.kod_mukim, l.keluasan, l.unit_keluasan,
                   l.kategori_guna_tanah, l.zoning, l.bahagian_pemilik, l.status_lot, l.klasifikasi, l.versi_rekod,
                   ST_Y(l.koordinat_pusat) AS lat, ST_X(l.koordinat_pusat) AS lng,
                   ds.status_pengesahan AS status_pengesahan_spatial, ds.keluasan_dikira, ds.sumber_data AS sumber_data_spatial,
                   (SELECT count(*) FROM penilaian p WHERE p.lot_id = l.id AND p.deleted_at IS NULL) AS bil_penilaian,
                   (SELECT count(*) FROM kontrak_pajakan k WHERE k.lot_id = l.id AND k.deleted_at IS NULL) AS bil_kontrak_pajakan,
                   (SELECT count(*) FROM cukai c WHERE c.lot_id = l.id AND c.deleted_at IS NULL) AS bil_cukai,
                   (SELECT count(*) FROM aduan a WHERE a.lot_id = l.id AND a.deleted_at IS NULL) AS bil_aduan,
                   (SELECT count(*) FROM kes_mahkamah_lot km WHERE km.lot_id = l.id) AS bil_kes_mahkamah,
                   l.updated_at
            FROM lot_tanah l
            LEFT JOIN data_spatial ds ON ds.lot_id = l.id AND ds.semasa
            WHERE l.deleted_at IS NULL
            SQL);

        // Candidate pairs only; scoring weights are configurable in the application
        // (pro.kriteria_duplikasi) so MAIS can tune them without a migration.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION sp_kesan_duplikasi_lot(p_ambang_nama real DEFAULT 0.6, p_lot uuid DEFAULT NULL)
            RETURNS TABLE (
                id_a uuid, id_b uuid, no_lot_a text, no_lot_b text,
                skor_nama real, sama_mukim boolean, beza_keluasan_peratus numeric, pertindihan_peratus numeric
            )
            LANGUAGE sql STABLE AS $$
                SELECT a.id, b.id, a.no_lot_normal::text, b.no_lot_normal::text,
                       similarity(a.no_lot_normal, b.no_lot_normal),
                       a.kod_mukim = b.kod_mukim,
                       CASE WHEN a.keluasan_m2 > 0 AND b.keluasan_m2 > 0
                            THEN round(abs(a.keluasan_m2 - b.keluasan_m2) / greatest(a.keluasan_m2, b.keluasan_m2) * 100, 2) END,
                       CASE WHEN ga.geometri IS NOT NULL AND gb.geometri IS NOT NULL AND ST_Intersects(ga.geometri, gb.geometri)
                            THEN round((ST_Area(ST_Intersection(ga.geometri, gb.geometri)::geography)
                                 / NULLIF(least(ST_Area(ga.geometri::geography), ST_Area(gb.geometri::geography)), 0) * 100)::numeric, 2)
                            ELSE 0 END
                FROM (SELECT l.*, CASE l.unit_keluasan WHEN 'hektar' THEN l.keluasan * 10000 WHEN 'ekar' THEN l.keluasan * 4046.8564 ELSE l.keluasan END AS keluasan_m2
                      FROM lot_tanah l WHERE l.deleted_at IS NULL AND l.status_lot NOT IN ('arkib', 'digabung')) a
                JOIN (SELECT l.*, CASE l.unit_keluasan WHEN 'hektar' THEN l.keluasan * 10000 WHEN 'ekar' THEN l.keluasan * 4046.8564 ELSE l.keluasan END AS keluasan_m2
                      FROM lot_tanah l WHERE l.deleted_at IS NULL AND l.status_lot NOT IN ('arkib', 'digabung')) b
                  ON a.id < b.id AND a.kod_daerah = b.kod_daerah
                LEFT JOIN data_spatial ga ON ga.lot_id = a.id AND ga.semasa
                LEFT JOIN data_spatial gb ON gb.lot_id = b.id AND gb.semasa
                WHERE (p_lot IS NULL OR a.id = p_lot OR b.id = p_lot)
                  AND (
                      similarity(a.no_lot_normal, b.no_lot_normal) >= p_ambang_nama
                      OR (a.kod_mukim = b.kod_mukim AND regexp_replace(a.no_lot_normal, '[^0-9]', '', 'g') = regexp_replace(b.no_lot_normal, '[^0-9]', '', 'g'))
                      OR (ga.geometri IS NOT NULL AND gb.geometri IS NOT NULL AND ST_Intersects(ga.geometri, gb.geometri)
                          AND NOT ST_Touches(ga.geometri, gb.geometri))
                  )
            $$
            SQL);

        GovernanceDefaults::seed();
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS sp_kesan_duplikasi_lot(real, uuid)');
        DB::statement('DROP VIEW IF EXISTS vw_profil_hartanah_360');
        DB::statement('DROP VIEW IF EXISTS vw_kualiti_data_rekod_tidak_lengkap');
        DB::statement('DROP VIEW IF EXISTS vw_lot_spatial_belum_disahkan');
    }
};
