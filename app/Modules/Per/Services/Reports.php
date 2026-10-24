<?php

namespace App\Modules\Per\Services;

use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Per\Models\PeringkatPemerolehan;
use App\Modules\Per\Models\Saluran;
use App\Modules\Per\Support\RefNames;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SRS-PER-27/28: acquisition reports by channel, district, period and status,
 * statistics for charts and the distribution map, and the special lists of
 * the SRS "Laporan yang boleh dijana". Every query runs through the case
 * model, so the user's district/bahagian scope applies (SRS-RBC-08).
 */
class Reports
{
    /**
     * @param  array<string, mixed>  $f  saluran, daerah, status, bahagian, peringkat, dari, hingga
     * @return Builder<Pemerolehan>
     */
    public function query(array $f): Builder
    {
        $q = Pemerolehan::query();
        if (! empty($f['saluran'])) {
            $q->whereIn('id_saluran', Saluran::query()->whereIn('kod_saluran', (array) $f['saluran'])->pluck('id'));
        }
        if (! empty($f['daerah'])) {
            $q->whereIn('kod_daerah', (array) $f['daerah']);
        }
        if (! empty($f['status'])) {
            $q->whereIn('status_pemerolehan', (array) $f['status']);
        }
        if (! empty($f['bahagian'])) {
            $q->where('kod_bahagian', $f['bahagian']);
        }
        if (! empty($f['peringkat'])) {
            $q->where('peringkat_semasa', $f['peringkat']);
        }
        if (! empty($f['dari'])) {
            $q->whereDate('tarikh_mula_kes', '>=', $f['dari']);
        }
        if (! empty($f['hingga'])) {
            $q->whereDate('tarikh_mula_kes', '<=', $f['hingga']);
        }

        return $q;
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    public function summary(array $f): array
    {
        $channels = Saluran::query()->orderBy('susunan')->get()->keyBy('id');
        $districts = RefNames::daerah();

        $byChannel = $this->query($f)->toBase()
            ->selectRaw('id_saluran, count(*) AS bilangan, coalesce(sum(nilai_pemerolehan), 0) AS nilai, count(*) FILTER (WHERE status_pemerolehan = ?) AS selesai', [Pemerolehan::SELESAI])
            ->groupBy('id_saluran')->get()->keyBy('id_saluran');

        $byDistrict = $this->query($f)->toBase()
            ->selectRaw('kod_daerah, count(*) AS bilangan, coalesce(sum(nilai_pemerolehan), 0) AS nilai, count(*) FILTER (WHERE status_pemerolehan = ?) AS selesai', [Pemerolehan::SELESAI])
            ->groupBy('kod_daerah')->orderBy('kod_daerah')->get();

        $byStatus = $this->query($f)->toBase()->selectRaw('status_pemerolehan, count(*) AS bilangan')->groupBy('status_pemerolehan')->pluck('bilangan', 'status_pemerolehan');

        $byYear = $this->query($f)->toBase()
            ->selectRaw("date_part('year', tarikh_mula_kes)::int AS tahun, id_saluran, count(*) AS bilangan, coalesce(sum(nilai_pemerolehan), 0) AS nilai")
            ->groupBy('tahun', 'id_saluran')->orderBy('tahun')->get();

        $byFund = $this->query($f)->toBase()
            ->selectRaw("coalesce(nullif(sumber_dana, ''), 'Tidak dinyatakan') AS sumber_dana, count(*) AS bilangan, coalesce(sum(nilai_pemerolehan), 0) AS nilai")
            ->groupBy(DB::raw("coalesce(nullif(sumber_dana, ''), 'Tidak dinyatakan')"))->orderByDesc('nilai')->get();

        $duration = $this->query($f)->toBase()->where('status_pemerolehan', Pemerolehan::SELESAI)
            ->selectRaw('id_saluran, avg(tempoh_keseluruhan_hari) AS purata_hari, avg(tempoh_sasaran_hari) AS sasaran, count(*) AS bilangan')
            ->groupBy('id_saluran')->get()->keyBy('id_saluran');

        $years = [];
        foreach ($byYear as $row) {
            $code = $channels[$row->id_saluran]->kod_saluran ?? '?';
            $years[$row->tahun] ??= ['tahun' => (int) $row->tahun, 'bilangan' => 0, 'nilai' => 0.0, 'saluran' => []];
            $years[$row->tahun]['bilangan'] += (int) $row->bilangan;
            $years[$row->tahun]['nilai'] += (float) $row->nilai;
            $years[$row->tahun]['saluran'][$code] = (int) $row->bilangan;
        }

        $total = $this->query($f)->count();

        return [
            'jumlah' => [
                'kes' => $total,
                'nilai' => (float) $byChannel->sum(fn ($r) => (float) $r->nilai),
                'selesai' => (int) ($byStatus[Pemerolehan::SELESAI] ?? 0),
                'aktif' => (int) collect(Pemerolehan::AKTIF)->sum(fn ($s) => (int) ($byStatus[$s] ?? 0)),
                'lewat' => $this->lateQuery($f)->count(),
                'dokumen_kurang' => (clone $this->query($f))->where('status_kelengkapan_dokumen', 'tidak_lengkap')->whereIn('status_pemerolehan', Pemerolehan::AKTIF)->count(),
            ],
            'mengikut_saluran' => $channels->map(fn (Saluran $s) => [
                'kod' => $s->kod_saluran, 'nama' => $s->nama_saluran, 'singkatan' => $s->singkatan, 'warna' => $s->warna,
                'bilangan' => (int) ($byChannel[$s->id]->bilangan ?? 0), 'nilai' => (float) ($byChannel[$s->id]->nilai ?? 0),
                'selesai' => (int) ($byChannel[$s->id]->selesai ?? 0),
                'purata_hari_selesai' => isset($duration[$s->id]) ? round((float) $duration[$s->id]->purata_hari, 1) : null,
                'tempoh_sasaran_hari' => $s->tempoh_sasaran_hari,
            ])->values()->all(),
            'mengikut_daerah' => $byDistrict->map(fn ($r) => [
                'kod' => $r->kod_daerah, 'nama' => $districts[$r->kod_daerah] ?? ($r->kod_daerah ?? 'Tidak diketahui'),
                'bilangan' => (int) $r->bilangan, 'nilai' => (float) $r->nilai, 'selesai' => (int) $r->selesai,
            ])->values()->all(),
            'mengikut_status' => collect(Pemerolehan::STATUS)->map(fn ($s) => ['status' => $s, 'bilangan' => (int) ($byStatus[$s] ?? 0)])->all(),
            'mengikut_tahun' => array_values($years),
            'mengikut_sumber_dana' => $byFund->map(fn ($r) => ['sumber_dana' => $r->sumber_dana, 'bilangan' => (int) $r->bilangan, 'nilai' => (float) $r->nilai])->values()->all(),
        ];
    }

    /**
     * SRS Modul 4 "Laporan yang boleh dijana" special lists.
     *
     * @param  array<string, mixed>  $f
     * @return list<array<string, mixed>>
     */
    public function special(string $type, array $f): array
    {
        $base = $this->query($f)->with(['saluran', 'peringkat']);

        return match ($type) {
            'dokumen_kurang' => $base->where('status_kelengkapan_dokumen', 'tidak_lengkap')->whereIn('status_pemerolehan', Pemerolehan::AKTIF)
                ->orderBy('tarikh_mula_kes')->limit(500)->get()
                ->map(fn (Pemerolehan $c) => $this->row($c) + ['kekurangan' => app(Checklist::class)->missingAll($c)])->all(),
            'notis_5a' => $this->notis5a($base),
            'keputusan_mmkn' => $base->whereNotNull('butiran_saluran->jenis_keputusan_mmkn')->orderBy('tarikh_mula_kes')->limit(500)->get()
                ->map(fn (Pemerolehan $c) => $this->row($c) + [
                    'jenis_keputusan_mmkn' => $c->butiran_saluran['jenis_keputusan_mmkn'] ?? null,
                    'tarikh_mmkn' => $c->butiran_saluran['tarikh_mmkn'] ?? null,
                    'no_kertas_mmkn' => $c->butiran_saluran['no_kertas_mmkn'] ?? null,
                ])->all(),
            'kes_lewat' => $this->lateQuery($f)->with(['pemerolehan.saluran', 'pemerolehan.peringkat', 'pegawai'])->limit(500)->get()
                ->map(fn (PeringkatPemerolehan $s) => $this->row($s->pemerolehan) + [
                    'peringkat' => $s->nama_peringkat, 'hari' => $s->hariBerjalan(), 'tempoh_sasaran_hari' => $s->tempoh_sasaran_hari,
                    'dieskalasi' => $s->status_eskalasi === 'dieskalasi', 'pegawai' => $s->pegawai?->name,
                ])->all(),
            'kes_selesai' => $base->where('status_pemerolehan', Pemerolehan::SELESAI)->orderByDesc('tarikh_selesai')->limit(500)->get()
                ->map(fn (Pemerolehan $c) => $this->row($c) + [
                    'tarikh_selesai' => $c->tarikh_selesai?->toDateString(), 'tempoh_keseluruhan_hari' => $c->tempoh_keseluruhan_hari,
                    'tempoh_sasaran_hari' => $c->tempoh_sasaran_hari, 'id_hak_milik' => $c->id_hak_milik_dijana,
                    'no_hak_milik' => $c->butiran_hak_milik['hak_milik']['no_hak_milik'] ?? null,
                    'kod_peti_besi' => $c->butiran_hak_milik['peti_besi']['kod_peti_besi'] ?? null,
                    'lokasi_rak' => $c->butiran_hak_milik['peti_besi']['lokasi_rak'] ?? null,
                ])->all(),
            'portal_wakaf' => $base->where('sumber_rekod', 'portal_wakaf')->orderByDesc('created_at')->limit(500)->get()
                ->map(fn (Pemerolehan $c) => $this->row($c) + ['rujukan_portal' => $c->rujukan_luaran])->all(),
            default => [],
        };
    }

    public const SPECIAL = ['dokumen_kurang', 'notis_5a', 'keputusan_mmkn', 'kes_lewat', 'kes_selesai', 'portal_wakaf'];

    /**
     * @param  Builder<Pemerolehan>  $base
     * @return list<array<string, mixed>>
     */
    private function notis5a(Builder $base): array
    {
        $payments = app(Payments5a::class);

        return $base->whereIn('id_saluran', Saluran::query()->where('kod_saluran', 'pemberimilikan_5a')->pluck('id'))
            ->whereIn('status_pemerolehan', Pemerolehan::AKTIF)
            ->whereNotNull('butiran_saluran->tarikh_akhir_bayaran')
            ->get()
            ->map(fn (Pemerolehan $c) => $this->row($c) + $payments->summary($c) + [
                'hari_lagi' => (int) now()->startOfDay()->diffInDays(Carbon::parse((string) $c->butiran_saluran['tarikh_akhir_bayaran']), false),
            ])
            ->filter(fn ($r) => ! $r['lengkap'])
            ->sortBy('hari_lagi')->values()->all();
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Builder<PeringkatPemerolehan>
     */
    private function lateQuery(array $f): Builder
    {
        $cases = $this->query($f)->select('id');

        return PeringkatPemerolehan::query()
            ->whereIn('id_pemerolehan', $cases)
            ->where('status_peringkat', PeringkatPemerolehan::SEMASA)
            ->whereRaw('(current_date - tarikh_masuk::date) > tempoh_sasaran_hari');
    }

    /** @return array<string, mixed> */
    public function row(Pemerolehan $c): array
    {
        $stage = $c->peringkatSemasa();

        return [
            'id' => $c->id, 'no_kes' => $c->no_kes, 'tajuk' => $c->tajuk,
            'saluran' => $c->saluran->kod_saluran, 'nama_saluran' => $c->saluran->nama_saluran, 'warna' => $c->saluran->warna,
            'kod_daerah' => $c->kod_daerah, 'nama_daerah' => RefNames::daerah()[$c->kod_daerah ?? ''] ?? $c->kod_daerah,
            'status' => $c->status_pemerolehan, 'peringkat_semasa' => $stage?->nama_peringkat,
            'tarikh_mula_kes' => $c->tarikh_mula_kes->toDateString(), 'nilai_pemerolehan' => $c->nilai_pemerolehan,
        ];
    }
}
