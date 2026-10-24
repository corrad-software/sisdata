<?php

namespace App\Modules\Ptn\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\DeepLinkProvider;
use App\Modules\Adm\Contracts\SearchProvider;
use App\Modules\Ptn\Models\IsuPemantauan;
use App\Modules\Ptn\Models\RekodLawatan;
use App\Modules\Ptn\Support\Akses;

/** Global search (SRS-GEN-05): visit and issue reference numbers, scoped by access. */
class LawatanSearchProvider implements DeepLinkProvider, SearchProvider
{
    public function __construct(private Akses $akses) {}

    public function entity(): string
    {
        return 'pemantauan_tanah';
    }

    public function search(string $query, User $user, int $limit = 10): array
    {
        $q = trim($query);
        if (mb_strlen($q) < 3 || (! $user->can('ptn.lawatan.lihat') && ! $user->can('ptn.isu.lihat'))) {
            return [];
        }
        $out = [];
        if ($user->can('ptn.lawatan.lihat')) {
            foreach (RekodLawatan::query()->untuk($user)->where('no_rujukan', 'ilike', '%'.$q.'%')->orderByDesc('tarikh_lawatan')->limit($limit)->get() as $v) {
                $out[] = ['id' => $v->id, 'title' => 'Lawatan '.$v->no_rujukan, 'subtitle' => $v->tarikh_lawatan->format('d/m/Y').' · '.$v->status_lawatan, 'link' => '/admin/ptn/lawatan/'.$v->id];
            }
        }
        if ($user->can('ptn.isu.lihat')) {
            foreach (IsuPemantauan::query()->untuk($user)->where(fn ($w) => $w->where('no_rujukan', 'ilike', '%'.$q.'%')->orWhere('keterangan', 'ilike', '%'.$q.'%'))->orderByDesc('tarikh_dibuka')->limit($limit)->get() as $i) {
                $out[] = ['id' => $i->id, 'title' => 'Isu '.$i->no_rujukan, 'subtitle' => mb_substr($i->keterangan, 0, 80), 'link' => '/admin/ptn/isu/'.$i->id];
            }
        }

        return array_slice($out, 0, $limit);
    }

    public function link(string $id, User $user): ?string
    {
        $v = RekodLawatan::query()->untuk($user)->find($id);
        if ($v !== null && $this->akses->bolehLihatLawatan($user, $v)) {
            return '/admin/ptn/lawatan/'.$v->id;
        }
        $i = $user->can('ptn.isu.lihat') ? IsuPemantauan::query()->untuk($user)->find($id) : null;

        return $i ? '/admin/ptn/isu/'.$i->id : null;
    }
}
