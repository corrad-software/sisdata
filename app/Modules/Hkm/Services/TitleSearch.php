<?php

namespace App\Modules\Hkm\Services;

use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Support\Kod;
use App\Modules\Hkm\Support\LotLookup;
use Illuminate\Database\Eloquent\Builder;

/**
 * SRS-HKM-14 / SRS-GEN-05: title search by the nine A-5.06 criteria — title
 * number, lot number, mukim, daerah, jenis tanah, land-use category, status,
 * area and keyword — singly or combined (AND). Queries go through the
 * ScopedByAccess global scope (or a caller-supplied scoped builder).
 */
class TitleSearch
{
    /** Accepted filter keys (also the export's recorded criteria). */
    public const FILTERS = [
        'q', 'no_hak_milik', 'no_lot', 'kod_mukim', 'kod_daerah', 'jenis_hak_milik', 'jenis_tanah', 'kategori_tanah',
        'status', 'keluasan_min', 'keluasan_max', 'pemilik', 'bendera', 'tamat_dalam_hari',
    ];

    public function __construct(private LotLookup $lots) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  Builder<HakMilik>|null  $query
     * @return Builder<HakMilik>
     */
    public function query(array $filters, ?Builder $query = null): Builder
    {
        $query ??= HakMilik::query();
        $f = array_filter(array_intersect_key($filters, array_flip(self::FILTERS)), fn ($v) => $v !== null && $v !== '');

        if (isset($f['q'])) {
            $term = '%'.mb_strtolower(trim((string) $f['q'])).'%';
            $query->where(function (Builder $q) use ($term, $f) {
                $q->whereRaw('lower(no_hak_milik) like ?', [$term])
                    ->orWhereRaw('lower(pemilik_berdaftar) like ?', [$term])
                    ->orWhereRaw("lower(coalesce(syarat_nyata, '')) like ?", [$term])
                    ->orWhereRaw("lower(coalesce(pejabat_pendaftaran, '')) like ?", [$term])
                    ->orWhereRaw("lower(coalesce(no_perserahan, '')) like ?", [$term])
                    ->orWhereIn('lot_id', $this->lots->idsByNumber((string) $f['q']));
            });
        }
        if (isset($f['no_hak_milik'])) {
            $needle = preg_replace('/\s+/', '', mb_strtoupper((string) $f['no_hak_milik']));
            $query->whereRaw("replace(upper(no_hak_milik), ' ', '') like ?", ['%'.$needle.'%']);
        }
        if (isset($f['no_lot'])) {
            $query->whereIn('lot_id', $this->lots->idsByNumber((string) $f['no_lot']));
        }
        foreach (['kod_mukim', 'kod_daerah', 'jenis_hak_milik', 'kategori_tanah'] as $col) {
            if (isset($f[$col])) {
                $values = is_array($f[$col]) ? $f[$col] : explode(',', (string) $f[$col]);
                $query->whereIn($col, array_map('trim', $values));
            }
        }
        if (isset($f['status'])) {
            $query->whereIn('status_semasa', is_array($f['status']) ? $f['status'] : explode(',', (string) $f['status']));
        }
        if (isset($f['jenis_tanah'])) {
            $query->whereIn('lot_id', $this->lots->idsByBahagian((string) $f['jenis_tanah']));
        }
        if (isset($f['keluasan_min']) && is_numeric($f['keluasan_min'])) {
            $query->where('keluasan', '>=', (float) $f['keluasan_min']);
        }
        if (isset($f['keluasan_max']) && is_numeric($f['keluasan_max'])) {
            $query->where('keluasan', '<=', (float) $f['keluasan_max']);
        }
        if (isset($f['pemilik'])) {
            $query->whereRaw('lower(pemilik_berdaftar) like ?', ['%'.mb_strtolower((string) $f['pemilik']).'%']);
        }
        if (isset($f['tamat_dalam_hari']) && is_numeric($f['tamat_dalam_hari'])) {
            $query->whereNotNull('tarikh_tamat_tempoh')->whereDate('tarikh_tamat_tempoh', '<=', today()->addDays((int) $f['tamat_dalam_hari']));
        }
        if (isset($f['bendera'])) {
            match ((string) $f['bendera']) {
                'sekatan_aktif' => $query->whereHas('sekatan', fn ($q) => $q->where('status_sekatan', 'aktif')),
                'dokumen_asal_tiada' => $query->where(fn ($q) => $q->whereDoesntHave('daftarPetiBesi')
                    ->orWhereHas('daftarPetiBesi', fn ($d) => $d->where('status_dokumen_asal', '!=', 'dalam_simpanan'))),
                'pemilikan_belum_selesai' => $query->whereHas('waris', fn ($q) => $q->whereIn('hubungan', Kod::HUBUNGAN_BERBAHAGI)->whereIn('status_pengesahan', Kod::WARIS_BELUM_SELESAI)),
                'belum_disahkan' => $query->where('disahkan', false),
                default => null,
            };
        }

        return $query;
    }
}
