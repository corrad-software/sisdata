<?php

namespace App\Modules\Hkm\Profile;

use App\Models\User;
use App\Modules\Hkm\Models\HakMilik;
use App\Modules\Hkm\Models\KronologiPemilikan;
use App\Modules\Hkm\Models\SejarahStatusTanah;
use App\Modules\Hkm\Support\Akses;
use App\Modules\Hkm\Support\Kod;
use App\Modules\Hkm\Support\RefLookup;
use App\Modules\Hkm\Support\StatusTanah;
use App\Modules\Pro\Contracts\ProfileSection;

/**
 * Profil Hartanah 360° (SRS-HKM-15, SRS-PRO-26) — tab Identiti: the lot's
 * titles; timeline (SRS-PRO-27): ownership chronology and status history.
 */
class HakMilikSection implements ProfileSection
{
    public function __construct(private Akses $akses, private RefLookup $ref, private StatusTanah $statuses) {}

    public function key(): string
    {
        return 'hak_milik';
    }

    public function tab(): string
    {
        return 'identiti';
    }

    public function label(): string
    {
        return __('hkm::hkm.profil.hak_milik');
    }

    public function forLot(string $lotId, User $user): ?array
    {
        $titles = $this->akses->titles($user)->where('lot_id', $lotId)->with('daftarPetiBesi.petiBesi')->orderByDesc('tarikh_pendaftaran')->get();
        if ($titles->isEmpty()) {
            return null;
        }

        $current = $titles->firstWhere(fn (HakMilik $h) => $h->status_semasa !== 'diganti') ?? $titles->first();

        return [
            'items' => $titles->map(function (HakMilik $h) {
                $doc = $h->daftarPetiBesi->first();

                return [
                    'id' => $h->id,
                    'no_hak_milik' => $h->no_hak_milik,
                    'jenis_hak_milik' => $h->jenis_hak_milik,
                    'jenis_hak_milik_nama' => $this->ref->namaJenis($h->jenis_hak_milik),
                    'kategori_tanah' => $h->kategori_tanah,
                    'pemilik_berdaftar' => $h->pemilik_berdaftar,
                    'tarikh_pendaftaran' => $h->tarikh_pendaftaran?->format('Y-m-d'),
                    'tarikh_tamat_tempoh' => $h->tarikh_tamat_tempoh?->format('Y-m-d'),
                    'status_semasa' => $h->status_semasa,
                    'status_semasa_nama' => $this->statuses->label($h->status_semasa),
                    'disahkan' => $h->disahkan,
                    'lokasi_simpanan' => $doc ? trim($doc->petiBesi->kod_peti_besi.' · '.implode(' · ', array_filter([$doc->lokasi_rak, $doc->no_fail]))) : null,
                    'status_dokumen_asal' => $doc ? (Kod::STATUS_DOKUMEN[$doc->status_dokumen_asal] ?? $doc->status_dokumen_asal) : null,
                    'link' => '/admin/hkm/hak-milik/'.$h->id,
                ];
            })->values()->all(),
            'link' => '/admin/hkm/hak-milik/'.$current->id,
        ];
    }

    public function timeline(string $lotId, User $user): array
    {
        $ids = $this->akses->titles($user)->where('lot_id', $lotId)->pluck('no_hak_milik', 'id');
        if ($ids->isEmpty()) {
            return [];
        }

        $events = [];
        foreach (KronologiPemilikan::query()->whereIn('hak_milik_id', $ids->keys())->orderBy('susunan_kronologi')->get() as $k) {
            $events[] = [
                'date' => ($k->tarikh_pertukaran ?? $k->created_at)->format('Y-m-d'),
                'type' => 'hkm.pemilikan',
                'title' => $k->pemilik_sebelum === null
                    ? __('hkm::hkm.profil.pemilik_pertama', ['pemilik' => $k->pemilik_selepas, 'no' => $ids[$k->hak_milik_id]])
                    : __('hkm::hkm.profil.pindah_milik', ['dari' => $k->pemilik_sebelum, 'ke' => $k->pemilik_selepas, 'no' => $ids[$k->hak_milik_id]]),
                'link' => '/admin/hkm/hak-milik/'.$k->hak_milik_id.'?tab=kronologi',
            ];
        }
        foreach (SejarahStatusTanah::query()->whereIn('hak_milik_id', $ids->keys())->orderBy('tarikh_berkuat_kuasa')->orderBy('id')->get() as $s) {
            $events[] = [
                'date' => $s->tarikh_berkuat_kuasa->format('Y-m-d'),
                'type' => 'hkm.status',
                'title' => __('hkm::hkm.profil.status', ['status' => $this->statuses->label($s->status_selepas), 'no' => $ids[$s->hak_milik_id]]),
                'link' => '/admin/hkm/hak-milik/'.$s->hak_milik_id.'?tab=kronologi',
            ];
        }

        usort($events, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $events;
    }
}
