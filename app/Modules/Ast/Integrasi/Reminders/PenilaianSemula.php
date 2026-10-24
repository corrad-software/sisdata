<?php

namespace App\Modules\Ast\Integrasi\Reminders;

use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ntf\Contracts\Reminder;
use App\Modules\Ntf\Contracts\ReminderSource;
use Carbon\CarbonImmutable;

/**
 * SRS-AST-19: revaluation due when tarikh_nilai_semasa + the category's
 * tempoh_ambang_penilaian_bulan is reached. Assets never valued fall due from
 * their registration date. No value is put in the notification (SRS-NTF-19).
 */
class PenilaianSemula implements ReminderSource
{
    public const TYPE = 'ast.penilaian_semula';

    public function type(): string
    {
        return self::TYPE;
    }

    public function defaultDays(): array
    {
        return [60, 30, 0];
    }

    public function due(int $daysBefore, CarbonImmutable $today): iterable
    {
        $target = $today->addDays($daysBefore)->toDateString();
        $rows = AsetFizikal::query()->withoutGlobalScope('access')->with('pegawai')->join('aset_kategori as k', 'k.id', '=', 'aset_fizikal.id_kategori')
            ->where('aset_fizikal.status_arkib', false)->where('aset_fizikal.sintetik', false)
            ->whereRaw("(COALESCE(aset_fizikal.tarikh_nilai_semasa, aset_fizikal.created_at::date) + (k.tempoh_ambang_penilaian_bulan || ' months')::interval)::date = ?::date", [$target])
            ->select('aset_fizikal.*')->get();
        foreach ($rows as $a) {
            yield new Reminder('aset_penilaian:'.$a->id.':'.$target, $a->pegawai ?? [], [
                'nama_aset' => $a->nama_aset, 'no_rujukan' => $a->no_rujukan_aset,
                'tarikh_nilai' => $a->tarikh_nilai_semasa?->format('d/m/Y') ?? 'belum pernah', 'tarikh' => CarbonImmutable::parse($target)->format('d/m/Y'), 'baki_hari' => $daysBefore,
            ], '/admin/ast/aset/'.$a->id);
        }
    }
}
