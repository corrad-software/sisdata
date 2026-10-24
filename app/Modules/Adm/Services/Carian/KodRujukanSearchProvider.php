<?php

namespace App\Modules\Adm\Services\Carian;

use App\Models\User;
use App\Modules\Adm\Contracts\DeepLinkProvider;
use App\Modules\Adm\Contracts\SearchProvider;
use App\Modules\Adm\Contracts\SearchSettings;
use App\Modules\Adm\Models\KodRujukan;
use App\Modules\Rbc\Contracts\AccessControl;

/** ADM's own entry in global search: reference codes, for holders of adm.kod_rujukan.lihat. */
class KodRujukanSearchProvider implements DeepLinkProvider, SearchProvider
{
    public const FIELDS = ['kod', 'keterangan', 'jenis_kod'];

    public function __construct(private AccessControl $access, private SearchSettings $settings) {}

    public function entity(): string
    {
        return 'kod_rujukan';
    }

    public function search(string $query, User $user, int $limit = 10): array
    {
        if (! $this->access->allows($user, 'adm.kod_rujukan.lihat')) {
            return [];
        }

        $fields = array_values(array_intersect($this->settings->fields($this->entity()) ?: ['kod', 'keterangan'], self::FIELDS));
        $like = '%'.addcslashes($query, '%_\\').'%';

        return KodRujukan::query()
            ->where(function ($q) use ($fields, $like) {
                foreach ($fields as $field) {
                    $q->orWhere($field, 'ilike', $like);
                }
            })
            ->orderBy('jenis_kod')->orderBy('susunan')
            ->limit($limit)->get()
            ->map(fn (KodRujukan $k) => $this->hit($k))
            ->all();
    }

    public function link(string $id, User $user): ?string
    {
        if (! ctype_digit($id) || ! $this->access->allows($user, 'adm.kod_rujukan.lihat')) {
            return null;
        }

        $kod = KodRujukan::query()->find((int) $id);

        return $kod ? $this->hit($kod)['link'] : null;
    }

    /** @return array{id: string, title: string, subtitle: string, link: string} */
    private function hit(KodRujukan $k): array
    {
        return [
            'id' => (string) $k->id,
            'title' => $k->kod.' — '.$k->keterangan,
            'subtitle' => $k->jenis_kod.($k->status !== KodRujukan::AKTIF ? ' · '.__('adm::adm.kod.inactive') : ''),
            'link' => '/admin/pentadbiran/kod-rujukan?jenis='.rawurlencode($k->jenis_kod).'&q='.rawurlencode($k->kod),
        ];
    }
}
