<?php

namespace App\Modules\Adm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Adm\Models\KonfigKelulusan;
use App\Modules\Adm\Models\Parameter;
use App\Modules\Adm\Services\KelulusanKonfigurasi;
use App\Modules\Adm\Services\ParameterService;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** SRS-ADM-01 parameters UI API, with SRS-ADM-18 routing of critical changes. */
class ParameterController extends Controller
{
    public function __construct(private ParameterService $parameters, private KelulusanKonfigurasi $approvals, private AccessControl $access) {}

    public function index(PermissionRegistry $registry): JsonResponse
    {
        $rows = Parameter::query()->get()->keyBy('kunci');
        $pending = KonfigKelulusan::query()->whereIn('jenis_konfig', ['parameter', 'parameter_reset'])
            ->where('status', KonfigKelulusan::MENUNGGU)->pluck('id', 'kunci');
        $modules = $registry->all();

        $groups = [];
        foreach ($this->parameters->declarations() as $key => $d) {
            $row = $rows->get($key);
            $groups[$d['modul']]['modul'] = $d['modul'];
            $groups[$d['modul']]['label'] = $modules[$d['modul']]['label'] ?? strtoupper($d['modul']);
            $groups[$d['modul']]['parameter'][] = [
                'kunci' => $key,
                'label' => $d['label'],
                'jenis' => $d['jenis'],
                'kritikal' => $d['kritikal'],
                'lalai' => $d['lalai'],
                'nilai' => $this->parameters->get($key),
                'ditetapkan' => $row !== null,
                'dikemas_kini_pada' => $row?->updated_at?->toIso8601String(),
                'id_kelulusan_menunggu' => $pending[$key] ?? null,
            ];
        }
        ksort($groups);

        return response()->json(['data' => array_values($groups)]);
    }

    public function update(Request $request, string $kunci): JsonResponse
    {
        $definition = $this->parameters->definition($kunci) ?? abort(404, __('adm::adm.parameter.unknown'));
        $this->guardCritical($request, $definition['kritikal']);

        $request->validate([
            'nilai' => ['present'],
            'justifikasi' => [$definition['kritikal'] ? 'required' : 'nullable', 'string', 'max:1000'],
        ], [], (array) __('adm::adm.attributes'));
        $value = $this->parameters->normalise($kunci, $request->input('nilai'));
        $justification = $request->input('justifikasi');

        if ($definition['kritikal']) {
            $approval = $this->approvals->request('parameter', $kunci, $this->parameters->get($kunci), $value, (string) $justification, $request->user());

            return response()->json(['message' => __('adm::adm.kelulusan.submitted'), 'data' => KelulusanController::present($approval)], 202);
        }

        $this->parameters->store($kunci, $value, $justification, $request->user());

        return response()->json(['message' => __('adm::adm.saved'), 'data' => ['kunci' => $kunci, 'nilai' => $this->parameters->get($kunci)]]);
    }

    /** Back to the declared default. */
    public function destroy(Request $request, string $kunci): JsonResponse
    {
        $definition = $this->parameters->definition($kunci) ?? abort(404, __('adm::adm.parameter.unknown'));
        $this->guardCritical($request, $definition['kritikal']);
        $request->validate(['justifikasi' => [$definition['kritikal'] ? 'required' : 'nullable', 'string', 'max:1000']], [], (array) __('adm::adm.attributes'));

        if ($definition['kritikal']) {
            $approval = $this->approvals->request('parameter_reset', $kunci, $this->parameters->get($kunci), $definition['lalai'], (string) $request->input('justifikasi'), $request->user());

            return response()->json(['message' => __('adm::adm.kelulusan.submitted'), 'data' => KelulusanController::present($approval)], 202);
        }

        $this->parameters->reset($kunci, $request->input('justifikasi'), $request->user());

        return response()->json(['message' => __('adm::adm.saved'), 'data' => ['kunci' => $kunci, 'nilai' => $this->parameters->get($kunci)]]);
    }

    /**
     * Separation of scope: Pentadbir Aplikasi can't touch security / session /
     * retention settings — those are the critical ones and need
     * adm.keselamatan.kemas_kini as well.
     */
    private function guardCritical(Request $request, bool $critical): void
    {
        if ($critical && ! $this->access->allows($request->user(), 'adm.keselamatan.kemas_kini')) {
            throw new AuthorizationException(__('adm::adm.parameter.security_scope'));
        }
    }
}
