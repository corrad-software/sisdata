<?php

namespace App\Modules\Pjk\Services;

use App\Models\User;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Pjk\Contracts\ESewa;
use App\Modules\Pjk\Contracts\GrpMais;
use App\Modules\Pjk\Mail\ResitMail;
use App\Modules\Pjk\Models\Bayaran;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\PenghantaranResit;
use App\Modules\Pjk\Models\Penyewa;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Pjk\Models\Resit;
use App\Modules\Pjk\Support\Dokumen;
use App\Modules\Pjk\Support\Kod;
use App\Modules\Pjk\Support\Luaran;
use App\Modules\Pjk\Support\Penomboran;
use App\Modules\Pjk\Support\Wang;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * SRS-PJK-17: official receipts. The serial number is gapless and
 * sequential (Penomboran inside the payment transaction); the receipt row is
 * immutable at DB level; a cancellation is a separate credit receipt. After
 * commit the receipt is rendered to PDF, stored in DMS against the contract,
 * e-mailed to the payer and posted to GRP MAIS / e-Sewa.
 */
class ResitService
{
    public function __construct(private DocumentStore $documents) {}

    public function issue(Bayaran $bayaran, Kontrak $kontrak): Resit
    {
        $email = $bayaran->emel_pembayar ?: Penyewa::query()->whereKey($kontrak->penyewa_id)->value('emel');

        return Resit::query()->create([
            'no_resit' => Penomboran::next('resit'),
            'jenis' => 'resit',
            'bayaran_id' => $bayaran->id,
            'kontrak_id' => $kontrak->id,
            'amaun' => $bayaran->amaun,
            'tarikh' => ($bayaran->tarikh_pengesahan ?? Carbon::today())->format('Y-m-d'),
            'emel_penerima' => $email ?: null,
        ]);
    }

    public function credit(Resit $original): Resit
    {
        return Resit::query()->create([
            'no_resit' => Penomboran::next('resit_kredit'),
            'jenis' => 'resit_kredit',
            'bayaran_id' => $original->bayaran_id,
            'kontrak_id' => $original->kontrak_id,
            'amaun' => Wang::dec(-Wang::sen($original->amaun)),
            'tarikh' => Carbon::today()->format('Y-m-d'),
            'id_resit_asal' => $original->id,
            'emel_penerima' => $original->emel_penerima,
        ]);
    }

    /** PDF → DMS, e-mail to payer, GRP + e-Sewa. Failures are logged, never undo the payment. */
    public function deliver(Resit $resit): void
    {
        $kontrak = Kontrak::query()->withoutGlobalScope('access')->find($resit->kontrak_id);
        if ($kontrak === null) {
            return;
        }
        $pdf = null;
        try {
            $pdf = $this->pdf($resit, $kontrak);
            if ($resit->id_dokumen === null) {
                $uploader = $this->uploader($kontrak);
                if ($uploader !== null) {
                    $id = $this->documents->store(Dokumen::file($pdf, str_replace('/', '-', $resit->no_resit).'.pdf'), Kontrak::SUBJECT, $kontrak->id, 'resit', $uploader, 'sulit', ['no_resit' => $resit->no_resit, 'jenis' => $resit->jenis]);
                    $resit->forceFill(['id_dokumen' => $id])->save();
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        $this->email($resit, $pdf);

        try {
            $date = $resit->tarikh->format('Y-m-d');
            Luaran::call('pjk_grp', 'post_resit', fn () => app(GrpMais::class)->postReceipt([
                'no_resit' => $resit->no_resit, 'jenis' => $resit->jenis, 'no_kontrak' => (string) $kontrak->no_kontrak,
                'amaun' => $resit->amaun, 'tarikh' => $date, 'kod_hasil' => 'HASIL_SEWA_'.strtoupper((string) $kontrak->bahagian),
            ]), ['no_resit' => $resit->no_resit]);
            Luaran::call('pjk_esewa', 'push_bayaran', fn () => app(ESewa::class)->pushPayment([
                'no_resit' => $resit->no_resit, 'no_kontrak' => (string) $kontrak->no_kontrak, 'amaun' => $resit->amaun, 'tarikh' => $date,
            ]), ['no_resit' => $resit->no_resit]);
        } catch (Throwable $e) {
            report($e); // the daily reconciliation reports what GRP / e-Sewa are missing (SRS-PJK-21)
        }
    }

    public function email(Resit $resit, ?string $pdf): void
    {
        $to = $resit->emel_penerima;
        if ($to === null || $to === '') {
            PenghantaranResit::query()->create(['resit_id' => $resit->id, 'status' => 'tiada_emel']);

            return;
        }
        try {
            // External payers are not SISDATA users, so MOD-NTF (user recipients only) can't carry this;
            // it is the one direct mail in PJK — see report.
            Mail::to($to)->send(new ResitMail($resit->no_resit, $resit->amaun, $pdf));
            PenghantaranResit::query()->create(['resit_id' => $resit->id, 'status' => 'dihantar']);
        } catch (Throwable $e) {
            report($e);
            PenghantaranResit::query()->create(['resit_id' => $resit->id, 'status' => 'gagal', 'mesej' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }

    public function pdf(Resit $resit, Kontrak $kontrak): string
    {
        $bayaran = Bayaran::query()->find($resit->bayaran_id);
        $premis = PremisAset::query()->withoutGlobalScope('access')->find($kontrak->premis_id);
        $title = $resit->jenis === 'resit_kredit' ? __('pjk::pjk.resit.tajuk_kredit') : __('pjk::pjk.resit.tajuk');

        return Dokumen::pdf($title, [[
            'jadual' => [
                [__('pjk::pjk.resit.no'), $resit->no_resit],
                [__('pjk::pjk.resit.tarikh'), $resit->tarikh->format('d/m/Y')],
                [__('pjk::pjk.resit.diterima_daripada'), $bayaran->nama_pembayar ?? $kontrak->penyewa ?? '—'],
                [__('pjk::pjk.resit.kontrak'), (string) $kontrak->no_kontrak],
                [__('pjk::pjk.resit.premis'), $premis->nama_premis ?? '—'],
                [__('pjk::pjk.resit.kaedah'), Kod::SALURAN_BAYARAN[$bayaran->saluran ?? ''] ?? '—'],
                [__('pjk::pjk.resit.rujukan'), $bayaran->rujukan_luar ?? $bayaran->no_bayaran ?? '—'],
                [__('pjk::pjk.resit.amaun'), Wang::rm($resit->amaun)],
            ],
        ]], __('pjk::pjk.resit.kaki'));
    }

    private function uploader(Kontrak $kontrak): ?User
    {
        return ($kontrak->id_pendaftar ? User::query()->find($kontrak->id_pendaftar) : null) ?? User::query()->orderBy('id')->first();
    }
}
