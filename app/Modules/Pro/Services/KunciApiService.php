<?php

namespace App\Modules\Pro\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Pro\Models\KunciApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Scoped API keys for the profile REST API (SRS-PRO-29): Sanctum personal
 * access tokens carrying `pro:api` + the granted scopes, with expiry, a per-key
 * rate limit, the calling system's name, and immediate revocation. A key acts
 * as its owner, so the owner's RBC scope and classification still apply.
 */
class KunciApiService
{
    public const BASE_ABILITY = 'pro:api';

    public const SCOPES = [
        'pro:lot' => 'Senarai dan butiran lot',
        'pro:geojson' => 'Geometri GeoJSON',
        'pro:kewangan' => 'Kedudukan kewangan',
    ];

    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  list<string>  $scopes
     * @return array{id: int, token: string, tamat_pada: string|null}
     */
    public function create(User $owner, string $name, string $system, array $scopes, int $days, int $ratePerMinute): array
    {
        $invalid = array_diff($scopes, array_keys(self::SCOPES));
        if ($scopes === [] || $invalid !== []) {
            throw ValidationException::withMessages(['skop' => [__('pro::pro.api.skop_tidak_sah')]]);
        }

        return DB::transaction(function () use ($owner, $name, $system, $scopes, $days, $ratePerMinute) {
            $expires = now()->addDays($days);
            $token = $owner->createToken('pro:'.$name, [self::BASE_ABILITY, ...$scopes], $expires);
            KunciApi::query()->create([
                'id_token' => $token->accessToken->getKey(),
                'id_pemilik' => $owner->id,
                'sistem_pemanggil' => $system,
                'skop' => $scopes,
                'had_kadar_seminit' => $ratePerMinute,
                'tamat_pada' => $expires,
            ]);
            $this->audit->record('cipta_kunci_api', 'pro', 'kunci_api_pro', (int) $token->accessToken->getKey(), meta: ['sistem_pemanggil' => $system, 'skop' => $scopes, 'klasifikasi' => 'sulit']);

            return ['id' => (int) $token->accessToken->getKey(), 'token' => $token->plainTextToken, 'tamat_pada' => $expires->toIso8601String()];
        });
    }

    public function revoke(int $tokenId, User $by): void
    {
        $key = KunciApi::query()->where('id_token', $tokenId)->first();
        if ($key === null) {
            throw ValidationException::withMessages(['id' => [__('pro::pro.api.tiada')]]);
        }
        DB::transaction(function () use ($key, $tokenId, $by) {
            PersonalAccessToken::query()->whereKey($tokenId)->delete();
            $key->update(['dibatalkan_pada' => now(), 'dibatalkan_oleh' => $by->id]);
        });
        $this->audit->record('batal_kunci_api', 'pro', 'kunci_api_pro', $tokenId, meta: ['klasifikasi' => 'sulit']);
    }

    /** @return list<array<string, mixed>> */
    public function list(?User $owner = null): array
    {
        return DB::table('kunci_api_pro as k')
            ->leftJoin('personal_access_tokens as t', 't.id', '=', 'k.id_token')
            ->leftJoin('users as u', 'u.id', '=', 'k.id_pemilik')
            ->when($owner !== null, fn ($q) => $q->where('k.id_pemilik', $owner->id))
            ->orderByDesc('k.id')
            ->get(['k.id_token', 'k.sistem_pemanggil', 'k.skop', 'k.had_kadar_seminit', 'k.tamat_pada', 'k.dibatalkan_pada', 'k.created_at', 't.name', 't.last_used_at', 'u.name as pemilik',
                DB::raw('(SELECT count(*) FROM log_penggunaan_api l WHERE l.id_token = k.id_token) AS bil_panggilan')])
            ->map(fn ($r) => [
                'id' => (int) $r->id_token,
                'nama' => $r->name ? substr((string) $r->name, 4) : null,
                'sistem_pemanggil' => $r->sistem_pemanggil,
                'skop' => json_decode((string) $r->skop, true),
                'had_kadar_seminit' => (int) $r->had_kadar_seminit,
                'tamat_pada' => $r->tamat_pada,
                'dibatalkan_pada' => $r->dibatalkan_pada,
                'terakhir_digunakan' => $r->last_used_at,
                'pemilik' => $r->pemilik,
                'bil_panggilan' => (int) $r->bil_panggilan,
                'status' => $r->dibatalkan_pada ? 'dibatalkan' : ($r->tamat_pada && $r->tamat_pada < now() ? 'tamat' : 'aktif'),
                'dicipta_pada' => $r->created_at,
            ])->all();
    }

    /**
     * SRS-PRO laporan "penggunaan kunci API mengikut kunci, sistem pemanggil dan tempoh".
     *
     * @return list<array<string, mixed>>
     */
    public function usage(?string $from = null, ?string $to = null): array
    {
        return DB::table('log_penggunaan_api')
            ->when($from, fn ($q) => $q->where('tarikh_masa', '>=', $from))
            ->when($to, fn ($q) => $q->where('tarikh_masa', '<=', $to.' 23:59:59'))
            ->groupBy('id_token', 'sistem_pemanggil')
            ->orderBy('id_token')
            ->get(['id_token', 'sistem_pemanggil', DB::raw('count(*) AS bil_panggilan'), DB::raw('sum(CASE WHEN kod_status >= 400 THEN 1 ELSE 0 END) AS bil_gagal'), DB::raw('max(tarikh_masa) AS terakhir')])
            ->map(fn ($r) => (array) $r)->all();
    }
}
