<?php

namespace App\Modules\Pws\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Pws\Models\KlienApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * SRS-PWS-23, SRS-INT-03/05/13: API clients for the portal server.
 *  - client credentials (id + secret) are exchanged for a short-lived bearer
 *    token (OAuth 2.0 client_credentials shape); only hashes are stored;
 *  - scoped (KlienApi::SKOP), IP allowlist, per-client rate limit, expiry;
 *  - rotation without outage: the previous secret stays valid for a grace
 *    period; revocation is immediate (tokens deleted).
 */
class KlienService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: KlienApi, 1: string} client and the plain secret (shown once)
     */
    public function create(array $input, User $by): array
    {
        $data = $this->validate($input, true);
        $secret = self::newSecret();
        $klien = KlienApi::query()->create([
            'nama' => $data['nama'],
            'id_klien' => 'phws_'.Str::lower(Str::random(20)),
            'cincangan_rahsia' => self::hash($secret),
            'skop' => array_values($data['skop']),
            'ip_dibenarkan' => array_values($data['ip_dibenarkan'] ?? []),
            'had_kadar_minit' => (int) ($data['had_kadar_minit'] ?? 120),
            'tarikh_luput' => $data['tarikh_luput'] ?? null,
            'status' => 'aktif',
            'dicipta_oleh' => $by->id,
        ]);

        return [$klien, $secret];
    }

    /** @param array<string, mixed> $input */
    public function update(KlienApi $klien, array $input): KlienApi
    {
        $data = $this->validate($input, false);
        $klien->fill(array_intersect_key($data, array_flip(['nama', 'skop', 'ip_dibenarkan', 'had_kadar_minit', 'tarikh_luput'])))->save();

        return $klien;
    }

    /** @return string the new plain secret (shown once) */
    public function rotate(KlienApi $klien): string
    {
        $secret = self::newSecret();
        $klien->forceFill([
            'cincangan_rahsia_lama' => $klien->cincangan_rahsia,
            'rahsia_lama_luput' => now()->addHours((int) config('pws.tempoh_tangguh_putaran_jam', 24)),
            'cincangan_rahsia' => self::hash($secret),
            'tarikh_putaran' => now(),
        ])->save();
        $this->audit->record('putar_kunci', 'pws', 'pws_klien_api', $klien->id);

        return $secret;
    }

    public function revoke(KlienApi $klien, string $reason): void
    {
        DB::transaction(function () use ($klien) {
            $klien->forceFill(['status' => 'dibatalkan', 'cincangan_rahsia_lama' => null, 'rahsia_lama_luput' => null])->save();
            DB::table('pws_token_akses')->where('klien_id', $klien->id)->delete();
        });
        $this->audit->record('batal_kunci', 'pws', 'pws_klien_api', $klien->id, reason: $reason);
    }

    /**
     * Client-credentials grant. Null when the credentials, status, expiry or
     * source IP do not allow it (the caller answers with one generic error).
     *
     * @return array{0: KlienApi, 1: string, 2: int}|null client, token, lifetime in seconds
     */
    public function issueToken(string $clientId, string $secret, ?string $ip): ?array
    {
        $klien = KlienApi::query()->where('id_klien', $clientId)->first();
        if ($klien === null || ! $klien->aktif() || ! $this->ipAllowed($klien, $ip)) {
            return null;
        }
        $hash = self::hash($secret);
        $current = hash_equals($klien->cincangan_rahsia, $hash);
        $previous = $klien->cincangan_rahsia_lama !== null && $klien->rahsia_lama_luput?->isFuture() && hash_equals($klien->cincangan_rahsia_lama, $hash);
        if (! $current && ! $previous) {
            return null;
        }

        $token = Str::random(64);
        $minutes = max(1, (int) config('pws.token_minit', 15));
        DB::table('pws_token_akses')->insert([
            'klien_id' => $klien->id, 'cincangan_token' => hash('sha256', $token), 'tamat_pada' => now()->addMinutes($minutes),
            'ip' => $ip, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('pws_token_akses')->where('tamat_pada', '<', now()->subDay())->delete(); // housekeeping

        return [$klien, $token, $minutes * 60];
    }

    /** Resolve a bearer token to its (still active) client. */
    public function clientForToken(string $token, ?string $ip): ?KlienApi
    {
        $row = DB::table('pws_token_akses')->where('cincangan_token', hash('sha256', $token))->where('tamat_pada', '>', now())->first();
        if ($row === null) {
            return null;
        }
        $klien = KlienApi::query()->find($row->klien_id);
        if ($klien === null || ! $klien->aktif() || ! $this->ipAllowed($klien, $ip)) {
            return null;
        }
        if ($klien->terakhir_digunakan === null || $klien->terakhir_digunakan->lt(now()->subMinute())) {
            DB::table('pws_klien_api')->where('id', $klien->id)->update(['terakhir_digunakan' => now()]);
        }

        return $klien;
    }

    public function ipAllowed(KlienApi $klien, ?string $ip): bool
    {
        $list = array_values(array_filter($klien->ip_dibenarkan ?? []));
        if ($list === []) {
            return true;
        }

        return $ip !== null && IpUtils::checkIp($ip, $list);
    }

    public static function hash(string $secret): string
    {
        return hash_hmac('sha256', $secret, (string) config('app.key'));
    }

    private static function newSecret(): string
    {
        return 'phws_s_'.Str::random(48);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validate(array $input, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return Validator::make($input, [
            'nama' => [$req, 'string', 'max:150'],
            'skop' => [$req, 'array', 'min:1'],
            'skop.*' => ['string', Rule::in(array_keys(KlienApi::SKOP))],
            'ip_dibenarkan' => ['sometimes', 'nullable', 'array', 'max:50'],
            'ip_dibenarkan.*' => ['string', 'max:64', function (string $attr, mixed $value, \Closure $fail) {
                $ip = explode('/', (string) $value, 2);
                if (filter_var($ip[0], FILTER_VALIDATE_IP) === false || (isset($ip[1]) && ! ctype_digit($ip[1]))) {
                    $fail(__('pws::pws.ralat.ip_tidak_sah'));
                }
            }],
            'had_kadar_minit' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'tarikh_luput' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after:today'],
        ], [], ['nama' => 'nama klien', 'skop' => 'skop', 'ip_dibenarkan' => 'senarai IP', 'had_kadar_minit' => 'had kadar', 'tarikh_luput' => 'tarikh luput'])->validate();
    }
}
