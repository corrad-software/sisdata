<?php

namespace App\Modules\Wfe\Database\Seeders;

use App\Modules\Wfe\Models\CutiUmum;
use Illuminate\Database\Seeder;

/**
 * SRS-WFE-06: Selangor public holidays 2026 and 2027.
 *
 * Source: Jabatan Perdana Menteri, Bahagian Kabinet, Perlembagaan dan
 * Perhubungan Antara Kerajaan — "Jadual Hari Kelepasan Am Persekutuan dan
 * Negeri" (https://www.kabinet.gov.my/hari-kelepasan-am/):
 *   2026: https://www.kabinet.gov.my/storage/2025/08/HKA-2026.pdf
 *   2027: https://www.kabinet.gov.my/storage/2026/08/HKA_2027.pdf (dikemas kini 20 Ogos 2026)
 * Only rows ticked for the Selangor column are included. Islamic dates and
 * Deepavali are marked "tertakluk kepada perubahan" in the source.
 *
 * Replacement holidays ("ganti") are derived per Akta Hari Kelepasan 1951
 * s.3 for states whose weekly rest day is Sunday (Selangor, weekend
 * Saturday–Sunday): a holiday on Sunday moves to the next working day. The
 * 2 Jun 2026 replacement is confirmed by kabinet.gov.my; the others must be
 * confirmed against the state gazette — admins can edit them in the UI.
 * Idempotent: safe to run again.
 */
class CutiUmumSelangorSeeder extends Seeder
{
    private const SRC_2026 = 'JPM Bahagian Kabinet — Jadual Hari Kelepasan Am 2026 (kabinet.gov.my/storage/2025/08/HKA-2026.pdf)';

    private const SRC_2027 = 'JPM Bahagian Kabinet — Jadual Hari Kelepasan Am 2027 (kabinet.gov.my/storage/2026/08/HKA_2027.pdf)';

    private const SRC_GANTI = 'Dikira: Akta Hari Kelepasan 1951 s.3 (cuti jatuh hari Ahad) — sahkan dengan pewartaan Negeri Selangor';

    /** @return list<array{0: string, 1: string, 2: string, 3: string, 4?: string}> tarikh, nama, jenis, sumber, catatan */
    public static function holidays(): array
    {
        $tbc = 'Tertakluk kepada perubahan';

        return [
            // 2026
            ['2026-01-01', 'Tahun Baharu', 'negeri', self::SRC_2026],
            ['2026-02-01', 'Hari Thaipusam', 'negeri', self::SRC_2026],
            ['2026-02-02', 'Cuti Ganti Hari Thaipusam', 'ganti', self::SRC_GANTI],
            ['2026-02-17', 'Tahun Baharu Cina', 'persekutuan', self::SRC_2026],
            ['2026-02-18', 'Tahun Baharu Cina (Hari Kedua)', 'persekutuan', self::SRC_2026],
            ['2026-03-07', 'Hari Nuzul Al-Quran', 'negeri', self::SRC_2026],
            ['2026-03-21', 'Hari Raya Puasa', 'persekutuan', self::SRC_2026, $tbc],
            ['2026-03-22', 'Hari Raya Puasa (Hari Kedua)', 'persekutuan', self::SRC_2026, $tbc],
            ['2026-03-23', 'Cuti Ganti Hari Raya Puasa (Hari Kedua)', 'ganti', self::SRC_GANTI],
            ['2026-05-01', 'Hari Pekerja', 'persekutuan', self::SRC_2026],
            ['2026-05-27', 'Hari Raya Qurban', 'persekutuan', self::SRC_2026, $tbc],
            ['2026-05-31', 'Hari Wesak', 'persekutuan', self::SRC_2026],
            ['2026-06-01', 'Hari Keputeraan Rasmi Seri Paduka Baginda Yang di-Pertuan Agong', 'persekutuan', self::SRC_2026],
            ['2026-06-02', 'Cuti Ganti Hari Wesak', 'ganti', 'kabinet.gov.my/bkpp/hari-kelepasan-am-persekutuan-negeri (cuti ganti negeri hujung minggu Ahad)'],
            ['2026-06-17', 'Awal Muharam (Maal Hijrah)', 'persekutuan', self::SRC_2026, $tbc],
            ['2026-08-25', 'Hari Keputeraan Nabi Muhammad S.A.W. (Maulidur Rasul)', 'persekutuan', self::SRC_2026, $tbc],
            ['2026-08-31', 'Hari Kebangsaan', 'persekutuan', self::SRC_2026],
            ['2026-09-16', 'Hari Malaysia', 'persekutuan', self::SRC_2026],
            ['2026-11-08', 'Hari Deepavali', 'persekutuan', self::SRC_2026, $tbc],
            ['2026-11-09', 'Cuti Ganti Hari Deepavali', 'ganti', self::SRC_GANTI],
            ['2026-12-11', 'Hari Keputeraan Sultan Selangor', 'negeri', self::SRC_2026],
            ['2026-12-25', 'Hari Krismas', 'persekutuan', self::SRC_2026],

            // 2027
            ['2027-01-01', 'Tahun Baharu', 'negeri', self::SRC_2027],
            ['2027-01-22', 'Hari Thaipusam', 'negeri', self::SRC_2027],
            ['2027-02-06', 'Tahun Baharu Cina', 'persekutuan', self::SRC_2027],
            ['2027-02-07', 'Tahun Baharu Cina (Hari Kedua)', 'persekutuan', self::SRC_2027],
            ['2027-02-08', 'Cuti Ganti Tahun Baharu Cina (Hari Kedua)', 'ganti', self::SRC_GANTI],
            ['2027-02-24', 'Hari Nuzul Al-Quran', 'negeri', self::SRC_2027, $tbc],
            ['2027-03-10', 'Hari Raya Puasa', 'persekutuan', self::SRC_2027, $tbc],
            ['2027-03-11', 'Hari Raya Puasa (Hari Kedua)', 'persekutuan', self::SRC_2027, $tbc],
            ['2027-05-01', 'Hari Pekerja', 'persekutuan', self::SRC_2027],
            ['2027-05-17', 'Hari Raya Qurban', 'persekutuan', self::SRC_2027, $tbc],
            ['2027-05-20', 'Hari Wesak', 'persekutuan', self::SRC_2027],
            ['2027-06-06', 'Awal Muharam (Maal Hijrah)', 'persekutuan', self::SRC_2027, $tbc],
            ['2027-06-07', 'Hari Keputeraan Rasmi Seri Paduka Baginda Yang di-Pertuan Agong', 'persekutuan', self::SRC_2027],
            ['2027-06-08', 'Cuti Ganti Awal Muharam', 'ganti', self::SRC_GANTI],
            ['2027-08-15', 'Hari Keputeraan Nabi Muhammad S.A.W. (Maulidur Rasul)', 'persekutuan', self::SRC_2027, $tbc],
            ['2027-08-16', 'Cuti Ganti Maulidur Rasul', 'ganti', self::SRC_GANTI],
            ['2027-08-31', 'Hari Kebangsaan', 'persekutuan', self::SRC_2027],
            ['2027-09-16', 'Hari Malaysia', 'persekutuan', self::SRC_2027],
            ['2027-10-28', 'Hari Deepavali', 'persekutuan', self::SRC_2027, $tbc],
            ['2027-12-11', 'Hari Keputeraan Sultan Selangor', 'negeri', self::SRC_2027],
            ['2027-12-25', 'Hari Krismas', 'persekutuan', self::SRC_2027],
        ];
    }

    public function run(): void
    {
        foreach (self::holidays() as $row) {
            CutiUmum::query()->updateOrCreate(
                ['tarikh' => $row[0], 'negeri' => 'selangor', 'nama' => $row[1]],
                ['jenis' => $row[2], 'sumber' => $row[3], 'catatan' => $row[4] ?? null],
            );
        }
    }
}
