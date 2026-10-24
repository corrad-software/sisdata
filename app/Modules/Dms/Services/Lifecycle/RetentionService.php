<?php

namespace App\Modules\Dms\Services\Lifecycle;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Dms\Models\Dokumen;
use App\Modules\Dms\Models\DokumenArkib;
use App\Modules\Dms\Models\DokumenKategori;
use App\Modules\Dms\Services\DmsSettings;
use App\Modules\Dms\Services\Storage\DocumentVault;
use App\Modules\Ntf\Contracts\Notifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * SRS-DMS-15 (expiry + reminders) and SRS-DMS-21/22 (retention → automatic
 * archive). Archiving moves the encrypted objects to the arkib/ prefix and
 * keeps every metadata/text column, so archived documents stay searchable.
 */
class RetentionService
{
    public function __construct(
        private DocumentVault $vault,
        private DmsSettings $settings,
        private AuditLogger $audit,
        private Notifier $notifier,
    ) {}

    /**
     * Archive every document whose active retention period has ended.
     *
     * @return int number archived
     */
    public function archiveDue(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $count = 0;

        DokumenKategori::query()->whereNotNull('tempoh_retensi_bulan')->each(function (DokumenKategori $kategori) use ($now, &$count) {
            $cutoff = $now->subMonths((int) $kategori->tempoh_retensi_bulan);
            Dokumen::query()
                ->where('kategori_id', $kategori->id)
                ->whereIn('status', ['aktif', 'tamat'])
                ->whereRaw('coalesce(tarikh_dokumen, created_at::date) <= ?', [$cutoff->toDateString()])
                ->orderBy('created_at')
                ->each(function (Dokumen $doc) use (&$count) {
                    $this->archive($doc, null, 'retensi_auto');
                    $count++;
                });
        });

        return $count;
    }

    public function archive(Dokumen $doc, ?User $by, string $reason = 'manual'): void
    {
        $doc->loadMissing('kategori', 'versi');
        $previous = $doc->status;
        $moves = [];

        foreach ($doc->versi as $version) {
            if ($version->kunci_storan !== null && str_starts_with($version->kunci_storan, 'dokumen/')) {
                $moves[$version->id] = [$version->kunci_storan, 'arkib/'.substr($version->kunci_storan, strlen('dokumen/'))];
            }
        }
        foreach ($moves as [$from, $to]) {
            $this->vault->move($from, $to);
        }

        DB::transaction(function () use ($doc, $by, $reason, $moves) {
            foreach ($doc->versi as $version) {
                if (isset($moves[$version->id])) {
                    $version->forceFill(['kunci_storan' => $moves[$version->id][1]])->save();
                }
            }
            $months = $doc->kategori->tempoh_arkib_bulan;
            DokumenArkib::query()->create([
                'dokumen_id' => $doc->id,
                'tarikh_arkib' => now(),
                'tarikh_layak_lupus' => $months !== null ? now()->addMonths($months)->toDateString() : null,
                'sebab' => $reason,
                'diarkib_oleh' => $by?->id,
            ]);
            $doc->forceFill(['status' => 'diarkib', 'tarikh_arkib' => now()])->save();
        });

        $this->audit->record('arkib', 'dms', 'dokumen', $doc->id, changes: ['status' => [$previous, 'diarkib']], meta: [
            'sebab' => $reason, 'objek_dipindah' => count($moves),
        ]);
    }

    /**
     * SRS-DMS-15: mark expired documents "tamat" and remind process owners
     * of documents expiring within the warning period (category override or
     * dms.amaran_luput_hari).
     *
     * @return array{tamat: int, peringatan: int}
     */
    public function processExpiry(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $today = $now->toDateString();

        $expired = 0;
        Dokumen::query()->where('status', 'aktif')->whereNotNull('tarikh_luput')->where('tarikh_luput', '<', $today)
            ->each(function (Dokumen $doc) use (&$expired) {
                $doc->forceFill(['status' => 'tamat'])->save();
                $expired++;
            });

        $reminded = 0;
        $default = $this->settings->expiryWarnDays();
        Dokumen::query()->with(['kategori', 'pemilikProses', 'pencipta'])
            ->where('status', 'aktif')
            ->whereNotNull('tarikh_luput')
            ->where('tarikh_luput', '>=', $today)
            ->whereNull('amaran_luput_pada')
            ->each(function (Dokumen $doc) use ($now, $default, &$reminded) {
                $days = $doc->kategori->tempoh_amaran_luput_hari ?? $default;
                $expiry = CarbonImmutable::parse($doc->tarikh_luput);
                if ($expiry->gt($now->addDays($days)->endOfDay())) {
                    return;
                }
                $owner = $doc->pemilikProses ?? $doc->pencipta;
                if ($owner !== null) {
                    $this->notifier->send('dms.luput_hampir', $owner, [
                        'no_rujukan' => $doc->no_rujukan,
                        'tajuk' => $doc->klasifikasi_keselamatan === 'rahsia' || $doc->klasifikasi_keselamatan === 'sulit' ? $doc->no_rujukan : $doc->tajuk,
                        'tarikh_luput' => $expiry->format('d/m/Y'),
                        'baki_hari' => (int) $now->startOfDay()->diffInDays($expiry->startOfDay()),
                    ], '/admin/dms/'.$doc->id, 'dms.luput_hampir:'.$doc->id);
                }
                $doc->forceFill(['amaran_luput_pada' => now()])->saveQuietly();
                $reminded++;
            });

        return ['tamat' => $expired, 'peringatan' => $reminded];
    }
}
