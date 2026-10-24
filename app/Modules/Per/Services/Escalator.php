<?php

namespace App\Modules\Per\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Per\Models\PeringkatPemerolehan;
use App\Modules\Wfe\Contracts\ApproverDirectory;
use Throwable;

/**
 * SRS-PER-26: a case that stays in a stage longer than the stage's target is
 * escalated automatically — the stage is flagged and the responsible officer
 * and his/her head of division (WFE's ApproverDirectory supervisor
 * hierarchy, plus the admin-configured recipients of `per.kes_lewat`) are
 * notified. The approval stage itself is escalated by WFE's own SLA engine
 * (wfe:semak-sla), so it is skipped here.
 */
class Escalator
{
    public const TYPE = 'per.kes_lewat';

    public function __construct(
        private Notifier $notifier,
        private ApproverDirectory $directory,
        private AuditLogger $audit,
    ) {}

    public function run(): int
    {
        $count = 0;
        PeringkatPemerolehan::query()
            ->with(['pemerolehan' => fn ($q) => $q->withoutGlobalScope('access')->with('saluran'), 'pegawai'])
            ->where('status_peringkat', PeringkatPemerolehan::SEMASA)
            ->where('status_eskalasi', 'tiada')
            ->where('jenis', '!=', 'kelulusan')
            ->whereRaw('(current_date - tarikh_masuk::date) > tempoh_sasaran_hari')
            ->whereHas('pemerolehan', fn ($q) => $q->withoutGlobalScope('access')->whereIn('status_pemerolehan', Pemerolehan::AKTIF))
            ->orderBy('id')
            ->chunkById(200, function ($stages) use (&$count) {
                foreach ($stages as $stage) {
                    $this->escalate($stage, $stage->pemerolehan);
                    $count++;
                }
            });

        return $count;
    }

    private function escalate(PeringkatPemerolehan $stage, Pemerolehan $case): void
    {
        $stage->forceFill(['status_eskalasi' => 'dieskalasi', 'tarikh_eskalasi' => now()])->save();

        $officer = $stage->pegawai ?? ($case->id_pegawai_bertanggungjawab ? User::query()->find($case->id_pegawai_bertanggungjawab) : null);
        $recipients = [];
        if ($officer !== null) {
            $recipients[$officer->id] = $officer;
            foreach ($this->directory->supervisorsOf($officer) as $head) {
                $recipients[$head->id] = $head;
            }
        }

        $late = (int) $stage->hariBerjalan() - $stage->tempoh_sasaran_hari;
        try {
            $this->notifier->send(self::TYPE, array_values($recipients), [
                'no_kes' => $case->no_kes,
                'saluran' => $case->saluran->nama_saluran,
                'peringkat' => $stage->nama_peringkat,
                'hari_lewat' => $late,
                'tempoh_sasaran_hari' => $stage->tempoh_sasaran_hari,
            ], $case->pautan(), 'per.eskalasi:'.$stage->id);
        } catch (Throwable $e) {
            report($e);
        }

        $this->audit->record('eskalasi', 'per', 'pemerolehan', $case->id, meta: [
            'no_kes' => $case->no_kes, 'peringkat' => $stage->kod_peringkat, 'hari_lewat' => $late,
            'penerima' => array_keys($recipients), 'klasifikasi' => 'sulit',
        ]);
    }
}
