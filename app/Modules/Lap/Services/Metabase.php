<?php

namespace App\Modules\Lap\Services;

use App\Models\User;
use App\Modules\Lap\Models\PapanBi;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

/**
 * Metabase OSS static ("signed") embedding (SRS-LAP-01, SRS-RBC-08).
 *
 * Metabase OSS has no per-user SSO / sandboxing (SAML/JWT SSO and data
 * sandboxes are paid Pro/Enterprise features), so per-user scope is carried
 * as LOCKED parameters inside a JWT signed with the embedding secret: the
 * browser cannot change them, and Metabase itself only reads aggregate
 * `laporan.bi_*` views through the read-only `sisdata_bi` role. An empty
 * locked value means "all" in Metabase, so a user whose scope is EMPTY is
 * refused here rather than embedded.
 */
class Metabase
{
    public function __construct(private AccessControl $access) {}

    public function configured(): bool
    {
        return is_string(config('lap.metabase.secret')) && strlen((string) config('lap.metabase.secret')) >= 32;
    }

    /** @return array{url: string, tamat: int, parameter: array<string, list<string>>} */
    public function embed(User $user, PapanBi $papan): array
    {
        if (! $this->configured()) {
            throw new ServiceUnavailableHttpException(null, __('lap::lap.bi.tidak_dikonfigurasi'));
        }
        $params = [];
        foreach ((array) $papan->parameter_kunci as $name => $type) {
            $scope = $this->access->scope($user, (string) $type);
            if ($scope === []) {
                throw new AccessDeniedHttpException(__('lap::lap.bi.skop_kosong'));
            }
            $params[(string) $name] = $scope ?? [];
        }
        $exp = time() + 60 * max(1, (int) config('lap.metabase.minit_luput', 10));
        $resource = $papan->jenis === 'question' ? 'question' : 'dashboard';
        $token = self::jwt(['resource' => [$resource => $papan->id_metabase], 'params' => (object) $params, 'exp' => $exp], (string) config('lap.metabase.secret'));

        return [
            'url' => rtrim((string) config('lap.metabase.url'), '/')."/embed/{$resource}/{$token}#bordered=false&titled=true",
            'tamat' => $exp,
            'parameter' => $params,
        ];
    }

    /** HS256 JWT (no library needed). */
    public static function jwt(array $payload, string $secret): string
    {
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $head = $b64((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = $b64((string) json_encode($payload, JSON_UNESCAPED_SLASHES));

        return "{$head}.{$body}.".$b64(hash_hmac('sha256', "{$head}.{$body}", $secret, true));
    }

    /** @return array{dikonfigurasi: bool, hidup: bool, versi: string|null, url: string} */
    public function status(): array
    {
        $url = rtrim((string) config('lap.metabase.url'), '/');
        $alive = false;
        $version = null;
        try {
            $alive = Http::timeout(2)->get($url.'/api/health')->successful();
            if ($alive) {
                $version = Http::timeout(2)->get($url.'/api/session/properties')->json('version.tag');
            }
        } catch (Throwable) {
            $alive = false;
        }

        return ['dikonfigurasi' => $this->configured(), 'hidup' => $alive, 'versi' => is_string($version) ? $version : null, 'url' => $url];
    }
}
