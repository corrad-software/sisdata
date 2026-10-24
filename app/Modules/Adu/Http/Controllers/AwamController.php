<?php

namespace App\Modules\Adu\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adu\Contracts\CaptchaVerifier;
use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Models\KronologiAduan;
use App\Modules\Adu\Models\Pengadu;
use App\Modules\Adu\Services\AduanService;
use App\Modules\Adu\Support\Lalai;
use App\Modules\Adu\Support\PenggunaSistem;
use App\Modules\Adu\Support\Rujukan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * SRS-ADU-02/03/04/22/24: public complaint API (no account) for the
 * separate portal (MOD-PWS / API Gateway). Rate-limited per IP and per phone
 * number, CAPTCHA, file type + size limits (and MOD-DMS whitelist, magic
 * number and antivirus behind it). Responses never reveal internal detail.
 */
class AwamController extends Controller
{
    public const MIMES = ['jpg', 'jpeg', 'png', 'pdf'];

    public function __construct(private AduanService $service, private Rujukan $ref, private Parameters $parameters) {}

    public function rujukan(): JsonResponse
    {
        return response()->json(['data' => [
            'kategori' => collect($this->ref->kategori())->map(fn ($nama, $kod) => ['kod' => $kod, 'nama' => $nama])->values(),
            'subkategori' => $this->ref->subkategori(),
            'jenis_fail' => self::MIMES,
            'saiz_maks_kb' => (int) $this->parameters->get('adu.awam_saiz_maks_kb', 10240),
            'bil_fail_maks' => (int) $this->parameters->get('adu.awam_bil_fail_maks', 5),
        ]]);
    }

    public function store(Request $request, CaptchaVerifier $captcha): JsonResponse
    {
        $ipKey = 'adu-awam-ip:'.sha1((string) $request->ip());
        $perIp = (int) $this->parameters->get('adu.awam_had_ip_sejam', 5);
        if (RateLimiter::tooManyAttempts($ipKey, $perIp)) {
            abort(429, __('adu::adu.ralat.had_kadar'));
        }

        $data = $request->validate([
            'kategori' => ['required', 'string', 'max:20'],
            'subkategori' => ['nullable', 'string', 'max:30'],
            'keterangan' => ['required', 'string', 'min:20', 'max:5000'],
            'lokasi_teks' => ['nullable', 'required_without:lat', 'string', 'max:500'],
            'lat' => ['nullable', 'numeric', 'between:0.5,7.5', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:99,120', 'required_with:lat'],
            'tanpa_nama' => ['nullable', 'boolean'],
            'nama' => ['nullable', 'exclude_if:tanpa_nama,true', 'required_unless:tanpa_nama,true', 'string', 'max:200'],
            'no_telefon' => ['nullable', 'exclude_if:tanpa_nama,true', 'required_without_all:emel,tanpa_nama', 'string', 'regex:/^[0-9+\-\s()]{7,30}$/'],
            'emel' => ['nullable', 'exclude_if:tanpa_nama,true', 'email', 'max:200'],
            'alamat' => ['nullable', 'exclude_if:tanpa_nama,true', 'string', 'max:500'],
            'saluran_makluman' => ['nullable', 'exclude_if:tanpa_nama,true', Rule::in(['emel', 'sms'])],
            'captcha' => ['required', 'string', 'max:2000'],
            'fail' => ['nullable', 'array', 'max:'.(int) $this->parameters->get('adu.awam_bil_fail_maks', 5)],
            'fail.*' => ['file', 'mimes:'.implode(',', self::MIMES), 'max:'.(int) $this->parameters->get('adu.awam_saiz_maks_kb', 10240)],
        ]);

        RateLimiter::hit($ipKey, 3600);
        if (! $captcha->verify($data['captcha'], $request->ip())) {
            throw ValidationException::withMessages(['captcha' => [__('adu::adu.ralat.captcha')]]);
        }
        if (! $this->ref->kategoriSah($data['kategori'])) {
            throw ValidationException::withMessages(['kategori' => [__('adu::adu.ralat.kategori')]]);
        }

        $anonymous = (bool) ($data['tanpa_nama'] ?? false);
        if (! $anonymous && ! empty($data['no_telefon'])) {
            $phoneKey = 'adu-awam-tel:'.Pengadu::cincang($data['no_telefon'], 'telefon');
            if (RateLimiter::tooManyAttempts($phoneKey, (int) $this->parameters->get('adu.awam_had_telefon_sehari', 3))) {
                abort(429, __('adu::adu.ralat.had_kadar'));
            }
            RateLimiter::hit($phoneKey, 86400);
        }

        // Content duplicate from the same contact within a day (SRS-ADU-24).
        if (! $anonymous && ($hash = Pengadu::cincang($data['emel'] ?? $data['no_telefon'] ?? null, isset($data['emel']) ? 'emel' : 'telefon'))) {
            $column = isset($data['emel']) ? 'cincangan_emel' : 'cincangan_telefon';
            $same = Aduan::query()->withoutGlobalScope('access')
                ->whereIn('id', Pengadu::query()->where($column, $hash)->where('created_at', '>=', now()->subDay())->select('aduan_id'))
                ->where('keterangan', $data['keterangan'])->first();
            if ($same) {
                return response()->json(['data' => ['no_rujukan_aduan' => $same->no_rujukan_aduan, 'pendua' => true], 'message' => __('adu::adu.mesej.awam_pendua', ['no' => $same->no_rujukan_aduan])], 200);
            }
        }

        $aduan = $this->service->terima(
            $data,
            'portal',
            null,
            null,
            $anonymous ? null : ['nama' => $data['nama'] ?? null, 'no_telefon' => $data['no_telefon'] ?? null, 'emel' => $data['emel'] ?? null, 'alamat' => $data['alamat'] ?? null, 'saluran_makluman' => $data['saluran_makluman'] ?? null],
            $anonymous,
            $request->file('fail', []),
            PenggunaSistem::portal(),
        );

        // SRS-ADU-03: reference number shown immediately.
        return response()->json(['data' => [
            'no_rujukan_aduan' => $aduan->no_rujukan_aduan,
            'tanpa_nama' => $aduan->status_tanpa_nama,
            'status' => Lalai::STATUS_AWAM[$aduan->status] ?? $aduan->status,
            'tarikh_terima' => $aduan->tarikh_terima?->toIso8601String(),
        ], 'message' => __('adu::adu.mesej.awam_diterima', ['no' => $aduan->no_rujukan_aduan])], 201);
    }

    /** SRS-ADU-04/22: status by reference + e-mail/phone (anonymous: reference only). */
    public function semak(Request $request): JsonResponse
    {
        $key = 'adu-awam-semak:'.sha1((string) $request->ip());
        if (RateLimiter::tooManyAttempts($key, (int) $this->parameters->get('adu.awam_had_semak_sejam', 30))) {
            abort(429, __('adu::adu.ralat.had_kadar'));
        }
        RateLimiter::hit($key, 3600);

        $data = $request->validate([
            'no_rujukan_aduan' => ['required', 'string', 'max:20'],
            'pengesahan' => ['nullable', 'string', 'max:200'],
        ]);
        $aduan = Aduan::query()->withoutGlobalScope('access')->where('no_rujukan_aduan', strtoupper(trim($data['no_rujukan_aduan'])))->first();
        $notFound = fn () => abort(404, __('adu::adu.ralat.tidak_dijumpai_awam'));
        if ($aduan === null) {
            $notFound();
        }
        if (! $aduan->status_tanpa_nama) {
            $pengadu = Pengadu::query()->where('aduan_id', $aduan->id)->first();
            $given = (string) ($data['pengesahan'] ?? '');
            $ok = $pengadu !== null && $given !== '' && (
                hash_equals((string) $pengadu->cincangan_emel, (string) Pengadu::cincang($given, 'emel'))
                || hash_equals((string) $pengadu->cincangan_telefon, (string) Pengadu::cincang($given, 'telefon'))
            );
            if (! $ok) {
                $notFound();
            }
        }

        $last = KronologiAduan::query()->where('aduan_id', $aduan->id)->whereNotNull('status_selepas')->latest('tarikh_masa')->first();

        return response()->json(['data' => [
            'no_rujukan_aduan' => $aduan->no_rujukan_aduan,
            'status' => Lalai::STATUS_AWAM[$aduan->status] ?? $aduan->status,
            'kategori' => $this->ref->namaKategori($aduan->kategori),
            'tarikh_terima' => $aduan->tarikh_terima?->toIso8601String(),
            'tarikh_kemas_kini' => ($last->tarikh_masa ?? $aduan->updated_at)->toIso8601String(),
            'tarikh_tutup' => $aduan->tarikh_tutup?->toIso8601String(),
            'tanpa_nama' => $aduan->status_tanpa_nama,
        ]]);
    }
}
