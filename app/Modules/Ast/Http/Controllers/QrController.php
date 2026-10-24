<?php

namespace App\Modules\Ast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Models\AsetKategori;
use App\Modules\Ast\Models\AsetQr;
use App\Modules\Ast\Models\AsetQrLogImbasan;
use App\Modules\Ast\Services\AsetService;
use App\Modules\Ast\Support\KodQr;
use App\Modules\Ast\Support\Lalai;
use App\Modules\Ast\Support\Rujukan;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Rbc\Contracts\AccessControl;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * SRS-AST-06..10: QR images, weather-proof label sheets (single and batch),
 * the public scan endpoint and the scan log.
 */
class QrController extends Controller
{
    public function __construct(private KodQr $qr, private Rujukan $ref) {}

    /**
     * Public (no account) scan — SRS-AST-08/09/10 and security controls: the
     * reference is opaque; a scanner without permission (or out of scope)
     * gets minimum identification only — never value or officer; every scan
     * is logged with time, scanner identity and device location if given.
     */
    public function imbas(Request $request, string $ref, AccessControl $access): JsonResponse
    {
        $d = $request->validate(['latitud' => ['nullable', 'numeric', 'between:-90,90'], 'longitud' => ['nullable', 'numeric', 'between:-180,180'], 'ketepatan_m' => ['nullable', 'numeric', 'min:0', 'max:100000']]);
        $ref = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $ref) ?? '');
        /** @var User|null $user */
        $user = $request->user('sanctum');
        $q = $ref !== '' ? AsetQr::query()->find($ref) : null;
        $a = $q ? AsetFizikal::query()->withoutGlobalScope('access')->find($q->id_aset) : null;
        $penuh = $a !== null && $user !== null && $user->can('ast.aset.lihat') && AsetFizikal::query()->untuk($user)->whereKey($a->id)->exists();
        $jenis = match (true) {
            $a === null => 'tidak_sah',
            ! $q->status_aktif => 'tidak_aktif',
            $penuh => 'penuh',
            default => 'minimum',
        };
        AsetQrLogImbasan::query()->create([
            'qr_ref' => mb_substr($ref, 0, 24), 'id_aset' => $a?->id, 'cap_masa' => now(), 'id_pengguna' => $user?->id,
            'peranan_pengimbas' => $user ? mb_substr(implode(',', $access->roles($user)), 0, 120) : null,
            'latitud_peranti' => $d['latitud'] ?? null, 'longitud_peranti' => $d['longitud'] ?? null, 'ketepatan_m' => $d['ketepatan_m'] ?? null,
            'jenis_paparan' => $jenis, 'ejen_pengguna' => mb_substr((string) $request->userAgent(), 0, 255), 'kod_daerah' => $a?->kod_daerah,
        ]);
        if ($a === null) {
            return response()->json(['message' => __('ast::ast.qr_tidak_sah'), 'code' => 'qr_tidak_sah'], 404);
        }
        $kategori = AsetKategori::query()->whereKey($a->id_kategori)->value('nama_kategori');
        $data = [
            'paparan' => $jenis, 'no_rujukan_aset' => $a->no_rujukan_aset, 'nama_aset' => $a->nama_aset, 'kategori' => $kategori,
            'jenis' => Lalai::JENIS_ASET[$a->jenis_aset] ?? $a->jenis_aset, 'daerah' => $this->ref->namaDaerah($a->kod_daerah), 'mukim' => $this->ref->namaMukim($a->kod_mukim),
            'pemilik' => 'Majlis Agama Islam Selangor (MAIS)', 'status' => $a->status_arkib ? 'Tidak aktif / diarkibkan' : 'Aktif',
            'mesej' => $jenis === 'tidak_aktif' ? __('ast::ast.qr_tidak_aktif') : __('ast::ast.qr_hubungi'),
            'log_masuk' => $user !== null,
        ];
        if ($penuh) {
            $data += [
                'id' => $a->id, 'pautan' => '/admin/ast/aset/'.$a->id, 'status_kondisi' => $a->status_kondisi, 'kondisi_nama' => Lalai::KONDISI[$a->status_kondisi] ?? $a->status_kondisi,
                'tarikh_pemeriksaan_terakhir' => $a->tarikh_pemeriksaan_terakhir?->toDateString(),
                'tindakan' => array_values(array_filter([
                    'lihat_profil',
                    $user->can('ast.lapangan.kemas_kini') && ! $a->status_arkib ? 'periksa_lapangan' : null,
                    $user->can('ast.pemeriksaan.cipta') && ! $a->status_arkib ? 'rekod_pemeriksaan' : null,
                    $user->can('ast.penyelenggaraan.cipta') && ! $a->status_arkib ? 'lapor_kerosakan' : null,
                ])),
            ];
        }

        return response()->json(['data' => $data]);
    }

    public function svg(string $aset): Response
    {
        $a = AsetFizikal::query()->findOrFail($aset);

        return response($this->qr->svg($this->qr->pautan($a->qr_ref), 320), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, max-age=300']);
    }

    /**
     * SRS-AST-07 / acceptance #2: A4 sheet of weather-proof labels (QR, asset
     * name, reference number, MAIS identity) for one or many assets (≤ 500).
     */
    public function label(Request $request, AuditLogger $audit): Response
    {
        $d = $request->validate(['id_aset' => ['required', 'array', 'min:1', 'max:500'], 'id_aset.*' => ['uuid'], 'templat' => ['required', Rule::in(array_keys(Lalai::TEMPLAT_LABEL))]]);
        $assets = AsetFizikal::query()->whereIn('id', $d['id_aset'])->where('status_arkib', false)->orderBy('no_rujukan_aset')->get();
        if ($assets->isEmpty()) {
            abort(404);
        }
        [$nama, $w, $h, $qrMm, $cols] = Lalai::TEMPLAT_LABEL[$d['templat']];
        $cells = '';
        foreach ($assets as $i => $a) {
            $png = base64_encode($this->qr->png($this->qr->pautan($a->qr_ref), 6));
            $fs = $w <= 50 ? 6.5 : ($w <= 70 ? 8 : 10);
            $cells .= '<td style="width:'.$w.'mm;height:'.$h.'mm;border:0.3mm dashed #999;padding:2mm;vertical-align:top">'
                .'<table style="width:100%;border-collapse:collapse"><tr>'
                .'<td style="width:'.($qrMm + 1).'mm;vertical-align:top"><img src="data:image/png;base64,'.$png.'" style="width:'.$qrMm.'mm;height:'.$qrMm.'mm" /></td>'
                .'<td style="vertical-align:top;padding-left:1.5mm;font-size:'.$fs.'pt;line-height:1.2">'
                .'<div style="font-weight:bold;font-size:'.($fs + 0.5).'pt">MAJLIS AGAMA ISLAM SELANGOR</div>'
                .'<div style="margin-top:1mm">'.e(mb_strimwidth($a->nama_aset, 0, $w <= 50 ? 40 : 80, '…')).'</div>'
                .'<div style="margin-top:1mm;font-family:DejaVu Sans Mono, monospace">'.e($a->no_rujukan_aset).'</div>'
                .'<div style="margin-top:1mm;color:#555">Imbas untuk maklumat aset · '.e($a->qr_ref).'</div>'
                .'</td></tr></table></td>';
            if (($i + 1) % $cols === 0) {
                $cells .= '</tr><tr>';
            }
        }
        $html = '<html><head><meta charset="utf-8"><style>@page{margin:8mm} body{font-family:DejaVu Sans, sans-serif;margin:0} table.lembaran{border-collapse:separate;border-spacing:2mm}</style></head>'
            .'<body><table class="lembaran"><tr>'.$cells.'</tr></table></body></html>';
        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait')->output();

        foreach ($assets as $a) {
            AsetQr::query()->whereKey($a->qr_ref)->increment('bilangan_cetakan', 1, ['templat_label' => $d['templat'], 'tarikh_cetakan_terakhir' => now()]);
        }
        $audit->record('cetak', 'ast', 'aset_qr', null, meta: ['templat' => $d['templat'], 'bilangan' => $assets->count(), 'aset' => $assets->pluck('no_rujukan_aset')->take(50)->all()]);

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="label-qr-aset-'.now()->format('Ymd-His').'.pdf"']);
    }

    public function janaSemula(Request $request, string $aset, AsetService $service): JsonResponse
    {
        $a = AsetFizikal::query()->findOrFail($aset);
        $d = $request->validate(['sebab' => ['required', 'string', 'max:255']]);
        if ($a->status_arkib) {
            abort(422, __('ast::ast.aset_diarkib'));
        }
        $q = $service->janaSemulaQr($a, $request->user(), $d['sebab']);

        return response()->json(['data' => ['qr_ref' => $q->qr_ref, 'pautan_awam' => $q->pautan_awam], 'message' => __('ast::ast.qr_dijana_semula')], 201);
    }

    public function status(Request $request, string $ref, AsetService $service): JsonResponse
    {
        $q = AsetQr::query()->findOrFail(strtoupper($ref));
        $a = AsetFizikal::query()->findOrFail($q->id_aset);
        $d = $request->validate(['status_aktif' => ['required', 'boolean'], 'sebab' => ['required_if:status_aktif,false', 'nullable', 'string', 'max:255']]);
        if ($d['status_aktif'] && ($a->status_arkib || $a->qr_ref !== $q->qr_ref)) {
            abort(422, __('ast::ast.qr_tidak_boleh_aktif'));
        }
        $service->statusQr($q, (bool) $d['status_aktif'], $d['sebab'] ?? null);

        return response()->json(['message' => __('ast::ast.qr_status')]);
    }

    public function imbasan(Request $request, string $aset): JsonResponse
    {
        $a = AsetFizikal::query()->findOrFail($aset);
        $p = AsetQrLogImbasan::query()->with('pengguna')->where('id_aset', $a->id)->orderByDesc('cap_masa')->paginate(30);

        return response()->json([
            'data' => collect($p->items())->map(fn (AsetQrLogImbasan $l) => [
                'id' => $l->id, 'qr_ref' => $l->qr_ref, 'cap_masa' => $l->cap_masa->toIso8601String(), 'pengguna' => $l->pengguna?->name, 'peranan' => $l->peranan_pengimbas,
                'latitud' => $l->latitud_peranti !== null ? (float) $l->latitud_peranti : null, 'longitud' => $l->longitud_peranti !== null ? (float) $l->longitud_peranti : null,
                'jenis_paparan' => $l->jenis_paparan,
            ])->values(),
            'meta' => ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'total' => $p->total()],
        ]);
    }
}
