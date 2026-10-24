<?php

namespace App\Modules\Kes\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\SearchProvider;
use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Kes\Support\Akses;

/** SRS-GEN-05 global search: cases by internal reference, court number or title — legal users with MFA only. */
class KesSearchProvider implements SearchProvider
{
    public function __construct(private Akses $akses, private Rujukan $ref, private LogCapaianKes $log) {}

    public function entity(): string
    {
        return 'kes_mahkamah';
    }

    public function search(string $query, User $user, int $limit = 10): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2 || ! $this->akses->bolehCapai($user)) {
            return [];
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%';
        $rows = KesMahkamah::query()->bolehDilihat($user)
            ->where(fn ($q) => $q->where('kes_mahkamah.no_rujukan', 'ilike', $like)->orWhere('kes_mahkamah.no_kes', 'ilike', $like)->orWhere('kes_mahkamah.tajuk', 'ilike', $like))
            ->orderByDesc('kes_mahkamah.created_at')->limit($limit)->get();
        if ($rows->isNotEmpty()) {
            $this->log->rekod('lihat_senarai', null, null, 'Carian global: '.$rows->count().' kes', ['kes' => $rows->pluck('id')->all()], $user);
        }

        return $rows->map(fn (KesMahkamah $k) => [
            'id' => $k->id,
            'title' => $k->no_rujukan.' — '.($k->tajuk ?? $k->no_kes),
            'subtitle' => trim(($this->ref->namaJenis($k->jenis) ?? '').' · '.$this->ref->namaStatus($k->status), ' ·'),
            'link' => '/admin/kes/kes/'.$k->id,
        ])->values()->all();
    }
}
