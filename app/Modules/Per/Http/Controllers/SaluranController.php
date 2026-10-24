<?php

namespace App\Modules\Per\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Per\Http\CasePresenter;
use App\Modules\Per\Models\PeringkatSaluran;
use App\Modules\Per\Models\Saluran;
use App\Modules\Per\Models\SemakDokumen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** /api/v1/per/saluran — the 11 channels and their configuration (SRS-PER-01/16/17/28). */
class SaluranController extends Controller
{
    public function __construct(private CasePresenter $presenter) {}

    public function index(): JsonResponse
    {
        $channels = Saluran::query()->with(['peringkat', 'senaraiSemak'])->orderBy('susunan')->get();

        return response()->json(['data' => $channels->map(fn (Saluran $s) => $this->presenter->channel($s, true))->values()]);
    }

    public function show(string $kod): JsonResponse
    {
        $channel = Saluran::query()->with(['peringkat', 'senaraiSemak'])->where('kod_saluran', $kod)->firstOrFail();

        return response()->json(['data' => $this->presenter->channel($channel, true)]);
    }

    /** PUT /saluran/{kod} — target days, stage targets/names, mandatory flags (changes apply to new cases). */
    public function update(Request $request, string $kod): JsonResponse
    {
        $channel = Saluran::query()->where('kod_saluran', $kod)->firstOrFail();
        $data = $request->validate([
            'tempoh_sasaran_hari' => ['sometimes', 'integer', 'min:1', 'max:3650'],
            'status_aktif' => ['sometimes', 'boolean'],
            'keterangan' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'peringkat' => ['sometimes', 'array'],
            'peringkat.*.id' => ['required', 'integer'],
            'peringkat.*.nama_peringkat' => ['sometimes', 'string', 'max:150'],
            'peringkat.*.tempoh_sasaran_hari' => ['sometimes', 'integer', 'min:1', 'max:3650'],
            'peringkat.*.aktif' => ['sometimes', 'boolean'],
            'senarai_semak' => ['sometimes', 'array'],
            'senarai_semak.*.id' => ['required', 'integer'],
            'senarai_semak.*.status_wajib' => ['sometimes', 'boolean'],
            'senarai_semak.*.nama_dokumen' => ['sometimes', 'string', 'max:200'],
        ]);

        DB::transaction(function () use ($channel, $data) {
            $channel->fill(array_intersect_key($data, array_flip(['tempoh_sasaran_hari', 'status_aktif', 'keterangan'])))->save();
            foreach ($data['peringkat'] ?? [] as $row) {
                $stage = PeringkatSaluran::query()->where('id_saluran', $channel->id)->findOrFail($row['id']);
                // The approval and final stages carry behaviour; they cannot be switched off.
                if (in_array($stage->jenis, ['kelulusan', 'simpanan'], true)) {
                    unset($row['aktif']);
                }
                $stage->fill(array_intersect_key($row, array_flip(['nama_peringkat', 'tempoh_sasaran_hari', 'aktif'])))->save();
            }
            foreach ($data['senarai_semak'] ?? [] as $row) {
                SemakDokumen::query()->where('id_saluran', $channel->id)->findOrFail($row['id'])
                    ->fill(array_intersect_key($row, array_flip(['status_wajib', 'nama_dokumen'])))->save();
            }
        });

        return $this->show($kod);
    }
}
