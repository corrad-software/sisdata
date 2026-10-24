<?php

namespace App\Modules\Tsu\Reminders;

use App\Modules\Ntf\Contracts\Reminder;
use App\Modules\Ntf\Contracts\ReminderSource;
use App\Modules\Tsu\Models\UrusanDokumen;
use App\Modules\Tsu\Models\UrusanTanah;
use App\Modules\Tsu\Support\Lalai;
use Carbon\CarbonImmutable;

/** SRS-TSU-23: a document of an open urusan (e.g. valuation report, approval letter) is about to expire. */
class DokumenHampirLuput implements ReminderSource
{
    public const TYPE = 'tsu.dokumen_hampir_luput';

    public function type(): string
    {
        return self::TYPE;
    }

    public function defaultDays(): array
    {
        return [30, 14, 7];
    }

    public function due(int $daysBefore, CarbonImmutable $today): iterable
    {
        $docs = UrusanDokumen::query()->whereDate('tarikh_luput', $today->addDays($daysBefore)->toDateString())->get();
        $urusan = UrusanTanah::query()->withoutGlobalScope('access')->with('pegawai')->whereIn('id', $docs->pluck('urusan_id'))->whereIn('status', Lalai::URUSAN_TERBUKA)->where('sintetik', false)->get()->keyBy('id');
        foreach ($docs as $d) {
            $u = $urusan[$d->urusan_id] ?? null;
            if ($u === null) {
                continue;
            }
            yield new Reminder('urusan_dokumen:'.$d->id, $u->pegawai ? [$u->pegawai] : [], [
                'no_rujukan' => $u->no_rujukan_mais, 'dokumen' => Lalai::KATEGORI_DOKUMEN[$d->kategori_dokumen][0] ?? $d->kategori_dokumen,
                'tarikh_luput' => $d->tarikh_luput?->format('d/m/Y'), 'baki_hari' => $daysBefore,
            ], '/admin/tsu/urusan/'.$u->id.'?tab=dokumen');
        }
    }
}
