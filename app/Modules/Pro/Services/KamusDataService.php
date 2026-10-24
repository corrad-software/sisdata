<?php

namespace App\Modules\Pro\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data dictionary + physical model (SRS-PRO-03/04/05, SRS-DAT-07), always
 * generated from the LIVE schema (PostgreSQL catalog) so it cannot drift,
 * merged with the curated business metadata in kamus_data.
 */
class KamusDataService
{
    /** Framework/infrastructure tables left out of the business dictionary. */
    public const EXCLUDE = ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens', 'spatial_ref_sys', 'personal_access_tokens'];

    /** @return list<array{nama_jadual: string, jenis: string, komen: string|null, nama_perniagaan: string|null, definisi: string|null, klasifikasi: string, bil_medan: int}> */
    public function tables(bool $includeInfra = false): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT c.relname AS nama_jadual,
                   CASE c.relkind WHEN 'r' THEN 'jadual' WHEN 'p' THEN 'jadual' WHEN 'v' THEN 'view' WHEN 'm' THEN 'materialized_view' END AS jenis,
                   obj_description(c.oid, 'pg_class') AS komen,
                   (SELECT count(*) FROM pg_attribute a WHERE a.attrelid = c.oid AND a.attnum > 0 AND NOT a.attisdropped) AS bil_medan
            FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public' AND c.relkind IN ('r', 'p', 'v', 'm')
              AND NOT EXISTS (SELECT 1 FROM pg_depend d WHERE d.objid = c.oid AND d.deptype = 'e')
            ORDER BY c.relname
            SQL);

        $curated = DB::table('kamus_data')->whereNull('nama_medan')->get()->keyBy('nama_jadual');
        $classes = DB::table('klasifikasi_data')->pluck('aras_kerahsiaan', 'entiti');

        return collect($rows)
            ->filter(fn ($r) => $includeInfra || ! in_array($r->nama_jadual, self::EXCLUDE, true))
            ->map(fn ($r) => [
                'nama_jadual' => (string) $r->nama_jadual,
                'jenis' => (string) $r->jenis,
                'komen' => $r->komen,
                'nama_perniagaan' => $curated[$r->nama_jadual]->nama_perniagaan ?? null,
                'definisi' => $curated[$r->nama_jadual]->definisi ?? $r->komen,
                'klasifikasi' => (string) ($curated[$r->nama_jadual]->klasifikasi ?? $classes[$r->nama_jadual] ?? 'dalaman'),
                'bil_medan' => (int) $r->bil_medan,
            ])->values()->all();
    }

    /**
     * Every column with technical + business metadata (SRS-PRO-05 field list).
     *
     * @return list<array<string, mixed>>
     */
    public function columns(?string $table = null, bool $includeInfra = false): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT c.table_name AS nama_jadual, c.column_name AS nama_medan, c.ordinal_position AS kedudukan,
                   CASE WHEN c.data_type = 'USER-DEFINED' THEN coalesce(
                        (SELECT format_type(a.atttypid, a.atttypmod) FROM pg_attribute a JOIN pg_class t ON t.oid = a.attrelid
                         JOIN pg_namespace n ON n.oid = t.relnamespace WHERE n.nspname = 'public' AND t.relname = c.table_name AND a.attname = c.column_name), c.udt_name)
                        ELSE c.data_type END AS jenis_data,
                   coalesce(c.character_maximum_length, c.numeric_precision) AS saiz, c.numeric_scale AS skala,
                   c.column_default AS nilai_lalai, c.is_nullable = 'YES' AS boleh_null,
                   col_description((quote_ident(c.table_schema) || '.' || quote_ident(c.table_name))::regclass, c.ordinal_position) AS komen
            FROM information_schema.columns c
            JOIN information_schema.tables t ON t.table_schema = c.table_schema AND t.table_name = c.table_name
            WHERE c.table_schema = 'public' AND (?::text IS NULL OR c.table_name = ?::text)
            ORDER BY c.table_name, c.ordinal_position
            SQL, [$table, $table]);

        $keys = $this->keyColumns();
        $curated = DB::table('kamus_data')->whereNotNull('nama_medan')->get()->keyBy(fn ($r) => $r->nama_jadual.'.'.$r->nama_medan);
        $tableCurated = DB::table('kamus_data')->whereNull('nama_medan')->get()->keyBy('nama_jadual');
        $classes = DB::table('klasifikasi_data')->pluck('aras_kerahsiaan', 'entiti');
        $stewards = $this->stewardsByTable();
        $extensionTables = collect($this->tables(true))->pluck('nama_jadual')->flip();

        return collect($rows)
            ->filter(fn ($r) => isset($extensionTables[$r->nama_jadual]) && ($includeInfra || ! in_array($r->nama_jadual, self::EXCLUDE, true)))
            ->map(function ($r) use ($keys, $curated, $tableCurated, $classes, $stewards) {
                $k = $r->nama_jadual.'.'.$r->nama_medan;
                $c = $curated[$k] ?? null;
                $steward = $stewards[$r->nama_jadual] ?? null;

                return [
                    'nama_jadual' => (string) $r->nama_jadual,
                    'nama_medan' => (string) $r->nama_medan,
                    'nama_perniagaan' => $c->nama_perniagaan ?? null,
                    'definisi' => $c->definisi ?? $r->komen,
                    'jenis_data' => (string) $r->jenis_data,
                    'saiz' => $r->saiz !== null ? (int) $r->saiz : null,
                    'skala' => $r->skala !== null ? (int) $r->skala : null,
                    'format' => $c->format ?? null,
                    'nilai_dibenarkan' => $c->nilai_dibenarkan ?? null,
                    'nilai_lalai' => $r->nilai_lalai,
                    'wajib' => $c !== null && $c->wajib !== null ? (bool) $c->wajib : ! $r->boleh_null,
                    'kunci' => $keys[$k] ?? [],
                    'data_owner' => $c->data_owner ?? $tableCurated[$r->nama_jadual]->data_owner ?? $steward['owner'] ?? null,
                    'data_steward' => $c->data_steward ?? $tableCurated[$r->nama_jadual]->data_steward ?? $steward['steward'] ?? null,
                    'klasifikasi' => (string) ($c->klasifikasi ?? $tableCurated[$r->nama_jadual]->klasifikasi ?? $classes[$r->nama_jadual] ?? 'dalaman'),
                    'dikurasi' => $c !== null,
                ];
            })->values()->all();
    }

    /**
     * Full physical model (SRS-PRO-04): constraints, indexes, views, functions.
     *
     * @return array{jadual: list<array<string, mixed>>, kekangan: list<object>, indeks: list<object>, view: list<object>, fungsi: list<object>}
     */
    public function physicalModel(): array
    {
        $constraints = DB::select(<<<'SQL'
            SELECT t.relname AS nama_jadual, con.conname AS nama, con.contype AS jenis, pg_get_constraintdef(con.oid) AS definisi
            FROM pg_constraint con JOIN pg_class t ON t.oid = con.conrelid JOIN pg_namespace n ON n.oid = t.relnamespace
            WHERE n.nspname = 'public' AND NOT EXISTS (SELECT 1 FROM pg_depend d WHERE d.objid = t.oid AND d.deptype = 'e')
            ORDER BY t.relname, con.contype, con.conname
            SQL);
        $indexes = DB::select(<<<'SQL'
            SELECT i.tablename AS nama_jadual, i.indexname AS nama, i.indexdef AS definisi,
                   (i.indexdef ILIKE '%USING gist%') AS spatial
            FROM pg_indexes i WHERE i.schemaname = 'public' AND i.tablename <> 'spatial_ref_sys'
            ORDER BY i.tablename, i.indexname
            SQL);
        $views = DB::select(<<<'SQL'
            SELECT c.relname AS nama, CASE c.relkind WHEN 'v' THEN 'view' ELSE 'materialized_view' END AS jenis, pg_get_viewdef(c.oid, true) AS definisi
            FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'public' AND c.relkind IN ('v', 'm')
              AND NOT EXISTS (SELECT 1 FROM pg_depend d WHERE d.objid = c.oid AND d.deptype = 'e')
            ORDER BY c.relname
            SQL);
        $functions = DB::select(<<<'SQL'
            SELECT p.proname AS nama, pg_get_function_identity_arguments(p.oid) AS argumen, pg_get_function_result(p.oid) AS hasil,
                   l.lanname AS bahasa, pg_get_functiondef(p.oid) AS definisi
            FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace JOIN pg_language l ON l.oid = p.prolang
            WHERE n.nspname = 'public' AND p.prokind IN ('f', 'p')
              AND NOT EXISTS (SELECT 1 FROM pg_depend d WHERE d.objid = p.oid AND d.deptype = 'e')
            ORDER BY p.proname
            SQL);

        return [
            'jadual' => $this->tables(true),
            'kekangan' => $constraints,
            'indeks' => $indexes,
            'view' => $views,
            'fungsi' => $functions,
        ];
    }

    /**
     * Foreign keys (for the ERD).
     *
     * @return list<object{dari: string, medan: string, ke: string, ke_medan: string, boleh_null: bool, unik: bool}>
     */
    public function foreignKeys(): array
    {
        return DB::select(<<<'SQL'
            SELECT src.relname AS dari, a.attname AS medan, dst.relname AS ke, b.attname AS ke_medan,
                   NOT a.attnotnull AS boleh_null,
                   EXISTS (SELECT 1 FROM pg_index ix WHERE ix.indrelid = src.oid AND ix.indisunique AND ix.indpred IS NULL AND ix.indnatts = 1 AND ix.indkey[0] = a.attnum) AS unik
            FROM pg_constraint con
            JOIN pg_class src ON src.oid = con.conrelid JOIN pg_class dst ON dst.oid = con.confrelid
            JOIN pg_namespace n ON n.oid = src.relnamespace
            JOIN pg_attribute a ON a.attrelid = con.conrelid AND a.attnum = con.conkey[1]
            JOIN pg_attribute b ON b.attrelid = con.confrelid AND b.attnum = con.confkey[1]
            WHERE n.nspname = 'public' AND con.contype = 'f'
            ORDER BY src.relname, a.attname
            SQL);
    }

    /** @return array<string, list<string>> "table.column" => [PK|FK|UQ] */
    private function keyColumns(): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT t.relname AS t, a.attname AS c, con.contype AS k
            FROM pg_constraint con JOIN pg_class t ON t.oid = con.conrelid JOIN pg_namespace n ON n.oid = t.relnamespace
            JOIN LATERAL unnest(con.conkey) AS k(attnum) ON true
            JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = k.attnum
            WHERE n.nspname = 'public' AND con.contype IN ('p', 'f', 'u')
            SQL);
        $out = [];
        foreach ($rows as $r) {
            $out[$r->t.'.'.$r->c][] = ['p' => 'PK', 'f' => 'FK', 'u' => 'UQ'][$r->k];
        }

        return array_map(fn ($v) => array_values(array_unique($v)), $out);
    }

    /** @return array<string, array{owner: string, steward: string}> */
    private function stewardsByTable(): array
    {
        $out = [];
        foreach (DB::table('daftar_stewardship_data')->where('status', 'aktif')->orderBy('id')->get() as $e) {
            foreach (explode(',', (string) $e->entiti_berkaitan) as $t) {
                $out[trim($t)] ??= ['owner' => $e->bahagian_data_owner, 'steward' => $e->jawatan_data_steward.' ('.$e->bahagian_data_steward.')'];
            }
        }

        return $out;
    }

    /** @return Collection<int, array<string, mixed>> */
    public function search(?string $q, ?string $table = null, ?string $klasifikasi = null): Collection
    {
        return collect($this->columns($table))
            ->when($q !== null && $q !== '', fn ($c) => $c->filter(fn ($r) => str_contains(mb_strtolower($r['nama_jadual'].' '.$r['nama_medan'].' '.($r['nama_perniagaan'] ?? '').' '.($r['definisi'] ?? '')), mb_strtolower((string) $q))))
            ->when($klasifikasi !== null && $klasifikasi !== '', fn ($c) => $c->where('klasifikasi', $klasifikasi))
            ->values();
    }
}
