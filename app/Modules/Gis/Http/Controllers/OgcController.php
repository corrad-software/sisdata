<?php

namespace App\Modules\Gis\Http\Controllers;

use App\Modules\Gis\Services\OgcProxy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** OGC WMS/WFS behind authentication + scope injection (SRS-GIS-25/32). */
class OgcController extends GisController
{
    public function __construct(private OgcProxy $proxy) {}

    public function proxy(Request $request, string $service): Response
    {
        return $this->proxy->handle($this->user($request), $service, $request->query(), route('gis.ogc', ['service' => $service]));
    }

    /** Connection details for external GIS tools (QGIS: WMS/WFS URL + Bearer token header). */
    public function info(Request $request): JsonResponse
    {
        return response()->json([
            'wms' => route('gis.ogc', ['service' => 'wms']),
            'wfs' => route('gis.ogc', ['service' => 'wfs']),
            'lapisan' => $this->proxy->published($this->user($request)),
            'pengesahan' => __('gis::gis.ogc.panduan_token'),
        ]);
    }
}
