<?php

namespace App\Modules\Kes\Http\Controllers;

use App\Models\User;
use App\Modules\Kes\Models\JenisKes;
use App\Modules\Kes\Models\PanelPeguam;
use App\Modules\Kes\Models\StatusKes;
use App\Modules\Kes\Services\Rujukan;
use App\Modules\Kes\Support\Kod;
use App\Modules\Ntf\Contracts\RecipientDirectory;
use App\Modules\Pro\Contracts\LotRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * SRS-KES-01/07 configuration without code changes (case types, status
 * stages), the panel-firm registry, and the code lists the forms need.
 */
class KonfigurasiController extends KesBaseController
{
    public function rujukan(Rujukan $ref, RecipientDirectory $directory): JsonResponse
    {
        $ref->lupa();

        return response()->json(['data' => [
            'jenis' => array_map(fn ($k, $v) => ['kod' => $k, 'nama' => $v['nama'], 'aktif' => $v['aktif']], array_keys($ref->jenis()), $ref->jenis()),
            'status' => array_map(fn ($k, $v) => ['kod' => $k, 'nama' => $v['nama'], 'terminal' => $v['terminal'], 'aktif' => $v['aktif']], array_keys($ref->status()), $ref->status()),
            'peringkat_mahkamah' => Kod::senarai(Kod::PERINGKAT_MAHKAMAH),
            'peranan' => Kod::senarai(Kod::PERANAN),
            'jenis_pihak' => Kod::senarai(Kod::JENIS_PIHAK),
            'status_wakilan' => Kod::senarai(Kod::STATUS_WAKILAN),
            'jenis_prosiding' => Kod::senarai(Kod::JENIS_PROSIDING),
            'status_prosiding' => Kod::senarai(Kod::STATUS_PROSIDING),
            'jenis_tarikh' => Kod::senarai(Kod::JENIS_TARIKH),
            'kategori_kos' => Kod::senarai(Kod::KATEGORI_KOS),
            'status_bayaran' => Kod::senarai(Kod::STATUS_BAYARAN),
            'jenis_keputusan' => Kod::senarai(Kod::JENIS_KEPUTUSAN),
            'jenis_tindakan' => Kod::senarai(Kod::JENIS_TINDAKAN),
            'kategori_dokumen' => Kod::senarai(Kod::KATEGORI_DOKUMEN),
            'peranan_lot' => Kod::senarai(Kod::PERANAN_LOT),
            'jenis_log' => Kod::senarai(Kod::JENIS_LOG),
            'panel' => PanelPeguam::query()->where('status', 'aktif')->orderBy('nama_firma')->get(['id', 'nama_firma'])->map(fn ($p) => ['id' => $p->id, 'nama_firma' => $p->nama_firma])->values(),
            // Officers who can hold a case (SRS-KES-20 dropdown).
            'pegawai' => collect($directory->resolve(RecipientDirectory::KEBENARAN, 'kes.kes.kemas_kini'))
                ->filter(fn (User $u) => $u->can('kes.rahsia.lihat'))->map(fn (User $u) => ['id' => $u->id, 'nama' => $u->name])->sortBy('nama')->values(),
            'daerah' => DB::table('ref_daerah')->where('aktif', true)->orderBy('nama')->get(['kod', 'nama']),
        ]]);
    }

    /** Lot picker (scoped by the user's access through LotRegistry). */
    public function cariLot(Request $request, LotRegistry $lots, Rujukan $ref): JsonResponse
    {
        $q = (string) $request->validate(['q' => ['required', 'string', 'min:1', 'max:60']])['q'];

        return response()->json(['data' => array_map(fn (array $l) => [...$l, 'mukim' => $ref->namaMukim($l['kod_mukim']), 'daerah' => $ref->namaDaerah($l['kod_daerah'])], $lots->search($q, $this->user($request), 15))]);
    }

    public function simpanJenis(Request $request, ?int $id = null): JsonResponse
    {
        $d = $request->validate([
            'kod' => [$id ? 'sometimes' : 'required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', Rule::unique('kes_jenis', 'kod')->ignore($id)],
            'nama' => ['required', 'string', 'max:150'], 'keterangan' => ['nullable', 'string', 'max:1000'],
            'susunan' => ['nullable', 'integer', 'min:0'], 'aktif' => ['nullable', 'boolean'],
        ]);
        $j = $id ? JenisKes::query()->findOrFail($id) : new JenisKes;
        if ($id) {
            unset($d['kod']); // the code is referenced by cases; only the label and state change
        }
        $j->fill($d)->save();

        return response()->json(['data' => $j, 'message' => __('kes::kes.mesej.disimpan')], $id ? 200 : 201);
    }

    public function simpanStatus(Request $request, ?int $id = null): JsonResponse
    {
        $d = $request->validate([
            'kod' => [$id ? 'sometimes' : 'required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', Rule::unique('kes_status', 'kod')->ignore($id)],
            'nama' => ['required', 'string', 'max:150'], 'susunan' => ['nullable', 'integer', 'min:0'],
            'terminal' => ['nullable', 'boolean'], 'aktif' => ['nullable', 'boolean'],
        ]);
        $s = $id ? StatusKes::query()->findOrFail($id) : new StatusKes;
        if ($id) {
            unset($d['kod']);
        }
        $s->fill($d)->save();

        return response()->json(['data' => $s, 'message' => __('kes::kes.mesej.disimpan')], $id ? 200 : 201);
    }

    public function senaraiKonfigurasi(): JsonResponse
    {
        return response()->json(['data' => [
            'jenis' => JenisKes::query()->orderBy('susunan')->get(),
            'status' => StatusKes::query()->orderBy('susunan')->get(),
            'panel' => PanelPeguam::query()->orderBy('nama_firma')->get()->map(fn (PanelPeguam $p) => [
                'id' => $p->id, 'nama_firma' => $p->nama_firma, 'no_pendaftaran' => $p->no_pendaftaran, 'pegawai_penghubung' => $p->pegawai_penghubung,
                'no_telefon' => $p->no_telefon, 'emel' => $p->emel, 'status' => $p->status, 'tarikh_lantikan' => $p->tarikh_lantikan?->toDateString(),
                'tarikh_tamat' => $p->tarikh_tamat?->toDateString(), 'id_pengguna' => $p->id_pengguna, 'sintetik' => $p->sintetik,
            ])->values(),
        ]]);
    }

    public function simpanPanel(Request $request, ?int $id = null): JsonResponse
    {
        $d = $request->validate([
            'nama_firma' => ['required', 'string', 'max:200'], 'no_pendaftaran' => ['nullable', 'string', 'max:60'],
            'pegawai_penghubung' => ['nullable', 'string', 'max:150'], 'no_telefon' => ['nullable', 'string', 'max:30'], 'emel' => ['nullable', 'email', 'max:150'],
            'alamat' => ['nullable', 'string', 'max:500'], 'status' => ['nullable', Rule::in(['aktif', 'digantung', 'tamat'])],
            'tarikh_lantikan' => ['nullable', 'date_format:Y-m-d'], 'tarikh_tamat' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tarikh_lantikan'],
            'id_pengguna' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $p = $id ? PanelPeguam::query()->findOrFail($id) : new PanelPeguam;
        $p->fill($d)->save();
        $this->akses->lupa();

        return response()->json(['data' => ['id' => $p->id, 'nama_firma' => $p->nama_firma, 'status' => $p->status], 'message' => __('kes::kes.mesej.disimpan')], $id ? 200 : 201);
    }
}
