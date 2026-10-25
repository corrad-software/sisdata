<?php

namespace App\Modules\Tkt\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Tkt\Models\Tiket;
use App\Modules\Tkt\Support\Lalai;
use Illuminate\Database\Eloquent\Builder;

/**
 * Duplicate detection: open tickets about the same module whose title is
 * trigram-similar (pg_trgm) within the last N days. Before submitting, a
 * reporter only sees their OWN similar tickets (others' titles would leak);
 * support staff see the suggested duplicate on the ticket.
 */
class Pendua
{
    public function __construct(private Parameters $parameters) {}

    /**
     * @return list<array{id: string, nombor_rujukan: string, tajuk: string, status: string, skor: float}>
     */
    public function cari(string $tajuk, ?string $modul, ?User $hanyaPelapor = null, ?string $kecuali = null, int $limit = 3): array
    {
        $tajuk = mb_strtolower(trim($tajuk));
        if (mb_strlen($tajuk) < 6) {
            return [];
        }
        $threshold = max(0.1, min(1.0, (float) $this->parameters->get('tkt.ambang_pendua', 0.45)));
        $days = max(1, (int) $this->parameters->get('tkt.tempoh_pendua_hari', 30));

        return Tiket::query()
            ->selectRaw('tkt_tiket.id, nombor_rujukan, tajuk, status, similarity(lower(tajuk), ?) as skor', [$tajuk])
            ->whereIn('status', [...Lalai::TERBUKA, 'selesai'])
            ->where('tarikh_buka', '>=', now()->subDays($days))
            ->when($modul !== null, fn (Builder $q) => $q->where('modul', $modul))
            ->when($hanyaPelapor !== null, fn (Builder $q) => $q->where('id_pelapor', $hanyaPelapor->id))
            ->when($kecuali !== null, fn (Builder $q) => $q->where('tkt_tiket.id', '!=', $kecuali))
            ->whereRaw('similarity(lower(tajuk), ?) >= ?', [$tajuk, $threshold])
            ->orderByDesc('skor')->limit($limit)->get()
            ->map(fn (Tiket $t) => [
                'id' => $t->id,
                'nombor_rujukan' => $t->nombor_rujukan,
                'tajuk' => $t->tajuk,
                'status' => $t->status,
                'skor' => round((float) $t->getAttribute('skor'), 3),
            ])->values()->all();
    }
}
