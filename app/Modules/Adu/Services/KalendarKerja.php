<?php

namespace App\Modules\Adu\Services;

use App\Modules\Adm\Contracts\Parameters;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SRS-ADU-13: SLA arithmetic in working days / hours, skipping the weekend
 * and the Selangor + federal public holidays MAIS maintains in MOD-WFE's
 * calendar (wf_cuti_umum, same parameters wfe.hari_hujung_minggu,
 * wfe.waktu_kerja_mula/tamat, wfe.negeri_kalendar) so both modules agree.
 * MOD-WFE exposes no calendar contract yet, so the table is read directly
 * (read-only), like the ref_* tables.
 */
class KalendarKerja
{
    public const TZ = 'Asia/Kuala_Lumpur';

    /** @var array<string, true>|null */
    private ?array $holidays = null;

    public function __construct(private Parameters $parameters) {}

    public function flush(): void
    {
        $this->holidays = null;
    }

    public function isWorkingDay(DateTimeInterface $date): bool
    {
        $local = $this->local($date);

        return ! in_array($local->isoWeekday(), $this->weekend(), true)
            && ! isset($this->holidays()[$local->toDateString()]);
    }

    /** Due moment for a period in `hari_bekerja` or `jam_bekerja` from $start. */
    public function due(DateTimeInterface $start, int $value, string $unit): CarbonImmutable
    {
        return $this->addWorkingMinutes($start, $value * ($unit === 'jam_bekerja' ? 60 : $this->minutesPerDay()));
    }

    public function addWorkingMinutes(DateTimeInterface $start, int $minutes): CarbonImmutable
    {
        $cursor = $this->clampForward($this->local($start));
        $remaining = $minutes;
        $guard = 0;
        while ($remaining > 0 && $guard++ < 5000) {
            $end = $this->at($cursor, $this->workEnd());
            $available = (int) $cursor->diffInMinutes($end);
            if ($available >= $remaining) {
                return $cursor->addMinutes($remaining)->utc();
            }
            $remaining -= $available;
            $cursor = $this->clampForward($end->addMinute());
        }

        return $cursor->utc();
    }

    /** Working minutes from $from to $to (negative when $to < $from). */
    public function workingMinutesBetween(DateTimeInterface $from, DateTimeInterface $to): int
    {
        $a = $this->local($from);
        $b = $this->local($to);
        if ($b->lessThan($a)) {
            return -$this->workingMinutesBetween($b, $a);
        }
        $minutes = 0;
        $day = $a->startOfDay();
        $guard = 0;
        while ($day->lessThanOrEqualTo($b) && $guard++ < 3700) {
            if ($this->isWorkingDay($day)) {
                $s = $this->at($day, $this->workStart())->max($a);
                $e = $this->at($day, $this->workEnd())->min($b);
                if ($e->greaterThan($s)) {
                    $minutes += (int) $s->diffInMinutes($e);
                }
            }
            $day = $day->addDay();
        }

        return $minutes;
    }

    public function minutesPerDay(): int
    {
        [$sh, $sm] = $this->workStart();
        [$eh, $em] = $this->workEnd();

        return max(1, ($eh * 60 + $em) - ($sh * 60 + $sm));
    }

    /** @return list<int> */
    private function weekend(): array
    {
        $value = $this->parameters->get('wfe.hari_hujung_minggu', [6, 7]);
        if (is_string($value)) {
            $value = json_decode($value, true) ?: [6, 7];
        }

        return array_values(array_map('intval', (array) $value));
    }

    private function clampForward(CarbonImmutable $t): CarbonImmutable
    {
        $guard = 0;
        while ($guard++ < 400) {
            if ($this->isWorkingDay($t)) {
                $open = $this->at($t, $this->workStart());
                if ($t->lessThan($open)) {
                    return $open;
                }
                if ($t->lessThan($this->at($t, $this->workEnd()))) {
                    return $t;
                }
            }
            $t = $this->at($t->addDay(), $this->workStart());
        }

        return $t;
    }

    /** @param array{0: int, 1: int} $hm */
    private function at(CarbonImmutable $day, array $hm): CarbonImmutable
    {
        return $day->setTime($hm[0], $hm[1], 0);
    }

    /** @return array{0: int, 1: int} */
    private function workStart(): array
    {
        return $this->hm((string) $this->parameters->get('wfe.waktu_kerja_mula', '08:00'), [8, 0]);
    }

    /** @return array{0: int, 1: int} */
    private function workEnd(): array
    {
        return $this->hm((string) $this->parameters->get('wfe.waktu_kerja_tamat', '17:00'), [17, 0]);
    }

    /**
     * @param  array{0: int, 1: int}  $fallback
     * @return array{0: int, 1: int}
     */
    private function hm(string $value, array $fallback): array
    {
        return preg_match('/^(\d{1,2}):(\d{2})$/', $value, $m) ? [min(23, (int) $m[1]), min(59, (int) $m[2])] : $fallback;
    }

    private function local(DateTimeInterface $date): CarbonImmutable
    {
        return CarbonImmutable::instance($date)->setTimezone(self::TZ);
    }

    /** @return array<string, true> */
    private function holidays(): array
    {
        if ($this->holidays === null) {
            $this->holidays = [];
            if (Schema::hasTable('wf_cuti_umum')) {
                $state = (string) $this->parameters->get('wfe.negeri_kalendar', 'selangor');
                foreach (DB::table('wf_cuti_umum')->where('negeri', $state)->pluck('tarikh') as $date) {
                    $this->holidays[CarbonImmutable::parse((string) $date)->toDateString()] = true;
                }
            }
        }

        return $this->holidays;
    }
}
