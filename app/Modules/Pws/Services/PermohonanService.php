<?php

namespace App\Modules\Pws\Services;

use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Pws\Models\KlienApi;
use App\Modules\Pws\Models\Permohonan;
use App\Modules\Pws\PwsServiceProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * SRS-PWS-07/08/21: applications from the portal.
 *
 *  - SISDATA issues the single reference (PHWS-YYYY-XXXXXXX) used on the
 *    portal, in e-mails and in SISDATA (it is PER's rujukan_luaran and PJK's
 *    rujukan_portal);
 *  - wakaf → outbox → MOD-PER pulls via WakafPortal (case "permohonan awam
 *    menunggu semakan"); sewaan → MOD-PJK portal endpoint with the
 *    integration account (application "diterima");
 *  - status for the applicant comes back from SISDATA (PER acknowledge /
 *    PJK status), checked against the applicant's e-mail (no enumeration).
 *
 * Supporting documents go to MOD-DMS (type whitelist, magic number, antivirus).
 */
class PermohonanService
{
    public const LAMPIRAN_MIMES = ['pdf', 'jpg', 'jpeg', 'png'];

    public function __construct(private InternalApi $api, private AkaunIntegrasi $accounts, private DocumentStore $documents) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  list<UploadedFile>  $files
     */
    public function wakaf(array $input, array $files, KlienApi $klien): Permohonan
    {
        $data = Validator::make($input, [
            'nama_pewakaf' => ['required', 'string', 'max:200'],
            'jenis_pemohon' => ['nullable', Rule::in(['individu', 'organisasi'])],
            'no_pengenalan' => ['nullable', 'string', 'max:30', 'regex:/^[0-9A-Za-z\-]+$/'],
            'emel' => ['required', 'email', 'max:150'],
            'jenis_wakaf' => ['nullable', Rule::in(['am', 'khas', 'irsod'])],
            'tujuan_wakaf' => ['nullable', 'string', 'max:2000'],
            'no_lot' => ['nullable', 'string', 'max:50'],
            'kod_mukim' => ['nullable', 'regex:/^\d{6}$/'],
            'keluasan' => ['nullable', 'string', 'max:40'],
            'nilai_anggaran' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'persetujuan_pdpa' => ['accepted'],
        ], [], self::LABELS)->validate();
        $this->validateFiles($files);

        $row = $this->newApplication('wakaf', $klien, $data['emel'], [
            ...array_diff_key($data, ['persetujuan_pdpa' => 1, 'emel' => 1]),
        ], isset($data['kod_mukim']) ? substr($data['kod_mukim'], 0, 4) : null, $data['jenis_wakaf'] ?? null);
        // Push now through PER's portal endpoint; on failure the row stays in
        // the outbox (PER's scheduled pull and pws:cuba-semula retry it).
        $status = $this->deliverWakaf($row);
        if ($status === 422) {
            $errors = $row->ralat_terakhir;
            $row->delete();
            throw ValidationException::withMessages(self::remapErrors((array) json_decode((string) $errors, true)));
        }
        $this->attach($row, $files);

        return $row->refresh();
    }

    /** Push one waqf registration to MOD-PER (POST /api/v1/per/portal-wakaf/permohonan). */
    public function deliverWakaf(Permohonan $row): int
    {
        $data = $row->kandungan ?? [];
        $payload = array_filter([
            'rujukan_portal' => $row->no_rujukan, 'nama_pewakaf' => $data['nama_pewakaf'] ?? null, 'no_pengenalan' => $data['no_pengenalan'] ?? null,
            'jenis_wakaf' => $data['jenis_wakaf'] ?? null, 'tujuan_wakaf' => $data['tujuan_wakaf'] ?? null, 'no_lot' => $data['no_lot'] ?? null,
            'kod_mukim' => $data['kod_mukim'] ?? null, 'nilai_anggaran' => $data['nilai_anggaran'] ?? null,
            'tarikh_permohonan' => $row->created_at->toDateString(),
        ], fn ($v) => $v !== null && $v !== '');

        LogIntegrasi::retry($row->bilangan_cuba);
        $res = $this->api->call('POST', 'per/portal-wakaf/permohonan', $payload, $this->accounts->portal(), [], null, [], $row->no_rujukan);
        LogIntegrasi::retry(null);
        $row->refresh(); // PER may already have acknowledged through WakafPortal

        if ($res['status'] === 422) {
            $errors = (array) ($res['body']['errors'] ?? ['nama_pewakaf' => [(string) ($res['body']['message'] ?? '')]]);
            $row->forceFill(['status_penghantaran' => 'gagal', 'ralat_terakhir' => mb_substr((string) json_encode($errors), 0, 500)])->save();

            return 422;
        }
        if (! in_array($res['status'], [200, 201], true)) {
            $row->forceFill(['status_penghantaran' => 'gagal', 'bilangan_cuba' => $row->bilangan_cuba + 1, 'ralat_terakhir' => 'HTTP '.$res['status']])->save();

            return $res['status'];
        }
        $per = (array) ($res['body']['data'] ?? []);
        $row->forceFill(['status_penghantaran' => 'diterima', 'kandungan' => null, 'ralat_terakhir' => null, 'bilangan_cuba' => $row->bilangan_cuba + 1,
            'rujukan_sisdata' => $per['no_kes'] ?? $row->rujukan_sisdata])->save();
        if ($row->status === 'dihantar') {
            $row->catatStatus('menunggu_semakan', __('pws::pws.status.menunggu_semakan', [], 'ms'));
        }

        return 200;
    }

    /**
     * SRS-PWS-22 retry: re-deliver what has not reached PER / PJK yet.
     *
     * @return array{wakaf: int, sewaan: int, gagal: int}
     */
    public function retryFailed(): array
    {
        Permohonan::query()->where('jenis', 'wakaf')->where('status_penghantaran', 'dihantar')
            ->where('updated_at', '<', now()->subMinutes(15))->update(['status_penghantaran' => 'gagal']);
        $out = ['wakaf' => 0, 'sewaan' => 0, 'gagal' => 0];
        $rows = Permohonan::query()->whereIn('status_penghantaran', ['menunggu', 'gagal'])->where('bilangan_cuba', '<', 10)
            ->whereNotNull('kandungan')->orderBy('id')->limit(200)->get();
        foreach ($rows as $row) {
            $ok = ($row->jenis === 'wakaf' ? $this->deliverWakaf($row) : $this->deliverRental($row)) === 200;
            $ok ? $out[$row->jenis]++ : $out['gagal']++;
            if (! $ok && $row->bilangan_cuba >= 10) {
                try {
                    app(Notifier::class)->send(PwsServiceProvider::NTF_GAGAL, [], ['no_rujukan' => $row->no_rujukan, 'jenis' => $row->jenis, 'bilangan_cuba' => $row->bilangan_cuba],
                        '/admin/pws/pemantauan', 'pws_gagal:'.$row->no_rujukan);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<UploadedFile>  $files
     */
    public function sewaan(array $input, array $files, KlienApi $klien, string $endUserIp): Permohonan
    {
        $data = Validator::make($input, [
            'kod_premis' => ['required', 'string', 'max:40'],
            'jenis_entiti' => ['required', Rule::in(['individu', 'syarikat'])],
            'nama' => ['required', 'string', 'max:200'],
            'no_pengenalan' => ['nullable', 'required_if:jenis_entiti,individu', 'string', 'max:20'],
            'no_pendaftaran_syarikat' => ['nullable', 'required_if:jenis_entiti,syarikat', 'string', 'max:30'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'poskod' => ['nullable', 'regex:/^\d{5}$/'],
            'telefon' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s]+$/'],
            'emel' => ['required', 'email', 'max:150'],
            'tarikh_mula_dicadang' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'tempoh_bulan' => ['nullable', 'integer', 'min:1', 'max:360'],
            'tujuan' => ['nullable', 'string', 'max:500'],
            'persetujuan_pdpa' => ['accepted'],
        ], [], self::LABELS)->validate();
        $this->validateFiles($files);

        $payload = array_filter([
            'kod_premis' => $data['kod_premis'],
            'pemohon' => array_filter([
                'jenis_entiti' => $data['jenis_entiti'], 'nama' => $data['nama'], 'no_pengenalan' => $data['no_pengenalan'] ?? null,
                'no_pendaftaran_syarikat' => $data['no_pendaftaran_syarikat'] ?? null, 'alamat' => $data['alamat'] ?? null,
                'poskod' => $data['poskod'] ?? null, 'telefon' => $data['telefon'] ?? null, 'emel' => $data['emel'],
            ], fn ($v) => $v !== null),
            'tarikh_mula_dicadang' => $data['tarikh_mula_dicadang'] ?? null,
            'tempoh_bulan' => $data['tempoh_bulan'] ?? null,
            'tujuan' => $data['tujuan'] ?? null,
        ], fn ($v) => $v !== null);

        $row = $this->newApplication('sewaan', $klien, $data['emel'], $payload, null, mb_substr($data['kod_premis'], 0, 40));
        $res = $this->deliverRental($row, $endUserIp);

        if ($res === 422) {
            $errors = $row->ralat_terakhir;
            $row->delete(); // nothing was created in SISDATA — the applicant corrects and resubmits
            throw ValidationException::withMessages(self::remapErrors((array) json_decode((string) $errors, true)));
        }
        if ($res !== 200) {
            // Kept in the outbox; pws:cuba-semula re-sends it (PJK is idempotent on the reference).
            return $row->refresh();
        }
        $this->attach($row, $files);

        return $row->refresh();
    }

    /**
     * Send (or re-send) a rental application to MOD-PJK. Returns 200 on
     * success, 422 on a validation refusal (errors JSON in ralat_terakhir),
     * otherwise the HTTP status (row marked gagal for retry).
     */
    public function deliverRental(Permohonan $row, ?string $ip = null): int
    {
        LogIntegrasi::retry($row->bilangan_cuba);
        $res = $this->api->call('POST', 'pjk/portal/permohonan', ['rujukan_portal' => $row->no_rujukan, ...($row->kandungan ?? [])],
            $this->accounts->portal(), [], $ip, [], $row->no_rujukan);
        LogIntegrasi::retry(null);

        if ($res['status'] === 422) {
            $errors = (array) ($res['body']['errors'] ?? ['kod_premis' => [(string) ($res['body']['message'] ?? '')]]);
            $row->forceFill(['status_penghantaran' => 'gagal', 'ralat_terakhir' => mb_substr((string) json_encode($errors), 0, 500)])->save();

            return 422;
        }
        if (! in_array($res['status'], [200, 201], true)) {
            $row->forceFill(['status_penghantaran' => 'gagal', 'bilangan_cuba' => $row->bilangan_cuba + 1, 'ralat_terakhir' => 'HTTP '.$res['status']])->save();

            return $res['status'];
        }
        $pjk = (array) ($res['body']['data'] ?? []);
        $row->forceFill([
            'status_penghantaran' => 'diterima', 'bilangan_cuba' => $row->bilangan_cuba + 1, 'rujukan_sisdata' => $pjk['no_permohonan'] ?? null,
            'kandungan' => null, 'ralat_terakhir' => null,
        ])->save();
        $row->catatStatus((string) ($pjk['status'] ?? 'diterima'), (string) ($pjk['status_nama'] ?? __('pws::pws.status.diterima', [], 'ms')));

        return 200;
    }

    /** Applicant status check: reference + the e-mail given on the form. */
    public function status(string $reference, string $email, string $endUserIp): ?Permohonan
    {
        $row = Permohonan::query()->where('no_rujukan', strtoupper(trim($reference)))->first();
        if ($row === null || $row->cincangan_emel === null || ! hash_equals($row->cincangan_emel, self::emailHash($email))) {
            return null;
        }
        if ($row->jenis === 'sewaan' && $row->status_penghantaran === 'diterima') {
            $this->refreshRental($row, $endUserIp);
        }

        return $row;
    }

    /** Pull the latest MOD-PJK status for a rental application (two-way status). */
    public function refreshRental(Permohonan $row, ?string $ip = null): void
    {
        $res = $this->api->call('GET', 'pjk/portal/permohonan/'.rawurlencode($row->no_rujukan), [], $this->accounts->portal(), [], $ip, [], $row->no_rujukan);
        if ($res['status'] === 200 && isset($res['body']['data']['status'])) {
            $d = (array) $res['body']['data'];
            $row->catatStatus((string) $d['status'], isset($d['status_nama']) ? (string) $d['status_nama'] : null);
        }
    }

    /**
     * Status payload for the portal — reference, type, status, history; never
     * personal data.
     *
     * @return array<string, mixed>
     */
    public static function present(Permohonan $row): array
    {
        return [
            'no_rujukan' => $row->no_rujukan,
            'jenis' => $row->jenis,
            'status' => $row->status,
            'status_nama' => self::label($row->status, $row->status_nama),
            'diterima_sisdata' => $row->status_penghantaran === 'diterima',
            'tarikh_hantar' => $row->created_at->toIso8601String(),
            'tarikh_kemas_kini' => ($row->tarikh_status ?? $row->updated_at)->toIso8601String(),
            'sejarah' => array_map(fn ($h) => ['status' => $h['status'], 'status_nama' => self::label((string) $h['status'], $h['nama'] ?? null), 'pada' => $h['pada']], $row->sejarah_status ?? []),
        ];
    }

    /** Status label in the caller's language (stored labels are BM; PJK's own labels as fallback). */
    public static function label(string $status, ?string $stored): string
    {
        return Lang::has('pws::pws.status.'.$status) ? __('pws::pws.status.'.$status) : ($stored ?? $status);
    }

    public static function emailHash(string $email): string
    {
        return hash_hmac('sha256', 'emel|'.mb_strtolower(trim($email)), (string) config('app.key'));
    }

    /**
     * @param  array<string, mixed>|null  $content
     */
    private function newApplication(string $type, KlienApi $klien, string $email, ?array $content, ?string $district, ?string $category): Permohonan
    {
        return DB::transaction(function () use ($type, $klien, $email, $content, $district, $category) {
            do {
                $ref = 'PHWS-'.now()->format('Y').'-'.strtoupper(Str::random(7));
            } while (Permohonan::query()->where('no_rujukan', $ref)->exists());

            $row = Permohonan::query()->create([
                'no_rujukan' => $ref, 'jenis' => $type, 'klien_id' => $klien->id, 'kandungan' => $content,
                'cincangan_emel' => self::emailHash($email), 'kod_daerah' => $district, 'kategori' => $category,
                'status_penghantaran' => $type === 'wakaf' ? 'menunggu' : 'dihantar', 'status' => 'dihantar',
            ]);
            $row->catatStatus('dihantar', __('pws::pws.status.dihantar', [], 'ms'));

            return $row;
        });
    }

    /** @param list<UploadedFile> $files */
    private function validateFiles(array $files): void
    {
        Validator::make(['lampiran' => $files], [
            'lampiran' => ['array', 'max:5'],
            'lampiran.*' => ['file', 'mimes:'.implode(',', self::LAMPIRAN_MIMES), 'max:5120'],
        ], [], ['lampiran' => 'lampiran', 'lampiran.*' => 'lampiran'])->validate();
    }

    /** @param list<UploadedFile> $files */
    private function attach(Permohonan $row, array $files): void
    {
        foreach ($files as $file) {
            try {
                $this->documents->store($file, 'pws_permohonan', $row->id, 'dokumen_sokongan_permohonan', $this->accounts->portal(), 'sulit', ['no_rujukan' => $row->no_rujukan]);
            } catch (DocumentRejected $e) {
                throw ValidationException::withMessages(['lampiran' => [$e->getMessage()]]);
            }
        }
    }

    /**
     * PJK nests applicant fields under "pemohon."; the portal form is flat.
     *
     * @param  array<string, mixed>  $errors
     * @return array<string, list<string>>
     */
    private static function remapErrors(array $errors): array
    {
        $out = [];
        foreach ($errors as $key => $messages) {
            $out[preg_replace('/^pemohon\./', '', (string) $key) ?? (string) $key] = array_values(array_map('strval', (array) $messages));
        }

        return $out;
    }

    private const LABELS = [
        'nama_pewakaf' => 'nama pewakaf', 'no_pengenalan' => 'no. pengenalan', 'emel' => 'e-mel', 'telefon' => 'telefon',
        'jenis_wakaf' => 'jenis wakaf', 'tujuan_wakaf' => 'tujuan wakaf', 'no_lot' => 'no. lot', 'kod_mukim' => 'mukim',
        'nilai_anggaran' => 'nilai anggaran', 'persetujuan_pdpa' => 'persetujuan notis privasi', 'kod_premis' => 'premis',
        'jenis_entiti' => 'jenis pemohon', 'nama' => 'nama', 'no_pendaftaran_syarikat' => 'no. pendaftaran syarikat',
        'tarikh_mula_dicadang' => 'tarikh mula dicadang', 'tempoh_bulan' => 'tempoh', 'tujuan' => 'tujuan',
    ];
}
