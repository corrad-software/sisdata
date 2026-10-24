<?php

namespace App\Modules\Adu\Services;

use App\Models\User;
use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Models\AuditCapaianPengadu;
use App\Modules\Adu\Support\Lalai;
use App\Modules\Adu\Support\Rujukan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * SRS-ADU-16/25/26/27: dashboard figures, reports and analytics. Every query
 * starts from Aduan::query(), so the user's daerah/mukim/klasifikasi scope
 * (ScopedByAccess) applies — a district officer only ever counts their
 * district (SRS-RBC-08).
 */
class Analitik
{
    public const PENAPIS = ['dari', 'hingga', 'kod_daerah', 'kod_mukim', 'kategori', 'saluran', 'status', 'id_pegawai'];

    /** Explicit viewer (KPI providers); null → the session user via ScopedByAccess. */
    private ?User $untuk = null;

    public function __construct(private Rujukan $ref) {}

    public function untuk(?User $user): static
    {
        $clone = clone $this;
        $clone->untuk = $user;

        return $clone;
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Builder<Aduan>
     */
    public function asas(array $f): Builder
    {
        $user = $this->untuk;

        return Aduan::query()
            ->when($user !== null, fn ($q) => $q->bolehDilihat($user))
            ->when($f['dari'] ?? null, fn ($q, $v) => $q->where('aduan.tarikh_terima', '>=', $v))
            ->when($f['hingga'] ?? null, fn ($q, $v) => $q->where('aduan.tarikh_terima', '<', now()->parse((string) $v)->addDay()))
            ->when($f['kod_daerah'] ?? null, fn ($q, $v) => $q->where('aduan.kod_daerah', $v))
            ->when($f['kod_mukim'] ?? null, fn ($q, $v) => $q->where('aduan.kod_mukim', $v))
            ->when($f['kategori'] ?? null, fn ($q, $v) => $q->where('aduan.kategori', $v))
            ->when($f['saluran'] ?? null, fn ($q, $v) => $q->where('aduan.saluran_penerimaan', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('aduan.status', $v))
            ->when($f['id_pegawai'] ?? null, fn ($q, $v) => $q->where('aduan.id_pegawai', $v));
    }

    /**
     * SRS-ADU-26 / acceptance #7.
     *
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    public function ringkasan(array $f): array
    {
        $row = $this->asas($f)->toBase()
            ->leftJoin('jejak_sla_aduan as j', fn ($j) => $j->on('j.aduan_id', '=', 'aduan.id')->where('j.semasa', true))
            ->selectRaw("count(*) AS jumlah,
                count(*) FILTER (WHERE aduan.status IN ('baharu','ditugaskan','dalam_siasatan','dirujuk','menunggu_kelulusan')) AS aktif,
                count(*) FILTER (WHERE aduan.status IN ('baharu','ditugaskan','dalam_siasatan','dirujuk','menunggu_kelulusan') AND j.masa_sasaran_selesai < now()) AS tertangguh,
                count(*) FILTER (WHERE aduan.status = 'ditutup') AS diselesaikan,
                count(*) FILTER (WHERE aduan.status = 'pendua') AS pendua,
                count(*) FILTER (WHERE aduan.status_bukan_tanah_mais) AS bukan_tanah_mais,
                count(*) FILTER (WHERE aduan.status_tanpa_nama) AS tanpa_nama,
                avg(EXTRACT(EPOCH FROM (aduan.tarikh_tutup - aduan.tarikh_terima)) / 86400.0) FILTER (WHERE aduan.status = 'ditutup') AS purata_hari_selesai,
                avg(EXTRACT(EPOCH FROM (j.masa_respons_sebenar - j.masa_mula)) / 3600.0) FILTER (WHERE j.masa_respons_sebenar IS NOT NULL) AS purata_jam_respons,
                count(*) FILTER (WHERE j.status_sla = 'dipenuhi') AS sla_dipenuhi,
                count(*) FILTER (WHERE j.status_sla = 'tidak_dipenuhi') AS sla_tidak_dipenuhi,
                count(*) FILTER (WHERE aduan.status IN ('baharu','ditugaskan','dalam_siasatan','dirujuk','menunggu_kelulusan') AND (j.masa_sasaran_selesai IS NULL OR j.masa_sasaran_selesai >= now())) AS aktif_dalam_sla")
            ->first();

        $closedWithSla = (int) $row->sla_dipenuhi + (int) $row->sla_tidak_dipenuhi;

        return [
            'jumlah' => (int) $row->jumlah,
            'aktif' => (int) $row->aktif,
            'tertangguh' => (int) $row->tertangguh,
            'diselesaikan' => (int) $row->diselesaikan,
            'pendua' => (int) $row->pendua,
            'bukan_tanah_mais' => (int) $row->bukan_tanah_mais,
            'tanpa_nama' => (int) $row->tanpa_nama,
            'purata_hari_selesai' => $row->purata_hari_selesai !== null ? round((float) $row->purata_hari_selesai, 1) : null,
            'purata_jam_respons' => $row->purata_jam_respons !== null ? round((float) $row->purata_jam_respons, 1) : null,
            'peratus_sla_dipenuhi' => $closedWithSla > 0 ? round(100 * (int) $row->sla_dipenuhi / $closedWithSla, 1) : null,
            'peratus_aktif_dalam_sla' => (int) $row->aktif > 0 ? round(100 * (int) $row->aktif_dalam_sla / (int) $row->aktif, 1) : null,
            'mengikut_kategori' => $this->pecahan($f, 'aduan.kategori', fn ($k) => $this->ref->namaKategori($k)),
            'mengikut_daerah' => $this->pecahan($f, 'aduan.kod_daerah', fn ($k) => $this->ref->namaDaerah($k)),
            'mengikut_saluran' => $this->pecahan($f, 'aduan.saluran_penerimaan', fn ($k) => Lalai::SALURAN[$k] ?? $k),
            'mengikut_status' => $this->pecahan($f, 'aduan.status', fn ($k) => Lalai::STATUS[$k] ?? $k),
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return list<array{kod: string|null, label: string|null, jumlah: int, aktif: int, ditutup: int}>
     */
    public function pecahan(array $f, string $column, callable $label): array
    {
        return $this->asas($f)->toBase()
            ->selectRaw("{$column} AS kod, count(*) AS jumlah,
                count(*) FILTER (WHERE aduan.status IN ('baharu','ditugaskan','dalam_siasatan','dirujuk','menunggu_kelulusan')) AS aktif,
                count(*) FILTER (WHERE aduan.status = 'ditutup') AS ditutup")
            ->groupBy(DB::raw($column))->orderByDesc('jumlah')->get()
            ->map(fn ($r) => ['kod' => $r->kod, 'label' => $r->kod === null ? '—' : $label((string) $r->kod), 'jumlah' => (int) $r->jumlah, 'aktif' => (int) $r->aktif, 'ditutup' => (int) $r->ditutup])
            ->values()->all();
    }

    /**
     * SLA performance by category / officer / district (SRS-ADU-25/27).
     *
     * @param  array<string, mixed>  $f
     * @return list<array<string, mixed>>
     */
    public function prestasiSla(array $f, string $ikut = 'kategori'): array
    {
        $column = ['kategori' => 'aduan.kategori', 'daerah' => 'aduan.kod_daerah', 'pegawai' => 'aduan.id_pegawai'][$ikut] ?? 'aduan.kategori';
        $rows = $this->asas($f)->toBase()
            ->join('jejak_sla_aduan as j', 'j.aduan_id', '=', 'aduan.id')
            ->leftJoin('users as u', 'u.id', '=', 'aduan.id_pegawai')
            ->selectRaw("{$column} AS kod, max(u.name) AS nama_pegawai, count(DISTINCT aduan.id) AS jumlah,
                count(*) FILTER (WHERE j.status_sla = 'dipenuhi') AS dipenuhi,
                count(*) FILTER (WHERE j.status_sla = 'tidak_dipenuhi') AS tidak_dipenuhi,
                count(*) FILTER (WHERE j.semasa AND j.masa_selesai_sebenar IS NULL AND j.masa_sasaran_selesai < now()) AS melepasi_terbuka,
                count(*) FILTER (WHERE j.masa_respons_sebenar IS NOT NULL AND j.masa_respons_sebenar <= j.masa_sasaran_respons) AS respons_dalam_sla,
                count(*) FILTER (WHERE j.masa_respons_sebenar IS NOT NULL) AS ada_respons,
                avg(EXTRACT(EPOCH FROM (j.masa_selesai_sebenar - j.masa_mula)) / 86400.0) AS purata_hari_selesai")
            ->groupBy(DB::raw($column))->orderByDesc('jumlah')->get();

        return $rows->map(function ($r) use ($ikut) {
            $closed = (int) $r->dipenuhi + (int) $r->tidak_dipenuhi;

            return [
                'kod' => $r->kod,
                'label' => match ($ikut) {
                    'daerah' => $this->ref->namaDaerah($r->kod),
                    'pegawai' => $r->nama_pegawai ?? '(belum ditugaskan)',
                    default => $this->ref->namaKategori($r->kod),
                },
                'jumlah' => (int) $r->jumlah,
                'dipenuhi' => (int) $r->dipenuhi,
                'tidak_dipenuhi' => (int) $r->tidak_dipenuhi,
                'melepasi_terbuka' => (int) $r->melepasi_terbuka,
                'peratus_dipenuhi' => $closed > 0 ? round(100 * (int) $r->dipenuhi / $closed, 1) : null,
                'peratus_respons_dalam_sla' => (int) $r->ada_respons > 0 ? round(100 * (int) $r->respons_dalam_sla / (int) $r->ada_respons, 1) : null,
                'purata_hari_selesai' => $r->purata_hari_selesai !== null ? round((float) $r->purata_hari_selesai, 1) : null,
            ];
        })->values()->all();
    }

    /**
     * Pending / overdue complaints with their escalation level.
     *
     * @param  array<string, mixed>  $f
     * @return list<array<string, mixed>>
     */
    public function tertangguh(array $f, int $limit = 500): array
    {
        return $this->asas($f)->terbuka()->toBase()
            ->join('jejak_sla_aduan as j', fn ($j) => $j->on('j.aduan_id', '=', 'aduan.id')->where('j.semasa', true))
            ->leftJoin('users as u', 'u.id', '=', 'aduan.id_pegawai')
            ->where('j.masa_sasaran_selesai', '<', now())
            ->selectRaw('aduan.id, aduan.no_rujukan_aduan, aduan.kategori, aduan.kod_daerah, aduan.status, u.name AS pegawai, j.masa_sasaran_selesai, j.bilangan_eskalasi,
                (SELECT max(e.peringkat) FROM eskalasi_aduan e WHERE e.aduan_id = aduan.id) AS peringkat_eskalasi')
            ->orderBy('j.masa_sasaran_selesai')->limit($limit)->get()
            ->map(fn ($r) => [
                'id' => $r->id, 'no_rujukan_aduan' => $r->no_rujukan_aduan, 'kategori' => $this->ref->namaKategori($r->kategori),
                'daerah' => $this->ref->namaDaerah($r->kod_daerah), 'status' => Lalai::STATUS[$r->status] ?? $r->status, 'pegawai' => $r->pegawai,
                'masa_sasaran_selesai' => (string) $r->masa_sasaran_selesai, 'peringkat_eskalasi' => $r->peringkat_eskalasi !== null ? (int) $r->peringkat_eskalasi : null,
            ])->values()->all();
    }

    /**
     * SRS-ADU-27: lots with repeat complaints.
     *
     * @param  array<string, mixed>  $f
     * @return list<array{lot_id: string, bilangan: int, terbuka: int, kategori: string|null, terakhir: string}>
     */
    public function berulang(array $f, int $min = 2, int $limit = 200): array
    {
        return $this->asas($f)->toBase()->whereNotNull('aduan.lot_id')
            ->selectRaw("aduan.lot_id, count(*) AS bilangan, count(*) FILTER (WHERE aduan.status IN ('baharu','ditugaskan','dalam_siasatan','dirujuk','menunggu_kelulusan')) AS terbuka,
                mode() WITHIN GROUP (ORDER BY aduan.kategori) AS kategori, max(aduan.tarikh_terima) AS terakhir")
            ->groupBy('aduan.lot_id')->havingRaw('count(*) >= ?', [$min])->orderByDesc('bilangan')->limit($limit)->get()
            ->map(fn ($r) => ['lot_id' => (string) $r->lot_id, 'bilangan' => (int) $r->bilangan, 'terbuka' => (int) $r->terbuka, 'kategori' => $this->ref->namaKategori($r->kategori), 'terakhir' => (string) $r->terakhir])
            ->values()->all();
    }

    /**
     * SRS-ADU-16/27: hotspots by mukim (count + open + top category).
     *
     * @param  array<string, mixed>  $f
     * @return list<array<string, mixed>>
     */
    public function kawasanPanas(array $f, int $limit = 50): array
    {
        return $this->asas($f)->toBase()->whereNotNull('aduan.kod_mukim')
            ->selectRaw("aduan.kod_mukim, max(aduan.kod_daerah) AS kod_daerah, count(*) AS jumlah,
                count(*) FILTER (WHERE aduan.status IN ('baharu','ditugaskan','dalam_siasatan','dirujuk','menunggu_kelulusan')) AS terbuka,
                mode() WITHIN GROUP (ORDER BY aduan.kategori) AS kategori_utama,
                ST_Y(ST_Centroid(ST_Collect(aduan.koordinat))) AS lat, ST_X(ST_Centroid(ST_Collect(aduan.koordinat))) AS lng")
            ->groupBy('aduan.kod_mukim')->orderByDesc('jumlah')->limit($limit)->get()
            ->map(fn ($r) => [
                'kod_mukim' => $r->kod_mukim, 'mukim' => $this->ref->namaMukim($r->kod_mukim), 'daerah' => $this->ref->namaDaerah($r->kod_daerah),
                'jumlah' => (int) $r->jumlah, 'terbuka' => (int) $r->terbuka, 'kategori_utama' => $this->ref->namaKategori($r->kategori_utama),
                'lat' => $r->lat !== null ? (float) $r->lat : null, 'lng' => $r->lng !== null ? (float) $r->lng : null,
            ])->values()->all();
    }

    /**
     * Monthly trend (received / closed) for the last N months.
     *
     * @param  array<string, mixed>  $f
     * @return list<array{bulan: string, diterima: int, ditutup: int}>
     */
    public function trend(array $f, int $months = 12): array
    {
        $from = now()->startOfMonth()->subMonths($months - 1);
        $received = $this->asas($f)->toBase()->where('aduan.tarikh_terima', '>=', $from)
            ->selectRaw("to_char(aduan.tarikh_terima AT TIME ZONE 'Asia/Kuala_Lumpur', 'YYYY-MM') AS b, count(*) AS n")->groupBy('b')->pluck('n', 'b');
        $closed = $this->asas($f)->toBase()->where('aduan.tarikh_tutup', '>=', $from)
            ->selectRaw("to_char(aduan.tarikh_tutup AT TIME ZONE 'Asia/Kuala_Lumpur', 'YYYY-MM') AS b, count(*) AS n")->groupBy('b')->pluck('n', 'b');
        $out = [];
        for ($i = 0; $i < $months; $i++) {
            $key = $from->copy()->addMonths($i)->format('Y-m');
            $out[] = ['bulan' => $key, 'diterima' => (int) ($received[$key] ?? 0), 'ditutup' => (int) ($closed[$key] ?? 0)];
        }

        return $out;
    }

    /**
     * Non-MAIS complaints with suggested agencies.
     *
     * @param  array<string, mixed>  $f
     * @return list<array<string, mixed>>
     */
    public function bukanMais(array $f, int $limit = 500): array
    {
        return $this->asas($f)->where('status_bukan_tanah_mais', true)->orderByDesc('tarikh_terima')->limit($limit)->get()
            ->map(fn (Aduan $a) => [
                'id' => $a->id, 'no_rujukan_aduan' => $a->no_rujukan_aduan, 'tarikh_terima' => $a->tarikh_terima?->toIso8601String(),
                'daerah' => $this->ref->namaDaerah($a->kod_daerah), 'kategori' => $this->ref->namaKategori($a->kategori),
                'status' => Lalai::STATUS[$a->status] ?? $a->status, 'agensi' => implode('; ', array_column($a->agensi_dicadang ?? [], 'nama')),
                'jawapan_dihantar' => $a->jawapan_dihantar,
            ])->values()->all();
    }

    /**
     * Resolutions by resolution category.
     *
     * @param  array<string, mixed>  $f
     * @return list<array{kod: string, label: string, jumlah: int}>
     */
    public function resolusi(array $f): array
    {
        return $this->asas($f)->toBase()->join('resolusi_aduan as r', 'r.aduan_id', '=', 'aduan.id')->where('r.status', 'lulus')
            ->selectRaw('r.kategori_resolusi AS kod, count(*) AS jumlah')->groupBy('r.kategori_resolusi')->orderByDesc('jumlah')->get()
            ->map(fn ($r) => ['kod' => (string) $r->kod, 'label' => Lalai::KATEGORI_RESOLUSI[$r->kod] ?? (string) $r->kod, 'jumlah' => (int) $r->jumlah])
            ->values()->all();
    }

    /**
     * SRS-ADU-21 report: reporter-identity access log (no identity values).
     *
     * @param  array<string, mixed>  $f
     * @return list<array<string, mixed>>
     */
    public function auditCapaian(array $f, int $limit = 1000): array
    {
        $ids = $this->asas($f)->select('aduan.id');

        return AuditCapaianPengadu::query()->whereIn('aduan_id', $ids)->with('pegawai')->orderByDesc('tarikh_masa')->limit($limit)->get()
            ->map(fn (AuditCapaianPengadu $r) => [
                'id' => $r->id, 'no_rujukan_aduan' => $r->no_rujukan_aduan, 'tarikh_masa' => $r->tarikh_masa->toIso8601String(),
                'pegawai' => $r->pegawai?->name, 'medan' => implode(', ', $r->medan_dicapai), 'tujuan' => $r->tujuan_capaian,
                'asas' => $r->asas_capaian, 'alamat_ip' => $r->alamat_ip,
            ])->values()->all();
    }
}
