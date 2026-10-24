<?php

namespace App\Modules\Pjk\Integration;

use App\Models\User;
use App\Modules\Adm\Contracts\SearchProvider;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Support\Akses;
use App\Modules\Pjk\Support\Kod;
use App\Modules\Rbc\Contracts\AccessControl;

/** Global search (SRS-GEN-05): contracts by number, tenant name, premise or lot number — scoped. */
class KontrakSearchProvider implements SearchProvider
{
    public function __construct(private Akses $akses, private AccessControl $access) {}

    public function entity(): string
    {
        return 'kontrak_pajakan';
    }

    public function search(string $query, User $user, int $limit = 10): array
    {
        $q = trim($query);
        if ($q === '' || ! $this->access->allows($user, 'pjk.kontrak.lihat')) {
            return [];
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';

        return $this->akses->kontrak($user)
            ->where(fn ($w) => $w->where('no_kontrak', 'ilike', $like)->orWhere('penyewa', 'ilike', $like)
                ->orWhereIn('premis_id', fn ($s) => $s->select('id')->from('premis_aset')->where('nama_premis', 'ilike', $like)->orWhere('kod_premis', 'ilike', $like))
                ->orWhereIn('lot_id', fn ($s) => $s->select('id')->from('lot_tanah')->where('no_lot', 'ilike', $like)))
            ->orderByDesc('tarikh_mula')->limit($limit)->get()
            ->map(fn (Kontrak $k) => [
                'id' => $k->id,
                'title' => (string) $k->no_kontrak.' — '.$k->penyewa,
                'subtitle' => (Kod::STATUS_KONTRAK[$k->status] ?? $k->status).' · '.$k->tarikh_mula?->format('d/m/Y').' – '.$k->tarikh_tamat?->format('d/m/Y'),
                'link' => '/admin/pjk/kontrak/'.$k->id,
            ])->values()->all();
    }
}
