<?php

namespace App\Modules\Tsu\Contracts;

/**
 * Registry of LotRecordTransfer handlers (singleton, MOD-TSU). Owning modules
 * register lazily:
 *
 *     $this->callAfterResolving(LotRecordTransfers::class, fn (LotRecordTransfers $r) => $r->register(KontrakTransfer::class));
 *
 * A registered handler replaces MOD-TSU's table-level stand-in of the same jenis.
 */
interface LotRecordTransfers
{
    /** @param class-string<LotRecordTransfer> $handler */
    public function register(string $handler): void;

    /** @return array<string, LotRecordTransfer> keyed by jenis */
    public function all(): array;
}
