<?php

namespace App\Modules\Wfe\Http;

use App\Models\User;
use App\Modules\Wfe\Models\Instans;
use App\Modules\Wfe\Models\Sejarah;
use App\Modules\Wfe\Models\Tugasan;
use App\Modules\Wfe\Services\Assignees;
use App\Modules\Wfe\Services\WorkingCalendar;

/** JSON shapes shared by the WFE controllers. */
class Present
{
    public function __construct(private Assignees $assignees, private WorkingCalendar $calendar) {}

    /**
     * @param  array<string, int|null>|null  $actingKeys  the viewer's keys (for boleh_bertindak / bagi_pihak)
     * @return array<string, mixed>
     */
    public function task(Tugasan $task, ?User $viewer = null, ?array $actingKeys = null): array
    {
        $instance = $task->instans;
        $stage = $task->peringkat;
        $onBehalfId = $actingKeys[$task->penerima_kunci] ?? $task->id_pelulus_asal;
        $canAct = $actingKeys !== null && array_key_exists($task->penerima_kunci, $actingKeys) && $task->isOpen()
            && ($task->jenis === Tugasan::SUSULAN || $instance->status === Instans::BERJALAN);

        return [
            'id' => $task->id,
            'jenis' => $task->jenis,
            'tajuk' => $task->tajuk ?? $instance->tajuk,
            'status' => $task->status,
            'keputusan' => $task->keputusan,
            'instans' => [
                'id' => $instance->id,
                'tajuk' => $instance->tajuk,
                'kod_aliran' => $instance->kod_aliran,
                'status' => $instance->status,
                'pemohon' => $instance->pemohon->name,
                'pautan' => $instance->pautan,
            ],
            'peringkat' => $stage ? [
                'kod' => $stage->kod,
                'nama' => $stage->nama_peringkat,
                'perlu_mfa' => $stage->perlu_mfa,
                'perlu_justifikasi' => $stage->perlu_justifikasi,
                'mod_kelulusan' => $stage->mod_kelulusan,
            ] : null,
            'penerima' => ['kunci' => $task->penerima_kunci, 'label' => $this->assignees->label($task->jenis_penerima, $task->nilai_penerima)],
            'bagi_pihak' => $onBehalfId ? User::query()->whereKey($onBehalfId)->value('name') : null,
            'tarikh_tugasan' => $task->created_at->toIso8601String(),
            'tarikh_akhir_sla' => $task->tarikh_akhir_sla?->toIso8601String(),
            'baki_sla_minit' => $task->tarikh_akhir_sla && $task->isOpen() ? $this->calendar->workingMinutesBetween(now(), $task->tarikh_akhir_sla) : null,
            'minit_sehari' => $this->calendar->minutesPerWorkingDay(),
            'ditangguh_hingga' => $task->ditangguh_hingga?->toIso8601String(),
            'aras_eskalasi' => $task->aras_eskalasi,
            'diselesaikan_oleh' => $task->penyelesai?->name,
            'diselesaikan_pada' => $task->diselesaikan_pada?->toIso8601String(),
            'boleh_bertindak' => $canAct && (! $viewer || ($viewer->id !== $instance->id_pemohon || $task->jenis === Tugasan::SUSULAN)),
        ];
    }

    /** @return array<string, mixed> */
    public function instance(Instans $instance, bool $withHistory = false): array
    {
        $data = [
            'id' => $instance->id,
            'tajuk' => $instance->tajuk,
            'kod_aliran' => $instance->kod_aliran,
            'nama_aliran' => $instance->definisi->nama,
            'no_versi' => $instance->no_versi_definisi,
            'modul' => $instance->modul,
            'jenis_subjek' => $instance->jenis_subjek,
            'id_rekod' => $instance->id_rekod,
            'pautan' => $instance->pautan,
            'kod_daerah' => $instance->kod_daerah,
            'status' => $instance->status,
            'keadaan' => $instance->keadaan,
            'peringkat_semasa' => $instance->peringkat?->nama_peringkat,
            'pemohon' => ['id' => $instance->id_pemohon, 'nama' => $instance->pemohon->name],
            'tarikh_mula' => $instance->tarikh_mula->toIso8601String(),
            'tarikh_tamat' => $instance->tarikh_tamat?->toIso8601String(),
            'ulasan_keputusan' => $instance->ulasan_keputusan,
        ];

        if ($withHistory) {
            $data['konteks'] = $instance->konteks;
            $data['sejarah'] = $instance->sejarah()->with('pengguna:id,name', 'bagiPihak:id,name')->get()
                ->map(fn (Sejarah $s) => $this->history($s))->all();
            $data['tugasan'] = $instance->tugasan()->with(['peringkat', 'penyelesai:id,name'])->get()->map(fn (Tugasan $t) => [
                'id' => $t->id,
                'jenis' => $t->jenis,
                'tajuk' => $t->tajuk,
                'peringkat' => $t->peringkat?->nama_peringkat,
                'penerima' => $this->assignees->label($t->jenis_penerima, $t->nilai_penerima),
                'status' => $t->status,
                'keputusan' => $t->keputusan,
                'tarikh_akhir_sla' => $t->tarikh_akhir_sla?->toIso8601String(),
                'aras_eskalasi' => $t->aras_eskalasi,
                'diselesaikan_oleh' => $t->penyelesai?->name,
            ])->all();
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public function history(Sejarah $s): array
    {
        return [
            'id' => $s->id,
            'peristiwa' => $s->peristiwa,
            'peringkat' => $s->nama_peringkat,
            'pengguna' => $s->pengguna?->name,
            'bagi_pihak' => $s->bagiPihak?->name,
            'peranan_semasa' => $s->peranan_semasa,
            'justifikasi' => $s->justifikasi,
            'alamat_ip' => $s->alamat_ip,
            'mfa_pada' => $s->mfa_pada?->toIso8601String(),
            'butiran' => $s->butiran,
            'tarikh_masa' => $s->tarikh_masa->toIso8601String(),
        ];
    }
}
