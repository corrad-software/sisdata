<?php

namespace App\Modules\Dms\Services;

use App\Models\User;
use App\Modules\Dms\Models\Dokumen;
use App\Modules\Dms\Services\Access\DocumentAccess;
use Illuminate\Database\Eloquent\Builder;

/**
 * SRS-DMS-12/13: search by any combination of metadata fields plus full text
 * over metadata and extracted/OCR content. The access scope is applied to the
 * base query, so result rows AND pagination totals only ever cover documents
 * the user may open.
 *
 * Postgres: `carian` tsvector ('simple' config — no Malay stemmer, so terms
 * are prefix-matched: "tana" finds "tanah"), plus pg_trgm similarity on the
 * title / file name to tolerate typos.
 */
class DocumentSearch
{
    public const FILTERS = ['q', 'kategori', 'modul_sumber', 'jenis_entiti_induk', 'id_rekod_induk', 'kod_daerah', 'kod_mukim', 'tahun', 'status', 'klasifikasi', 'pemilik_proses_id', 'tarikh_dari', 'tarikh_hingga', 'kata_kunci', 'meta', 'luput_dalam_hari', 'status_indeks'];

    public function __construct(private DocumentAccess $access) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Dokumen>
     */
    public function query(User $user, array $filters): Builder
    {
        $query = $this->access->scopeVisible(Dokumen::query(), $user)->with('kategori');

        $text = trim((string) ($filters['q'] ?? ''));
        $tsquery = $this->tsquery($text);

        if ($text !== '') {
            $query->where(function (Builder $q) use ($tsquery, $text) {
                if ($tsquery !== null) {
                    $q->whereRaw("dokumen.carian @@ to_tsquery('simple', ?)", [$tsquery]);
                }
                $q->orWhereRaw('dokumen.tajuk % ?', [$text])
                    ->orWhere('dokumen.no_rujukan', 'ilike', $this->like($text))
                    ->orWhere('dokumen.nama_fail', 'ilike', $this->like($text));
            });
            $query->select('dokumen.*');
            if ($tsquery !== null) {
                $query->selectRaw(
                    "ts_headline('simple', left(coalesce(dokumen.teks_kandungan, ''), 60000), to_tsquery('simple', ?), 'MaxFragments=2, MaxWords=18, MinWords=6, StartSel=«, StopSel=»') as petikan",
                    [$tsquery],
                );
                $query->orderByRaw("ts_rank(dokumen.carian, to_tsquery('simple', ?)) + similarity(dokumen.tajuk, ?) desc", [$tsquery, $text]);
            } else {
                $query->orderByRaw('similarity(dokumen.tajuk, ?) desc', [$text]);
            }
        }

        $this->exact($query, $filters, [
            'modul_sumber' => 'modul_sumber', 'jenis_entiti_induk' => 'jenis_entiti_induk', 'id_rekod_induk' => 'id_rekod_induk',
            'kod_daerah' => 'kod_daerah', 'kod_mukim' => 'kod_mukim', 'tahun' => 'tahun', 'klasifikasi' => 'klasifikasi_keselamatan',
            'pemilik_proses_id' => 'pemilik_proses_id', 'status_indeks' => 'status_indeks',
        ]);

        if (! empty($filters['kategori'])) {
            $values = (array) $filters['kategori'];
            $query->whereHas('kategori', fn (Builder $k) => $k->where(
                fn (Builder $w) => $w->whereIn('kod', $values)->orWhereIn('id', array_values(array_filter($values, 'is_numeric')))
            ));
        }

        // Disposed tombstones only when asked for explicitly.
        if (! empty($filters['status'])) {
            $query->whereIn('dokumen.status', (array) $filters['status']);
        } else {
            $query->where('dokumen.status', '!=', 'dilupus');
        }

        if (! empty($filters['tarikh_dari'])) {
            $query->where('dokumen.tarikh_dokumen', '>=', $filters['tarikh_dari']);
        }
        if (! empty($filters['tarikh_hingga'])) {
            $query->where('dokumen.tarikh_dokumen', '<=', $filters['tarikh_hingga']);
        }
        if (! empty($filters['kata_kunci'])) {
            foreach ((array) $filters['kata_kunci'] as $keyword) {
                $query->whereRaw('dokumen.kata_kunci @> ?::jsonb', [json_encode([(string) $keyword])]);
            }
        }
        if (! empty($filters['meta']) && is_array($filters['meta'])) {
            foreach ($filters['meta'] as $field => $value) {
                if ($value === null || $value === '' || ! preg_match('/^[a-z0-9_]{1,50}$/', (string) $field)) {
                    continue;
                }
                $query->whereRaw('dokumen.metadata ->> ? = ?', [(string) $field, (string) $value]);
            }
        }
        if (! empty($filters['luput_dalam_hari'])) {
            $query->whereNotNull('dokumen.tarikh_luput')
                ->where('dokumen.tarikh_luput', '<=', now()->addDays((int) $filters['luput_dalam_hari'])->toDateString());
        }

        return $query->orderByDesc('dokumen.created_at');
    }

    /** "tanah lot 12" → "tanah:* & lot:* & 12:*" (only letters/digits survive, so no tsquery syntax injection). */
    public function tsquery(string $text): ?string
    {
        preg_match_all('/[\pL\pN]+/u', mb_strtolower($text), $m);
        $terms = array_slice(array_unique($m[0]), 0, 12);

        return $terms === [] ? null : implode(' & ', array_map(fn ($t) => $t.':*', $terms));
    }

    /**
     * @param  Builder<Dokumen>  $query
     * @param  array<string, mixed>  $filters
     * @param  array<string, string>  $map
     */
    private function exact(Builder $query, array $filters, array $map): void
    {
        foreach ($map as $filter => $column) {
            $value = $filters[$filter] ?? null;
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $query->whereIn('dokumen.'.$column, array_map('strval', (array) $value));
        }
    }

    private function like(string $text): string
    {
        return '%'.addcslashes($text, '%_\\').'%';
    }
}
