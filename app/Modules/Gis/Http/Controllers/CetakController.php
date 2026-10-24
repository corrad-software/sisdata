<?php

namespace App\Modules\Gis\Http\Controllers;

use App\Modules\Aud\Contracts\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * SRS-GIS-33 map print / export. The browser renders the map canvas (the
 * user's own authorised view); the server composes the PDF with title,
 * scale, legend, north arrow, projection, generation timestamp, the
 * generating user and the attribute table — and audits every print/export.
 */
class CetakController extends GisController
{
    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'tajuk' => ['required', 'string', 'max:150'],
            'imej' => ['required', 'string', 'max:15000000', 'starts_with:data:image/png;base64,,data:image/jpeg;base64,'],
            'skala' => ['nullable', 'string', 'max:60'],
            'unjuran' => ['nullable', 'string', 'max:120'],
            'pusat' => ['nullable', 'array'],
            'zum' => ['nullable', 'numeric'],
            'lapisan' => ['nullable', 'array', 'max:50'],
            'lapisan.*' => ['string', 'max:60'],
            'legenda' => ['nullable', 'array', 'max:30'],
            'legenda.*.nama' => ['required', 'string', 'max:150'],
            'legenda.*.entri' => ['nullable', 'array', 'max:40'],
            'legenda.*.entri.*.label' => ['required', 'string', 'max:150'],
            'legenda.*.entri.*.warna' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'atribut' => ['nullable', 'array', 'max:500'],
            'atribut.*' => ['array'],
            'orientasi' => ['nullable', Rule::in(['landscape', 'portrait'])],
        ];
    }

    public function pdf(Request $request, AuditLogger $audit): Response
    {
        $data = $request->validate($this->rules());
        $user = $this->user($request);
        $image = $data['imej'];
        $binary = base64_decode((string) substr($image, (int) strpos($image, ',') + 1), true);
        if ($binary === false || @getimagesizefromstring($binary) === false) {
            throw ValidationException::withMessages(['imej' => [__('gis::gis.cetak.imej_tidak_sah')]]);
        }

        $now = now();
        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $legend = '';
        foreach ($data['legenda'] ?? [] as $group) {
            $legend .= '<div class="lg"><b>'.$esc($group['nama']).'</b>';
            foreach ($group['entri'] ?? [] as $e) {
                $legend .= '<div><span class="sw" style="background:'.$esc($e['warna']).'"></span>'.$esc($e['label']).'</div>';
            }
            $legend .= '</div>';
        }
        $table = '';
        $rows = array_slice($data['atribut'] ?? [], 0, 500);
        if ($rows !== []) {
            $cols = array_slice(array_keys((array) $rows[0]), 0, 8);
            $table = '<h3>'.$esc(__('gis::gis.cetak.jadual_atribut')).'</h3><table class="t"><tr>'.implode('', array_map(fn ($c) => '<th>'.$esc($c).'</th>', $cols)).'</tr>';
            foreach ($rows as $row) {
                $table .= '<tr>'.implode('', array_map(fn ($c) => '<td>'.$esc(is_scalar($row[$c] ?? null) ? $row[$c] : json_encode($row[$c] ?? null)).'</td>', $cols)).'</tr>';
            }
            $table .= '</table>';
        }
        $meta = [
            __('gis::gis.cetak.skala') => $data['skala'] ?? '—',
            __('gis::gis.cetak.unjuran') => $data['unjuran'] ?? 'EPSG:3857 (Web Mercator) · koordinat EPSG:4326 (WGS 84)',
            __('gis::gis.cetak.dijana_pada') => $now->copy()->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i:s').' (MYT)',
            __('gis::gis.cetak.dijana_oleh') => $user->name.' ('.$user->email.')',
        ];
        $metaHtml = implode('', array_map(fn ($k, $v) => '<div><b>'.$esc($k).':</b> '.$esc($v).'</div>', array_keys($meta), $meta));
        $north = '<div class="north"><div class="arrow">▲</div>U</div>';

        $html = '<html><head><meta charset="utf-8"><style>
            body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#18181b}
            h1{font-size:16px;margin:0 0 6px} h3{font-size:12px;margin:12px 0 4px}
            .map{position:relative;border:1px solid #a1a1aa;text-align:center} .map img{height:118mm}
            .north{position:absolute;top:8px;right:10px;background:#fff;border:1px solid #71717a;padding:2px 6px;text-align:center;font-weight:bold}
            .arrow{font-size:14px;line-height:14px} .side{margin-top:6px} .lg{display:inline-block;vertical-align:top;margin:0 16px 6px 0}
            .sw{display:inline-block;width:10px;height:10px;margin-right:4px;border:1px solid #52525b}
            .t{border-collapse:collapse;width:100%} .t th,.t td{border:1px solid #d4d4d8;padding:2px 4px;text-align:left}
            .foot{margin-top:6px;color:#52525b}
        </style></head><body>
            <h1>'.$esc($data['tajuk']).'</h1>
            <div class="map"><img src="'.$esc($image).'">'.$north.'</div>
            <div class="side">'.$legend.'</div>
            <div class="foot">'.$metaHtml.'<div>'.$esc(__('gis::gis.cetak.penafian')).'</div></div>
            '.$table.'
        </body></html>';

        $audit->record('cetak', 'gis', 'peta', null, meta: ['tajuk' => $data['tajuk'], 'format' => 'pdf', 'lapisan' => $data['lapisan'] ?? [], 'pusat' => $data['pusat'] ?? null, 'zum' => $data['zum'] ?? null, 'bilangan_atribut' => count($rows)]);

        return Pdf::loadHTML($html)->setPaper('a4', $data['orientasi'] ?? 'landscape')->download('peta-'.$now->format('Ymd-His').'.pdf');
    }

    /** The PNG export is composed in the browser; this records it in the audit trail. */
    public function log(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'tajuk' => ['required', 'string', 'max:150'],
            'format' => ['required', Rule::in(['png', 'jpeg'])],
            'lapisan' => ['nullable', 'array', 'max:50'],
            'pusat' => ['nullable', 'array'],
            'zum' => ['nullable', 'numeric'],
        ]);
        $audit->record('eksport', 'gis', 'peta', null, meta: $data);

        return response()->json(['message' => __('gis::gis.cetak.direkod'), 'dijana_pada' => now()->toIso8601String(), 'dijana_oleh' => $this->user($request)->name]);
    }
}
