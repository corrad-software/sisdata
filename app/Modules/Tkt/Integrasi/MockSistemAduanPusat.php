<?php

namespace App\Modules\Tkt\Integrasi;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Tkt\Contracts\CentralTicketSystem;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Local stand-in for the Sistem Aduan berpusat MAIS (no access yet —
 * PLAN §11 "adapter + mock"). Accepts every push and logs it; parameter
 * tkt.mock_pusat_gagal = true simulates an outage so retry and
 * reconciliation can be exercised.
 */
class MockSistemAduanPusat implements CentralTicketSystem
{
    public function __construct(private Parameters $parameters) {}

    public function pushStatus(string $complaintNumber, array $payload, string $idempotencyKey): void
    {
        if ((bool) $this->parameters->get('tkt.mock_pusat_gagal', false)) {
            throw new RuntimeException('Sistem Aduan berpusat (mock) tidak dapat dihubungi.');
        }
        Log::info('tkt.mock_pusat.push', ['aduan' => $complaintNumber, 'kunci' => $idempotencyKey, 'status' => $payload['status'] ?? null]);
    }
}
