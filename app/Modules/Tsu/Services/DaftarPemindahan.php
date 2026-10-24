<?php

namespace App\Modules\Tsu\Services;

use App\Modules\Tsu\Contracts\LotRecordTransfer;
use App\Modules\Tsu\Contracts\LotRecordTransfers;
use App\Modules\Tsu\Support\PemindahanJadual;
use Illuminate\Support\Facades\Schema;

/**
 * LotRecordTransfers registry (SRS-TSU-20). Handlers registered by the
 * owning modules win; for the satellite tables that exist but have no
 * registered handler, a table-level stand-in (PemindahanJadual) is used.
 */
class DaftarPemindahan implements LotRecordTransfers
{
    /** @var list<class-string<LotRecordTransfer>> */
    private array $classes = [];

    /** @var array<string, LotRecordTransfer>|null */
    private ?array $resolved = null;

    public function register(string $handler): void
    {
        $this->classes[] = $handler;
        $this->resolved = null;
    }

    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }
        $out = [];
        foreach ($this->standIns() as $h) {
            $out[$h->jenis()] = $h;
        }
        foreach ($this->classes as $class) {
            $h = app($class);
            $out[$h->jenis()] = $h;
        }

        return $this->resolved = $out;
    }

    /** @return list<LotRecordTransfer> */
    private function standIns(): array
    {
        $defs = [
            ['kontrak_pajakan', 'Kontrak pajakan / sewaan', 'kontrak_pajakan', "COALESCE(no_kontrak, 'Kontrak ' || left(id::text, 8)) || ' (' || status || ')'", 'pjk', []],
            ['premis_aset', 'Premis / aset sewaan', 'premis_aset', "kod_premis || ' — ' || nama_premis", 'pjk', []],
            ['obligasi_cukai', 'Akaun cukai (tanah / petak / taksiran)', 'obligasi_cukai', "upper(jenis_cukai) || ' ' || no_akaun_cukai", 'cki', ['cukai' => 'obligasi_id']],
            ['aset_fizikal', 'Aset fizikal', 'aset_fizikal', "'Aset ' || left(id::text, 8)", 'ast', []],
        ];
        $out = [];
        foreach ($defs as [$jenis, $label, $table, $sql, $module, $ikut]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'lot_id')) {
                continue;
            }
            if ($table === 'aset_fizikal') {
                foreach (['no_aset', 'kod_aset', 'no_siri'] as $col) {
                    if (Schema::hasColumn($table, $col)) {
                        $sql = "COALESCE({$col}::text, 'Aset ' || left(id::text, 8))";
                        break;
                    }
                }
            }
            $ikut = array_filter($ikut, fn ($fk, $t) => Schema::hasTable($t) && Schema::hasColumn($t, $fk) && Schema::hasColumn($t, 'lot_id'), ARRAY_FILTER_USE_BOTH);
            $out[] = new PemindahanJadual($jenis, $label, $table, $sql, $module, $ikut);
        }

        return $out;
    }
}
