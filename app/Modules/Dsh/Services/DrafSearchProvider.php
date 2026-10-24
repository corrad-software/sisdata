<?php

namespace App\Modules\Dsh\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\SearchProvider;
use App\Modules\Dsh\Models\DrafTransaksi;

/** The user's own transaction drafts in global search (SRS-DSH-22/24) — never anyone else's. */
class DrafSearchProvider implements SearchProvider
{
    public function entity(): string
    {
        return 'dsh_draf_transaksi';
    }

    public function search(string $query, User $user, int $limit = 10): array
    {
        $q = trim($query);
        if ($q === '') {
            return [];
        }

        return DrafTransaksi::query()->where('id_pengguna', $user->id)
            ->where(fn ($w) => $w->where('tajuk', 'ilike', '%'.addcslashes($q, '%_\\').'%')->orWhere('jenis', 'ilike', '%'.addcslashes($q, '%_\\').'%'))
            ->orderByDesc('tarikh_simpan')->limit($limit)->get()
            ->map(fn (DrafTransaksi $d) => [
                'id' => $d->id,
                'title' => $d->tajuk,
                'subtitle' => strtoupper($d->modul).' · '.__('dsh::dsh.draf_disimpan', ['masa' => $d->tarikh_simpan->format('d/m/Y H:i')]),
                'link' => '/admin/dsh/inbox?tab=draf&draf='.$d->id,
            ])->values()->all();
    }
}
