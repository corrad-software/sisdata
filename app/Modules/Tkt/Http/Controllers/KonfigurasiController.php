<?php

namespace App\Modules\Tkt\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tkt\Models\AhliPasukan;
use App\Modules\Tkt\Models\Pasukan;
use App\Modules\Tkt\Models\PemetaanAduan;
use App\Modules\Tkt\Models\PeraturanPenugasan;
use App\Modules\Tkt\Models\SlaKonfigurasi;
use App\Modules\Tkt\Support\Lalai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * SRS-TKT-08/09/10/15/21 administration without code: SLA per severity
 * (new dated versions — history kept), escalation hierarchy, teams /
 * members (L1 / L2 / vendor / BTM), assignment rules, central-system
 * mapping. Every change is audited (MOD-AUD observer).
 */
class KonfigurasiController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => [
            'sla' => SlaKonfigurasi::query()->orderBy('keterukan')->orderByDesc('tarikh_berkuat_kuasa')->orderByDesc('id')->get()
                ->map(fn (SlaKonfigurasi $c) => [
                    'id' => $c->id, 'keterukan' => $c->keterukan, 'keterukan_nama' => Lalai::keterukan($c->keterukan), 'ambang_respons' => $c->ambang_respons,
                    'ambang_penyelesaian' => $c->ambang_penyelesaian, 'waktu_perkhidmatan' => $c->waktu_perkhidmatan, 'ambang_amaran_peratus' => $c->ambang_amaran_peratus,
                    'hierarki_eskalasi' => $c->hierarki_eskalasi, 'tarikh_berkuat_kuasa' => $c->tarikh_berkuat_kuasa->toDateString(), 'aktif' => $c->aktif, 'catatan' => $c->catatan,
                    'semasa' => SlaKonfigurasi::semasa($c->keterukan)?->id === $c->id,
                ])->values(),
            'pasukan' => Pasukan::query()->with('ahli.pengguna')->orderBy('aras')->orderBy('id')->get()->map(fn (Pasukan $p) => [
                'id' => $p->id, 'kod' => $p->kod, 'nama' => $p->nama, 'aras' => $p->aras, 'jenis' => $p->jenis, 'nama_vendor' => $p->nama_vendor, 'modul' => $p->modul, 'aktif' => $p->aktif,
                'ahli' => $p->ahli->map(fn (AhliPasukan $a) => ['id' => $a->id, 'id_pengguna' => $a->id_pengguna, 'nama' => $a->pengguna?->name, 'emel' => $a->pengguna?->email, 'peranan' => $a->peranan, 'aktif' => $a->aktif])->values(),
            ])->values(),
            'peraturan' => PeraturanPenugasan::query()->with('pasukan')->orderByDesc('aktif')->orderByDesc('keutamaan')->orderBy('id')->get()->map(fn (PeraturanPenugasan $r) => [
                'id' => $r->id, 'modul' => $r->modul, 'kategori' => $r->kategori, 'keterukan' => $r->keterukan, 'id_pasukan' => $r->id_pasukan, 'pasukan' => $r->pasukan?->nama,
                'id_juruteknik' => $r->id_juruteknik, 'keutamaan' => $r->keutamaan, 'aktif' => $r->aktif,
            ])->values(),
            'pemetaan' => PemetaanAduan::query()->orderBy('jenis')->orderBy('id')->get(['id', 'jenis', 'nilai_tempatan', 'nilai_berpusat', 'arah', 'aktif']),
            'nota_sla' => __('tkt::tkt.konfigurasi.nota_sla'),
        ]]);
    }

    public function simpanSla(Request $request): JsonResponse
    {
        $data = $request->validate([
            'keterukan' => ['required', 'integer', 'between:1,4'],
            'ambang_respons' => ['required', 'integer', 'min:1', 'max:525600'],
            'ambang_penyelesaian' => ['required', 'integer', 'min:1', 'max:525600', 'gte:ambang_respons'],
            'waktu_perkhidmatan' => ['required', Rule::in(['waktu_pejabat', '24x7'])],
            'ambang_amaran_peratus' => ['required', 'integer', 'between:10,99'],
            'hierarki_eskalasi' => ['required', 'array', 'min:1', 'max:6'],
            'hierarki_eskalasi.*.peringkat' => ['required', 'integer', 'between:1,6', 'distinct'],
            'hierarki_eskalasi.*.penerima' => ['required', 'string', 'max:60', 'regex:/^(juruteknik|penyelia|pengurus|peranan:[a-z0-9_]+)$/'],
            'hierarki_eskalasi.*.selepas_minit' => ['required', 'integer', 'min:0', 'max:525600'],
            'tarikh_berkuat_kuasa' => ['required', 'date'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);
        $levels = collect($data['hierarki_eskalasi'])->map(fn ($l) => ['peringkat' => (int) $l['peringkat'], 'penerima' => (string) $l['penerima'], 'selepas_minit' => (int) $l['selepas_minit']])
            ->sortBy('peringkat')->values()->all();
        $row = SlaKonfigurasi::query()->create(['hierarki_eskalasi' => $levels, 'aktif' => true] + $data);

        return response()->json(['data' => ['id' => $row->id], 'message' => __('tkt::tkt.mesej.sla_disimpan')], 201);
    }

    public function nyahaktifSla(SlaKonfigurasi $sla): JsonResponse
    {
        $sla->forceFill(['aktif' => false])->save();

        return response()->json(['message' => __('tkt::tkt.mesej.dikemas_kini')]);
    }

    public function simpanPasukan(Request $request, ?Pasukan $pasukan = null): JsonResponse
    {
        $data = $request->validate([
            'kod' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_]+$/', Rule::unique('tkt_pasukan', 'kod')->ignore($pasukan?->id)],
            'nama' => ['required', 'string', 'max:150'],
            'aras' => ['required', Rule::in(['L1', 'L2'])],
            'jenis' => ['required', Rule::in(['dalaman', 'vendor'])],
            'nama_vendor' => ['nullable', 'string', 'max:150'],
            'modul' => ['nullable', 'array'],
            'modul.*' => [Rule::in(array_keys(Lalai::MODUL))],
            'aktif' => ['nullable', 'boolean'],
        ]);
        $data['modul'] = ($data['modul'] ?? []) === [] ? null : array_values($data['modul']);
        $pasukan ??= new Pasukan;
        $pasukan->fill($data + ['aktif' => true])->save();

        return response()->json(['data' => ['id' => $pasukan->id], 'message' => __('tkt::tkt.mesej.dikemas_kini')], $pasukan->wasRecentlyCreated ? 201 : 200);
    }

    public function simpanAhli(Request $request, Pasukan $pasukan): JsonResponse
    {
        $data = $request->validate([
            'id_pengguna' => ['required', 'integer', 'exists:users,id'],
            'peranan' => ['required', Rule::in(array_keys(Lalai::PERANAN_AHLI))],
            'aktif' => ['nullable', 'boolean'],
        ]);
        $row = AhliPasukan::query()->updateOrCreate(['id_pasukan' => $pasukan->id, 'id_pengguna' => $data['id_pengguna']], ['peranan' => $data['peranan'], 'aktif' => $data['aktif'] ?? true]);

        return response()->json(['data' => ['id' => $row->id], 'message' => __('tkt::tkt.mesej.dikemas_kini')]);
    }

    public function simpanPeraturan(Request $request, ?PeraturanPenugasan $peraturan = null): JsonResponse
    {
        $data = $request->validate([
            'modul' => ['nullable', Rule::in(array_keys(Lalai::MODUL))],
            'kategori' => ['nullable', Rule::in(array_keys(Lalai::KATEGORI))],
            'keterukan' => ['nullable', 'integer', 'between:1,4'],
            'id_pasukan' => ['required', 'integer', 'exists:tkt_pasukan,id'],
            'id_juruteknik' => ['nullable', 'integer', 'exists:users,id'],
            'keutamaan' => ['nullable', 'integer', 'between:-1000,1000'],
            'aktif' => ['nullable', 'boolean'],
        ]);
        $peraturan ??= new PeraturanPenugasan;
        $peraturan->fill($data + ['keutamaan' => 0, 'aktif' => true])->save();

        return response()->json(['data' => ['id' => $peraturan->id], 'message' => __('tkt::tkt.mesej.dikemas_kini')], $peraturan->wasRecentlyCreated ? 201 : 200);
    }

    public function simpanPemetaan(Request $request, ?PemetaanAduan $pemetaan = null): JsonResponse
    {
        $data = $request->validate([
            'jenis' => ['required', Rule::in(['kategori', 'keterukan', 'status', 'medan'])],
            'nilai_tempatan' => ['required', 'string', 'max:60'],
            'nilai_berpusat' => ['required', 'string', 'max:60'],
            'arah' => ['nullable', Rule::in(['masuk', 'keluar', 'dua_hala'])],
            'aktif' => ['nullable', 'boolean'],
        ]);
        $pemetaan ??= new PemetaanAduan;
        $pemetaan->fill($data + ['arah' => 'dua_hala', 'aktif' => true])->save();

        return response()->json(['data' => ['id' => $pemetaan->id], 'message' => __('tkt::tkt.mesej.dikemas_kini')], $pemetaan->wasRecentlyCreated ? 201 : 200);
    }

    /** Active users for the team-member picker. */
    public function pengguna(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        return response()->json(['data' => User::query()->where('is_active', true)
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('name', 'ilike', '%'.addcslashes($q, '%_\\').'%')->orWhere('email', 'ilike', '%'.addcslashes($q, '%_\\').'%')))
            ->orderBy('name')->limit(20)->get(['id', 'name', 'email'])->map(fn (User $u) => ['id' => $u->id, 'nama' => $u->name, 'emel' => $u->email])->values()]);
    }
}
