<?php

namespace App\Modules\Pws\Http\Controllers\Awam;

use App\Modules\Pws\Models\Kandungan;
use App\Modules\Pws\Models\StatistikTerbitan;
use App\Modules\Pws\Services\InternalApi;
use App\Modules\Pws\Services\KandunganService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * Read-only public feeds (scope "baca") — Awam data only (SRS-PWS-06/17/18,
 * SRS-GEN classification): every field is whitelisted here again even when
 * the source module already returns a public shape, so a new internal field
 * upstream can never leak to the portal.
 */
class MaklumatController
{
    /** Fields of MOD-PJK's public premises shape that the portal may show. */
    private const PREMIS = ['kod_premis', 'nama_premis', 'jenis_aset', 'kategori_hartanah', 'kategori_hartanah_nama', 'keluasan_m2', 'kod_daerah', 'kod_mukim', 'kadar_tawaran_sebulan', 'keterangan'];

    private const AGIHAN = ['tempoh', 'kod_daerah', 'kategori_hartanah', 'status_perolehan', 'bil_tempat', 'hasil', 'agihan'];

    public function __construct(private InternalApi $api) {}

    /** SRS-PWS-06: premises / land available to rent or lease (read-only from SISDATA). */
    public function hartanah(Request $request): JsonResponse
    {
        $query = $request->validate([
            'kod_daerah' => ['nullable', 'regex:/^\d{4}$/'],
            'kod_mukim' => ['nullable', 'regex:/^\d{6}$/'],
            'kategori_hartanah' => ['nullable', 'string', 'max:30'],
            'jenis_aset' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        ksort($query);
        $res = Cache::remember('pws:awam:hartanah:'.md5((string) json_encode($query)), 60, function () use ($query, $request) {
            $r = $this->api->call('GET', 'pjk/awam/katalog', $query, null, [], $request->ip());

            return $r['status'] === 200 ? $r['body'] : null;
        });
        if ($res === null) {
            Cache::forget('pws:awam:hartanah:'.md5((string) json_encode($query)));
            abort(503, __('pws::pws.ralat.sisdata_tiada'));
        }

        return response()->json([
            'data' => array_map(fn ($p) => array_intersect_key((array) $p, array_flip(self::PREMIS)), (array) ($res['data'] ?? [])),
            'meta' => array_intersect_key((array) ($res['meta'] ?? []), array_flip(['total', 'current_page', 'last_page'])),
        ]);
    }

    /** SRS-PWS-17/18/19: confirmed benefit distribution, wakaf vs purchased, with disclaimer. */
    public function agihan(Request $request): JsonResponse
    {
        $query = $request->validate(['tempoh' => ['nullable', 'string', 'max:20'], 'kod_daerah' => ['nullable', 'regex:/^\d{4}$/']]);
        ksort($query);
        $res = Cache::remember('pws:awam:agihan:'.md5((string) json_encode($query)), 300, function () use ($query, $request) {
            $r = $this->api->call('GET', 'pjk/awam/agihan', $query, null, [], $request->ip());

            return $r['status'] === 200 ? ['body' => $r['body'], 'pada' => now()->toIso8601String()] : null;
        });
        if ($res === null) {
            Cache::forget('pws:awam:agihan:'.md5((string) json_encode($query)));
            abort(503, __('pws::pws.ralat.sisdata_tiada'));
        }

        return response()->json([
            'data' => array_map(function ($g) {
                $row = array_intersect_key((array) $g, array_flip(self::AGIHAN));
                $row['penerima'] = array_map(fn ($p) => ['nama' => (string) ($p['nama'] ?? ''), 'amaun' => (string) ($p['amaun'] ?? '0.00')], (array) ($g['penerima'] ?? []));

                return $row;
            }, (array) ($res['body']['data'] ?? [])),
            'meta' => ['dikemas_kini' => $res['pada'], 'sumber' => 'SISDATA (MOD-PJK, angka disahkan sahaja)', 'penafian' => __('pws::pws.agihan.penafian')],
        ]);
    }

    /** Published CMS content: news, announcements, FAQ, waqf projects (SRS-PWS-04/06). */
    public function kandungan(Request $request): JsonResponse
    {
        $q = $request->validate([
            'jenis' => ['nullable', Rule::in(array_keys(Kandungan::JENIS))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $page = Kandungan::query()->where('status_terbitan', 'diterbitkan')
            ->when($q['jenis'] ?? null, fn ($w, $j) => $w->where('jenis', $j))
            ->orderByDesc('tarikh_terbit')->paginate((int) ($q['per_page'] ?? 10));

        return response()->json([
            'data' => collect($page->items())->map(fn (Kandungan $k) => KandunganService::presentPublic($k))->all(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function kandunganSatu(string $slug): JsonResponse
    {
        $k = Kandungan::query()->where('status_terbitan', 'diterbitkan')->where('slug', $slug)->first();
        abort_if($k === null, 404, __('pws::pws.ralat.tiada'));

        return response()->json(['data' => KandunganService::presentPublic($k)]);
    }

    /** Approved aggregate statistics only. */
    public function statistik(): JsonResponse
    {
        $rows = StatistikTerbitan::query()->where('status', 'diterbitkan')->orderByDesc('tarikh_terbit')->limit(12)->get();

        return response()->json(['data' => $rows->map(fn (StatistikTerbitan $s) => [
            'tajuk' => $s->tajuk, 'tempoh' => $s->tempoh, 'data' => $s->data, 'tarikh_terbit' => $s->tarikh_terbit?->toIso8601String(),
        ])->all()]);
    }
}
