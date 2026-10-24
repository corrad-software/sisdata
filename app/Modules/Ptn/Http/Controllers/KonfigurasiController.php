<?php

namespace App\Modules\Ptn\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ptn\Models\KekerapanPemantauan;
use App\Modules\Ptn\Models\KonfigurasiAmbangWarna;
use App\Modules\Ptn\Models\TemplatSenaraiSemak;
use App\Modules\Ptn\Services\DaftarIsyaratRisiko;
use App\Modules\Ptn\Support\KodWarna;
use App\Modules\Ptn\Support\Lalai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Pentadbir Sistem configuration without code (SRS-PTN-02/04/11/15):
 * checklist templates (versioned), visit frequency per lot category × risk
 * level, colour thresholds and risk weights (effective-dated history).
 */
class KonfigurasiController extends Controller
{
    public function index(KodWarna $warna, DaftarIsyaratRisiko $signals): JsonResponse
    {
        $current = $warna->semasa();
        $kategori = [...array_map(fn ($v) => $v[0], Lalai::KATEGORI_RISIKO), ...Lalai::KATEGORI_AMBANG_LAIN];

        return response()->json(['data' => [
            'templat' => TemplatSenaraiSemak::query()->orderBy('kod')->orderByDesc('versi')->get()->map(fn ($t) => [
                'id' => $t->id, 'kod' => $t->kod, 'nama' => $t->nama, 'kategori_lot' => $t->kategori_lot, 'jenis_pemeriksaan' => $t->jenis_pemeriksaan,
                'versi' => $t->versi, 'status_aktif' => $t->status_aktif, 'senarai_item' => $t->senarai_item, 'bil_item' => count($t->senarai_item),
            ])->values(),
            'kekerapan' => KekerapanPemantauan::query()->orderBy('kategori_lot')->orderByRaw("CASE tahap_risiko WHEN 'rendah' THEN 0 WHEN 'sederhana' THEN 1 WHEN 'tinggi' THEN 2 ELSE 3 END")->get(['id', 'kategori_lot', 'tahap_risiko', 'kekerapan_setahun', 'aktif']),
            'ambang' => array_map(fn ($kod, $label) => ['kategori_risiko' => $kod, 'label' => $label] + ($current[$kod] ?? null ? [
                'ambang_kuning' => $current[$kod]->ambang_kuning !== null ? (float) $current[$kod]->ambang_kuning : null,
                'ambang_merah' => $current[$kod]->ambang_merah !== null ? (float) $current[$kod]->ambang_merah : null,
                'tempoh_kelewatan_kuning' => $current[$kod]->tempoh_kelewatan_kuning, 'tempoh_kelewatan_merah' => $current[$kod]->tempoh_kelewatan_merah,
                'pemberat' => $current[$kod]->pemberat !== null ? (float) $current[$kod]->pemberat : null,
                'tarikh_berkuat_kuasa' => $current[$kod]->tarikh_berkuat_kuasa->toIso8601String(),
            ] : []), array_keys($kategori), $kategori),
            'sejarah_ambang' => KonfigurasiAmbangWarna::query()->orderByDesc('tarikh_berkuat_kuasa')->orderByDesc('id')->limit(50)->get()->map(fn ($a) => [
                'id' => $a->id, 'kategori_risiko' => $a->kategori_risiko, 'ambang_kuning' => $a->ambang_kuning, 'ambang_merah' => $a->ambang_merah,
                'tempoh_kelewatan_kuning' => $a->tempoh_kelewatan_kuning, 'tempoh_kelewatan_merah' => $a->tempoh_kelewatan_merah, 'pemberat' => $a->pemberat,
                'tarikh_berkuat_kuasa' => $a->tarikh_berkuat_kuasa->toIso8601String(),
            ]),
            'sumber_isyarat' => $signals->sumber(),
        ]]);
    }

    /** @return array<string, mixed> */
    private function templatRules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:200'], 'kategori_lot' => ['nullable', 'string', 'max:30'],
            'jenis_pemeriksaan' => ['required', Rule::in(array_keys(Lalai::JENIS_PEMERIKSAAN))],
            'senarai_item' => ['required', 'array', 'min:1', 'max:100'], 'senarai_item.*.kod' => ['required', 'string', 'max:50', 'distinct'],
            'senarai_item.*.kategori' => ['required', Rule::in(array_keys(Lalai::KATEGORI_ITEM))], 'senarai_item.*.label' => ['required', 'string', 'max:255'],
            'senarai_item.*.kategori_isu' => ['nullable', Rule::in(array_keys(Lalai::KATEGORI_ISU))],
        ];
    }

    /** @param list<array<string, mixed>> $items */
    private function semakKategori(array $items): void
    {
        $have = array_unique(array_column($items, 'kategori'));
        $missing = array_diff(array_keys(Lalai::KATEGORI_ITEM), $have);
        if ($missing !== []) {
            // SRS-PTN-04: "sekurang-kurangnya" the five categories.
            throw ValidationException::withMessages(['senarai_item' => __('ptn::ptn.templat_kategori_wajib', ['senarai' => implode(', ', array_map(fn ($k) => Lalai::KATEGORI_ITEM[$k], $missing))])]);
        }
    }

    public function simpanTemplat(Request $request): JsonResponse
    {
        $d = $request->validate(['kod' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9\-]+$/', 'unique:templat_senarai_semak,kod'], ...$this->templatRules()]);
        $this->semakKategori($d['senarai_item']);
        $t = TemplatSenaraiSemak::query()->create($d + ['versi' => 1, 'status_aktif' => true, 'id_pencipta' => $request->user()->id]);

        return response()->json(['data' => $t], 201);
    }

    /** A change creates a new version; earlier visits keep theirs. */
    public function versiTemplat(Request $request, int $templat): JsonResponse
    {
        $old = TemplatSenaraiSemak::query()->findOrFail($templat);
        $d = $request->validate($this->templatRules());
        $this->semakKategori($d['senarai_item']);
        $new = DB::transaction(function () use ($old, $d, $request) {
            $versi = (int) TemplatSenaraiSemak::query()->where('kod', $old->kod)->max('versi') + 1;
            TemplatSenaraiSemak::query()->where('kod', $old->kod)->where('status_aktif', true)->get()->each(fn ($t) => $t->forceFill(['status_aktif' => false])->save());

            return TemplatSenaraiSemak::query()->create($d + ['kod' => $old->kod, 'versi' => $versi, 'status_aktif' => true, 'id_pencipta' => $request->user()->id]);
        });

        return response()->json(['data' => $new], 201);
    }

    public function statusTemplat(Request $request, int $templat): JsonResponse
    {
        $t = TemplatSenaraiSemak::query()->findOrFail($templat);
        $d = $request->validate(['status_aktif' => ['required', 'boolean']]);
        $t->forceFill(['status_aktif' => $d['status_aktif']])->save();

        return response()->json(['data' => $t]);
    }

    public function simpanKekerapan(Request $request): JsonResponse
    {
        $d = $request->validate(['baris' => ['required', 'array', 'max:200'], 'baris.*.kategori_lot' => ['required', 'string', 'max:30'],
            'baris.*.tahap_risiko' => ['required', Rule::in(array_keys(Lalai::TAHAP_RISIKO))], 'baris.*.kekerapan_setahun' => ['required', 'integer', 'between:1,52'], 'baris.*.aktif' => ['nullable', 'boolean']]);
        foreach ($d['baris'] as $r) {
            KekerapanPemantauan::query()->updateOrCreate(['kategori_lot' => $r['kategori_lot'], 'tahap_risiko' => $r['tahap_risiko']], ['kekerapan_setahun' => $r['kekerapan_setahun'], 'aktif' => $r['aktif'] ?? true]);
        }

        return response()->json(['data' => KekerapanPemantauan::query()->orderBy('kategori_lot')->get()]);
    }

    public function simpanAmbang(Request $request, KodWarna $warna): JsonResponse
    {
        $kategori = [...array_keys(Lalai::KATEGORI_RISIKO), ...array_keys(Lalai::KATEGORI_AMBANG_LAIN)];
        $d = $request->validate([
            'kategori_risiko' => ['required', Rule::in($kategori)],
            'ambang_kuning' => ['nullable', 'numeric', 'between:0,100'], 'ambang_merah' => ['nullable', 'numeric', 'between:0,100', 'gte:ambang_kuning'],
            'tempoh_kelewatan_kuning' => ['nullable', 'integer', 'between:0,365'], 'tempoh_kelewatan_merah' => ['nullable', 'integer', 'between:0,365'],
            'pemberat' => ['nullable', 'numeric', 'between:0,100'], 'tarikh_berkuat_kuasa' => ['nullable', 'date'],
        ]);
        $row = $warna->tetapkan($d['kategori_risiko'], array_diff_key($d, ['kategori_risiko' => 1]), $request->user());

        return response()->json(['data' => $row, 'message' => __('ptn::ptn.ambang_disimpan')], 201);
    }
}
