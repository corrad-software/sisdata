<?php

namespace App\Modules\Ptn\Services;

use App\Models\User;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Ptn\Models\IsuPemantauan;
use App\Modules\Ptn\Models\TindakanPembetulan;
use App\Modules\Ptn\Support\Akses;
use App\Modules\Ptn\Support\AliranPtn;
use App\Modules\Ptn\Support\KodWarna;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS-PTN-13/14/19: corrective actions per issue, colour-coded against the
 * configured deadline thresholds; completion verified through MOD-WFE
 * (ptn.pengesahan_tindakan); an issue closes when all its actions are
 * verified. Overdue actions notify the officer and their supervisor.
 */
class TindakanService
{
    public function __construct(private KodWarna $warna, private WorkflowEngine $workflow, private Notifier $notifier, private Akses $akses) {}

    /** @param array{langkah_tindakan: string, id_pegawai_bertanggungjawab: int, tarikh_sasaran: string} $d */
    public function cipta(IsuPemantauan $isu, array $d, User $by): TindakanPembetulan
    {
        if (! $isu->terbuka()) {
            throw ValidationException::withMessages(['isu' => __('ptn::ptn.isu_ditutup')]);
        }
        $t = new TindakanPembetulan;
        $t->forceFill([
            'id_isu' => $isu->id, 'kod_daerah' => $isu->kod_daerah, 'kod_mukim' => $isu->kod_mukim, 'klasifikasi' => $isu->klasifikasi,
            'langkah_tindakan' => $d['langkah_tindakan'], 'id_pegawai_bertanggungjawab' => $d['id_pegawai_bertanggungjawab'],
            'tarikh_sasaran' => $d['tarikh_sasaran'], 'tarikh_sasaran_asal' => $d['tarikh_sasaran'], 'status_kemajuan' => 'belum_mula',
            'id_pencipta' => $by->id, 'sintetik' => (bool) $isu->sintetik,
        ]);
        $t->kod_warna_status = $this->warnaUntuk($t);
        $t->save();
        if ($isu->status_isu === 'terbuka') {
            $isu->forceFill(['status_isu' => 'dalam_tindakan'])->save();
        }
        if ($t->pegawai) {
            $this->notifier->send('ptn.tindakan_ditugaskan', $t->pegawai, ['no_rujukan' => $isu->no_rujukan, 'langkah' => mb_substr($t->langkah_tindakan, 0, 120), 'tarikh_sasaran' => $t->tarikh_sasaran->format('d/m/Y')], '/admin/ptn/isu/'.$isu->id);
        }

        return $t;
    }

    /**
     * Progress update; moving the target date needs a justification (kept).
     *
     * @param  array{status_kemajuan?: string, peratus_kemajuan?: int, catatan_kemajuan?: string|null, tarikh_sasaran?: string, justifikasi_lanjutan?: string|null, id_pegawai_bertanggungjawab?: int}  $d
     */
    public function kemaskini(TindakanPembetulan $t, array $d, User $by): TindakanPembetulan
    {
        if (in_array($t->status_kemajuan, ['selesai', 'menunggu_pengesahan'], true)) {
            throw ValidationException::withMessages(['tindakan' => __('ptn::ptn.tindakan_dikunci')]);
        }
        if (isset($d['tarikh_sasaran']) && $d['tarikh_sasaran'] !== $t->tarikh_sasaran->toDateString()) {
            if (empty($d['justifikasi_lanjutan'])) {
                throw ValidationException::withMessages(['justifikasi_lanjutan' => __('ptn::ptn.justifikasi_wajib')]);
            }
            $t->tarikh_sasaran = Carbon::parse($d['tarikh_sasaran']);
            $t->justifikasi_lanjutan = trim(($t->justifikasi_lanjutan ? $t->justifikasi_lanjutan."\n" : '').now()->format('d/m/Y').': '.$d['justifikasi_lanjutan']);
        }
        foreach (['status_kemajuan', 'peratus_kemajuan', 'catatan_kemajuan', 'id_pegawai_bertanggungjawab'] as $f) {
            if (array_key_exists($f, $d) && $f !== 'status_kemajuan') {
                $t->setAttribute($f, $d[$f]);
            }
        }
        if (isset($d['status_kemajuan']) && in_array($d['status_kemajuan'], ['belum_mula', 'dalam_tindakan'], true)) {
            $t->status_kemajuan = $d['status_kemajuan'];
        }
        $t->kod_warna_status = $this->warnaUntuk($t);
        $t->save();

        return $t;
    }

    /** Officer reports the action done → verification through MOD-WFE. */
    public function mohonPengesahan(TindakanPembetulan $t, ?string $catatan, User $by): TindakanPembetulan
    {
        if ($t->status_kemajuan === 'selesai' || $t->status_kemajuan === 'menunggu_pengesahan') {
            throw ValidationException::withMessages(['tindakan' => __('ptn::ptn.tindakan_dikunci')]);
        }
        $this->workflow->start(AliranPtn::TINDAKAN, AliranPtn::SUBJEK_TINDAKAN, $t->id, $by, [
            'kod_daerah' => $t->kod_daerah, 'tahap_keterukan' => $t->isu->tahap_keterukan ?? null, 'pautan' => '/admin/ptn/isu/'.$t->id_isu,
        ], 'Pengesahan tindakan pembetulan '.($t->isu->no_rujukan ?? ''));
        $t->forceFill([
            'status_kemajuan' => 'menunggu_pengesahan', 'peratus_kemajuan' => 100, 'tarikh_siap' => now()->toDateString(),
            'catatan_kemajuan' => $catatan ?? $t->catatan_kemajuan,
        ]);
        $t->kod_warna_status = $this->warnaUntuk($t);
        $t->save();

        return $t;
    }

    /** WorkflowCompleted for ptn.pengesahan_tindakan. */
    public function keputusan(TindakanPembetulan $t, string $outcome, ?int $decidedBy, ?string $remarks): void
    {
        if ($outcome === 'lulus') {
            $t->forceFill(['status_kemajuan' => 'selesai', 'id_pengesah' => $decidedBy, 'tarikh_disahkan' => now(), 'kod_warna_status' => 'hijau'])->save();
            $isu = $t->isu;
            if ($isu && ! $isu->tindakan()->where('status_kemajuan', '!=', 'selesai')->exists()) {
                $isu->forceFill(['status_isu' => 'ditutup', 'tarikh_ditutup' => now()->toDateString()])->save();
            }
        } else {
            $t->forceFill(['status_kemajuan' => 'dalam_tindakan', 'tarikh_siap' => null, 'catatan_kemajuan' => trim(($t->catatan_kemajuan ?? '')."\nPengesahan ditolak: ".($remarks ?? ''))]);
            $t->kod_warna_status = $this->warnaUntuk($t);
            $t->save();
        }
    }

    public function warnaUntuk(TindakanPembetulan $t): string
    {
        return $this->warna->untukTarikh($t->tarikh_sasaran, 'tindakan', $t->status_kemajuan === 'selesai' || $t->status_kemajuan === 'menunggu_pengesahan');
    }

    /**
     * Daily: refresh stored colours; overdue (red) actions notify the
     * responsible officer and their supervisors (SRS-PTN-19). Upcoming
     * deadlines are reminders through MOD-NTF (Reminders\TindakanHampirTamat).
     *
     * @return array{dikemas_kini: int, lewat: int}
     */
    public function semakTertunggak(): array
    {
        $updated = 0;
        $late = 0;
        TindakanPembetulan::query()->withoutGlobalScope('access')->with(['pegawai', 'isu' => fn ($q) => $q->withoutGlobalScope('access')])
            ->whereIn('status_kemajuan', ['belum_mula', 'dalam_tindakan'])->orderBy('id')
            ->chunkById(500, function ($rows) use (&$updated, &$late) {
                foreach ($rows as $t) {
                    $c = $this->warnaUntuk($t);
                    if ($c !== $t->kod_warna_status) {
                        DB::table('tindakan_pembetulan')->where('id', $t->id)->update(['kod_warna_status' => $c, 'updated_at' => now()]);
                        $updated++;
                    }
                    if ($c !== 'merah') {
                        continue;
                    }
                    $late++;
                    $to = [];
                    if ($t->pegawai) {
                        $to[$t->pegawai->id] = $t->pegawai;
                    }
                    foreach ($this->akses->penyeliaKepada($t->pegawai) as $s) {
                        $to[$s->id] = $s;
                    }
                    $this->notifier->send('ptn.tindakan_lewat', array_values($to), [
                        'no_rujukan' => $t->isu->no_rujukan ?? '', 'langkah' => mb_substr($t->langkah_tindakan, 0, 120),
                        'tarikh_sasaran' => $t->tarikh_sasaran->format('d/m/Y'), 'hari_lewat' => (int) $t->tarikh_sasaran->diffInDays(now()->startOfDay()),
                    ], '/admin/ptn/isu/'.$t->id_isu, 'ptn.lewat:'.$t->id.':'.now()->toDateString());
                }
            });

        return ['dikemas_kini' => $updated, 'lewat' => $late];
    }
}
