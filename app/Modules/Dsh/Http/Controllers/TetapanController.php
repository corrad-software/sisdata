<?php

namespace App\Modules\Dsh\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dsh\Models\AmbangIndikator;
use App\Modules\Dsh\Models\DefinisiWidget;
use App\Modules\Dsh\Services\Ambang;
use App\Modules\Dsh\Services\Aras;
use App\Modules\Dsh\Services\KpiService;
use App\Modules\Dsh\Services\WidgetKatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin configuration without code (SRS-DSH-10): widget definitions per
 * level (title, visual, size, order, active) and traffic-light / heatmap
 * thresholds per indicator. Changes are audited by MOD-AUD's model observer.
 */
class TetapanController extends Controller
{
    public function __construct(private KpiService $kpis, private Ambang $ambang) {}

    public function index(): JsonResponse
    {
        $kpi = [];
        foreach ($this->kpis->registered() as $key => $p) {
            $kpi[] = ['key' => $key, 'label' => $p->label(), 'domain' => $p->domain(), 'modul' => KpiService::modul($p), 'kebenaran' => $p->permission(), 'ambang' => $this->ambang->for($key)];
        }

        return response()->json(['data' => [
            'kpi' => $kpi,
            'widget' => DefinisiWidget::query()->orderBy('susunan')->get(),
            'ambang' => AmbangIndikator::query()->orderBy('kod_indikator')->get(),
            'visual' => WidgetKatalog::VISUALS,
            'aras' => Aras::ALL,
        ]]);
    }

    public function saveWidget(Request $request, string $kod): JsonResponse
    {
        abort_unless(preg_match('/^(kpi|sistem):[a-z0-9_.]+$/', $kod) === 1, 422, __('dsh::dsh.widget_tidak_sah'));
        $data = $request->validate([
            'tajuk' => ['nullable', 'string', 'max:160'],
            'jenis_visual' => ['required', Rule::in(WidgetKatalog::VISUALS)],
            'pertanyaan_sumber' => ['nullable', 'string', 'max:120'],
            'aras_dashboard' => ['required', 'array', 'min:1'],
            'aras_dashboard.*' => [Rule::in(Aras::ALL)],
            'saiz' => ['nullable', 'integer', 'min:1', 'max:4'],
            'susunan' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'aktif' => ['nullable', 'boolean'],
        ]);
        $widget = DefinisiWidget::query()->updateOrCreate(['kod_widget' => $kod], [
            'tajuk' => $data['tajuk'] ?? null,
            'jenis_visual' => $data['jenis_visual'],
            'pertanyaan_sumber' => $data['pertanyaan_sumber'] ?? $kod,
            'aras_dashboard' => array_values(array_unique($data['aras_dashboard'])),
            'saiz' => $data['saiz'] ?? 1,
            'susunan' => $data['susunan'] ?? 100,
            'aktif' => $data['aktif'] ?? true,
            'dikemas_kini_oleh' => $request->user()->id,
        ]);

        return response()->json(['message' => __('dsh::dsh.tetapan_disimpan'), 'data' => $widget]);
    }

    public function deleteWidget(string $kod): JsonResponse
    {
        DefinisiWidget::query()->where('kod_widget', $kod)->firstOrFail()->delete();

        return response()->json(['message' => __('dsh::dsh.tetapan_dipadam')]);
    }

    public function saveThreshold(Request $request, string $kod): JsonResponse
    {
        abort_unless(preg_match('/^[a-z0-9_]+\.[a-z0-9_.]+$/', $kod) === 1, 422, __('dsh::dsh.widget_tidak_sah'));
        $data = $request->validate([
            'arah' => ['required', Rule::in(['tinggi', 'rendah'])],
            'nilai_hijau' => ['required', 'numeric'],
            'nilai_kuning' => ['required', 'numeric'],
            'nilai_merah' => ['nullable', 'numeric'],
        ]);
        $ok = $data['arah'] === 'tinggi' ? $data['nilai_hijau'] >= $data['nilai_kuning'] : $data['nilai_hijau'] <= $data['nilai_kuning'];
        abort_unless($ok, 422, __('dsh::dsh.ambang_tidak_konsisten'));
        $row = AmbangIndikator::query()->updateOrCreate(['kod_indikator' => $kod], $data + ['dikemas_kini_oleh' => $request->user()->id]);
        $this->ambang->flush();

        return response()->json(['message' => __('dsh::dsh.tetapan_disimpan'), 'data' => $row]);
    }

    public function deleteThreshold(string $kod): JsonResponse
    {
        AmbangIndikator::query()->where('kod_indikator', $kod)->firstOrFail()->delete();
        $this->ambang->flush();

        return response()->json(['message' => __('dsh::dsh.tetapan_dipadam')]);
    }
}
