<?php

namespace App\Modules\Lap\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Lap\Models\KatalogLaporan;
use App\Modules\Lap\Services\Enjin;
use App\Modules\Lap\Services\Katalog;
use App\Modules\Lap\Support\LaporanStandard;
use App\Modules\Lap\Support\SetData;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Central report catalogue, report access settings and reporting data
 * dictionary (SRS-LAP-02/09/23), plus the curated datasets offered to the
 * ad hoc builder (SRS-LAP-03).
 */
class KatalogController extends Controller
{
    public function __construct(private Katalog $katalog, private AccessControl $access, private Enjin $enjin) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->katalog->sync();
        $admin = $this->access->allows($user, 'lap.katalog.kemas_kini');
        $rows = KatalogLaporan::query()->orderBy('susunan')->get()
            ->filter(fn (KatalogLaporan $k) => $admin || $this->katalog->canOpen($user, $k))
            ->map(fn (KatalogLaporan $k) => $this->present($k, $user))->values();

        return response()->json(['data' => $rows, 'kategori' => array_map(fn ($c) => ['kod' => $c, 'nama' => __('lap::lap.kategori.'.$c)], LaporanStandard::KATEGORI)]);
    }

    public function show(Request $request, string $kod): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->katalog->sync();
        $k = KatalogLaporan::query()->where('kod_laporan', $kod)->firstOrFail();
        if (! $this->katalog->canOpen($user, $k) && ! $this->access->allows($user, 'lap.katalog.kemas_kini')) {
            abort(403, __('lap::lap.ralat.tiada_akses'));
        }
        $data = $this->present($k, $user);
        $data['bahagian_laporan'] = $k->jenis === 'komposit' ? array_map(fn ($s) => ['kod' => $s->kod_laporan, 'nama' => $s->nama], $this->katalog->sections($user, $k)) : [];
        $data['kamus'] = $k->set_data ? $this->dictionary((string) $k->set_data, $user) : [];

        return response()->json(['data' => $data]);
    }

    public function update(Request $request, string $kod): JsonResponse
    {
        $k = KatalogLaporan::query()->where('kod_laporan', $kod)->firstOrFail();
        $data = $request->validate([
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'definisi_metrik' => ['nullable', 'string', 'max:4000'],
            'pemilik' => ['nullable', 'string', 'max:150'],
            'peranan_dibenarkan' => ['nullable', 'array'],
            'peranan_dibenarkan.*' => ['string', 'max:60'],
            'aktif' => ['sometimes', 'boolean'],
            'status_pengesahan' => ['sometimes', Rule::in(['draf', 'disahkan'])],
        ]);
        if (isset($data['status_pengesahan']) && $data['status_pengesahan'] !== $k->status_pengesahan) {
            $k->disahkan_oleh = $data['status_pengesahan'] === 'disahkan' ? $request->user()?->id : null;
            $k->disahkan_pada = $data['status_pengesahan'] === 'disahkan' ? now() : null;
        }
        if (array_key_exists('peranan_dibenarkan', $data)) {
            $data['peranan_dibenarkan'] = $data['peranan_dibenarkan'] ? array_values(array_unique($data['peranan_dibenarkan'])) : null;
        }
        $k->fill($data)->save();

        return response()->json(['message' => __('lap::lap.disimpan'), 'data' => $this->present($k, $request->user())]);
    }

    /** Datasets the user may build reports on (fields, types, allowed operators). */
    public function sets(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $out = [];
        foreach ($this->katalog->sets($user) as $key => $s) {
            $fields = [];
            foreach ($this->enjin->fields($s, $user) as $k => $f) {
                $fields[] = ['kunci' => $k, 'label' => $f['label'], 'jenis' => $f['jenis']];
            }
            $out[] = ['kunci' => $key, 'label' => $s['label'], 'kategori' => $s['kategori'], 'modul' => $s['modul'], 'tarikh' => $s['tarikh'], 'medan' => $fields];
        }

        return response()->json(['data' => $out, 'op' => Enjin::OPS, 'fungsi' => Enjin::FUNGSI, 'pratetap' => array_map(fn ($p) => ['kod' => $p, 'nama' => __('lap::lap.pratetap.'.$p)], Enjin::PRATETAP)]);
    }

    /** SRS-LAP-23: reporting data dictionary — every report with metric, source, owner; every dataset field with MOD-PRO's kamus_data definition. */
    public function kamus(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->katalog->sync();
        $reports = KatalogLaporan::query()->orderBy('susunan')->get()->filter(fn ($k) => $this->katalog->canOpen($user, $k))
            ->map(fn ($k) => ['kod' => $k->kod_laporan, 'nama' => $k->nama, 'kategori' => $k->kategori, 'jenis' => $k->jenis, 'definisi_metrik' => $k->definisi_metrik, 'sumber_data' => $k->sumber_data, 'pemilik' => $k->pemilik, 'status_pengesahan' => $k->status_pengesahan])->values();
        $sets = [];
        foreach ($this->katalog->sets($user) as $key => $s) {
            $sets[] = ['kunci' => $key, 'label' => $s['label'], 'paparan' => 'laporan.'.$s['paparan'], 'jadual_sumber' => $s['jadual_sumber'], 'modul' => $s['modul'], 'medan' => $this->dictionary($key, $user)];
        }

        return response()->json(['data' => ['laporan' => $reports, 'set_data' => $sets, 'pautan_kamus_pro' => '/admin/pro/kamus', 'dokumen' => 'docs/data/kamus-data.md']]);
    }

    /** @return list<array<string, mixed>> */
    private function dictionary(string $setKey, User $user): array
    {
        $set = SetData::get($setKey);
        if (! $set) {
            return [];
        }
        $fields = $this->enjin->fields($set, $user);
        $pairs = array_values(array_filter(array_map(fn ($f) => $f['sumber'] ?? null, $fields)));
        $kamus = [];
        if ($pairs !== []) {
            $q = DB::table('kamus_data');
            foreach ($pairs as $p) {
                [$t, $m] = explode('.', $p, 2);
                $q->orWhere(fn ($w) => $w->where('nama_jadual', $t)->where('nama_medan', $m));
            }
            foreach ($q->get(['nama_jadual', 'nama_medan', 'nama_perniagaan', 'definisi', 'format', 'nilai_dibenarkan', 'data_owner', 'data_steward', 'klasifikasi']) as $r) {
                $kamus[$r->nama_jadual.'.'.$r->nama_medan] = (array) $r;
            }
        }
        $out = [];
        foreach ($fields as $k => $f) {
            $d = isset($f['sumber']) ? ($kamus[$f['sumber']] ?? null) : null;
            $out[] = [
                'kunci' => $k, 'label' => $f['label'], 'jenis' => $f['jenis'], 'sumber' => $f['sumber'] ?? __('lap::lap.kamus.terbitan'),
                'definisi' => $d['definisi'] ?? null, 'nama_perniagaan' => $d['nama_perniagaan'] ?? null, 'nilai_dibenarkan' => $d['nilai_dibenarkan'] ?? null,
                'data_owner' => $d['data_owner'] ?? null, 'data_steward' => $d['data_steward'] ?? null, 'klasifikasi' => $d['klasifikasi'] ?? null,
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function present(KatalogLaporan $k, ?User $user): array
    {
        return [
            'kod' => $k->kod_laporan, 'nama' => $k->nama, 'kategori' => $k->kategori, 'kategori_nama' => __('lap::lap.kategori.'.$k->kategori), 'jenis' => $k->jenis,
            'keterangan' => $k->keterangan, 'definisi_metrik' => $k->definisi_metrik, 'sumber_data' => $k->sumber_data, 'pemilik' => $k->pemilik,
            'kebenaran' => $k->kebenaran ?? ($k->set_data ? SetData::get((string) $k->set_data)['kebenaran'] ?? null : null),
            'peranan_dibenarkan' => $k->peranan_dibenarkan ?? [], 'set_data' => $k->set_data, 'definisi' => $k->definisi, 'pautan' => $k->pautan,
            'bahagian' => $k->bahagian ?? [], 'status_pengesahan' => $k->status_pengesahan, 'disahkan_pada' => $k->disahkan_pada?->toIso8601String(),
            'aktif' => $k->aktif, 'boleh_buka' => $user ? $this->katalog->canOpen($user, $k) : false,
        ];
    }
}
