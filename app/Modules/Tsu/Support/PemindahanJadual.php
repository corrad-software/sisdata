<?php

namespace App\Modules\Tsu\Support;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Tsu\Contracts\LotRecordTransfer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Table-level stand-in LotRecordTransfer for a lot-anchored table whose owner
 * (MOD-PJK, MOD-CKI, MOD-AST) has not registered its own handler yet: moves
 * `lot_id` (and the dependent rows listed in $ikut) in one transaction and
 * writes an explicit before/after audit entry under the owner's module code.
 */
class PemindahanJadual implements LotRecordTransfer
{
    /**
     * @param  string  $labelSql  SQL expression for the record label (no Sulit/Rahsia columns)
     * @param  array<string, string>  $ikut  dependent table => FK column pointing at this table's id (moved together)
     */
    public function __construct(
        private string $jenis,
        private string $label,
        private string $table,
        private string $labelSql,
        private string $module,
        private array $ikut = [],
    ) {}

    public function jenis(): string
    {
        return $this->jenis;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function forLot(string $lotId): array
    {
        $q = DB::table($this->table)->where('lot_id', $lotId)->selectRaw('id::text AS id, '.$this->labelSql.' AS label');
        if (DB::getSchemaBuilder()->hasColumn($this->table, 'deleted_at')) {
            $q->whereNull('deleted_at');
        }

        return $q->orderBy('id')->get()->map(fn ($r) => ['id' => (string) $r->id, 'label' => (string) $r->label])->all();
    }

    public function transfer(string $recordId, string $fromLotId, string $toLotId, User $by, string $reason): void
    {
        DB::transaction(function () use ($recordId, $fromLotId, $toLotId, $reason) {
            $moved = DB::table($this->table)->where('id', $recordId)->where('lot_id', $fromLotId)->update(['lot_id' => $toLotId]);
            if ($moved !== 1) {
                throw new InvalidArgumentException("Rekod {$this->jenis} #{$recordId} tiada pada lot asal.");
            }
            foreach ($this->ikut as $table => $fk) {
                DB::table($table)->where($fk, $recordId)->where('lot_id', $fromLotId)->update(['lot_id' => $toLotId]);
            }
            app(AuditLogger::class)->record('pindah_lot', $this->module, $this->table, $recordId, changes: ['lot_id' => [$fromLotId, $toLotId]], meta: ['sumber' => 'tsu.pemindahan_rekod', 'sebab' => $reason]);
        });
    }
}
