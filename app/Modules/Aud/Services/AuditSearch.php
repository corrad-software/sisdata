<?php

namespace App\Modules\Aud\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SRS-AUD-10/11/21: one query over the online trail, the archive, or both —
 * every filter alone or combined.
 */
class AuditSearch
{
    public const SUMBER_DALAM_TALIAN = 'dalam_talian';

    public const SUMBER_ARKIB = 'arkib';

    public const SUMBER_SEMUA = 'semua';

    /** Filters accepted by the search API / export (all optional). */
    public const FILTERS = [
        'id_pengguna', 'pengguna', 'peranan', 'modul', 'nama_entiti', 'id_rekod', 'jenis_tindakan',
        'alamat_ip', 'status_transaksi', 'saluran_capaian', 'klasifikasi', 'dari', 'hingga', 'sumber',
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters): Builder
    {
        $query = DB::query()->fromSub($this->source((string) ($filters['sumber'] ?? self::SUMBER_DALAM_TALIAN)), 'a');

        foreach (['modul', 'nama_entiti', 'id_rekod', 'jenis_tindakan', 'alamat_ip', 'status_transaksi', 'saluran_capaian', 'klasifikasi'] as $exact) {
            if (isset($filters[$exact]) && $filters[$exact] !== '') {
                $query->where($exact, (string) $filters[$exact]);
            }
        }

        if (! empty($filters['id_pengguna'])) {
            $query->where('id_pengguna', (int) $filters['id_pengguna']);
        }

        if (! empty($filters['pengguna'])) {
            $query->where('pengecam_pengguna', 'ilike', '%'.addcslashes((string) $filters['pengguna'], '%_\\').'%');
        }

        if (! empty($filters['peranan'])) {
            $query->whereRaw('peranan_semasa::jsonb @> ?::jsonb', [json_encode([(string) $filters['peranan']])]);
        }

        // Date range is in Malaysian local dates (what the user sees).
        if (! empty($filters['dari'])) {
            $query->where('tarikh_masa_tempatan', '>=', Carbon::parse((string) $filters['dari'])->startOfDay()->format('Y-m-d H:i:s'));
        }
        if (! empty($filters['hingga'])) {
            $query->where('tarikh_masa_tempatan', '<', Carbon::parse((string) $filters['hingga'])->addDay()->startOfDay()->format('Y-m-d H:i:s'));
        }

        return $query;
    }

    /**
     * All activity on one record, oldest first (SRS-AUD-11): entries about the
     * record itself plus entries other modules linked to it with
     * meta.induk_entiti / meta.induk_id (e.g. a document uploaded to a lot).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function chronology(string $entity, string $id, string $source = self::SUMBER_SEMUA): Collection
    {
        $rows = DB::query()->fromSub($this->source($source), 'a')
            ->where(function (Builder $q) use ($entity, $id) {
                $q->where(fn (Builder $w) => $w->where('nama_entiti', $entity)->where('id_rekod', $id))
                    ->orWhereRaw('meta::jsonb @> ?::jsonb', [json_encode(['induk_entiti' => $entity, 'induk_id' => $id])]);
            })
            ->orderBy('tarikh_masa_utc')->orderBy('no_urutan')
            ->limit(5000)
            ->get();

        return $this->present($rows);
    }

    /**
     * @param  Collection<array-key, \stdClass>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public function present(Collection $rows): Collection
    {
        $onlineIds = $rows->where('sumber', self::SUMBER_DALAM_TALIAN)->pluck('id')->all();
        $changes = $onlineIds === [] ? collect() : DB::table('audit_perubahan_medan')
            ->whereIn('id_audit', $onlineIds)->orderBy('id')->get()->groupBy('id_audit');

        return $rows->values()->map(fn (\stdClass $r) => $this->presentRow($r, $changes));
    }

    /**
     * @param  Collection<array-key, mixed>  $changes  field changes of online rows, grouped by id_audit
     * @return array<string, mixed>
     */
    private function presentRow(\stdClass $r, Collection $changes): array
    {
        $perubahan = $r->sumber === self::SUMBER_ARKIB
            ? (array) json_decode((string) ($r->perubahan ?? '[]'), true)
            : ($changes->get($r->id)?->map(fn ($c) => (array) $c)->all() ?? []);

        return [
            'id' => (int) $r->id,
            'no_urutan' => (int) $r->no_urutan,
            'sumber' => $r->sumber,
            'tarikh_masa_utc' => Carbon::parse($r->tarikh_masa_utc)->utc()->toIso8601ZuluString('microsecond'),
            'tarikh_masa_tempatan' => Carbon::parse($r->tarikh_masa_tempatan)->format('Y-m-d H:i:s'),
            'id_pengguna' => $r->id_pengguna === null ? null : (int) $r->id_pengguna,
            'pengecam_pengguna' => $r->pengecam_pengguna,
            'peranan_semasa' => (array) json_decode((string) $r->peranan_semasa, true),
            'id_sesi' => $r->id_sesi,
            'alamat_ip' => $r->alamat_ip,
            'user_agent' => $r->user_agent,
            'jenis_peranti' => $r->jenis_peranti,
            'lokasi_anggaran' => $r->lokasi_anggaran,
            'saluran_capaian' => $r->saluran_capaian,
            'modul' => $r->modul,
            'nama_entiti' => $r->nama_entiti,
            'id_rekod' => $r->id_rekod,
            'jenis_tindakan' => $r->jenis_tindakan,
            'status_transaksi' => $r->status_transaksi,
            'sebab_perubahan' => $r->sebab_perubahan,
            'id_korelasi' => $r->id_korelasi,
            'klasifikasi' => $r->klasifikasi,
            'meta' => $r->meta === null ? null : json_decode((string) $r->meta, true),
            'cincangan_sebelum' => $r->cincangan_sebelum,
            'cincangan_rekod' => $r->cincangan_rekod,
            'perubahan' => array_map(fn (array $c) => [
                'nama_medan' => $c['nama_medan'],
                'nilai_asal' => $c['nilai_asal'],
                'nilai_baharu' => $c['nilai_baharu'],
                'sensitif_ditapis' => (bool) $c['sensitif_ditapis'],
            ], $perubahan),
        ];

    }

    private function source(string $sumber): Builder
    {
        $columns = ['id', 'cincangan_rekod', ...ChainHasher::FIELDS];

        $online = DB::table('audit_rekod')->select($columns)
            ->selectRaw("'dalam_talian' as sumber, null::text as perubahan");
        $archive = DB::table('audit_arkib')->select($columns)
            ->selectRaw("'arkib' as sumber, perubahan::text as perubahan");

        return match ($sumber) {
            self::SUMBER_ARKIB => $archive,
            self::SUMBER_SEMUA => $online->unionAll($archive),
            default => $online,
        };
    }
}
