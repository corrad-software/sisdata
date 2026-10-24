<?php

namespace App\Modules\Ref\Services;

use Random\Randomizer;

/**
 * SYNTHETIC Malaysian-style people for sample data. Names are combined at
 * random from generic given-name lists (no real person is looked up or
 * copied); IC numbers use place-of-birth code "00", which JPN does not
 * issue, so they can never be a real MyKad number (PDPA).
 */
class SyntheticPeople
{
    private const MALAY_MALE = ['Ahmad', 'Muhammad', 'Mohd', 'Abdul', 'Ismail', 'Hassan', 'Hussin', 'Rahman', 'Razak', 'Kamal', 'Faizal', 'Hafiz', 'Azman', 'Zulkifli', 'Rosli', 'Hamdan', 'Yusof', 'Ibrahim', 'Salleh', 'Osman', 'Rashid', 'Harun', 'Jamal', 'Nordin', 'Zainal', 'Shukri', 'Aziz', 'Idris', 'Kassim', 'Saiful'];

    private const MALAY_MALE_2 = ['Amin', 'Hakim', 'Firdaus', 'Ikhwan', 'Syafiq', 'Aiman', 'Fikri', 'Najib', 'Haziq', 'Rizal', 'Shah', 'Iskandar', 'Fauzi', 'Nizam', 'Hisham'];

    private const MALAY_FEMALE = ['Siti', 'Nur', 'Noor', 'Aminah', 'Fatimah', 'Zainab', 'Rohani', 'Rosnah', 'Halimah', 'Aishah', 'Khadijah', 'Mariam', 'Salmah', 'Zaleha', 'Norhayati', 'Faridah', 'Rahimah', 'Azizah', 'Hasnah', 'Suraya'];

    private const MALAY_FEMALE_2 = ['Aisyah', 'Hidayah', 'Nadia', 'Farhana', 'Syazwani', 'Amirah', 'Izzati', 'Nabilah', 'Atiqah', 'Liyana', 'Sabrina', 'Huda', 'Zahra', 'Hanani', 'Najwa'];

    private const ORGANISATIONS = ['Majlis Agama Islam Selangor'];

    public function __construct(private readonly Randomizer $random) {}

    /**
     * @return array{nama: string, no_pengenalan: string, jantina: string}
     */
    public function person(int $minYear = 1935, int $maxYear = 1995): array
    {
        $male = $this->random->getInt(0, 1) === 1;
        $given = $male
            ? $this->pick(self::MALAY_MALE).($this->random->getInt(0, 2) > 0 ? ' '.$this->pick(self::MALAY_MALE_2) : '')
            : $this->pick(self::MALAY_FEMALE).($this->random->getInt(0, 2) > 0 ? ' '.$this->pick(self::MALAY_FEMALE_2) : '');
        $father = $this->pick(self::MALAY_MALE).($this->random->getInt(0, 1) ? ' '.$this->pick(self::MALAY_MALE_2) : '');

        return [
            'nama' => $given.($male ? ' bin ' : ' binti ').$father,
            'no_pengenalan' => $this->icNumber($male, $minYear, $maxYear),
            'jantina' => $male ? 'L' : 'P',
        ];
    }

    public function organisation(): string
    {
        return self::ORGANISATIONS[0];
    }

    /**
     * YYMMDD-00-NNNN: valid-looking date, impossible place-of-birth code "00",
     * last digit odd for male / even for female (as on MyKad).
     */
    public function icNumber(bool $male, int $minYear = 1935, int $maxYear = 1995): string
    {
        $year = $this->random->getInt($minYear, $maxYear);
        $month = $this->random->getInt(1, 12);
        $day = $this->random->getInt(1, (int) date('t', (int) mktime(0, 0, 0, $month, 1, $year)));
        $serial = $this->random->getInt(0, 499) * 2 + ($male ? 1 : 0);

        return sprintf('%02d%02d%02d-00-%04d', $year % 100, $month, $day, $this->random->getInt(0, 9) * 1000 + $serial);
    }

    /**
     * @template T
     *
     * @param  list<T>  $list
     * @return T
     */
    private function pick(array $list): mixed
    {
        return $list[$this->random->getInt(0, count($list) - 1)];
    }

    /**
     * True when $ic is a synthetic number from this class (place code "00").
     */
    public static function isSyntheticIc(string $ic): bool
    {
        return (bool) preg_match('/^\d{6}-00-\d{4}$/', $ic);
    }
}
