<?php

namespace Tests\Modules\Rbc\Fixtures;

use App\Modules\Rbc\Contracts\Concerns\ScopedByAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only stand-in for a scoped domain record (e.g. MOD-PRO's lot_tanah)
 * to prove the ScopedByAccess trait (SRS-RBC-07).
 *
 * @property int $id
 * @property string $no_lot
 * @property string $kod_daerah
 * @property string $klasifikasi
 */
class LotUjian extends Model
{
    use ScopedByAccess;

    protected $table = 'rbc_ujian_lot';

    protected $guarded = [];

    /** @var array<string, string> */
    protected array $accessScopes = ['daerah' => 'kod_daerah', 'klasifikasi' => 'klasifikasi'];

    public static function createTable(): void
    {
        Schema::create('rbc_ujian_lot', function (Blueprint $table) {
            $table->id();
            $table->string('no_lot');
            $table->string('kod_daerah');
            $table->string('klasifikasi')->default('dalaman');
            $table->timestamps();
        });
    }
}
