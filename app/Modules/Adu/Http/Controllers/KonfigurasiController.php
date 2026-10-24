<?php

namespace App\Modules\Adu\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Adu\Models\PeraturanPenugasan;
use App\Modules\Adu\Models\PeraturanTriaj;
use App\Modules\Adu\Models\SlaKategori;
use App\Modules\Adu\Models\SubkategoriAduan;
use App\Modules\Adu\Support\Lalai;
use App\Modules\Adu\Support\Rujukan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * SRS-ADU-08/09/11/12/14 configuration (Pentadbir Sistem): subcategories,
 * SLA + escalation path per category, triage rules, assignment rules.
 * Changes are audited field-by-field by MOD-AUD. An SLA change is a NEW row
 * with its own effective date, so complaints already running keep the SLA
 * they started with.
 */
class KonfigurasiController extends Controller
{
    public function __construct(private Rujukan $ref) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => [
            'sla' => SlaKategori::query()->orderBy('kategori')->orderByDesc('tarikh_berkuat_kuasa')->get()->map(fn (SlaKategori $s) => $s->toArray() + ['kategori_nama' => $this->ref->namaKategori($s->kategori)]),
            'subkategori' => SubkategoriAduan::query()->orderBy('kod_kategori')->orderBy('susunan')->get(),
            'triaj' => PeraturanTriaj::query()->orderBy('jenis_peraturan')->orderBy('id')->get(),
            'penugasan' => PeraturanPenugasan::query()->with('pegawai:id,name')->orderBy('keutamaan')->get(),
        ]]);
    }

    public function simpanSla(Request $request, ?SlaKategori $sla = null): JsonResponse
    {
        $data = $request->validate([
            'kategori' => ['required', 'string', 'max:20'],
            'subkategori' => ['nullable', 'string', 'max:30'],
            'tempoh_respons' => ['required', 'integer', 'min:1', 'max:999'],
            'tempoh_penyelesaian' => ['required', 'integer', 'min:1', 'max:9999', 'gte:tempoh_respons'],
            'unit_masa' => ['required', Rule::in(['hari_bekerja', 'jam_bekerja'])],
            'laluan_eskalasi' => ['required', 'array', 'min:1', 'max:10'],
            'laluan_eskalasi.*.peringkat' => ['required', 'integer', 'min:1', 'max:20', 'distinct'],
            'laluan_eskalasi.*.sasaran' => ['required', Rule::in(['respons', 'selesai'])],
            'laluan_eskalasi.*.peratus' => ['required', 'integer', 'min:1', 'max:500'],
            'laluan_eskalasi.*.penerima' => ['required', 'array', 'min:1'],
            'laluan_eskalasi.*.penerima.*' => ['required', 'string', 'regex:/^(pegawai|penyelia|ketua_bahagian|peranan:[a-z0-9_]+)$/'],
            'tarikh_berkuat_kuasa' => ['required', 'date'],
            'aktif' => ['nullable', 'boolean'],
        ]);
        $this->kategori($data['kategori']);
        // A row already in force is never rewritten: the change becomes a new version.
        $row = $sla !== null && $sla->tarikh_berkuat_kuasa->isFuture() ? $sla : new SlaKategori;
        $row->fill($data + ['aktif' => $data['aktif'] ?? true])->save();

        return response()->json(['data' => $row, 'message' => __('adu::adu.mesej.konfigurasi_disimpan')], $sla === null || $row->id !== $sla->id ? 201 : 200);
    }

    public function simpanSubkategori(Request $request, ?SubkategoriAduan $subkategori = null): JsonResponse
    {
        $data = $request->validate([
            'kod_kategori' => ['required', 'string', 'max:20'],
            'kod' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_]+$/'],
            'nama' => ['required', 'string', 'max:150'],
            'aktif' => ['nullable', 'boolean'],
            'susunan' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
        $this->kategori($data['kod_kategori']);
        $dup = SubkategoriAduan::query()->where('kod_kategori', $data['kod_kategori'])->where('kod', $data['kod'])
            ->when($subkategori, fn ($q) => $q->whereKeyNot($subkategori->id))->exists();
        if ($dup) {
            throw ValidationException::withMessages(['kod' => [__('adu::adu.ralat.kod_wujud')]]);
        }
        $row = $subkategori ?? new SubkategoriAduan;
        $row->fill($data + ['aktif' => $data['aktif'] ?? true, 'susunan' => $data['susunan'] ?? 0])->save();

        return response()->json(['data' => $row, 'message' => __('adu::adu.mesej.konfigurasi_disimpan')], $subkategori ? 200 : 201);
    }

    public function simpanTriaj(Request $request, ?PeraturanTriaj $triaj = null): JsonResponse
    {
        $data = $request->validate([
            'jenis_peraturan' => ['required', Rule::in(['kata_kunci', 'semakan_berulang'])],
            'kata_kunci' => ['nullable', 'required_if:jenis_peraturan,kata_kunci', 'array', 'max:50'],
            'kata_kunci.*' => ['string', 'min:2', 'max:60'],
            'kategori_cadangan' => ['nullable', 'required_if:jenis_peraturan,kata_kunci', 'string', 'max:20'],
            'keutamaan_cadangan' => ['nullable', Rule::in(array_keys(Lalai::KEUTAMAAN))],
            'tempoh_semakan_berulang' => ['nullable', 'required_if:jenis_peraturan,semakan_berulang', 'integer', 'min:1', 'max:365'],
            'jejari_meter' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'status_aktif' => ['nullable', 'boolean'],
        ]);
        if (! empty($data['kategori_cadangan'])) {
            $this->kategori($data['kategori_cadangan']);
        }
        $row = $triaj ?? new PeraturanTriaj;
        $row->fill($data + ['status_aktif' => $data['status_aktif'] ?? true])->save();

        return response()->json(['data' => $row, 'message' => __('adu::adu.mesej.konfigurasi_disimpan')], $triaj ? 200 : 201);
    }

    public function simpanPenugasan(Request $request, ?PeraturanPenugasan $penugasan = null): JsonResponse
    {
        $data = $request->validate([
            'kategori' => ['nullable', 'string', 'max:20'],
            'kod_daerah' => ['nullable', 'string', 'size:4'],
            'id_pegawai' => ['nullable', 'required_without:kod_peranan', 'integer', 'exists:users,id'],
            'kod_peranan' => ['nullable', 'required_without:id_pegawai', 'string', 'max:50'],
            'bahagian' => ['nullable', 'string', 'max:30'],
            'keutamaan' => ['nullable', 'integer', 'min:0', 'max:999'],
            'aktif' => ['nullable', 'boolean'],
        ]);
        if (! empty($data['kategori'])) {
            $this->kategori($data['kategori']);
        }
        $row = $penugasan ?? new PeraturanPenugasan;
        $row->fill($data + ['keutamaan' => $data['keutamaan'] ?? 100, 'aktif' => $data['aktif'] ?? true])->save();

        return response()->json(['data' => $row->load('pegawai:id,name'), 'message' => __('adu::adu.mesej.konfigurasi_disimpan')], $penugasan ? 200 : 201);
    }

    private function kategori(string $kod): void
    {
        if (! $this->ref->kategoriSah($kod)) {
            throw ValidationException::withMessages(['kategori' => [__('adu::adu.ralat.kategori')]]);
        }
    }
}
