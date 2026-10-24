<?php

namespace App\Modules\Adu\Services;

use App\Modules\Adu\Contracts\ReporterMessenger;
use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Models\MaklumanPengadu;
use App\Modules\Adu\Models\Pengadu;
use App\Modules\Adu\Support\Lalai;
use Throwable;

/**
 * SRS-ADU-05/22: notices to the reporter on each main status change
 * (diterima, ditugaskan, siasatan, selesai) and for the "bukan tanah MAIS"
 * reply. Anonymous complaints get none. Content carries only the reference
 * number and public status — never internal detail. Every attempt is logged
 * (makluman_pengadu) and recorded in the timeline as communication.
 */
class MaklumPengadu
{
    public const PERISTIWA = ['diterima', 'ditugaskan', 'siasatan', 'selesai', 'bukan_tanah_mais', 'dibuka_semula', 'komunikasi'];

    public function __construct(private ReporterMessenger $messenger, private Kronologi $kronologi) {}

    /** @return bool true when delivered on at least one channel */
    public function hantar(Aduan $aduan, string $peristiwa, ?string $teksTambahan = null): bool
    {
        if ($aduan->status_tanpa_nama) {
            return false;
        }
        /** @var Pengadu|null $pengadu */
        $pengadu = Pengadu::query()->where('aduan_id', $aduan->id)->first();
        if ($pengadu === null || $pengadu->saluran_makluman === 'tiada') {
            return false;
        }

        $subject = (string) __('adu::adu.makluman.subjek', ['no' => $aduan->no_rujukan_aduan]);
        $body = (string) __('adu::adu.makluman.'.$peristiwa, [
            'no' => $aduan->no_rujukan_aduan,
            'status' => Lalai::STATUS_AWAM[$aduan->status] ?? $aduan->status,
        ]);
        if ($teksTambahan !== null && $teksTambahan !== '') {
            $body .= "\n\n".$teksTambahan;
        }
        $body .= "\n\n".__('adu::adu.makluman.penutup', ['no' => $aduan->no_rujukan_aduan]);

        $delivered = false;
        $channels = $pengadu->saluran_makluman === 'sms' ? ['sms' => $pengadu->no_telefon, 'emel' => $pengadu->emel] : ['emel' => $pengadu->emel, 'sms' => $pengadu->no_telefon];
        foreach ($channels as $channel => $to) {
            if ($to === null || $to === '') {
                continue;
            }
            try {
                $channel === 'emel' ? $this->messenger->email($to, $subject, $body) : $this->messenger->sms($to, $subject.' — '.$body);
                MaklumanPengadu::query()->create(['aduan_id' => $aduan->id, 'peristiwa' => $peristiwa, 'saluran' => $channel, 'status' => 'dihantar']);
                $delivered = true;
                break;
            } catch (Throwable $e) {
                report($e);
                MaklumanPengadu::query()->create(['aduan_id' => $aduan->id, 'peristiwa' => $peristiwa, 'saluran' => $channel, 'status' => 'gagal', 'ralat' => mb_substr($e->getMessage(), 0, 500)]);
            }
        }

        $this->kronologi->rekod($aduan, 'komunikasi', (string) __('adu::adu.kronologi.makluman', [
            'peristiwa' => __('adu::adu.peristiwa.'.$peristiwa), 'status' => $delivered ? __('adu::adu.dihantar') : __('adu::adu.gagal'),
        ]), komunikasi: true, data: ['peristiwa' => $peristiwa, 'berjaya' => $delivered]);

        return $delivered;
    }
}
