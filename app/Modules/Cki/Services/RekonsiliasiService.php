<?php

namespace App\Modules\Cki\Services;

use App\Models\User;
use App\Modules\Cki\Contracts\GrpPembayaran;
use App\Modules\Cki\Contracts\PenyediaTidakTersedia;
use App\Modules\Cki\Models\Cukai;
use App\Modules\Cki\Models\Penyelarasan;
use App\Modules\Cki\Models\RekonsiliasiLarian;
use App\Modules\Cki\Models\Resit;
use App\Modules\Cki\Models\Transaksi;
use App\Modules\Cki\Support\Penerima;
use App\Modules\Cki\Support\Wang;
use App\Modules\Ntf\Contracts\Notifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS-CKI-20 / SRS-INT-08 / kriteria 6: reconciliation of tax payments
 * between system records, receipts and GRP MAIS. Deviations: bill paid
 * without receipt, receipt without bill, amount differences between bill and
 * receipt, and between system and GRP (or a GRP-routed payment missing in GRP).
 */
class RekonsiliasiService
{
    public function __construct(private GrpPembayaran $grp, private Panggilan $panggilan, private Notifier $notifier, private Penerima $penerima) {}

    public function jalankan(CarbonImmutable $dari, CarbonImmutable $hingga, ?User $user = null): RekonsiliasiLarian
    {
        try {
            $grpRows = $this->panggilan->call('grp_bayaran', null, fn () => $this->grp->bayaran($dari, $hingga), ['dari' => $dari->format('Y-m-d'), 'hingga' => $hingga->format('Y-m-d')], null, 'cki_grp');
        } catch (PenyediaTidakTersedia) {
            throw ValidationException::withMessages(['grp' => __('cki::cki.ralat.grp_gagal')]);
        }
        $byPermohonan = [];
        foreach ($grpRows as $g) {
            $byPermohonan[$g['no_permohonan']] = $g;
        }

        return DB::transaction(function () use ($dari, $hingga, $user, $byPermohonan) {
            $run = RekonsiliasiLarian::query()->create(['dari' => $dari->format('Y-m-d'), 'hingga' => $hingga->format('Y-m-d'), 'id_pengguna' => $user?->id]);
            $sisihan = 0;
            $padan = 0;
            $add = function (array $row) use ($run, &$sisihan) {
                Penyelarasan::query()->create($row + ['id_larian' => $run->id, 'status_padanan' => 'sisihan']);
                $sisihan++;
            };

            // 1. Bills paid in the period without any receipt.
            $paid = Cukai::query()->withoutGlobalScope('access')->where('status_bayaran', 'dibayar')
                ->whereBetween('tarikh_bayar', [$dari->format('Y-m-d'), $hingga->format('Y-m-d')])->get();
            $receiptsByBill = Resit::query()->whereIn('id_bil', $paid->pluck('id'))->get()->groupBy('id_bil');
            foreach ($paid as $b) {
                $rs = $receiptsByBill[$b->id] ?? collect();
                if ($rs->isEmpty()) {
                    $add(['id_bil' => $b->id, 'jenis' => 'bil_tanpa_resit', 'amaun_sistem' => $b->amaun_dibayar, 'kod_daerah' => $b->kod_daerah,
                        'catatan_sisihan' => __('cki::cki.rekonsiliasi.bil_tanpa_resit', ['no' => $b->no_bil])]);

                    continue;
                }
                $sum = Wang::add(...$rs->pluck('amaun')->map(fn ($a) => (string) $a)->all());
                if (Wang::cmp($sum, $b->amaun_dibayar) !== 0) {
                    $add(['id_bil' => $b->id, 'jenis' => 'beza_amaun_resit', 'amaun_sistem' => $b->amaun_dibayar, 'amaun_resit' => $sum, 'kod_daerah' => $b->kod_daerah,
                        'catatan_sisihan' => __('cki::cki.rekonsiliasi.beza_resit', ['no' => $b->no_bil, 'beza' => Wang::rm(Wang::sub($sum, $b->amaun_dibayar))])]);
                } else {
                    $padan++;
                }
            }

            // 2. Receipts in the period that match no bill.
            foreach (Resit::query()->whereNull('id_bil')->whereBetween('tarikh_resit', [$dari->format('Y-m-d'), $hingga->format('Y-m-d')])->get() as $r) {
                $add(['id_resit' => $r->id, 'jenis' => 'resit_tanpa_bil', 'amaun_resit' => $r->amaun, 'kod_daerah' => $r->kod_daerah,
                    'catatan_sisihan' => __('cki::cki.rekonsiliasi.resit_tanpa_bil', ['no' => $r->no_resit, 'bil' => $r->no_bil_rujukan ?? '—'])]);
            }

            // 3. GRP-routed payments: system vs GRP MAIS.
            $grpTx = Transaksi::query()->withoutGlobalScope('access')->whereNotNull('rujukan_grp')->whereIn('status_transaksi', ['dibayar', 'selesai'])
                ->whereBetween('tarikh_bayaran', [$dari->format('Y-m-d'), $hingga->format('Y-m-d')])->get();
            foreach ($grpTx as $t) {
                $g = $byPermohonan[$t->no_permohonan] ?? null;
                $rec = Penyelarasan::query()->withoutGlobalScope('access')->where('id_transaksi', $t->id)->whereNull('id_larian')->first();
                if ($g === null) {
                    $add(['id_transaksi' => $t->id, 'id_bil' => $t->id_bil, 'jenis' => 'tiada_dalam_grp', 'rujukan_grp' => $t->rujukan_grp, 'amaun_sistem' => $t->amaun, 'kod_daerah' => $t->kod_daerah,
                        'catatan_sisihan' => __('cki::cki.rekonsiliasi.tiada_grp', ['no' => $t->no_permohonan])]);
                    $rec?->forceFill(['status_padanan' => 'sisihan'])->save();
                } elseif (Wang::cmp($g['amaun'], $t->amaun) !== 0) {
                    $add(['id_transaksi' => $t->id, 'id_bil' => $t->id_bil, 'jenis' => 'beza_amaun_grp', 'rujukan_grp' => $g['rujukan_grp'], 'amaun_sistem' => $t->amaun, 'amaun_grp' => $g['amaun'], 'kod_daerah' => $t->kod_daerah,
                        'catatan_sisihan' => __('cki::cki.rekonsiliasi.beza_grp', ['no' => $t->no_permohonan, 'beza' => Wang::rm(Wang::sub($g['amaun'], $t->amaun))])]);
                    $rec?->forceFill(['status_padanan' => 'sisihan', 'amaun_grp' => $g['amaun']])->save();
                } else {
                    $padan++;
                    $rec?->forceFill(['status_padanan' => 'padan', 'amaun_grp' => $g['amaun'], 'rujukan_grp' => $g['rujukan_grp']])->save();
                }
            }

            $run->forceFill(['bil_padan' => $padan, 'bil_sisihan' => $sisihan])->save();
            if ($sisihan > 0) {
                $this->notifier->send('cki.sisihan_rekonsiliasi', $this->penerima->untuk('cki.rekonsiliasi.lihat'), [
                    'dari' => $dari->format('d/m/Y'), 'hingga' => $hingga->format('d/m/Y'), 'bil_sisihan' => $sisihan,
                ], '/admin/cki/rekonsiliasi?larian='.$run->id, 'cki.sisihan:'.$run->id);
            }

            return $run;
        });
    }
}
