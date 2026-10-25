<?php

namespace App\Modules\Kba\Services;

use App\Modules\Kba\Models\Analitik as AnalitikModel;
use App\Modules\Kba\Models\Artikel;
use App\Modules\Kba\Models\SumberDokumen;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Knowledge-base analytics & reports (SRS-KBA-18 + "Laporan yang boleh
 * dijana"): most asked questions, content gaps, most / never cited sources,
 * content status, usage by module and role, AI infrastructure usage and
 * index coverage. Computed from the prompt log; `kba:analitik` keeps a
 * monthly roll-up in kba_analitik.
 */
class Analitik
{
    /** @return array{0: Carbon, 1: Carbon} */
    public static function tempoh(?string $dari, ?string $hingga): array
    {
        $to = $hingga !== null ? Carbon::parse($hingga)->endOfDay() : now()->endOfDay();
        $from = $dari !== null ? Carbon::parse($dari)->startOfDay() : $to->copy()->subDays(29)->startOfDay();

        return [$from, $to];
    }

    /** @return array<string, mixed> */
    public function ringkasan(Carbon $dari, Carbon $hingga): array
    {
        $logs = fn () => DB::table('kba_log_prompt as l')->whereBetween('l.cap_masa', [$dari, $hingga]);

        $kerap = $logs()->leftJoin('kba_maklum_balas as m', 'm.id_log_prompt', '=', 'l.id')
            ->selectRaw("l.prompt_dinormalkan AS soalan, COUNT(*) AS bilangan,
                ROUND(100.0 * SUM(CASE WHEN l.status = 'berjaya' AND COALESCE(m.penilaian, 'membantu') = 'membantu' THEN 1 ELSE 0 END) / COUNT(*)) AS kadar_memuaskan,
                MAX(l.modul) AS modul, MAX(l.cap_masa) AS terakhir")
            ->where('l.status', '<>', 'ditolak')
            ->groupBy('l.prompt_dinormalkan')->orderByDesc('bilangan')->orderByDesc('terakhir')->limit(20)->get();

        $dirujuk = DB::table(DB::raw('(SELECT (e->>\'id_sumber\')::bigint AS id_sumber FROM kba_log_prompt l, jsonb_array_elements(l.senarai_sumber_dipetik) e WHERE l.cap_masa BETWEEN ? AND ?) x'))
            ->addBinding([$dari, $hingga], 'from')
            ->join('kba_sumber_dokumen as d', 'd.id', '=', 'x.id_sumber')
            ->selectRaw('d.id, d.tajuk, d.jenis_dokumen, d.asal, COUNT(*) AS bilangan')
            ->groupBy('d.id', 'd.tajuk', 'd.jenis_dokumen', 'd.asal')->orderByDesc('bilangan')->limit(20)->get();
        $citedIds = DB::table(DB::raw('(SELECT DISTINCT (e->>\'id_sumber\')::bigint AS id_sumber FROM kba_log_prompt l, jsonb_array_elements(l.senarai_sumber_dipetik) e WHERE l.cap_masa BETWEEN ? AND ?) x'))
            ->addBinding([$dari, $hingga], 'from')->pluck('id_sumber')->map(fn ($v) => (int) $v)->all();
        $tidakDirujuk = SumberDokumen::query()->where('aktif', true)->whereNotIn('id', $citedIds ?: [0])
            ->orderBy('tajuk')->limit(50)->get(['id', 'tajuk', 'jenis_dokumen', 'asal']);

        $mengikutModul = $logs()->selectRaw("COALESCE(l.modul, '-') AS modul, COUNT(*) AS bilangan, SUM(CASE WHEN l.status = 'tiada_sumber' THEN 1 ELSE 0 END) AS tiada_sumber")
            ->groupBy(DB::raw("COALESCE(l.modul, '-')"))->orderByDesc('bilangan')->get();
        $mengikutPeranan = DB::table(DB::raw("(SELECT jsonb_array_elements_text(CASE WHEN jsonb_array_length(l.peranan) = 0 THEN '[\"-\"]'::jsonb ELSE l.peranan END) AS peranan, l.status FROM kba_log_prompt l WHERE l.cap_masa BETWEEN ? AND ?) x"))
            ->addBinding([$dari, $hingga], 'from')
            ->selectRaw("peranan, COUNT(*) AS bilangan, SUM(CASE WHEN status = 'tiada_sumber' THEN 1 ELSE 0 END) AS tiada_sumber")
            ->groupBy('peranan')->orderByDesc('bilangan')->limit(30)->get();

        $infra = $logs()->selectRaw("COUNT(*) AS prompt, COALESCE(SUM(l.bilangan_token), 0) AS token, COALESCE(ROUND(AVG(l.masa_respons_ms)), 0) AS purata_ms,
                SUM(CASE WHEN l.mod_jawapan = 'generatif' THEN 1 ELSE 0 END) AS generatif, SUM(CASE WHEN l.mod_jawapan = 'ekstraktif' THEN 1 ELSE 0 END) AS ekstraktif,
                SUM(CASE WHEN l.status = 'ditolak' THEN 1 ELSE 0 END) AS ditolak")->first();
        $model = $logs()->whereNotNull('l.model_digunakan')->selectRaw('l.model_digunakan AS model, COUNT(*) AS bilangan, ROUND(AVG(l.masa_respons_ms)) AS purata_ms')
            ->groupBy('l.model_digunakan')->get();

        $artikel = Artikel::query()->selectRaw('status, COUNT(*) AS bilangan')->groupBy('status')->pluck('bilangan', 'status');
        $lewat = Artikel::query()->where('status', 'diterbitkan')->whereDate('tarikh_semakan_seterusnya', '<', now()->toDateString())->count();
        $tiket = Artikel::query()->where('sumber_asal', 'tiket')->selectRaw('status, COUNT(*) AS bilangan')->groupBy('status')->pluck('bilangan', 'status');

        $maklumBalas = DB::table('kba_maklum_balas')->whereBetween('cap_masa', [$dari, $hingga])
            ->selectRaw('penilaian, status_tindakan, COUNT(*) AS bilangan')->groupBy('penilaian', 'status_tindakan')->get();

        return [
            'tempoh' => ['dari' => $dari->toDateString(), 'hingga' => $hingga->toDateString()],
            'soalan_kerap' => $kerap,
            'jurang' => $this->jurang($dari, $hingga),
            'sumber_paling_dirujuk' => $dirujuk,
            'sumber_tidak_dirujuk' => $tidakDirujuk,
            'mengikut_modul' => $mengikutModul,
            'mengikut_peranan' => $mengikutPeranan,
            'infrastruktur' => ['ringkasan' => $infra, 'model' => $model],
            'artikel' => ['status' => $artikel, 'melepasi_tarikh_semakan' => $lewat, 'dari_tiket' => $tiket],
            'maklum_balas' => $maklumBalas,
            'liputan' => $this->liputan(),
        ];
    }

    /**
     * Content gaps: questions answered "insufficient sources", or whose
     * answer users marked not helpful / wrong source.
     *
     * @return list<array{soalan: string, bilangan: int, tiada_sumber: int, maklum_balas_negatif: int, terakhir: string, contoh: string}>
     */
    public function jurang(Carbon $dari, Carbon $hingga, int $had = 50): array
    {
        return DB::table('kba_log_prompt as l')
            ->leftJoin('kba_maklum_balas as m', 'm.id_log_prompt', '=', 'l.id')
            ->whereBetween('l.cap_masa', [$dari, $hingga])
            ->where(fn ($q) => $q->where('l.status', 'tiada_sumber')->orWhereIn('m.penilaian', ['tidak_membantu', 'sumber_salah']))
            ->selectRaw("l.prompt_dinormalkan AS soalan, COUNT(*) AS bilangan,
                SUM(CASE WHEN l.status = 'tiada_sumber' THEN 1 ELSE 0 END) AS tiada_sumber,
                SUM(CASE WHEN m.penilaian IN ('tidak_membantu', 'sumber_salah') THEN 1 ELSE 0 END) AS maklum_balas_negatif,
                MAX(l.cap_masa) AS terakhir, MIN(l.prompt) AS contoh")
            ->groupBy('l.prompt_dinormalkan')->orderByDesc('bilangan')->orderByDesc('terakhir')->limit($had)->get()
            ->map(fn ($r) => [
                'soalan' => (string) $r->soalan, 'bilangan' => (int) $r->bilangan, 'tiada_sumber' => (int) $r->tiada_sumber,
                'maklum_balas_negatif' => (int) $r->maklum_balas_negatif, 'terakhir' => (string) $r->terakhir, 'contoh' => mb_substr((string) $r->contoh, 0, 300),
            ])->values()->all();
    }

    /**
     * Index coverage report (acceptance #1): every source, version, index
     * status and last index date.
     *
     * @return list<array<string, mixed>>
     */
    public function liputan(): array
    {
        return SumberDokumen::query()->orderBy('asal')->orderBy('tajuk')->get()
            ->map(fn (SumberDokumen $s) => [
                'id' => $s->id, 'tajuk' => $s->tajuk, 'asal' => $s->asal, 'jenis_dokumen' => $s->jenis_dokumen, 'versi' => $s->versiPaparan(),
                'klasifikasi' => $s->klasifikasi, 'status_indeks' => $s->status_indeks, 'bilangan_segmen' => $s->bilangan_segmen,
                'tarikh_indeks_akhir' => $s->tarikh_indeks_akhir?->toIso8601String(), 'aktif' => $s->aktif, 'mesej_indeks' => $s->mesej_indeks,
            ])->values()->all();
    }

    /** Monthly roll-up into kba_analitik (SRS-KBA-18 entity). */
    public function gulung(string $tempoh): int
    {
        $from = Carbon::createFromFormat('Y-m', $tempoh)->startOfMonth();
        $to = $from->copy()->endOfMonth();
        $rows = DB::table('kba_log_prompt as l')->leftJoin('kba_maklum_balas as m', 'm.id_log_prompt', '=', 'l.id')
            ->whereBetween('l.cap_masa', [$from, $to])->where('l.status', '<>', 'ditolak')
            ->selectRaw("l.prompt_dinormalkan AS soalan, COUNT(*) AS bilangan, MAX(l.modul) AS modul,
                ROUND(100.0 * SUM(CASE WHEN l.status = 'berjaya' AND COALESCE(m.penilaian, 'membantu') = 'membantu' THEN 1 ELSE 0 END) / COUNT(*)) AS kadar,
                MODE() WITHIN GROUP (ORDER BY (l.senarai_sumber_dipetik->0->>'id_sumber')) AS sumber")
            ->groupBy('l.prompt_dinormalkan')->get();

        $n = 0;
        foreach ($rows as $r) {
            $sumberId = $r->sumber !== null ? (int) $r->sumber : null;
            $artikelId = null;
            if ($sumberId !== null && ($kod = SumberDokumen::query()->whereKey($sumberId)->value('kod')) !== null && str_starts_with((string) $kod, 'artikel:')) {
                $artikelId = (int) substr((string) $kod, 8);
            }
            AnalitikModel::query()->updateOrCreate(['tempoh' => $tempoh, 'soalan_dinormalkan' => (string) $r->soalan], [
                'bilangan_ditanya' => (int) $r->bilangan,
                'kadar_jawapan_memuaskan' => (int) $r->kadar,
                'id_sumber_paling_dirujuk' => $sumberId !== null && SumberDokumen::query()->whereKey($sumberId)->exists() ? $sumberId : null,
                'id_artikel_paling_dirujuk' => $artikelId !== null && Artikel::query()->whereKey($artikelId)->exists() ? $artikelId : null,
                'modul' => $r->modul,
            ]);
            $n++;
        }

        return $n;
    }
}
