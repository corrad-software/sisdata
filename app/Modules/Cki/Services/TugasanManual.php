<?php

namespace App\Modules\Cki\Services;

use App\Models\User;
use App\Modules\Cki\Models\Obligasi;
use App\Modules\Cki\Support\Akses;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aliran proses langkah 3 / SRS-CKI-07/18: the manual-recording task list —
 * active obligations outside API coverage, or whose agency is in fallback
 * mode, that have no bill yet for the assessment year.
 */
class TugasanManual
{
    public function __construct(private Akses $akses) {}

    /** @return Builder<Obligasi> */
    public function query(int $tahun, ?User $user = null): Builder
    {
        $q = $user ? $this->akses->obligasi($user) : Obligasi::query()->withoutGlobalScope('access');

        return $q->where('obligasi_cukai.aktif', true)
            ->where(fn ($w) => $w->where('obligasi_cukai.status_liputan_api', 'luar')
                ->orWhereIn('obligasi_cukai.kod_agensi', fn ($s) => $s->select('kod_agensi')->from('daftar_liputan_integrasi')->where('mod_semasa', 'sandaran')))
            ->whereNotExists(fn ($s) => $s->selectRaw('1')->from('cukai')->whereColumn('cukai.obligasi_id', 'obligasi_cukai.id')
                ->where('cukai.tahun_taksiran', $tahun)->whereNull('cukai.deleted_at'));
    }

    /** @return array<string, int> kod_daerah => count */
    public function ringkasanDaerah(int $tahun): array
    {
        return $this->query($tahun)->groupBy('obligasi_cukai.kod_daerah')->selectRaw('obligasi_cukai.kod_daerah, count(*) as n')
            ->pluck('n', 'kod_daerah')->map(fn ($n) => (int) $n)->all();
    }
}
