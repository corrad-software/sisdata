<?php

namespace App\Modules\Tsu\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Tsu\Models\KemajuanKerjaUkur;
use App\Modules\Tsu\Models\LantikanJurukur;
use App\Modules\Tsu\Support\Makluman;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * SRS-TSU-11/12: survey progress by milestone / % complete. A report that is
 * behind the straight-line schedule by more than the configured threshold
 * marks the appointment late and notifies the responsible officer and the
 * head of division. External surveyors may only report on their own
 * appointment (security control "capaian jurukur luar").
 */
class UkurService
{
    public function __construct(private Parameters $parameters, private Makluman $makluman) {}

    public function bolehLapor(User $user, LantikanJurukur $l): bool
    {
        if ($user->can('tsu.ukur.kemas_kini')) {
            return true;
        }

        return $user->can('tsu.jurukur.kemas_kini') && $l->jurukur->id_pengguna === $user->id;
    }

    /** @param array{milestone: string, peratus_siap: int, tarikh_lapor?: string|null, catatan?: string|null} $data */
    public function report(LantikanJurukur $l, array $data, User $user): KemajuanKerjaUkur
    {
        if (! $this->bolehLapor($user, $l)) {
            throw new HttpException(403, __('tsu::tsu.ukur.bukan_lantikan_anda'));
        }
        if ($l->status !== 'aktif') {
            throw ValidationException::withMessages(['status' => [__('tsu::tsu.ukur.lantikan_tidak_aktif')]]);
        }
        $peratus = (int) $data['peratus_siap'];
        if ($peratus < $l->peratus_siap_semasa) {
            throw ValidationException::withMessages(['peratus_siap' => [__('tsu::tsu.ukur.peratus_menurun', ['semasa' => $l->peratus_siap_semasa])]]);
        }
        $on = Carbon::parse($data['tarikh_lapor'] ?? today()->toDateString());
        $expected = $l->peratusDijangka($on);
        $late = $this->lewat($peratus, $expected);
        $wasLate = $l->lewat;

        $k = DB::transaction(function () use ($l, $data, $user, $peratus, $expected, $late, $on) {
            $k = KemajuanKerjaUkur::query()->create([
                'lantikan_id' => $l->id, 'milestone' => $data['milestone'], 'peratus_siap' => $peratus, 'peratus_dijangka' => $expected,
                'lewat' => $late, 'tarikh_lapor' => $on->toDateString(), 'catatan' => $data['catatan'] ?? null, 'id_pelapor' => $user->id,
            ]);
            $l->forceFill(['peratus_siap_semasa' => $peratus, 'lewat' => $late])->save();

            return $k;
        });
        if ($late && ! $wasLate) {
            $this->notifyLate($l, $peratus, $expected);
        }

        return $k;
    }

    public function lewat(int $actual, int $expected): bool
    {
        return $actual + (int) $this->parameters->get('tsu.ambang_lewat_ukur_peratus', 10) < $expected;
    }

    /** Daily check (tsu:semak-tempoh): appointments drifting behind without a new report. */
    public function checkAll(): int
    {
        $n = 0;
        foreach (LantikanJurukur::query()->with(['jurukur', 'urusan'])->where('status', 'aktif')->where('lewat', false)->get() as $l) {
            $expected = $l->peratusDijangka();
            if ($this->lewat($l->peratus_siap_semasa, $expected)) {
                $l->forceFill(['lewat' => true])->save();
                $this->notifyLate($l, $l->peratus_siap_semasa, $expected);
                $n++;
            }
        }

        return $n;
    }

    private function notifyLate(LantikanJurukur $l, int $actual, int $expected): void
    {
        $u = $l->urusan;
        $this->makluman->hantar('tsu.ukur_lewat', $this->makluman->denganKetua($u->pegawai), [
            'no_rujukan' => $u->no_rujukan_mais, 'jurukur' => $l->jurukur->nama_firma, 'peratus_siap' => $actual, 'peratus_dijangka' => $expected,
            'tarikh_jangka_siap' => $l->tarikh_jangka_siap->format('d/m/Y'),
        ], '/admin/tsu/urusan/'.$u->id.'?tab=ukur', 'tsu.ukur_lewat:'.$l->id);
    }
}
