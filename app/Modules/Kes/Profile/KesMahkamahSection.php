<?php

namespace App\Modules\Kes\Profile;

use App\Models\User;
use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Kes\Models\KosGuaman;
use App\Modules\Kes\Services\LogCapaianKes;
use App\Modules\Kes\Services\Rujukan;
use App\Modules\Kes\Services\StatusLot;
use App\Modules\Kes\Support\Akses;
use App\Modules\Kes\Support\Kod;
use App\Modules\Pro\Contracts\FinancialSeries;
use App\Modules\Pro\Contracts\ProfileSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * SRS-KES-05/06/15 on the Profil Hartanah 360° (tab risiko, key
 * "kes_mahkamah" — replaces MOD-PRO's placeholder): case history of the
 * lot, the "dalam tindakan undang-undang" flag, and legal costs as
 * `kos_guaman` events in the lot's cost chart. Rahsia: only users with the
 * legal + Rahsia grants and MFA see anything (others get null — the section
 * is hidden, not shown as a placeholder); each display is logged.
 */
class KesMahkamahSection implements FinancialSeries, ProfileSection
{
    public function __construct(private Akses $akses, private Rujukan $ref, private StatusLot $status, private LogCapaianKes $log) {}

    public function key(): string
    {
        return 'kes_mahkamah';
    }

    public function tab(): string
    {
        return 'risiko';
    }

    public function label(): string
    {
        return (string) __('kes::kes.profil.label');
    }

    public function forLot(string $lotId, User $user): ?array
    {
        if (! $this->akses->bolehCapai($user)) {
            return null;
        }
        $cases = $this->kes($lotId, $user)->orderByDesc('created_at')->limit(50)->get();
        $flags = [];
        if (($this->status->untuk([$lotId])[$lotId] ?? false)) {
            $flags[] = ['level' => 'danger', 'label' => (string) __('kes::kes.profil.bendera_tindakan', ['n' => $cases->filter(fn (KesMahkamah $k) => ! $k->terminal())->count()])];
        }
        if ($cases->isNotEmpty()) {
            $this->log->rekod('lihat_senarai', null, null, 'Profil 360° lot '.$lotId.': '.$cases->count().' kes', ['lot_id' => $lotId, 'kes' => $cases->pluck('id')->all()], $user);
        }

        return [
            'items' => $cases->map(fn (KesMahkamah $k) => [
                'id' => $k->id,
                'label' => $k->no_rujukan.' · '.($this->ref->namaJenis($k->jenis) ?? '—'),
                'nilai' => $this->ref->namaStatus($k->status).($k->tarikh_prosiding_berikutnya ? ' · prosiding '.$k->tarikh_prosiding_berikutnya->format('d/m/Y') : ''),
                'kod' => $k->terminal() ? 'selesai' : 'aktif',
                'link' => '/admin/kes/kes/'.$k->id,
            ])->values()->all(),
            'flags' => $flags,
            'link' => '/admin/kes/kes?lot_id='.$lotId,
        ];
    }

    public function timeline(string $lotId, User $user): array
    {
        if (! $this->akses->bolehCapai($user)) {
            return [];
        }
        $events = [];
        foreach ($this->kes($lotId, $user)->orderByDesc('created_at')->limit(50)->get() as $k) {
            $link = '/admin/kes/kes/'.$k->id;
            $events[] = ['date' => ($k->tarikh_pemfailan ?? $k->created_at)->toDateString(), 'type' => 'kes_mahkamah', 'title' => (string) __('kes::kes.profil.peristiwa_daftar', ['no' => $k->no_rujukan]), 'link' => $link];
            if ($k->tarikh_keputusan !== null) {
                $events[] = ['date' => $k->tarikh_keputusan->toDateString(), 'type' => 'kes_mahkamah', 'title' => (string) __('kes::kes.profil.peristiwa_keputusan', ['no' => $k->no_rujukan]), 'link' => $link];
            }
        }

        return $events;
    }

    /**
     * SRS-KES-15 / acceptance #5: every cost item of a case linked to the lot
     * (except rejected ones), at its full recorded amount.
     */
    public function financialSeries(string $lotId, User $user): array
    {
        if (! $this->akses->bolehCapai($user)) {
            return [];
        }
        $ids = $this->kes($lotId, $user)->pluck('kes_mahkamah.id');
        $out = [];
        foreach (KosGuaman::query()->with('kes')->whereIn('kes_mahkamah_id', $ids)->where('status_bayaran', '!=', 'ditolak')->orderBy('tarikh')->get() as $c) {
            $out[] = [
                'tarikh' => $c->tarikh->toDateString(),
                'jenis' => 'kos_guaman',
                'amaun' => (float) $c->amaun,
                'keterangan' => (Kod::KATEGORI_KOS[$c->kategori_kos] ?? $c->kategori_kos).' — '.$c->kes?->no_rujukan,
                'link' => '/admin/kes/kes/'.$c->kes_mahkamah_id.'?tab=kos',
            ];
        }

        return $out;
    }

    /** @return Builder<KesMahkamah> */
    private function kes(string $lotId, User $user): Builder
    {
        return KesMahkamah::query()->bolehDilihat($user)->whereIn('kes_mahkamah.id', DB::table('kes_mahkamah_lot')->where('lot_id', $lotId)->select('kes_mahkamah_id'));
    }
}
