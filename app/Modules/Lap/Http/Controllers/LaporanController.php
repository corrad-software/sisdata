<?php

namespace App\Modules\Lap\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Lap\Models\LogPenjanaan;
use App\Modules\Lap\Services\Eksport;
use App\Modules\Lap\Services\Penjana;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Run, drill down and export reports (SRS-LAP-02/03/04/05/07/10/11/12).
 */
class LaporanController extends Controller
{
    public function __construct(private Penjana $penjana) {}

    /** @return array<string, mixed> */
    private function source(Request $request): array
    {
        $data = $request->validate([
            'sumber' => ['required', 'array'],
            'sumber.katalog' => ['nullable', 'string', 'max:80'],
            'sumber.definisi' => ['nullable', 'uuid'],
            'sumber.adhoc' => ['nullable', 'array'],
            'sumber.tajuk' => ['nullable', 'string', 'max:200'],
        ]);
        $s = array_filter($data['sumber'], fn ($v) => $v !== null && $v !== '');
        if (count(array_intersect_key($s, array_flip(['katalog', 'definisi', 'adhoc']))) !== 1) {
            throw ValidationException::withMessages(['sumber' => __('lap::lap.ralat.sumber')]);
        }

        return $s;
    }

    /** @return array<string, mixed> */
    private function params(Request $request): array
    {
        $request->validate([
            'parameter' => ['nullable', 'array'],
            'parameter.tempoh' => ['nullable', 'array'],
            'parameter.tempoh.dari' => ['nullable', 'date_format:Y-m-d'],
            'parameter.tempoh.hingga' => ['nullable', 'date_format:Y-m-d'],
            'parameter.tempoh.pratetap' => ['nullable', 'string', 'max:20'],
            'parameter.penapis' => ['nullable', 'array', 'max:20'],
            'parameter.kod_daerah' => ['nullable', 'string', 'max:10'],
            'parameter.kod_mukim' => ['nullable', 'string', 'max:10'],
        ]);

        return (array) $request->input('parameter', []);
    }

    public function run(Request $request): JsonResponse
    {
        $source = $this->source($request);
        $params = $this->params($request);
        $opts = $request->validate([
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:1', 'max:500'],
            'drill' => ['nullable', 'array', 'max:3'],
        ]);
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $this->penjana->papar($user, $source, $params, array_filter($opts, fn ($v) => $v !== null))]);
    }

    public function export(Request $request): Response
    {
        $source = $this->source($request);
        $params = $this->params($request);
        $data = $request->validate([
            'format' => ['required', Rule::in(Eksport::FORMAT)],
            'latar' => ['nullable', 'boolean'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $out = $this->penjana->eksport($user, $source, $params, $data['format'], (bool) ($data['latar'] ?? false));

        if ($out[0] === 'latar') {
            /** @var LogPenjanaan $log */
            $log = $out[1];

            return response()->json(['message' => __('lap::lap.latar_beratur'), 'data' => ['id_log' => $log->id, 'status' => $log->status]], 202);
        }

        return new Response($out[2], 200, [
            'Content-Type' => $out[3],
            'Content-Disposition' => 'attachment; filename="'.$out[4].'"',
            'Cache-Control' => 'no-store',
            'X-Lap-Log' => $out[1]->id,
        ]);
    }

    /** SRS-LAP-05: a chart was exported as an image in the browser — record it (SRS-LAP-10). */
    public function image(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'tajuk' => ['required', 'string', 'max:250'],
            'kod_laporan' => ['nullable', 'string', 'max:80'],
            'id_definisi' => ['nullable', 'uuid'],
            'bilangan_rekod' => ['nullable', 'integer', 'min:0'],
            'parameter' => ['nullable', 'array'],
        ]);
        $log = LogPenjanaan::query()->create([
            'kod_laporan' => $data['kod_laporan'] ?? null, 'id_definisi' => $data['id_definisi'] ?? null, 'tajuk' => $data['tajuk'],
            'parameter_digunakan' => ['parameter' => $data['parameter'] ?? []], 'format_eksport' => 'png', 'mod' => 'segerak',
            'bilangan_rekod' => (int) ($data['bilangan_rekod'] ?? 0), 'id_pengguna' => $request->user()?->id, 'cap_masa' => now(), 'status' => 'berjaya',
        ]);
        $audit->record('eksport', 'lap', 'lap_log_penjanaan', $log->id, meta: ['format' => 'png', 'laporan' => $data['tajuk'], 'kod_laporan' => $data['kod_laporan'] ?? null, 'bilangan_rekod' => $log->bilangan_rekod]);

        return response()->json(['data' => ['id_log' => $log->id]], 201);
    }
}
