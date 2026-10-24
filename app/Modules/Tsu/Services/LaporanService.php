<?php

namespace App\Modules\Tsu\Services;

use App\Models\User;
use App\Modules\Tsu\Models\UrusanTanah;
use App\Modules\Tsu\Support\Lalai;
use App\Modules\Tsu\Support\Wang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SRS-TSU-26/27 reports, always within the viewer's daerah / mukim /
 * klasifikasi scope (the base query is UrusanTanah::untuk($user)).
 */
class LaporanService
{
    /**
     * @param  array{kod_daerah?: string|null, kod_mukim?: string|null, tahun?: int|null, jenis_urusan?: string|null}  $f
     * @return Builder<UrusanTanah>
     */
    public function base(User $user, array $f = []): Builder
    {
        return UrusanTanah::query()->untuk($user)
            ->when($f['kod_daerah'] ?? null, fn ($q, $v) => $q->where('kod_daerah', $v))
            ->when($f['kod_mukim'] ?? null, fn ($q, $v) => $q->where('kod_mukim', $v))
            ->when($f['jenis_urusan'] ?? null, fn ($q, $v) => $q->where('jenis_urusan', $v))
            ->when($f['tahun'] ?? null, fn ($q, $v) => $q->whereYear('tarikh_mula', (int) $v));
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    public function ringkasan(User $user, array $f = []): array
    {
        $ids = $this->base($user, $f)->select('id');
        $open = $this->base($user, $f)->whereIn('status', Lalai::URUSAN_TERBUKA);

        $byJenis = (clone $open)->selectRaw('jenis_urusan AS kod, count(*) AS n')->groupBy('jenis_urusan')->pluck('n', 'kod');
        $byDaerah = (clone $open)->selectRaw('urusan_tanah.kod_daerah AS kod, count(*) AS n')->groupBy('urusan_tanah.kod_daerah')->orderBy('kod')->pluck('n', 'kod');
        $daerahNama = DB::table('ref_daerah')->whereIn('kod', $byDaerah->keys())->where('aktif', true)->pluck('nama', 'kod');
        $byStage = DB::table('urusan_peringkat as p')->join('urusan_tanah as u', fn ($j) => $j->on('u.id', '=', 'p.urusan_id')->on('u.peringkat_semasa', '=', 'p.no_urutan'))
            ->whereIn('u.id', (clone $open)->select('id'))->selectRaw('u.jenis_urusan, p.nama_peringkat, count(*) AS n')->groupBy('u.jenis_urusan', 'p.nama_peringkat')->orderBy('u.jenis_urusan')->get();
        $avg = $this->base($user, $f)->whereIn('status', ['selesai', 'ditutup'])->whereNotNull('tarikh_siap')
            ->selectRaw('jenis_urusan AS kod, round(avg(tarikh_siap - tarikh_mula)::numeric, 1) AS hari, count(*) AS n')->groupBy('jenis_urusan')->toBase()->get()->keyBy('kod');
        $kos = DB::table('kos_urusan')->whereIn('urusan_id', $ids)->where('status_bayaran', '!=', 'dibatalkan')
            ->selectRaw('extract(year from COALESCE(tarikh_bayaran, tarikh_invois, created_at::date))::int AS tahun, kategori_kos, sum(amaun)::text AS jumlah')
            ->groupBy('tahun', 'kategori_kos')->orderBy('tahun')->get();
        $overdue = DB::table('urusan_peringkat as p')->join('urusan_tanah as u', 'u.id', '=', 'p.urusan_id')
            ->whereIn('u.id', (clone $open)->select('id'))->whereIn('p.status', ['aktif', 'menunggu_kelulusan'])
            ->whereIn('p.pihak_bertanggungjawab', Lalai::PIHAK_LUAR)->where('p.tarikh_sasaran', '<', today())
            ->selectRaw('u.id, u.no_rujukan_mais, u.jenis_urusan, u.kod_daerah, p.nama_peringkat, p.pihak_bertanggungjawab, p.tarikh_sasaran::text AS tarikh_sasaran, (?::date - p.tarikh_sasaran) AS hari_lewat', [today()->toDateString()])
            ->orderByDesc('hari_lewat')->limit(200)->get();

        return [
            'aktif_mengikut_jenis' => array_map(fn ($k) => ['kod' => $k, 'nama' => Lalai::JENIS_URUSAN[$k], 'nilai' => (int) ($byJenis[$k] ?? 0)], array_keys(Lalai::JENIS_URUSAN)),
            'aktif_mengikut_daerah' => $byDaerah->map(fn ($n, $k) => ['kod' => (string) $k, 'nama' => $daerahNama[$k] ?? (string) $k, 'nilai' => (int) $n])->values()->all(),
            'aktif_mengikut_peringkat' => $byStage->map(fn ($r) => ['jenis_urusan' => $r->jenis_urusan, 'jenis_nama' => Lalai::JENIS_URUSAN[$r->jenis_urusan] ?? $r->jenis_urusan, 'peringkat' => $r->nama_peringkat, 'nilai' => (int) $r->n])->all(),
            'purata_tempoh' => array_map(fn ($k) => ['kod' => $k, 'nama' => Lalai::JENIS_URUSAN[$k], 'purata_hari' => isset($avg[$k]) ? (float) $avg[$k]->hari : null, 'bilangan_selesai' => (int) ($avg[$k]->n ?? 0)], array_keys(Lalai::JENIS_URUSAN)),
            'kos_tahunan' => $this->kosTahunan($kos),
            'tertunggak_pihak_luar' => $overdue->map(fn ($r) => [
                'id' => $r->id, 'no_rujukan_mais' => $r->no_rujukan_mais, 'jenis_nama' => Lalai::JENIS_URUSAN[$r->jenis_urusan] ?? $r->jenis_urusan, 'kod_daerah' => $r->kod_daerah,
                'peringkat' => $r->nama_peringkat, 'pihak' => Lalai::PIHAK[$r->pihak_bertanggungjawab] ?? $r->pihak_bertanggungjawab, 'tarikh_sasaran' => $r->tarikh_sasaran, 'hari_lewat' => (int) $r->hari_lewat,
            ])->all(),
            'prestasi_jurukur' => $this->prestasiJurukur($ids),
            'sejarah_pecahan' => DB::table('lot_sejarah_pecahan as s')->whereIn('s.urusan_id', $ids)->join('lot_tanah as a', 'a.id', '=', 's.id_lot_asal')->join('lot_tanah as b', 'b.id', '=', 's.id_lot_baharu')
                ->join('urusan_tanah as u', 'u.id', '=', 's.urusan_id')
                ->selectRaw('s.id, u.no_rujukan_mais, s.jenis_perubahan, a.no_lot AS lot_asal, b.no_lot AS lot_baharu, s.tarikh_berkuat_kuasa::text AS tarikh, s.id_lot_asal, s.id_lot_baharu')
                ->orderByDesc('s.id')->limit(200)->get()->map(fn ($r) => (array) $r)->all(),
            'rekod_dipindah' => DB::table('pemindahan_rekod as r')->whereIn('r.urusan_id', $ids)->where('r.status_pemindahan', 'dilaksanakan')
                ->join('lot_tanah as a', 'a.id', '=', 'r.id_lot_asal')->join('lot_tanah as b', 'b.id', '=', 'r.id_lot_baharu')
                ->selectRaw('r.id, r.jenis_rekod, r.label_rekod, a.no_lot AS lot_asal, b.no_lot AS lot_baharu, r.tarikh_pemindahan::text AS tarikh')
                ->orderByDesc('r.id')->limit(200)->get()->map(fn ($r) => (array) $r)->all(),
        ];
    }

    /**
     * @param  Collection<int, \stdClass>  $rows
     * @return list<array<string, mixed>>
     */
    private function kosTahunan(Collection $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $y = (int) $r->tahun;
            $out[$y] ??= ['tahun' => $y, 'jumlah' => '0.00', ...array_fill_keys(array_keys(Lalai::KATEGORI_KOS), '0.00')];
            $out[$y][$r->kategori_kos] = Wang::norm($r->jumlah);
            $out[$y]['jumlah'] = bcadd($out[$y]['jumlah'], Wang::norm($r->jumlah), 2);
        }
        ksort($out);

        return array_values($out);
    }

    /**
     * @param  Builder<UrusanTanah>  $ids
     * @return list<array<string, mixed>>
     */
    private function prestasiJurukur(Builder $ids): array
    {
        return DB::table('lantikan_jurukur as l')->join('panel_jurukur as j', 'j.id', '=', 'l.jurukur_id')->whereIn('l.urusan_id', $ids)
            ->selectRaw("j.id, j.nama_firma, j.no_lesen_jurukur, count(*) AS bilangan, count(*) FILTER (WHERE l.status = 'siap') AS siap,
                round(avg(l.tarikh_siap - l.tarikh_mula) FILTER (WHERE l.tarikh_siap IS NOT NULL)::numeric, 1) AS purata_hari_sebenar,
                round(avg(l.tempoh_siap_hari)::numeric, 1) AS purata_hari_sasaran, count(*) FILTER (WHERE l.lewat OR (l.tarikh_siap > l.tarikh_jangka_siap)) AS lewat,
                sum(l.nilai_kontrak)::text AS jumlah_kontrak")
            ->groupBy('j.id', 'j.nama_firma', 'j.no_lesen_jurukur')->orderBy('j.nama_firma')->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id, 'nama_firma' => $r->nama_firma, 'no_lesen_jurukur' => $r->no_lesen_jurukur, 'bilangan' => (int) $r->bilangan, 'siap' => (int) $r->siap,
                'purata_hari_sebenar' => $r->purata_hari_sebenar !== null ? (float) $r->purata_hari_sebenar : null, 'purata_hari_sasaran' => (float) $r->purata_hari_sasaran,
                'lewat' => (int) $r->lewat, 'jumlah_kontrak' => Wang::norm($r->jumlah_kontrak),
            ])->all();
    }

    /**
     * SRS-TSU-26: works report (disposal, change of conditions, survey and demarcation) by status, district, surveyor, cost and duration.
     *
     * @param  array<string, mixed>  $f
     * @return list<array<string, mixed>>
     */
    public function kerja(User $user, array $f = []): array
    {
        $q = $this->base($user, $f)
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['jurukur_id'] ?? null, fn ($q, $v) => $q->whereExists(fn ($s) => $s->from('lantikan_jurukur')->whereColumn('lantikan_jurukur.urusan_id', 'urusan_tanah.id')->where('jurukur_id', (int) $v)))
            ->when(! empty($f['ukur']), fn ($q) => $q->whereExists(fn ($s) => $s->from('lantikan_jurukur')->whereColumn('lantikan_jurukur.urusan_id', 'urusan_tanah.id')))
            ->orderByDesc('tarikh_mula')->limit(2000)->get();
        $ids = $q->pluck('id')->all();
        $kos = DB::table('kos_urusan')->whereIn('urusan_id', $ids)->where('status_bayaran', '!=', 'dibatalkan')->selectRaw('urusan_id, sum(amaun)::text AS j')->groupBy('urusan_id')->pluck('j', 'urusan_id');
        $jurukur = DB::table('lantikan_jurukur as l')->join('panel_jurukur as j', 'j.id', '=', 'l.jurukur_id')->whereIn('l.urusan_id', $ids)
            ->selectRaw("l.urusan_id, string_agg(DISTINCT j.nama_firma, '; ') AS n")->groupBy('l.urusan_id')->pluck('n', 'urusan_id');
        $stage = DB::table('urusan_peringkat as p')->join('urusan_tanah as u', fn ($j) => $j->on('u.id', '=', 'p.urusan_id')->on('u.peringkat_semasa', '=', 'p.no_urutan'))
            ->whereIn('u.id', $ids)->pluck('p.nama_peringkat', 'u.id');

        return $q->map(fn (UrusanTanah $u) => [
            'id' => $u->id, 'no_rujukan_mais' => $u->no_rujukan_mais, 'jenis_urusan' => $u->jenis_urusan, 'jenis_nama' => $u->jenisLabel(),
            'kod_daerah' => $u->kod_daerah, 'kod_mukim' => $u->kod_mukim, 'status' => $u->status, 'status_nama' => Lalai::STATUS_URUSAN[$u->status] ?? $u->status,
            'peringkat_semasa' => $stage[$u->id] ?? null, 'tarikh_mula' => $u->tarikh_mula->toDateString(), 'tarikh_siap' => $u->tarikh_siap?->toDateString(),
            'tempoh_hari' => (int) $u->tarikh_mula->diffInDays($u->tarikh_siap ?? today()), 'lewat_sasaran' => $u->tarikh_siap === null && $u->tarikh_sasaran_siap->lt(today()),
            'jurukur' => $jurukur[$u->id] ?? null, 'jumlah_kos' => Wang::norm($kos[$u->id] ?? '0'), 'status_ptd' => $u->status_ptd,
        ])->values()->all();
    }
}
