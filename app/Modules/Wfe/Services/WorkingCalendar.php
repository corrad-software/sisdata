<?php

namespace App\Modules\Wfe\Services;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Wfe\Models\CutiUmum;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * SRS-WFE-06: SLA arithmetic in Selangor working days / working hours.
 *
 * Working days exclude the configured weekend (Selangor: Saturday + Sunday)
 * and every date in wf_cuti_umum for the configured state. Working hours are
 * the window wfe.waktu_kerja_mula–wfe.waktu_kerja_tamat on working days.
 * All arithmetic happens in Malaysian time; results are returned in UTC.
 */
class WorkingCalendar
{
    public const TZ = 'Asia/Kuala_Lumpur';

    /** @var array<string, true>|null */
    private ?array $holidays = null;

    public function __construct(private Parameters $parameters) {}

    /** Forget cached holidays (after an admin edits the calendar). */
    public function flush(): void
    {
        $this->holidays = null;
    }

    public function isHoliday(DateTimeInterface $date): bool
    {
        return isset($this->holidays()[$this->local($date)->toDateString()]);
    }

    public function isWorkingDay(DateTimeInterface $date): bool
    {
        $local = $this->local($date);

        return ! in_array($local->isoWeekday(), $this->weekend(), true) && ! $this->isHoliday($local);
    }

    /** Due time for an SLA value in the given unit (hari_bekerja | jam). */
    public function due(DateTimeInterface $start, int $value, string $unit): CarbonImmutable
    {
        return $unit === 'jam' ? $this->addWorkingHours($start, $value) : $this->addWorkingDays($start, $value);
    }

    /**
     * End of the n-th working day after $start's date (n = 0: end of today if
     * it is a working day, else of the next working day).
     */
    public function addWorkingDays(DateTimeInterface $start, int $days): CarbonImmutable
    {
        $day = $this->local($start)->startOfDay();
        if ($days === 0) {
            while (! $this->isWorkingDay($day)) {
                $day = $day->addDay();
            }
        }
        $counted = 0;
        while ($counted < $days) {
            $day = $day->addDay();
            if ($this->isWorkingDay($day)) {
                $counted++;
            }
        }

        return $this->atTime($day, $this->workEnd())->utc();
    }

    public function addWorkingHours(DateTimeInterface $start, int $hours): CarbonImmutable
    {
        return $this->addWorkingMinutes($start, $hours * 60);
    }

    public function addWorkingMinutes(DateTimeInterface $start, int $minutes): CarbonImmutable
    {
        $cursor = $this->clampForward($this->local($start));
        $remaining = $minutes;
        $guard = 0;

        while ($remaining > 0 && $guard++ < 5000) {
            $end = $this->atTime($cursor, $this->workEnd());
            $available = (int) $cursor->diffInMinutes($end);
            if ($available >= $remaining) {
                return $cursor->addMinutes($remaining)->utc();
            }
            $remaining -= $available;
            $cursor = $this->clampForward($end->addMinute());
        }

        return $cursor->utc();
    }

    /**
     * Working minutes from $from to $to; negative when $to is before $from
     * (used for "baki SLA" and overdue display).
     */
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
                $open = $this->atTime($day, $this->workStart());
                $close = $this->atTime($day, $this->workEnd());
                $s = $open->max($a);
                $e = $close->min($b);
                if ($e->greaterThan($s)) {
                    $minutes += (int) $s->diffInMinutes($e);
                }
            }
            $day = $day->addDay();
        }

        return $minutes;
    }

    public function minutesPerWorkingDay(): int
    {
        [$sh, $sm] = $this->workStart();
        [$eh, $em] = $this->workEnd();

        return max(1, ($eh * 60 + $em) - ($sh * 60 + $sm));
    }

    /** @return list<int> ISO weekday numbers (1 = Monday … 7 = Sunday) */
    public function weekend(): array
    {
        $value = $this->parameters->get('wfe.hari_hujung_minggu', [6, 7]);
        if (is_string($value)) {
            $value = json_decode($value, true) ?: [6, 7];
        }

        return array_values(array_map('intval', (array) $value));
    }

    /** Next moment inside working hours at or after $t. */
    private function clampForward(CarbonImmutable $t): CarbonImmutable
    {
        $guard = 0;
        while ($guard++ < 400) {
            if ($this->isWorkingDay($t)) {
                $open = $this->atTime($t, $this->workStart());
                $close = $this->atTime($t, $this->workEnd());
                if ($t->lessThan($open)) {
                    return $open;
                }
                if ($t->lessThan($close)) {
                    return $t;
                }
            }
            $t = $this->atTime($t->addDay(), $this->workStart());
        }

        return $t;
    }

    /** @param array{0: int, 1: int} $hm */
    private function atTime(CarbonImmutable $day, array $hm): CarbonImmutable
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
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $value, $m)) {
            return $fallback;
        }

        return [min(23, (int) $m[1]), min(59, (int) $m[2])];
    }

    private function local(DateTimeInterface $date): CarbonImmutable
    {
        return CarbonImmutable::instance($date)->setTimezone(self::TZ);
    }

    /** @return array<string, true> */
    private function holidays(): array
    {
        if ($this->holidays === null) {
            $state = (string) $this->parameters->get('wfe.negeri_kalendar', 'selangor');
            $this->holidays = [];
            foreach (CutiUmum::query()->where('negeri', $state)->pluck('tarikh') as $date) {
                $this->holidays[CarbonImmutable::parse($date)->toDateString()] = true;
            }
        }

        return $this->holidays;
    }
}
