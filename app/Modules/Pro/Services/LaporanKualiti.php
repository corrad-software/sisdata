<?php

namespace App\Modules\Pro\Services;

use App\Models\User;
use App\Modules\Pro\Models\LotTanah;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data-quality report (SRS-PRO-24, SRS-DAT-09): incomplete records, pending
 * duplicates, expired documents, outstanding tax, geometry needing
 * verification, lots without geometry, broken cross-references and
 * orphans — filterable by daerah, mukim, bahagian, entity and steward, and
 * always limited to the lots the user may see.
 */
class LaporanKualiti
{
    public const CATEGORIES = [
        'tidak_lengkap' => 'lot_tanah',
        'duplikasi' => 'lot_tanah',
        'dokumen_tamat' => 'dokumen',
        'cukai_tertunggak' => 'cukai',
        'geometri_belum_disahkan' => 'data_spatial',
        'tanpa_geometri' => 'data_spatial',
        'rujukan_bercanggah' => 'lot_tanah',
        'rekod_yatim' => 'data_spatial',
    ];

    public function __construct(private LotQuery $lots, private RujukanInduk $ref) {}

    /**
     * @param  array<string, mixed>  $filters  kod_daerah, kod_mukim, bahagian_pemilik, entiti, id_data_steward
     * @return array{ringkasan: list<array{kod: string, label: string, entiti: string, bilangan: int, tersedia: bool}>, butiran: array<string, list<array<string, mixed>>>}
     */
    public function build(?User $user, array $filters = [], int $limit = 200): array
    {
        $lotFilters = array_intersect_key($filters, array_flip(['kod_daerah', 'kod_mukim', 'bahagian_pemilik', 'id_data_steward']));
        $ids = $user !== null
            ? $this->lots->for($user, $lotFilters)->select('lot_tanah.id')->toBase()
            : $this->unscoped($lotFilters);

        $summary = [];
        $details = [];
        foreach (self::CATEGORIES as $code => $entity) {
            if (! empty($filters['entiti']) && $filters['entiti'] !== $entity) {
                continue;
            }
            [$available, $query] = $this->query($code, clone $ids);
            $count = $available && $query !== null ? (clone $query)->count() : 0;
            $summary[] = ['kod' => $code, 'label' => (string) __('pro::pro.kualiti_kategori.'.$code), 'entiti' => $entity, 'bilangan' => $count, 'tersedia' => $available];
            $details[$code] = $available && $query !== null && $count > 0
                ? $query->limit($limit)->get()->map(fn ($r) => $this->row($code, (array) $r))->all()
                : [];
        }

        return ['ringkasan' => $summary, 'butiran' => $details];
    }

    /**
     * Flat rows for export.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function flat(?User $user, array $filters = []): array
    {
        $report = $this->build($user, $filters, 5000);
        $rows = [];
        foreach ($report['butiran'] as $code => $items) {
            foreach ($items as $item) {
                $rows[] = ['kategori' => (string) __('pro::pro.kualiti_kategori.'.$code), ...$item];
            }
        }

        return $rows;
    }

    /** @param array<string, mixed> $filters */
    private function unscoped(array $filters): QueryBuilder
    {
        $q = DB::table('lot_tanah')->whereNull('deleted_at')->where('status_lot', '!=', 'arkib')->select('lot_tanah.id');
        foreach ($filters as $field => $value) {
            if ($value !== null && $value !== '') {
                $q->whereIn('lot_tanah.'.$field, (array) $value);
            }
        }

        return $q;
    }

    /** @return array{0: bool, 1: QueryBuilder|null} */
    private function query(string $code, QueryBuilder $ids): array
    {
        $lotCols = ['l.id as lot_id', 'l.no_lot', 'l.kod_mukim', 'l.kod_daerah', 'l.bahagian_pemilik', 'l.id_data_steward'];

        return match ($code) {
            'tidak_lengkap' => [true, DB::table('vw_kualiti_data_rekod_tidak_lengkap as l')->whereIn('l.lot_id', $ids)
                ->select(['l.lot_id', 'l.no_lot', 'l.kod_mukim', 'l.kod_daerah', 'l.bahagian_pemilik', 'l.id_data_steward', DB::raw("array_to_string(l.medan_tidak_lengkap, ',') as keterangan")])
                ->orderBy('l.no_lot')],
            'duplikasi' => [true, DB::table('cadangan_duplikasi as c')->join('lot_tanah as l', 'l.id', '=', 'c.id_rekod_a')
                ->where('c.keputusan', 'menunggu')->whereIn('c.id_rekod_a', $ids)
                ->select([...$lotCols, DB::raw("'Skor ' || c.skor_padanan || ' dengan lot ' || (SELECT no_lot FROM lot_tanah b WHERE b.id = c.id_rekod_b) as keterangan")])
                ->orderByDesc('c.skor_padanan')],
            'dokumen_tamat' => Schema::hasTable('dokumen') && Schema::hasColumn('dokumen', 'tarikh_luput')
                ? [true, DB::table('dokumen as d')->join('lot_tanah as l', DB::raw('l.id::text'), '=', 'd.id_rekod_induk')
                    ->where('d.jenis_entiti_induk', 'lot_tanah')->whereNull('d.deleted_at')
                    ->whereNotNull('d.tarikh_luput')->where('d.tarikh_luput', '<=', now()->addDays(30)->toDateString())
                    ->whereIn('l.id', $ids)
                    ->select([...$lotCols, DB::raw("d.tajuk || ' — luput ' || to_char(d.tarikh_luput, 'DD/MM/YYYY') as keterangan")])
                    ->orderBy('d.tarikh_luput')]
                : [false, null],
            'cukai_tertunggak' => Schema::hasTable('cukai')
                ? [true, DB::table('cukai as c')->join('lot_tanah as l', 'l.id', '=', 'c.lot_id')
                    ->whereNull('c.deleted_at')->where('c.status_bayaran', '!=', 'dibayar')
                    ->where('c.tarikh_akhir', '<', now()->toDateString())->whereIn('l.id', $ids)
                    ->select([...$lotCols, DB::raw("coalesce(c.no_bil, '-') || ' RM' || coalesce(c.jumlah::text, '0') as keterangan")])]
                : [false, null],
            'geometri_belum_disahkan' => [true, DB::table('vw_lot_spatial_belum_disahkan as l')->whereNotNull('l.id_spatial')->whereIn('l.lot_id', $ids)
                ->select(['l.lot_id', 'l.no_lot', 'l.kod_mukim', 'l.kod_daerah', 'l.bahagian_pemilik', 'l.id_data_steward', 'l.status_pengesahan as keterangan'])
                ->orderBy('l.no_lot')],
            'tanpa_geometri' => [true, DB::table('vw_lot_spatial_belum_disahkan as l')->whereNull('l.id_spatial')->whereIn('l.lot_id', $ids)
                ->select(['l.lot_id', 'l.no_lot', 'l.kod_mukim', 'l.kod_daerah', 'l.bahagian_pemilik', 'l.id_data_steward', DB::raw("'tiada geometri' as keterangan")])
                ->orderBy('l.no_lot')],
            'rujukan_bercanggah' => [true, DB::table('lot_tanah as l')->whereIn('l.id', $ids)
                ->where(fn ($w) => $w
                    ->whereNotExists(fn ($q) => $q->from('ref_mukim as m')->whereColumn('m.kod', 'l.kod_mukim')->whereNull('m.sah_hingga')->whereColumn('m.daerah_kod', 'l.kod_daerah'))
                    ->orWhere(fn ($k) => $k->whereNotNull('l.kategori_guna_tanah')
                        ->whereNotExists(fn ($q) => $q->from('ref_kategori_guna_tanah as k')->whereColumn('k.kod', 'l.kategori_guna_tanah')->whereNull('k.sah_hingga'))))
                ->select([...$lotCols, DB::raw("'mukim/daerah/kategori tidak sepadan dengan MOD-REF' as keterangan")])],
            'rekod_yatim' => [true, DB::table('data_spatial as d')->join('lot_tanah as l', 'l.id', '=', 'd.lot_id')
                ->where('d.semasa', true)->where(fn ($w) => $w->whereNotNull('l.deleted_at')->orWhere('l.status_lot', 'digabung'))
                ->select([...$lotCols, DB::raw("'geometri semasa pada lot yang dipadam/digabung' as keterangan")])],
            default => [false, null],
        };
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    private function row(string $code, array $r): array
    {
        return [
            'lot_id' => $r['lot_id'] ?? null,
            'no_lot' => $r['no_lot'] ?? null,
            'kod_mukim' => $r['kod_mukim'] ?? null,
            'nama_mukim' => $this->ref->namaMukim($r['kod_mukim'] ?? null),
            'kod_daerah' => $r['kod_daerah'] ?? null,
            'nama_daerah' => $this->ref->namaDaerah($r['kod_daerah'] ?? null),
            'bahagian_pemilik' => $r['bahagian_pemilik'] ?? null,
            'id_data_steward' => $r['id_data_steward'] ?? null,
            'keterangan' => $r['keterangan'] ?? null,
            'pautan' => isset($r['lot_id']) ? '/admin/pro/lot/'.$r['lot_id'] : null,
        ];
    }

    /**
     * Scheduled run: route every problem record to its steward (SRS-PRO-12).
     *
     * @return array<string, int> tasks created per category
     */
    public function routeAll(PenghalaKualiti $router): array
    {
        $report = $this->build(null, [], 100000);
        $created = [];
        foreach ($report['butiran'] as $code => $items) {
            $created[$code] = 0;
            if (! in_array($code, ['tidak_lengkap', 'duplikasi', 'geometri_belum_disahkan', 'tanpa_geometri', 'rujukan_bercanggah', 'dokumen_tamat', 'cukai_tertunggak'], true)) {
                continue;
            }
            foreach ($items as $item) {
                $lot = LotTanah::query()->withoutGlobalScope('access')->find($item['lot_id']);
                if ($lot === null) {
                    continue;
                }
                $created[$code] += $router->route(self::CATEGORIES[$code] === 'data_spatial' ? 'data_spatial' : 'lot_tanah', $lot->id, [[
                    'medan' => 'laporan', 'label' => (string) __('pro::pro.kualiti_kategori.'.$code), 'kod' => $code,
                    'mesej' => (string) ($item['keterangan'] ?? ''), 'tahap' => 'amaran',
                ]], $lot, 'laporan_berjadual')->count();
            }
        }

        return $created;
    }
}
