<?php

namespace App\Modules\Wfe\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Wfe\Models\CutiUmum;
use App\Modules\Wfe\Services\WorkingCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** SRS-WFE-06: the public-holiday calendar admins keep up to date. */
class KalendarController extends Controller
{
    public function __construct(private WorkingCalendar $calendar, private Parameters $parameters) {}

    public function index(Request $request): JsonResponse
    {
        $year = (int) $request->query('tahun', now(WorkingCalendar::TZ)->year);
        $rows = CutiUmum::query()->where('negeri', $this->state())->whereYear('tarikh', $year)->orderBy('tarikh')->get()
            ->map(fn (CutiUmum $c) => $this->row($c));

        return response()->json([
            'data' => $rows,
            'meta' => [
                'tahun' => $year,
                'negeri' => $this->state(),
                'hujung_minggu' => $this->calendar->weekend(),
                'waktu_kerja' => [$this->parameters->get('wfe.waktu_kerja_mula', '08:00'), $this->parameters->get('wfe.waktu_kerja_tamat', '17:00')],
                'tahun_ada' => CutiUmum::query()->where('negeri', $this->state())->selectRaw('distinct extract(year from tarikh)::int as y')->orderBy('y')->pluck('y'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $holiday = CutiUmum::create($this->validated($request) + ['negeri' => $this->state()]);
        $this->calendar->flush();

        return response()->json(['message' => __('wfe::wfe.message.cuti_disimpan'), 'data' => $this->row($holiday)], 201);
    }

    public function update(Request $request, CutiUmum $cuti): JsonResponse
    {
        $cuti->fill($this->validated($request, $cuti))->save();
        $this->calendar->flush();

        return response()->json(['message' => __('wfe::wfe.message.cuti_disimpan'), 'data' => $this->row($cuti)]);
    }

    public function destroy(CutiUmum $cuti): JsonResponse
    {
        $cuti->delete();
        $this->calendar->flush();

        return response()->json(['message' => __('wfe::wfe.message.cuti_dipadam')]);
    }

    /** Preview an SLA due date: mula + nilai (unit). */
    public function compute(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mula' => ['required', 'date'],
            'nilai' => ['required', 'integer', 'min:0', 'max:1000'],
            'unit' => ['required', Rule::in(['hari_bekerja', 'jam'])],
        ]);
        $due = $this->calendar->due(Carbon::parse($data['mula'], WorkingCalendar::TZ), (int) $data['nilai'], $data['unit']);

        return response()->json(['data' => ['tarikh_akhir' => $due->toIso8601String(), 'tempatan' => $due->setTimezone(WorkingCalendar::TZ)->format('d/m/Y H:i')]]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?CutiUmum $current = null): array
    {
        return $request->validate([
            'tarikh' => ['required', 'date_format:Y-m-d', Rule::unique('wf_cuti_umum')->where(fn ($q) => $q->where('negeri', $this->state())->where('nama', $request->input('nama')))->ignore($current?->id)],
            'nama' => ['required', 'string', 'max:255'],
            'jenis' => ['required', Rule::in(['persekutuan', 'negeri', 'ganti', 'khas'])],
            'sumber' => ['nullable', 'string', 'max:255'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /** @return array<string, mixed> */
    private function row(CutiUmum $c): array
    {
        return [
            'id' => $c->id,
            'tarikh' => $c->tarikh->toDateString(),
            'nama' => $c->nama,
            'jenis' => $c->jenis,
            'sumber' => $c->sumber,
            'catatan' => $c->catatan,
        ];
    }

    private function state(): string
    {
        return (string) $this->parameters->get('wfe.negeri_kalendar', 'selangor');
    }
}
