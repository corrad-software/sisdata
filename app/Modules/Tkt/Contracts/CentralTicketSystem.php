<?php

namespace App\Modules\Tkt\Contracts;

/**
 * SRS-TKT-14..16 / SRS-INT-11: outbound adapter to the Sistem Aduan
 * berpusat MAIS for tickets that came from it (status + resolution pushed
 * back). MAIS has not given access yet, so MOD-TKT binds
 * Integrasi\MockSistemAduanPusat; a live adapter (through the API Gateway)
 * replaces the binding without touching the rest of the module.
 */
interface CentralTicketSystem
{
    /**
     * Push the current status (already mapped to the central system's
     * vocabulary and field names) of one complaint. The idempotency key is
     * stable for one change, so a retried push is not applied twice.
     *
     * @param  array<string, scalar|null>  $payload
     *
     * @throws \RuntimeException when the remote system rejects or is unreachable (the outbox retries)
     */
    public function pushStatus(string $complaintNumber, array $payload, string $idempotencyKey): void;
}
