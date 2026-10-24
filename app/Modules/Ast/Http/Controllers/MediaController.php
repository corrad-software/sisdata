<?php

namespace App\Modules\Ast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ast\Models\AsetDokumen;
use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Models\AsetGambar;
use App\Modules\Ast\Models\AsetInsurans;
use App\Modules\Ast\Models\AsetJaminan;
use App\Modules\Ast\Services\PemeriksaanService;
use App\Modules\Ast\Support\Lalai;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Asset gallery (SRS-AST-12), supporting documents (SRS-AST-22), insurance
 * and warranties — files only through MOD-DMS (SRS-GEN-04).
 */
class MediaController extends Controller
{
    /** SRS-AST-22 categories → MOD-DMS document categories. */
    public const KATEGORI_DMS = [
        'pelan_bangunan' => 'pelan', 'perakuan_siap' => 'surat_rasmi', 'permit' => 'surat_rasmi', 'polisi_insurans' => 'perjanjian',
        'dokumen_pemilikan' => 'geran', 'jaminan' => 'perjanjian', 'invois' => 'resit', 'laporan_penilaian' => 'laporan', 'lain' => 'lain',
    ];

    public function __construct(private DocumentStore $documents) {}

    /**
     * Chronological gallery: the asset's own photos plus, for users who may see
     * monitoring visits, photos of MOD-PTN visits to the same lot (read-only).
     */
    public function gambar(Request $request, string $aset): JsonResponse
    {
        $a = AsetFizikal::query()->findOrFail($aset);
        $out = AsetGambar::query()->with('perakam')->where('id_aset', $a->id)->orderByDesc('tarikh_gambar')->limit(300)->get()
            ->map(fn (AsetGambar $g) => [
                'id' => $g->id, 'sumber' => 'aset', 'tarikh_gambar' => $g->tarikh_gambar->toIso8601String(), 'latitud' => $g->latitud !== null ? (float) $g->latitud : null,
                'longitud' => $g->longitud !== null ? (float) $g->longitud : null, 'perakam' => $g->perakam?->name, 'kategori_gambar' => $g->kategori_gambar,
                'kategori_nama' => Lalai::KATEGORI_GAMBAR[$g->kategori_gambar] ?? $g->kategori_gambar, 'keterangan' => $g->keterangan,
                'id_pemeriksaan' => $g->id_pemeriksaan, 'id_rekod_penyelenggaraan' => $g->id_rekod_penyelenggaraan, 'imej_kecil' => null, 'pautan' => null,
            ])->all();
        if ($request->user()->can('ptn.lawatan.lihat')) {
            $ptn = DB::table('gambar_lawatan as g')->join('rekod_lawatan as v', 'v.id', '=', 'g.id_lawatan')->where('v.lot_id', $a->lot_id)
                ->whereIn('v.status_lawatan', ['dihantar', 'disahkan'])->where(fn ($w) => $w->whereNull('g.keputusan_semakan')->orWhere('g.keputusan_semakan', '!=', 'ditolak'))
                ->orderByDesc('g.tarikh_masa')->limit(60)
                ->get(['g.id', 'g.tarikh_masa', 'g.kapsyen', 'g.imej_kecil', 'v.id as id_lawatan', 'v.no_rujukan', DB::raw('ST_Y(g.koordinat_gambar) AS lat'), DB::raw('ST_X(g.koordinat_gambar) AS lng')]);
            foreach ($ptn as $p) {
                $out[] = [
                    'id' => 'ptn-'.$p->id, 'sumber' => 'ptn', 'tarikh_gambar' => $p->tarikh_masa, 'latitud' => $p->lat !== null ? (float) $p->lat : null, 'longitud' => $p->lng !== null ? (float) $p->lng : null,
                    'perakam' => null, 'kategori_gambar' => 'lawatan_ptn', 'kategori_nama' => 'Lawatan pemantauan '.$p->no_rujukan, 'keterangan' => $p->kapsyen,
                    'id_pemeriksaan' => null, 'id_rekod_penyelenggaraan' => null, 'imej_kecil' => $p->imej_kecil, 'pautan' => '/admin/ptn/lawatan/'.$p->id_lawatan,
                ];
            }
        }
        usort($out, fn ($x, $y) => strcmp((string) $y['tarikh_gambar'], (string) $x['tarikh_gambar']));

        return response()->json(['data' => $out]);
    }

    public function simpanGambar(Request $request, string $aset, PemeriksaanService $service): JsonResponse
    {
        $a = AsetFizikal::query()->findOrFail($aset);
        $meta = $request->validate([
            'fail' => ['required', 'file', 'image', 'max:20480'], 'kategori_gambar' => ['nullable', Rule::in(['umum', 'sebelum', 'selepas'])],
            'keterangan' => ['nullable', 'string', 'max:255'], 'latitud' => ['nullable', 'numeric', 'between:-90,90'], 'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'tarikh_gambar' => ['nullable', 'date'], 'id_luar_talian' => ['nullable', 'uuid'],
        ]);
        try {
            $r = $service->tambahGambar($a, $request->file('fail'), $meta, $request->user());
        } catch (DocumentRejected $e) {
            throw ValidationException::withMessages(['fail' => $e->getMessage()]);
        }

        return response()->json(['data' => ['id' => $r['gambar']->id], 'status_segerak' => $r['status'], 'message' => __('ast::ast.gambar_disimpan')], $r['status'] === 'pendua' ? 200 : 201);
    }

    public function urlGambar(Request $request, string $aset, int $gambar): JsonResponse
    {
        $a = AsetFizikal::query()->findOrFail($aset);
        $g = AsetGambar::query()->where('id_aset', $a->id)->findOrFail($gambar);

        return response()->json(['data' => ['url' => $g->laluan_fail ? $this->documents->temporaryUrl($g->laluan_fail, $request->user()) : null]]);
    }

    public function dokumen(Request $request, string $aset): JsonResponse
    {
        $a = AsetFizikal::query()->findOrFail($aset);

        return response()->json(['data' => AsetDokumen::query()->where('id_aset', $a->id)->orderByDesc('tarikh_muat_naik')->get()->map(fn (AsetDokumen $d) => [
            'id' => $d->id, 'id_dokumen_dms' => $d->id_dokumen_dms, 'kategori_dokumen' => $d->kategori_dokumen, 'kategori_nama' => Lalai::KATEGORI_DOKUMEN[$d->kategori_dokumen] ?? $d->kategori_dokumen,
            'tajuk' => $d->tajuk, 'tarikh_luput' => $d->tarikh_luput?->toDateString(), 'luput' => $d->tarikh_luput !== null && $d->tarikh_luput->isPast(),
            'tarikh_muat_naik' => $d->tarikh_muat_naik->toIso8601String(),
        ])->values()]);
    }

    public function simpanDokumen(Request $request, string $aset): JsonResponse
    {
        $a = AsetFizikal::query()->findOrFail($aset);
        $d = $request->validate([
            'fail' => ['required', 'file', 'max:51200'], 'kategori_dokumen' => ['required', Rule::in(array_keys(Lalai::KATEGORI_DOKUMEN))],
            'tajuk' => ['nullable', 'string', 'max:200'], 'tarikh_luput' => ['nullable', 'date'],
        ]);
        $docId = $this->simpanDms($request, $a, $d['kategori_dokumen'], ['tajuk' => $d['tajuk'] ?? null, 'tarikh_luput' => $d['tarikh_luput'] ?? null]);
        $row = AsetDokumen::query()->create([
            'id_aset' => $a->id, 'id_dokumen_dms' => $docId, 'kategori_dokumen' => $d['kategori_dokumen'], 'tajuk' => $d['tajuk'] ?? $request->file('fail')->getClientOriginalName(),
            'tarikh_luput' => $d['tarikh_luput'] ?? null, 'tarikh_muat_naik' => now(), 'id_pengguna' => $request->user()->id,
            'kod_daerah' => $a->kod_daerah, 'kod_mukim' => $a->kod_mukim, 'klasifikasi' => $a->klasifikasi,
        ]);

        return response()->json(['data' => ['id' => $row->id, 'id_dokumen_dms' => $docId], 'message' => __('ast::ast.dokumen_disimpan')], 201);
    }

    public function urlDokumen(Request $request, string $aset, int $dokumen): JsonResponse
    {
        $a = AsetFizikal::query()->findOrFail($aset);
        $d = AsetDokumen::query()->where('id_aset', $a->id)->findOrFail($dokumen);

        return response()->json(['data' => ['url' => $this->documents->temporaryUrl($d->id_dokumen_dms, $request->user())]]);
    }

    public function insurans(string $aset): JsonResponse
    {
        $a = AsetFizikal::query()->findOrFail($aset);

        return response()->json(['data' => [
            'insurans' => AsetInsurans::query()->where('id_aset', $a->id)->orderByDesc('tarikh_tamat')->get()->map(fn (AsetInsurans $i) => [
                'id' => $i->id, 'no_polisi' => $i->no_polisi, 'penanggung' => $i->penanggung, 'jenis_perlindungan' => $i->jenis_perlindungan,
                'nilai_dilindungi' => $i->nilai_dilindungi, 'premium' => $i->premium, 'tarikh_mula' => $i->tarikh_mula->toDateString(), 'tarikh_tamat' => $i->tarikh_tamat->toDateString(),
                'aktif' => ! $i->tarikh_tamat->isPast(), 'id_dokumen_dms' => $i->id_dokumen_dms,
            ])->values(),
            'jaminan' => AsetJaminan::query()->where('id_aset', $a->id)->orderByDesc('tarikh_tamat')->get()->map(fn (AsetJaminan $j) => [
                'id' => $j->id, 'perkara' => $j->perkara, 'pembekal' => $j->pembekal, 'tarikh_mula' => $j->tarikh_mula->toDateString(), 'tarikh_tamat' => $j->tarikh_tamat->toDateString(),
                'aktif' => ! $j->tarikh_tamat->isPast(), 'catatan' => $j->catatan, 'id_dokumen_dms' => $j->id_dokumen_dms,
            ])->values(),
        ]]);
    }

    public function simpanInsurans(Request $request, string $aset): JsonResponse
    {
        $a = AsetFizikal::query()->findOrFail($aset);
        $d = $request->validate([
            'no_polisi' => ['required', 'string', 'max:60'], 'penanggung' => ['required', 'string', 'max:150'], 'jenis_perlindungan' => ['nullable', 'string', 'max:80'],
            'nilai_dilindungi' => ['nullable', 'numeric', 'min:0', 'max:99999999999999'], 'premium' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'tarikh_mula' => ['required', 'date'], 'tarikh_tamat' => ['required', 'date', 'after:tarikh_mula'], 'fail' => ['nullable', 'file', 'max:51200'],
        ]);
        $doc = $request->hasFile('fail') ? $this->simpanDms($request, $a, 'polisi_insurans', ['no_polisi' => $d['no_polisi']]) : null;
        $i = AsetInsurans::query()->create(collect($d)->except('fail')->all() + [
            'id_aset' => $a->id, 'id_dokumen_dms' => $doc, 'kod_daerah' => $a->kod_daerah, 'kod_mukim' => $a->kod_mukim, 'klasifikasi' => $a->klasifikasi,
        ]);

        return response()->json(['data' => ['id' => $i->id], 'message' => __('ast::ast.insurans_disimpan')], 201);
    }

    public function simpanJaminan(Request $request, string $aset): JsonResponse
    {
        $a = AsetFizikal::query()->findOrFail($aset);
        $d = $request->validate([
            'perkara' => ['required', 'string', 'max:200'], 'pembekal' => ['nullable', 'string', 'max:150'], 'tarikh_mula' => ['required', 'date'],
            'tarikh_tamat' => ['required', 'date', 'after:tarikh_mula'], 'catatan' => ['nullable', 'string', 'max:2000'], 'fail' => ['nullable', 'file', 'max:51200'],
        ]);
        $doc = $request->hasFile('fail') ? $this->simpanDms($request, $a, 'jaminan', ['perkara' => $d['perkara']]) : null;
        $j = AsetJaminan::query()->create(collect($d)->except('fail')->all() + [
            'id_aset' => $a->id, 'id_dokumen_dms' => $doc, 'kod_daerah' => $a->kod_daerah, 'kod_mukim' => $a->kod_mukim, 'klasifikasi' => $a->klasifikasi,
        ]);

        return response()->json(['data' => ['id' => $j->id], 'message' => __('ast::ast.jaminan_disimpan')], 201);
    }

    /** @param array<string, mixed> $meta */
    private function simpanDms(Request $request, AsetFizikal $a, string $kategori, array $meta): string
    {
        try {
            return $this->documents->store($request->file('fail'), 'aset_fizikal', $a->id, self::KATEGORI_DMS[$kategori] ?? 'lain', $request->user(), $a->klasifikasi, array_filter($meta + [
                'no_rujukan_aset' => $a->no_rujukan_aset, 'kategori_aset' => $kategori, 'pautan_induk' => '/admin/ast/aset/'.$a->id,
            ], fn ($v) => $v !== null));
        } catch (DocumentRejected $e) {
            throw ValidationException::withMessages(['fail' => $e->getMessage()]);
        }
    }
}
