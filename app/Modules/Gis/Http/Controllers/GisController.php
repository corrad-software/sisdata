<?php

namespace App\Modules\Gis\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

abstract class GisController extends Controller
{
    protected function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /** @return array{0: float, 1: float, 2: float, 3: float}|null */
    protected function bbox(Request $request): ?array
    {
        $raw = $request->query('bbox');
        if ($raw === null || $raw === '') {
            return null;
        }
        $parts = array_map('trim', explode(',', (string) $raw));
        if (count($parts) !== 4 || array_filter($parts, fn ($p) => ! is_numeric($p)) !== []) {
            throw ValidationException::withMessages(['bbox' => [__('gis::gis.bbox_tidak_sah')]]);
        }
        [$x0, $y0, $x1, $y1] = array_map('floatval', $parts);
        if ($x0 >= $x1 || $y0 >= $y1 || $x0 < -180 || $x1 > 180 || $y0 < -90 || $y1 > 90) {
            throw ValidationException::withMessages(['bbox' => [__('gis::gis.bbox_tidak_sah')]]);
        }

        return [$x0, $y0, $x1, $y1];
    }

    /** @return array<string, string> scalar equality filters from ?penapis[col]=val */
    protected function filters(Request $request): array
    {
        $out = [];
        foreach ((array) $request->input('penapis', []) as $k => $v) {
            if (is_string($k) && preg_match('/^[a-z_]{1,40}$/', $k) && is_scalar($v) && $v !== '') {
                $out[$k] = mb_substr((string) $v, 0, 100);
            }
        }

        return $out;
    }
}
