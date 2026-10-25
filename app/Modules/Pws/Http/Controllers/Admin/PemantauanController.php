<?php

namespace App\Modules\Pws\Http\Controllers\Admin;

use App\Modules\Pws\Models\Permohonan;
use App\Modules\Pws\Models\SesiBayaran;
use App\Modules\Pws\Services\PermohonanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Gateway monitoring for MAIS officers (SRS-PWS-22/27, reports in the
 * module section): portal submissions and their delivery to PER/PJK, the
 * integration log with failures and retries, payment sessions by channel,
 * and portal analytics. No personal data (the outbox content is never shown).
 */
class PemantauanController
{
    public function ringkasan(): JsonResponse
    {
        $apps = DB::table('pws_permohonan')->select('jenis', 'status_penghantaran', DB::raw('count(*) as n'))->groupBy('jenis', 'status_penghantaran')->get();
        $pay = DB::table('pws_sesi_bayaran')->select('saluran_bayaran', 'status', DB::raw('count(*) as n'), DB::raw('sum(jumlah) as amaun'))->groupBy('saluran_bayaran', 'status')->get();
        $since = now()->subDay();
        $log = DB::table('pws_log_integrasi')->where('cap_masa', '>=', $since)
            ->selectRaw('arah, count(*) as n, sum(case when kod_status >= 400 or kod_status is null then 1 else 0 end) as gagal, avg(tempoh_ms) as purata_ms')->groupBy('arah')->get();

        return response()->json(['data' => [
            'permohonan' => $apps->map(fn ($r) => ['jenis' => $r->jenis, 'status_penghantaran' => $r->status_penghantaran, 'bilangan' => (int) $r->n])->all(),
            'bayaran' => $pay->map(fn ($r) => ['saluran' => $r->saluran_bayaran, 'status' => $r->status, 'bilangan' => (int) $r->n, 'amaun' => number_format((float) $r->amaun, 2, '.', '')])->all(),
            'integrasi_24j' => $log->map(fn ($r) => ['arah' => $r->arah, 'bilangan' => (int) $r->n, 'gagal' => (int) $r->gagal, 'purata_ms' => $r->purata_ms !== null ? (int) round((float) $r->purata_ms) : null])->all(),
        ]]);
    }

    public function permohonan(Request $request): JsonResponse
    {
        $q = $request->validate([
            'jenis' => ['nullable', Rule::in(['wakaf', 'sewaan'])],
            'status_penghantaran' => ['nullable', Rule::in(['menunggu', 'dihantar', 'diterima', 'gagal'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $page = Permohonan::query()
            ->when($q['jenis'] ?? null, fn ($w, $v) => $w->where('jenis', $v))
            ->when($q['status_penghantaran'] ?? null, fn ($w, $v) => $w->where('status_penghantaran', $v))
            ->orderByDesc('created_at')->paginate((int) ($q['per_page'] ?? 25));

        return response()->json([
            'data' => collect($page->items())->map(fn (Permohonan $p) => [
                'id' => $p->id, 'no_rujukan' => $p->no_rujukan, 'jenis' => $p->jenis, 'status' => $p->status, 'status_nama' => PermohonanService::label($p->status, $p->status_nama),
                'status_penghantaran' => $p->status_penghantaran, 'bilangan_cuba' => $p->bilangan_cuba, 'ralat_terakhir' => $p->ralat_terakhir,
                'rujukan_sisdata' => $p->rujukan_sisdata, 'kod_daerah' => $p->kod_daerah, 'dihantar' => $p->created_at->toIso8601String(),
                'tarikh_status' => $p->tarikh_status?->toIso8601String(),
            ])->all(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    /** SRS-PWS-22: manual retry of failed deliveries (the scheduler also retries). */
    public function cubaSemula(PermohonanService $service): JsonResponse
    {
        $r = $service->retryFailed();

        return response()->json(['data' => $r, 'message' => __('pws::pws.mesej.cuba_semula', $r)]);
    }

    public function log(Request $request): JsonResponse
    {
        $q = $request->validate([
            'arah' => ['nullable', Rule::in(['masuk', 'keluar'])],
            'gagal' => ['nullable', 'boolean'],
            'rujukan' => ['nullable', 'string', 'max:80'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);
        $page = DB::table('pws_log_integrasi as l')->leftJoin('pws_klien_api as k', 'k.id', '=', 'l.klien_id')
            ->when($q['arah'] ?? null, fn ($w, $v) => $w->where('l.arah', $v))
            ->when((bool) ($q['gagal'] ?? false), fn ($w) => $w->where(fn ($x) => $x->where('l.kod_status', '>=', 400)->orWhereNull('l.kod_status')))
            ->when($q['rujukan'] ?? null, fn ($w, $v) => $w->where('l.nombor_rujukan', $v))
            ->select('l.*', 'k.nama as nama_klien')->orderByDesc('l.id')->paginate((int) ($q['per_page'] ?? 50));

        return response()->json([
            'data' => collect($page->items())->map(fn ($r) => [
                'id' => $r->id, 'arah' => $r->arah, 'kaedah' => $r->kaedah, 'endpoint' => $r->endpoint, 'klien' => $r->nama_klien,
                'nombor_rujukan' => $r->nombor_rujukan, 'ringkasan_payload' => json_decode((string) $r->ringkasan_payload, true),
                'kod_status' => $r->kod_status, 'mesej_ralat' => $r->mesej_ralat, 'tempoh_ms' => $r->tempoh_ms,
                'bilangan_cuba_semula' => $r->bilangan_cuba_semula, 'cap_masa' => (string) $r->cap_masa,
            ])->all(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function bayaran(): JsonResponse
    {
        return response()->json(['data' => SesiBayaran::query()->orderByDesc('created_at')->limit(100)->get()->map(fn (SesiBayaran $s) => [
            'no_rujukan' => $s->no_rujukan, 'no_kontrak' => $s->no_kontrak, 'jumlah' => (string) $s->jumlah, 'saluran' => $s->saluran_bayaran,
            'status' => $s->status, 'disahkan_melalui' => $s->disahkan_melalui, 'dimulakan' => $s->cap_masa_mula?->toIso8601String(),
            'disahkan' => $s->cap_masa_pengesahan?->toIso8601String(),
        ])->all()]);
    }

    /** SRS-PWS-27: visits, popular pages, form completion rate. */
    public function analitik(Request $request): JsonResponse
    {
        $q = $request->validate(['dari' => ['nullable', 'date_format:Y-m-d'], 'hingga' => ['nullable', 'date_format:Y-m-d']]);
        $from = $q['dari'] ?? now()->subDays(30)->toDateString();
        $to = $q['hingga'] ?? now()->toDateString();
        $base = DB::table('pws_analitik_lawatan')->whereBetween('tarikh', [$from, $to]);
        $tot = (clone $base)->selectRaw('coalesce(sum(bilangan_lawatan),0) as lawatan, coalesce(sum(borang_dimulakan),0) as mula, coalesce(sum(borang_dihantar),0) as hantar')->first();
        $popular = (clone $base)->select('halaman', DB::raw('sum(bilangan_lawatan) as n'))->groupBy('halaman')->orderByDesc('n')->limit(10)->get();
        $daily = (clone $base)->select('tarikh', DB::raw('sum(bilangan_lawatan) as n'))->groupBy('tarikh')->orderBy('tarikh')->get();
        $apps = DB::table('pws_permohonan')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->select('jenis', 'status', DB::raw('count(*) as n'))->groupBy('jenis', 'status')->get();
        $started = (int) ($tot->mula ?? 0);

        return response()->json(['data' => [
            'dari' => $from, 'hingga' => $to,
            'jumlah_lawatan' => (int) ($tot->lawatan ?? 0),
            'kadar_penyempurnaan_borang' => $started > 0 ? round(((int) $tot->hantar) / $started * 100, 1) : null,
            'halaman_popular' => $popular->map(fn ($r) => ['halaman' => $r->halaman, 'lawatan' => (int) $r->n])->all(),
            'lawatan_harian' => $daily->map(fn ($r) => ['tarikh' => (string) $r->tarikh, 'lawatan' => (int) $r->n])->all(),
            'permohonan' => $apps->map(fn ($r) => ['jenis' => $r->jenis, 'status' => $r->status, 'bilangan' => (int) $r->n])->all(),
        ]]);
    }
}
