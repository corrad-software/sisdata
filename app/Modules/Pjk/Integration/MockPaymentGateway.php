<?php

namespace App\Modules\Pjk\Integration;

use App\Modules\Adm\Contracts\Secrets;
use App\Modules\Pjk\Contracts\GatewayException;
use App\Modules\Pjk\Contracts\InvalidGatewaySignature;
use App\Modules\Pjk\Contracts\PaymentGateway;
use App\Modules\Pjk\Support\Wang;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Local stand-in for the MAIS payment gateway (FPX + card), used until the
 * live adapter exists. State lives in the cache. It can simulate success,
 * failure, cancellation, a missing callback (resolved only by the status
 * query), a never-resolving transaction (timeout) and an outage.
 *
 * Callbacks are signed exactly like a real gateway would:
 * header `X-Pjk-Signature: t=<unix>,v1=<hex hmac-sha256("t.body")>`.
 */
class MockPaymentGateway implements PaymentGateway
{
    public const HEADER = 'x-pjk-signature';

    public const TOLERANCE_SECONDS = 300;

    private const PREFIX = 'pjk:mockgw:';

    public function code(): string
    {
        return 'mock';
    }

    public function channels(): array
    {
        return ['fpx', 'kad'];
    }

    public function initiate(array $payment): array
    {
        $this->assertUp();
        $ref = 'MOCK-'.strtoupper(Str::random(12));
        $today = CarbonImmutable::today()->format('Y-m-d');
        Cache::put(self::PREFIX.'tx:'.$payment['no_rujukan'], [
            'no_rujukan' => $payment['no_rujukan'],
            'rujukan_gateway' => $ref,
            'amaun' => Wang::norm($payment['amaun']),
            'amaun_penyelesaian' => Wang::norm($payment['amaun']),
            'saluran' => $payment['saluran'],
            'status' => 'menunggu',
            'tarikh' => $today,
        ], now()->addDays(40));
        $index = Cache::get(self::PREFIX.'hari:'.$today, []);
        $index[] = $payment['no_rujukan'];
        Cache::put(self::PREFIX.'hari:'.$today, array_values(array_unique($index)), now()->addDays(40));

        return ['rujukan_gateway' => $ref, 'url_bayaran' => '/admin/pjk/gateway-ujian/'.rawurlencode($payment['no_rujukan'])];
    }

    /**
     * Simulate the payer finishing on the gateway page.
     *
     * `$known` (no_rujukan, rujukan_gateway, amaun, saluran, tarikh) restores
     * the mock's state when the cache no longer holds it — e.g. a per-request
     * `array` cache store (UAT) or an evicted entry — so the test page keeps
     * working; the signed callback is unchanged.
     *
     * @param  string  $outcome  berjaya | gagal | dibatalkan | tiada_panggilan_balik | tamat_masa
     * @param  array<string, string>|null  $known
     * @return array{body: string, headers: array<string, string>}|null the signed callback, or null when none is sent
     */
    public function simulate(string $noRujukan, string $outcome, ?string $settledAmount = null, ?array $known = null): ?array
    {
        $tx = Cache::get(self::PREFIX.'tx:'.$noRujukan);
        if ($tx === null && $known !== null && ($known['no_rujukan'] ?? null) === $noRujukan && isset($known['rujukan_gateway'], $known['amaun'])) {
            $tx = ['status' => 'menunggu', 'amaun' => Wang::norm($known['amaun'])] + $known + ['amaun_penyelesaian' => Wang::norm($known['amaun']), 'tarikh' => CarbonImmutable::today()->format('Y-m-d')];
            $index = Cache::get(self::PREFIX.'hari:'.$tx['tarikh'], []);
            $index[] = $noRujukan;
            Cache::put(self::PREFIX.'hari:'.$tx['tarikh'], array_values(array_unique($index)), now()->addDays(40));
        }
        if ($tx === null) {
            return null;
        }
        $tx['status'] = match ($outcome) {
            'berjaya', 'tiada_panggilan_balik' => 'berjaya',
            'gagal' => 'gagal',
            'dibatalkan' => 'dibatalkan',
            default => 'menunggu',
        };
        $tx['kod_respons'] = match ($tx['status']) {
            'berjaya' => '00',
            'gagal' => '51',
            'dibatalkan' => 'C1',
            default => null,
        };
        if ($settledAmount !== null) {
            $tx['amaun_penyelesaian'] = Wang::norm($settledAmount);
        }
        Cache::put(self::PREFIX.'tx:'.$noRujukan, $tx, now()->addDays(40));

        if (in_array($outcome, ['tiada_panggilan_balik', 'tamat_masa'], true)) {
            return null;
        }

        return $this->callback($tx);
    }

    /**
     * @param  array<string, mixed>  $tx
     * @return array{body: string, headers: array<string, string>}
     */
    public function callback(array $tx, ?int $timestamp = null, ?string $eventId = null): array
    {
        $body = (string) json_encode([
            'id_acara' => $eventId ?? 'EVT-'.Str::upper(Str::random(16)),
            'no_rujukan' => $tx['no_rujukan'],
            'status' => $tx['status'],
            'amaun' => $tx['amaun'],
            'rujukan_gateway' => $tx['rujukan_gateway'],
            'kod_respons' => $tx['kod_respons'] ?? null,
        ]);

        return ['body' => $body, 'headers' => [self::HEADER => $this->sign($body, $timestamp ?? time())]];
    }

    public function sign(string $body, int $timestamp): string
    {
        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $this->secret());
    }

    public function parseCallback(string $rawBody, array $headers): array
    {
        $header = $headers[self::HEADER] ?? '';
        if (! preg_match('/^t=(\d+),v1=([0-9a-f]{64})$/', $header, $m)) {
            throw new InvalidGatewaySignature('Tandatangan tiada atau rosak.');
        }
        if (abs(time() - (int) $m[1]) > self::TOLERANCE_SECONDS) {
            throw new InvalidGatewaySignature('Tandatangan di luar tempoh sah.');
        }
        if (! hash_equals(hash_hmac('sha256', $m[1].'.'.$rawBody, $this->secret()), $m[2])) {
            throw new InvalidGatewaySignature('Tandatangan tidak sah.');
        }
        $data = json_decode($rawBody, true);
        if (! is_array($data) || ! isset($data['no_rujukan'], $data['status'], $data['amaun'])) {
            throw new InvalidGatewaySignature('Muatan tidak lengkap.');
        }

        return [
            'id_acara' => (string) ($data['id_acara'] ?? hash('sha256', $rawBody)),
            'no_rujukan' => (string) $data['no_rujukan'],
            'status' => (string) $data['status'],
            'amaun' => Wang::norm((string) $data['amaun']),
            'rujukan_gateway' => isset($data['rujukan_gateway']) ? (string) $data['rujukan_gateway'] : null,
            'kod_respons' => isset($data['kod_respons']) ? (string) $data['kod_respons'] : null,
        ];
    }

    public function query(string $noRujukan): ?array
    {
        $this->assertUp();
        $tx = Cache::get(self::PREFIX.'tx:'.$noRujukan);

        return $tx === null ? null : [
            'status' => $tx['status'],
            'amaun' => $tx['amaun'],
            'rujukan_gateway' => $tx['rujukan_gateway'],
            'kod_respons' => $tx['kod_respons'] ?? null,
        ];
    }

    public function settlement(CarbonImmutable $date): array
    {
        $this->assertUp();
        $out = [];
        foreach (Cache::get(self::PREFIX.'hari:'.$date->format('Y-m-d'), []) as $ref) {
            $tx = Cache::get(self::PREFIX.'tx:'.$ref);
            if ($tx !== null && $tx['status'] === 'berjaya') {
                $out[] = ['rujukan' => $tx['no_rujukan'], 'rujukan_gateway' => $tx['rujukan_gateway'], 'amaun' => $tx['amaun_penyelesaian'], 'status' => 'berjaya', 'tarikh' => $tx['tarikh']];
            }
        }

        return $out;
    }

    /** Simulate an outage (initiate/query/settlement throw) — SRS-INT-10 manual fallback testing. */
    public function setDown(bool $down): void
    {
        $down ? Cache::put(self::PREFIX.'down', true, now()->addHour()) : Cache::forget(self::PREFIX.'down');
    }

    private function assertUp(): void
    {
        if (Cache::get(self::PREFIX.'down')) {
            throw new GatewayException('Payment gateway tidak dapat dihubungi (simulasi).');
        }
    }

    private function secret(): string
    {
        $secrets = app()->bound(Secrets::class) ? app(Secrets::class) : null;
        $stored = $secrets?->get('pjk.gateway_rahsia');

        return $stored !== null && $stored !== '' ? $stored : hash_hmac('sha256', 'pjk-mock-gateway', (string) config('app.key'));
    }
}
