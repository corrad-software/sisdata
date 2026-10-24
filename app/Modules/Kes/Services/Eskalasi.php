<?php

namespace App\Modules\Kes\Services;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Kes\Models\Prosiding;
use App\Modules\Kes\Support\Penerima;
use App\Modules\Ntf\Contracts\Notifier;
use Illuminate\Database\Eloquent\Builder;

/**
 * SRS-KES-11: a proceeding took place (date passed) more than
 * `kes.hari_tanpa_tarikh_berikutnya` days ago, the case is still active and
 * no next proceeding has been recorded → escalate once to the Ketua
 * Bahagian. Run daily by `kes:semak-eskalasi`.
 */
class Eskalasi
{
    public function __construct(private Parameters $parameters, private Penerima $penerima, private Notifier $notifier, private Rujukan $ref) {}

    /** @return int number of cases escalated */
    public function jalankan(): int
    {
        $hari = max(1, (int) $this->parameters->get('kes.hari_tanpa_tarikh_berikutnya', 7));
        $had = now()->subDays($hari)->endOfDay();
        $terminal = $this->ref->statusTerminal();
        $n = 0;
        $calon = Prosiding::query()->whereNull('eskalasi_dihantar_pada')->where('tarikh_prosiding', '<=', $had)
            ->whereIn('status', ['selesai', 'ditangguh', 'dijadualkan'])
            ->whereHas('kes', fn ($q) => $q->where('status_arkib', false)->whereNotIn('status', $terminal))
            ->orderByDesc('tarikh_prosiding')->get()->unique('kes_mahkamah_id');
        foreach ($calon as $p) {
            $kes = $p->kes;
            if ($kes === null) {
                continue;
            }
            // Any later proceeding or a next date means the case is not stalled.
            $ada = Prosiding::query()->where('kes_mahkamah_id', $kes->id)->where('tarikh_prosiding', '>', $p->tarikh_prosiding)->exists() || $p->tarikh_berikutnya !== null;
            if ($ada) {
                continue;
            }
            $ketua = $this->penerima->ketuaBahagian();
            $this->notifier->send('kes.eskalasi_tiada_tarikh', $ketua, [
                'no_rujukan' => $kes->no_rujukan, 'tarikh_prosiding' => $p->tarikh_prosiding->format('d/m/Y'), 'hari' => (int) $p->tarikh_prosiding->diffInDays(now()),
            ], '/admin/kes/kes/'.$kes->id.'?tab=prosiding', 'kes_eskalasi:'.$p->id);
            Prosiding::query()->where('kes_mahkamah_id', $kes->id)->whereNull('eskalasi_dihantar_pada')->where('tarikh_prosiding', '<=', $p->tarikh_prosiding)->update(['eskalasi_dihantar_pada' => now()]);
            $n++;
        }

        return $n;
    }

    /** Active cases without any future scheduled proceeding (report "kes tanpa tarikh prosiding berikutnya"). */
    /**
     * @param  Builder<KesMahkamah>  $q
     * @return Builder<KesMahkamah>
     */
    public static function tanpaTarikh(Builder $q): Builder
    {
        return $q->aktif()->where('kes_mahkamah.status_arkib', false)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('kes_prosiding as p')->whereColumn('p.kes_mahkamah_id', 'kes_mahkamah.id')
                ->where('p.status', 'dijadualkan')->where('p.tarikh_prosiding', '>=', now()->startOfDay()));
    }
}
