<?php

namespace App\Modules\Adu\Profile;

use App\Models\User;
use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Support\Lalai;
use App\Modules\Adu\Support\Rujukan;
use App\Modules\Pro\Contracts\ProfileSection;

/**
 * SRS-ADU-15: complaint history of a lot on the Profil Hartanah 360° (risiko
 * tab, key "aduan" as expected by MOD-PRO) plus timeline events. Scoped by
 * the viewer's access through Aduan's ScopedByAccess; reporter identity is
 * never shown here.
 */
class AduanSection implements ProfileSection
{
    public function __construct(private Rujukan $ref) {}

    public function key(): string
    {
        return 'aduan';
    }

    public function tab(): string
    {
        return 'risiko';
    }

    public function label(): string
    {
        return (string) __('adu::adu.profil.label');
    }

    public function forLot(string $lotId, User $user): ?array
    {
        if (! $user->can('adu.aduan.lihat')) {
            return null;
        }
        $rows = Aduan::query()->bolehDilihat($user)->where('lot_id', $lotId)->orderByDesc('tarikh_terima')->limit(50)->get();
        $open = $rows->filter(fn (Aduan $a) => $a->terbuka());
        $flags = [];
        if ($open->isNotEmpty()) {
            $flags[] = ['level' => $open->contains(fn (Aduan $a) => in_array($a->tahap_keutamaan, ['tinggi', 'kritikal'], true)) ? 'danger' : 'warning', 'label' => (string) __('adu::adu.profil.bendera_terbuka', ['n' => $open->count()])];
        }
        if ($rows->count() >= 3) {
            $flags[] = ['level' => 'info', 'label' => (string) __('adu::adu.profil.bendera_berulang', ['n' => $rows->count()])];
        }

        return [
            'items' => $rows->map(fn (Aduan $a) => [
                'id' => $a->id,
                'no_rujukan' => $a->no_rujukan_aduan,
                'tarikh' => $a->tarikh_terima?->toDateString(),
                'kategori' => $this->ref->namaKategori($a->kategori),
                'status' => Lalai::STATUS[$a->status] ?? $a->status,
                'keutamaan' => Lalai::KEUTAMAAN[$a->tahap_keutamaan] ?? $a->tahap_keutamaan,
                'terbuka' => $a->terbuka(),
                'link' => '/admin/adu/aduan/'.$a->id,
            ])->values()->all(),
            'flags' => $flags,
            'link' => '/admin/adu/aduan?lot_id='.$lotId,
        ];
    }

    public function timeline(string $lotId, User $user): array
    {
        if (! $user->can('adu.aduan.lihat')) {
            return [];
        }
        $events = [];
        foreach (Aduan::query()->bolehDilihat($user)->where('lot_id', $lotId)->orderByDesc('tarikh_terima')->limit(50)->get() as $a) {
            $events[] = ['date' => (string) $a->tarikh_terima?->toDateString(), 'type' => 'aduan', 'title' => (string) __('adu::adu.profil.peristiwa_terima', ['no' => $a->no_rujukan_aduan, 'kategori' => $this->ref->namaKategori($a->kategori)]), 'link' => '/admin/adu/aduan/'.$a->id];
            if ($a->tarikh_tutup !== null) {
                $events[] = ['date' => $a->tarikh_tutup->toDateString(), 'type' => 'aduan', 'title' => (string) __('adu::adu.profil.peristiwa_tutup', ['no' => $a->no_rujukan_aduan]), 'link' => '/admin/adu/aduan/'.$a->id];
            }
        }

        return $events;
    }
}
