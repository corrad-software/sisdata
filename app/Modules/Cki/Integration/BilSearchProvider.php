<?php

namespace App\Modules\Cki\Integration;

use App\Models\User;
use App\Modules\Adm\Contracts\SearchProvider;
use App\Modules\Cki\Models\Cukai;
use App\Modules\Cki\Support\Akses;
use App\Modules\Cki\Support\Kod;
use App\Modules\Cki\Support\Wang;
use App\Modules\Rbc\Contracts\AccessControl;

/** Global search (SRS-GEN-05): tax bills by bill no., tax account no. or lot no. — scoped to the user's district. */
class BilSearchProvider implements SearchProvider
{
    public function __construct(private Akses $akses, private AccessControl $access) {}

    public function entity(): string
    {
        return 'bil_cukai';
    }

    public function search(string $query, User $user, int $limit = 10): array
    {
        $q = trim($query);
        if ($q === '' || ! $this->access->allows($user, 'cki.bil.lihat')) {
            return [];
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';

        return $this->akses->cukai($user)->whereNotNull('cukai.obligasi_id')
            ->where(fn ($w) => $w->where('cukai.no_bil', 'ilike', $like)
                ->orWhereIn('cukai.obligasi_id', fn ($s) => $s->select('id')->from('obligasi_cukai')->where('no_akaun_cukai', 'ilike', $like))
                ->orWhereIn('cukai.lot_id', fn ($s) => $s->select('id')->from('lot_tanah')->where('no_lot', 'ilike', $like)))
            ->orderByDesc('cukai.tahun_taksiran')->limit($limit)->get()
            ->map(fn (Cukai $c) => [
                'id' => $c->id,
                'title' => (string) $c->no_bil.' — '.(Kod::JENIS_CUKAI[$c->jenis] ?? $c->jenis).' '.$c->tahun_taksiran,
                'subtitle' => Wang::rm($c->jumlahPerlu()).' · '.(Kod::STATUS_BAYARAN[$c->status_bayaran] ?? $c->status_bayaran).' · '.$c->kod_daerah,
                'link' => '/admin/cki/bil/'.$c->id,
            ])->values()->all();
    }
}
