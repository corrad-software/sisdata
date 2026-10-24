<?php

namespace App\Modules\Pjk\Http\Controllers;

use App\Modules\Pjk\Services\GatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SRS-PJK-16: payment gateway callback (server-to-server, no session). The
 * signature over the raw body is verified by the adapter; events are
 * idempotent; the response never leaks internals.
 */
class GatewayWebhookController
{
    public function __invoke(Request $request, GatewayService $service): JsonResponse
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[strtolower($name)] = (string) ($values[0] ?? '');
        }
        [$status, $result] = $service->callback($request->getContent(), $headers, $request->ip());

        return response()->json(['diterima' => $status === 200, 'keputusan' => $result], $status);
    }
}
