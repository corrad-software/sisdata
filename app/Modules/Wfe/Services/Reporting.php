<?php

namespace App\Modules\Wfe\Services;

use App\Modules\Wfe\Models\Instans;
use App\Modules\Wfe\Models\Sejarah;
use App\Modules\Wfe\Models\Tugasan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SRS-WFE-13 (monitoring dashboard) and SRS-WFE-23 (performance report).
 * Every figure is computed over the instances the current user may see:
 * Instans carries the RBC district scope, and task figures are restricted to
 * those instances.
 */
class Reporting
{
    public function __construct(private Assignees $assignees, private WorkingCalendar $calendar) {}

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $visible = Instans::query()->select('id');

        $byStatus = Instans::query()->selectRaw('status, count(*) as jumlah')->groupBy('status')->pluck('jumlah', 'status')
            ->map(fn ($n) => (int) $n)->all();

        $openTasks = Tugasan::query()->whereIn('id_instans', $visible)->whereIn('wf_tugasan.status', [Tugasan::MENUNGGU, Tugasan::DITANGGUH]);

        $perStage = (clone $openTasks)->where('jenis', Tugasan::KELULUSAN)
            ->join('wf_peringkat', 'wf_peringkat.id', '=', 'wf_tugasan.id_peringkat')
            ->join('wf_instans', 'wf_instans.id', '=', 'wf_tugasan.id_instans')
            ->selectRaw('wf_instans.kod_aliran, wf_peringkat.kod, wf_peringkat.nama_peringkat, count(*) as jumlah, sum(case when wf_tugasan.tarikh_akhir_sla < ? then 1 else 0 end) as lewat', [now()])
            ->groupBy('wf_instans.kod_aliran', 'wf_peringkat.kod', 'wf_peringkat.nama_peringkat')
            ->orderByDesc('jumlah')->toBase()->get()
            ->map(fn ($r) => [
                'kod_aliran' => $r->kod_aliran, 'peringkat' => $r->kod, 'nama_peringkat' => $r->nama_peringkat,
                'jumlah' => (int) $r->jumlah, 'lewat' => (int) $r->lewat,
            ])->all();

        $perApprover = $this->backlog();

        $avg = Instans::query()->whereIn('status', [Instans::LULUS, Instans::TOLAK])->whereNotNull('tarikh_tamat')
            ->selectRaw('kod_aliran, count(*) as jumlah, avg(extract(epoch from (tarikh_tamat - tarikh_mula))) / 3600 as purata_jam')
            ->groupBy('kod_aliran')->orderBy('kod_aliran')->toBase()->get()
            ->map(fn ($r) => ['kod_aliran' => $r->kod_aliran, 'jumlah' => (int) $r->jumlah, 'purata_jam' => round((float) $r->purata_jam, 2)])->all();

        $recent = Sejarah::query()->whereIn('id_instans', $visible)
            ->whereIn('peristiwa', ['lulus', 'tolak', 'kembali', 'tangguh', 'tarik_balik', 'eskalasi', 'pindah_versi'])
            ->with('pengguna:id,name', 'bagiPihak:id,name')->orderByDesc('id')->limit(15)->get()
            ->map(fn (Sejarah $s) => [
                'id_instans' => $s->id_instans, 'peristiwa' => $s->peristiwa, 'peringkat' => $s->nama_peringkat,
                'pengguna' => $s->pengguna?->name, 'bagi_pihak' => $s->bagiPihak?->name,
                'justifikasi' => $s->justifikasi, 'tarikh_masa' => $s->tarikh_masa->toIso8601String(),
            ])->all();

        return [
            'aktif' => $byStatus[Instans::BERJALAN] ?? 0,
            'ikut_status' => $byStatus,
            'tugasan_tertangguh' => (clone $openTasks)->count(),
            'tugasan_lewat' => (clone $openTasks)->where('tarikh_akhir_sla', '<', now())->count(),
            'ikut_peringkat' => $perStage,
            'ikut_pelulus' => $perApprover,
            'purata_penyelesaian' => $avg,
            'tindakan_terkini' => $recent,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function performance(?Carbon $from = null, ?Carbon $to = null, ?string $flowCode = null): array
    {
        $visible = Instans::query()->select('id')->when($flowCode, fn ($q) => $q->where('kod_aliran', $flowCode));
        $tasks = Tugasan::query()->whereIn('id_instans', $visible)->where('jenis', Tugasan::KELULUSAN)
            ->when($from, fn ($q) => $q->where('wf_tugasan.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('wf_tugasan.created_at', '<=', $to));

        $perStage = (clone $tasks)
            ->join('wf_peringkat', 'wf_peringkat.id', '=', 'wf_tugasan.id_peringkat')
            ->join('wf_instans', 'wf_instans.id', '=', 'wf_tugasan.id_instans')
            ->selectRaw("wf_instans.kod_aliran, wf_peringkat.kod, wf_peringkat.nama_peringkat,
                count(*) as jumlah,
                sum(case when wf_tugasan.status = 'selesai' then 1 else 0 end) as selesai,
                sum(case when wf_tugasan.status in ('menunggu','ditangguh') then 1 else 0 end) as tertunggak,
                sum(case when wf_tugasan.aras_eskalasi > 0 then 1 else 0 end) as dieskalasi,
                avg(case when wf_tugasan.status = 'selesai' then extract(epoch from (wf_tugasan.diselesaikan_pada - wf_tugasan.created_at)) end) / 3600 as purata_jam")
            ->groupBy('wf_instans.kod_aliran', 'wf_peringkat.kod', 'wf_peringkat.nama_peringkat')
            ->toBase()->get()
            ->map(fn ($r) => [
                'kod_aliran' => $r->kod_aliran,
                'peringkat' => $r->kod,
                'nama_peringkat' => $r->nama_peringkat,
                'jumlah' => (int) $r->jumlah,
                'selesai' => (int) $r->selesai,
                'tertunggak' => (int) $r->tertunggak,
                'dieskalasi' => (int) $r->dieskalasi,
                'purata_jam' => $r->purata_jam !== null ? round((float) $r->purata_jam, 2) : null,
            ])->values();

        $total = (clone $tasks)->count();
        $escalated = (clone $tasks)->where('aras_eskalasi', '>', 0)->count();

        // Bottleneck: stages ranked by average time, then by backlog.
        $bottlenecks = $perStage->sortByDesc(fn ($s) => [($s['purata_jam'] ?? 0), $s['tertunggak']])->take(5)->values()->all();

        $levels = DB::table('wf_eskalasi_log')->whereIn('id_tugasan', (clone $tasks)->select('wf_tugasan.id'))
            ->selectRaw('aras, count(*) as jumlah')->groupBy('aras')->orderBy('aras')->pluck('jumlah', 'aras')->map(fn ($n) => (int) $n)->all();

        return [
            'tempoh' => ['dari' => $from?->toDateString(), 'hingga' => $to?->toDateString()],
            'ikut_peringkat' => $perStage->all(),
            'kesesakan' => $bottlenecks,
            'eskalasi' => [
                'jumlah_tugasan' => $total,
                'dieskalasi' => $escalated,
                'kadar_peratus' => $total > 0 ? round($escalated / $total * 100, 1) : 0.0,
                'ikut_aras' => $levels,
            ],
            'tertunggak_ikut_pelulus' => $this->backlog($flowCode),
        ];
    }

    /** @return list<array{penerima: string, label: string, jumlah: int, lewat: int}> */
    private function backlog(?string $flowCode = null): array
    {
        $visible = Instans::query()->select('id')->when($flowCode, fn ($q) => $q->where('kod_aliran', $flowCode));

        return Tugasan::query()->whereIn('id_instans', $visible)->whereIn('status', [Tugasan::MENUNGGU, Tugasan::DITANGGUH])
            ->selectRaw('penerima_kunci, jenis_penerima, nilai_penerima, count(*) as jumlah, sum(case when tarikh_akhir_sla < ? then 1 else 0 end) as lewat', [now()])
            ->groupBy('penerima_kunci', 'jenis_penerima', 'nilai_penerima')->orderByDesc('jumlah')->toBase()->get()
            ->map(fn ($r) => [
                'penerima' => $r->penerima_kunci,
                'jenis' => $r->jenis_penerima,
                'label' => $this->assignees->label($r->jenis_penerima, $r->nilai_penerima),
                'jumlah' => (int) $r->jumlah,
                'lewat' => (int) $r->lewat,
            ])->values()->all();
    }

    /** SLA display helper: remaining working minutes (negative = overdue). */
    public function slaLeft(Tugasan $task): ?int
    {
        return $task->tarikh_akhir_sla ? $this->calendar->workingMinutesBetween(now(), $task->tarikh_akhir_sla) : null;
    }
}
