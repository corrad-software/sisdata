<?php

namespace App\Modules\Tkt\Services;

use App\Models\User;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Tkt\Models\LaporanBulanan;
use App\Modules\Tkt\Support\Eksport;
use App\Modules\Tkt\Support\PenggunaSistem;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * SRS-TKT-19 / §18.6: the monthly support report is generated
 * automatically (`tkt:laporan-bulanan`, 1st of each month for the previous
 * month) from the ticket data; the figures are kept in
 * tkt_laporan_bulanan and the PDF + Excel copies are filed in MOD-DMS.
 * It can be re-generated / downloaded on demand from the report screen.
 */
class LaporanBulananService
{
    public function __construct(private Application $app, private Analitik $analitik) {}

    public function tajuk(string $bulan): string
    {
        return (string) __('tkt::tkt.laporan.bulanan_tajuk', ['bulan' => $bulan]);
    }

    public function jana(string $bulan, ?User $oleh = null): LaporanBulanan
    {
        $report = $this->analitik->laporanBulanan($bulan);
        $title = $this->tajuk($bulan);
        $footer = (string) __('tkt::tkt.laporan.dijana', ['masa' => now()->setTimezone(KalendarSokongan::TZ)->format('d/m/Y H:i')]);
        $row = LaporanBulanan::query()->firstOrNew(['bulan' => $bulan]);
        $row->fill(['ringkasan' => $report['ringkasan'], 'dijana_pada' => now(), 'id_penjana' => $oleh?->id, 'ralat' => null]);
        $row->save();

        $errors = [];
        foreach (['pdf' => fn () => Eksport::pdf($title, $report['seksyen'], $footer), 'xlsx' => fn () => Eksport::xlsx($title, $report['seksyen'], $footer)] as $ext => $render) {
            try {
                $path = tempnam(sys_get_temp_dir(), 'tkt').'.'.$ext;
                file_put_contents($path, $render());
                $file = new UploadedFile($path, 'laporan-sokongan-'.$bulan.'.'.$ext, $ext === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
                $id = $this->app->make(DocumentStore::class)->store($file, 'tkt_laporan_bulanan', $row->id, 'laporan', $oleh ?? PenggunaSistem::akaun(), 'dalaman', []);
                $row->forceFill([$ext === 'pdf' ? 'id_dokumen_pdf' : 'id_dokumen_xlsx' => $id]);
                @unlink($path);
            } catch (Throwable $e) {
                report($e);
                $errors[] = strtoupper($ext).': '.$e->getMessage();
            }
        }
        $row->forceFill(['ralat' => $errors === [] ? null : mb_substr(implode(' | ', $errors), 0, 500)])->save();

        try {
            $this->app->make(Notifier::class)->send('tkt.laporan_bulanan', [], [
                'bulan' => $bulan,
                'peratus_selesai' => $report['ringkasan']['peratus_selesai'],
                'dibuka' => $report['ringkasan']['dibuka'],
            ], '/admin/tkt/laporan?bulan='.$bulan, 'tkt:laporan:'.$bulan);
        } catch (Throwable $e) {
            report($e);
        }

        return $row;
    }
}
