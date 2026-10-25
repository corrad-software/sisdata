<?php

namespace App\Modules\Kba\Services;

use App\Models\User;
use App\Modules\Kba\Ai\Vektor;
use App\Modules\Kba\Contracts\EmbeddingProvider;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Hybrid retrieval (SRS-KBA-03/06/07/10): pgvector cosine search, Postgres
 * full-text (`simple` config, BM + glossary-expanded EN terms) and pg_trgm
 * fuzzy matching, fused by reciprocal rank. The permission predicate from
 * Capaian sits in EVERY leg's WHERE clause — filtering happens before
 * ranking, never after.
 */
class Pencari
{
    private const RRF_K = 60;

    private const CALON = 40;

    public function __construct(private Capaian $capaian, private EmbeddingProvider $embedder) {}

    /**
     * @param  array{modul?: string|null, jenis_dokumen?: string|null, versi?: string|null, dari?: string|null, hingga?: string|null, status?: string|null, pemilik?: int|null, id_sumber?: int|null}  $penapis
     * @param  bool  $semantik  false = conventional keyword search only (SRS-KBA-10)
     * @return list<array{id_segmen: int, id_sumber: int, dokumen: string, versi: string, jenis_dokumen: string, modul: string|null, nombor_seksyen: string|null, tajuk_seksyen: string|null, laluan_seksyen: string|null, kandungan: string, klasifikasi: string, mengandungi_arahan: bool, skor: float, kesamaan: float|null, pautan: string, status: string}>
     */
    public function cari(string $soalan, ?User $user, int $had = 5, array $penapis = [], bool $semantik = true): array
    {
        $tokens = Teks::contentTokens($soalan);
        $terms = Glosari::expand($tokens);
        $vector = $semantik ? $this->queryVector($soalan) : null;
        if ($terms === [] && $vector === null) {
            return [];
        }

        $acc = $this->capaian->predicate($user);
        [$filterSql, $filterBindings] = $this->filters($penapis);
        $base = "FROM kba_segmen s JOIN kba_sumber_dokumen d ON d.id = s.id_sumber
                 WHERE {$acc['sql']} AND d.aktif = true {$filterSql}";
        $baseBindings = [...$acc['bindings'], ...$filterBindings];

        $legs = [];
        $bindings = [];
        if ($vector !== null) {
            $legs[] = "SELECT s.id, row_number() OVER (ORDER BY s.embedding <=> ?::vector) AS r, 1.0 AS w {$base}
                       AND s.status = 'aktif' AND s.embedding IS NOT NULL AND s.model_embedding = ?
                       ORDER BY s.embedding <=> ?::vector LIMIT ".self::CALON;
            array_push($bindings, $vector, ...$baseBindings);
            array_push($bindings, $this->embedder->model(), $vector);
        }
        if ($terms !== []) {
            $tsquery = implode(' | ', array_map(fn (string $t) => $this->tsTerm($t), $terms));
            $legs[] = "SELECT s.id, row_number() OVER (ORDER BY ts_rank_cd(s.carian, to_tsquery('simple', ?)) DESC) AS r, 1.0 AS w {$base}
                       AND s.status = 'aktif' AND s.carian @@ to_tsquery('simple', ?)
                       ORDER BY ts_rank_cd(s.carian, to_tsquery('simple', ?)) DESC LIMIT ".self::CALON;
            array_push($bindings, $tsquery, ...$baseBindings);
            array_push($bindings, $tsquery, $tsquery);

            $fuzzy = array_slice(array_values(array_filter($tokens, fn (string $t) => mb_strlen($t) >= 4)), 0, 6);
            if ($fuzzy !== []) {
                $sum = implode(' + ', array_fill(0, count($fuzzy), 'word_similarity(?, s.kandungan_teks)'));
                $any = implode(' OR ', array_fill(0, count($fuzzy), '? <% s.kandungan_teks'));
                $legs[] = "SELECT s.id, row_number() OVER (ORDER BY ({$sum}) DESC) AS r, 0.5 AS w {$base}
                           AND s.status = 'aktif' AND ({$any})
                           ORDER BY ({$sum}) DESC LIMIT ".self::CALON;
                array_push($bindings, ...$fuzzy, ...$baseBindings, ...$fuzzy, ...$fuzzy);
            }
        }

        $union = implode(' UNION ALL ', array_map(fn (string $leg) => "({$leg})", $legs));
        $simSql = $vector !== null ? '1 - (s.embedding <=> ?::vector)' : 'NULL::float';
        $sql = "WITH calon AS ({$union}),
                fusi AS (SELECT id, SUM(w / (".self::RRF_K." + r)) AS skor FROM calon GROUP BY id)
                SELECT s.id, s.id_sumber, s.nombor_seksyen, s.tajuk_seksyen, s.laluan_seksyen, s.kandungan_teks, s.klasifikasi,
                       s.mengandungi_arahan, s.status, COALESCE(s.modul, d.modul) AS modul, d.tajuk, d.versi, d.no_versi, d.jenis_dokumen,
                       f.skor, CASE WHEN s.model_embedding = ? THEN {$simSql} ELSE NULL END AS kesamaan
                FROM fusi f JOIN kba_segmen s ON s.id = f.id JOIN kba_sumber_dokumen d ON d.id = s.id_sumber
                ORDER BY f.skor DESC, s.id LIMIT ?";
        $bindings[] = $this->embedder->model();
        if ($vector !== null) {
            $bindings[] = $vector;
        }
        $bindings[] = max(1, $had);

        try {
            DB::statement("SET hnsw.iterative_scan = 'relaxed_order'");
        } catch (Throwable) {
            // pgvector < 0.8: fine for small corpora
        }

        return array_map(fn ($r) => [
            'id_segmen' => (int) $r->id,
            'id_sumber' => (int) $r->id_sumber,
            'dokumen' => (string) $r->tajuk,
            'versi' => $r->versi !== null ? (string) $r->versi : 'v'.$r->no_versi,
            'jenis_dokumen' => (string) $r->jenis_dokumen,
            'modul' => $r->modul !== null ? (string) $r->modul : null,
            'nombor_seksyen' => $r->nombor_seksyen,
            'tajuk_seksyen' => $r->tajuk_seksyen,
            'laluan_seksyen' => $r->laluan_seksyen,
            'kandungan' => (string) $r->kandungan_teks,
            'klasifikasi' => (string) $r->klasifikasi,
            'mengandungi_arahan' => (bool) $r->mengandungi_arahan,
            'skor' => round((float) $r->skor, 6),
            'kesamaan' => $r->kesamaan !== null ? round((float) $r->kesamaan, 4) : null,
            'pautan' => self::pautan((int) $r->id_sumber, (int) $r->id),
            'status' => (string) $r->status,
        ], DB::select($sql, $bindings));
    }

    public static function pautan(int $idSumber, int $idSegmen): string
    {
        return "/admin/kba/sumber/{$idSumber}?segmen={$idSegmen}#segmen-{$idSegmen}";
    }

    /** null when no embedding model answers — keyword legs still work. */
    private function queryVector(string $soalan): ?string
    {
        try {
            $v = $this->embedder->embed([$soalan], 'soalan')[0] ?? null;

            return $v === null ? null : Vektor::literal($v);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function tsTerm(string $t): string
    {
        $t = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $t) ?? '';
        $parts = array_values(array_filter(explode(' ', trim($t))));
        $parts = array_map(fn (string $p) => "'".$p."'".(mb_strlen($p) >= 5 ? ':*' : ''), $parts);

        return count($parts) > 1 ? '('.implode(' & ', $parts).')' : ($parts[0] ?? "''");
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array{0: string, 1: list<mixed>}
     */
    private function filters(array $p): array
    {
        $sql = '';
        $b = [];
        if (! empty($p['modul'])) {
            $sql .= ' AND COALESCE(s.modul, d.modul) = ?';
            $b[] = strtolower((string) $p['modul']);
        }
        if (! empty($p['jenis_dokumen'])) {
            $sql .= ' AND d.jenis_dokumen = ?';
            $b[] = (string) $p['jenis_dokumen'];
        }
        if (! empty($p['versi'])) {
            $sql .= ' AND d.versi = ?';
            $b[] = (string) $p['versi'];
        }
        if (! empty($p['dari'])) {
            $sql .= ' AND d.tarikh_indeks_akhir >= ?';
            $b[] = (string) $p['dari'];
        }
        if (! empty($p['hingga'])) {
            $sql .= ' AND d.tarikh_indeks_akhir < (?::date + 1)';
            $b[] = (string) $p['hingga'];
        }
        if (! empty($p['status'])) {
            $sql .= ' AND d.status_indeks = ?';
            $b[] = (string) $p['status'];
        }
        if (! empty($p['pemilik'])) {
            $sql .= ' AND d.id_pemilik = ?';
            $b[] = (int) $p['pemilik'];
        }
        if (! empty($p['id_sumber'])) {
            $sql .= ' AND d.id = ?';
            $b[] = (int) $p['id_sumber'];
        }

        return [$sql, $b];
    }
}
