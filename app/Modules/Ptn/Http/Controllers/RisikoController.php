<?php

namespace App\Modules\Ptn\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pro\Contracts\LotRegistry;
use App\Modules\Ptn\Http\Present;
use App\Modules\Ptn\Models\IsuPemantauan;
use App\Modules\Ptn\Models\JadualPemantauan;
use App\Modules\Ptn\Models\RekodLawatan;
use App\Modules\Ptn\Models\RiskRatingLot;
use App\Modules\Ptn\Services\Analitik;
use App\Modules\Ptn\Services\RisikoService;
use App\Modules\Ptn\Support\KodWarna;
use App\Modules\Ptn\Support\Rujukan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** SRS-PTN-11/12/14/16/24: risk dashboard, colour-coded lot list, lot monitoring card. */
class RisikoController extends Controller
{
    public function __construct(private RisikoService $risiko, private KodWarna $warna, private Rujukan $ref) {}

    public function dashboard(Request $request, Analitik $analitik): JsonResponse
    {
        $f = $request->validate(['kod_daerah' => ['nullable', 'string'], 'tahun' => ['nullable', 'integer']]);

        return response()->json(['data' => $analitik->dashboard($request->user(), $f)]);
    }

    /** Colour-coded lot list (SRS-PTN-16: same colours + text labels as map and dashboard). */
    public function lot(Request $request): JsonResponse
    {
        $f = $request->validate(['kod_daerah' => ['nullable', 'string'], 'kod_mukim' => ['nullable', 'string'], 'kod_warna' => ['nullable', 'in:hijau,kuning,merah'], 'q' => ['nullable', 'string', 'max:50'], 'per_page' => ['nullable', 'integer', 'max:100']]);
        [$case, $b] = $this->warna->sqlSkor('risk_rating_lot.skor_keseluruhan');
        $colour = "CASE WHEN risk_rating_lot.paksa_merah THEN 'merah' ELSE {$case} END";
        $q = RiskRatingLot::query()->where('risk_rating_lot.semasa', true)->join('lot_tanah as l', 'l.id', '=', 'risk_rating_lot.lot_id')->whereNull('l.deleted_at')
            ->select(['risk_rating_lot.*', 'l.no_lot', 'l.kategori_guna_tanah'])->selectRaw("{$colour} AS warna_semasa", $b)
            ->when($f['kod_daerah'] ?? null, fn ($q, $v) => $q->where('risk_rating_lot.kod_daerah', $v))
            ->when($f['kod_mukim'] ?? null, fn ($q, $v) => $q->where('risk_rating_lot.kod_mukim', $v))
            ->when($f['kod_warna'] ?? null, fn ($q, $v) => $q->whereRaw("{$colour} = ?", [...$b, $v]))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where('l.no_lot', 'ilike', '%'.$v.'%'))
            ->orderByDesc('risk_rating_lot.paksa_merah')->orderByDesc('risk_rating_lot.skor_keseluruhan');
        $page = $q->paginate((int) ($f['per_page'] ?? 25));
        $ids = $page->getCollection()->pluck('lot_id')->all();
        $last = RekodLawatan::query()->whereIn('lot_id', $ids)->whereIn('status_lawatan', ['dihantar', 'disahkan'])->groupBy('lot_id')->selectRaw('lot_id, MAX(tarikh_lawatan) AS t')->get()->pluck('t', 'lot_id');
        $open = IsuPemantauan::query()->whereIn('lot_id', $ids)->whereIn('status_isu', IsuPemantauan::TERBUKA)->groupBy('lot_id')->selectRaw('lot_id, COUNT(*) AS n')->get()->pluck('n', 'lot_id');

        return response()->json([
            'data' => $page->getCollection()->map(fn (RiskRatingLot $r) => $this->risiko->papar($r, false) + [
                'no_lot' => $r->getAttribute('no_lot'), 'kategori_guna_tanah' => $r->getAttribute('kategori_guna_tanah'),
                'daerah' => $this->ref->namaDaerah($r->kod_daerah), 'mukim' => $this->ref->namaMukim($r->kod_mukim),
                'lawatan_terakhir' => $last[$r->lot_id] ?? null, 'isu_terbuka' => (int) ($open[$r->lot_id] ?? 0),
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /** Lot monitoring card: risk (six categories), schedule, visits, issues (SRS-PTN-16 kad rekod lot). */
    public function lotButiran(Request $request, string $lot, LotRegistry $lots, Present $present): JsonResponse
    {
        $user = $request->user();
        $l = $lots->find($lot, $user);
        if ($l === null) {
            abort(404);
        }
        $r = $this->risiko->semasa($lot);
        $visits = RekodLawatan::query()->with('pegawai')->where('lot_id', $lot)->whereNotIn('status_lawatan', ['ditolak'])->orderByDesc('tarikh_lawatan')->limit(20)->get();

        return response()->json(['data' => [
            'lot' => $l + ['daerah' => $this->ref->namaDaerah($l['kod_daerah']), 'mukim' => $this->ref->namaMukim($l['kod_mukim']), 'pautan' => $lots->profileLink($lot)],
            'risiko' => $r ? $this->risiko->papar($r, $user->can('ptn.risiko.lihat')) : null,
            'jadual' => JadualPemantauan::query()->with('pegawai')->where('lot_id', $lot)->whereIn('status_pelaksanaan', JadualPemantauan::TERBUKA)->orderBy('tarikh_sasaran')->limit(12)->get()->map(fn ($j) => $present->jadual($j, [$lot => $l]))->values(),
            'lawatan' => $visits->map(fn ($v) => $present->lawatan($v, $l['no_lot']))->values(),
            'isu' => IsuPemantauan::query()->where('lot_id', $lot)->orderByDesc('tarikh_dibuka')->limit(50)->get()->map(fn ($i) => $present->isu($i, $l['no_lot']))->values(),
        ]]);
    }

    public function kira(Request $request): JsonResponse
    {
        $d = $request->validate(['lot_id' => ['nullable', 'uuid']]);
        $r = $this->risiko->kira(isset($d['lot_id']) ? [$d['lot_id']] : null);

        return response()->json(['data' => $r, 'message' => __('ptn::ptn.risiko_dikira', ['n' => $r['lot']])]);
    }
}
