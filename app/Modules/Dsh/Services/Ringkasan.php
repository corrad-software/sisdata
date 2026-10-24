<?php

namespace App\Modules\Dsh\Services;

use App\Models\User;
use App\Modules\Dsh\Models\Langganan;
use App\Modules\Dsh\Models\Snapshot;
use App\Modules\Ntf\Contracts\Notifier;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * KPI snapshots and the scheduled e-mail digest (via MOD-NTF). A snapshot
 * freezes the KPI values one user saw at one time — computed AS that user, so
 * the scope is theirs — and can be exported later (PDF / Excel). The digest
 * notification carries only counts and non-classified headline figures plus
 * a link; the link target enforces access again (SRS-NTF-19).
 */
class Ringkasan
{
    public const JENIS_NOTIFIKASI = 'dsh.ringkasan_berkala';

    public const HARI = ['harian' => 1, 'mingguan' => 7, 'bulanan' => 30];

    public function __construct(private KpiService $kpis, private WidgetKatalog $widgets, private Aras $aras, private Skop $skop, private Notifier $notifier) {}

    /**
     * KPIs on the user's $aras layout (visible widgets only).
     *
     * @return list<array<string, mixed>>
     */
    public function compute(User $user, string $aras, array $filters, bool $fresh = false): array
    {
        $keys = [];
        foreach ($this->widgets->layout($user, $aras) as $w) {
            if ($w['kpi'] !== null && ! $w['tersembunyi']) {
                $keys[] = $w['kpi'];
            }
        }

        return $keys === [] ? [] : $this->kpis->compute($user, $filters, $keys, $fresh);
    }

    public function snapshot(User $user, string $aras, array $filters, string $sumber = 'manual'): Snapshot
    {
        return Snapshot::query()->create([
            'id_pengguna' => $user->id,
            'aras' => $aras,
            'tandatangan_skop' => $this->skop->signature($user),
            'penapis' => $filters,
            'nilai' => $this->compute($user, $aras, $filters, true),
            'sumber' => $sumber,
            'dijana_pada' => Carbon::now(),
        ]);
    }

    public function due(Langganan $l, ?Carbon $now = null): bool
    {
        $now ??= Carbon::now();

        return $l->aktif && ($l->terakhir_dihantar === null || $l->terakhir_dihantar->lte($now->copy()->subDays(self::HARI[$l->kekerapan] ?? 1)->addHour()));
    }

    /** Send every due digest; returns the number sent. */
    public function sendDue(): int
    {
        $sent = 0;
        foreach (Langganan::query()->where('aktif', true)->with('pengguna')->get() as $l) {
            $user = $l->pengguna;
            if (! $user || ! $user->is_active || ! $this->due($l) || ! $this->aras->allows($user, $l->aras)) {
                continue;
            }
            try {
                $this->send($l, $user);
                $sent++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $sent;
    }

    public function send(Langganan $l, User $user): Snapshot
    {
        $snap = $this->snapshot($user, $l->aras, (array) $l->penapis, 'berjadual');
        $kpis = $snap->nilai;
        $lines = [];
        foreach ($kpis as $k) {
            $provider = $this->kpis->find($user, (string) $k['key']);
            // Classified summaries (Sulit/Rahsia grants) never go into a notification body.
            if ($provider === null || preg_match('/\.(sulit|rahsia)\./', $provider->permission()) || $k['nilai'] === null) {
                continue;
            }
            $lines[] = '• '.$k['label'].': '.$this->fmt($k['nilai'], (string) $k['unit']).($k['lampu'] ? ' ('.__('dsh::dsh.lampu.'.$k['lampu']).')' : '');
        }

        $this->notifier->send(self::JENIS_NOTIFIKASI, $user, [
            'aras' => (string) __('dsh::dsh.aras.'.$l->aras),
            'tarikh' => Carbon::now()->format('d/m/Y H:i'),
            'bil_kpi' => count($kpis),
            'bil_merah' => count(array_filter($kpis, fn ($k) => ($k['lampu'] ?? null) === 'merah')),
            'bil_kuning' => count(array_filter($kpis, fn ($k) => ($k['lampu'] ?? null) === 'kuning')),
            'ringkasan' => implode("\n", array_slice($lines, 0, 12)),
        ], '/admin/dsh/snapshot/'.$snap->id, 'dsh.ringkasan:'.$l->id.':'.Carbon::now()->format('Ymd'));

        $l->forceFill(['terakhir_dihantar' => Carbon::now()])->save();

        return $snap;
    }

    private function fmt(mixed $v, string $unit): string
    {
        $n = number_format((float) $v, floor((float) $v) == (float) $v ? 0 : 1, '.', ',');

        return match ($unit) {
            'RM' => 'RM '.$n,
            '%' => $n.'%',
            default => trim($n.' '.$unit),
        };
    }
}
