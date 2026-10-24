<?php

namespace App\Modules\Dms\Services\Lifecycle;

use App\Modules\Dms\Services\DmsSettings;
use App\Modules\Dms\Services\Recipients;
use App\Modules\Ntf\Contracts\Notifier;
use Illuminate\Support\Facades\DB;

/** SRS-DMS-25: storage quota and the 80 % alert. */
class QuotaService
{
    public function __construct(private DmsSettings $settings, private Notifier $notifier, private Recipients $recipients) {}

    public function usedBytes(): int
    {
        $versions = (int) DB::table('dokumen_versi')->whereNotNull('kunci_storan')->sum('saiz_bait');
        $quarantine = (int) DB::table('dokumen_kuarantin')->whereNotNull('kunci_storan')->sum('saiz_bait');

        return $versions + $quarantine;
    }

    /**
     * @return array{kuota_bait: int, guna_bait: int, peratus: float, amaran_peratus: int, amaran: bool, ikut_modul: list<array{kod: string, bait: int, bilangan: int}>, ikut_kategori: list<array{kod: string, nama: string, bait: int, bilangan: int}>}
     */
    public function summary(): array
    {
        $quota = $this->settings->quotaBytes();
        $used = $this->usedBytes();
        $percent = $quota > 0 ? round($used / $quota * 100, 2) : 0.0;

        $byModule = DB::table('dokumen_versi as v')
            ->join('dokumen as d', 'd.id', '=', 'v.dokumen_id')
            ->whereNotNull('v.kunci_storan')
            ->groupBy('d.modul_sumber')
            ->selectRaw('d.modul_sumber as kod, sum(v.saiz_bait) as bait, count(distinct d.id) as bilangan')
            ->orderByDesc('bait')
            ->get()
            ->map(fn ($r) => ['kod' => (string) $r->kod, 'bait' => (int) $r->bait, 'bilangan' => (int) $r->bilangan])
            ->all();

        $byCategory = DB::table('dokumen_versi as v')
            ->join('dokumen as d', 'd.id', '=', 'v.dokumen_id')
            ->join('dokumen_kategori as k', 'k.id', '=', 'd.kategori_id')
            ->whereNotNull('v.kunci_storan')
            ->groupBy('k.kod', 'k.nama')
            ->selectRaw('k.kod, k.nama, sum(v.saiz_bait) as bait, count(distinct d.id) as bilangan')
            ->orderByDesc('bait')
            ->get()
            ->map(fn ($r) => ['kod' => (string) $r->kod, 'nama' => (string) $r->nama, 'bait' => (int) $r->bait, 'bilangan' => (int) $r->bilangan])
            ->all();

        return [
            'kuota_bait' => $quota,
            'guna_bait' => $used,
            'peratus' => $percent,
            'amaran_peratus' => $this->settings->quotaWarnPercent(),
            'amaran' => $percent >= $this->settings->quotaWarnPercent(),
            'ikut_modul' => array_values($byModule),
            'ikut_kategori' => array_values($byCategory),
        ];
    }

    public function wouldExceed(int $extraBytes): bool
    {
        $quota = $this->settings->quotaBytes();

        return $quota > 0 && $this->usedBytes() + $extraBytes > $quota;
    }

    /** Alert once a day while usage is at or above the threshold. Returns whether it alerted. */
    public function checkAndAlert(): bool
    {
        $summary = $this->summary();
        if (! $summary['amaran']) {
            return false;
        }

        $recipients = $this->recipients->withPermission('dms.kuota.lihat');
        if ($recipients->isEmpty()) {
            return false;
        }

        $this->notifier->send('dms.kuota_amaran', $recipients, [
            'peratus' => (string) $summary['peratus'],
            'guna_gb' => number_format($summary['guna_bait'] / 1024 ** 3, 2),
            'kuota_gb' => number_format($summary['kuota_bait'] / 1024 ** 3, 0),
        ], '/admin/dms/tetapan?tab=kuota', 'dms.kuota_amaran:'.now()->toDateString());

        return true;
    }
}
