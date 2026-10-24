<?php

namespace App\Modules\Cki\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\JobProgress;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Cki\Contracts\PenyediaCukaiBerpusat;
use App\Modules\Cki\Contracts\PenyediaTidakTersedia;
use App\Modules\Cki\Models\Cukai;
use App\Modules\Cki\Models\Konflik;
use App\Modules\Cki\Models\Liputan;
use App\Modules\Cki\Models\Obligasi;
use App\Modules\Cki\Models\Resit;
use App\Modules\Cki\Support\Masker;
use App\Modules\Cki\Support\Penerima;
use App\Modules\Cki\Support\Wang;
use App\Modules\Ntf\Contracts\Notifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS-CKI-05: scheduled (at least daily) bill & status enquiry for every lot
 * in API coverage — records are created / updated automatically with
 * sumber_rekod = api. SRS-CKI-08: an agency that fails after retries goes to
 * fallback mode; when it answers again its bills are re-synchronised.
 * SRS-CKI-09: a manual / imported bill that differs from the API is not
 * overwritten — a conflict with both versions waits for an officer.
 */
class SemakanService
{
    public function __construct(
        private PenyediaCukaiBerpusat $penyedia,
        private Panggilan $panggilan,
        private LiputanService $liputan,
        private BilService $bil,
        private AuditLogger $audit,
        private Notifier $notifier,
        private Penerima $penerima,
    ) {}

    /**
     * @return array{agensi: int, obligasi: int, baharu: int, dikemas_kini: int, konflik: int, tanpa_ubah: int, gagal: list<string>, dipulihkan: list<string>}
     */
    public function jalankan(?string $kodAgensi = null, ?int $tahun = null, ?User $by = null): array
    {
        $tahun ??= (int) Carbon::today()->format('Y');
        $out = ['agensi' => 0, 'obligasi' => 0, 'baharu' => 0, 'dikemas_kini' => 0, 'konflik' => 0, 'tanpa_ubah' => 0, 'gagal' => [], 'dipulihkan' => []];
        $agencies = Liputan::query()->where('perkhidmatan_semakan', true)->when($kodAgensi, fn ($q) => $q->where('kod_agensi', $kodAgensi))->orderBy('kod_agensi')->get();

        foreach ($agencies as $agensi) {
            $out['agensi']++;
            try {
                $this->panggilan->call('ping', $agensi->kod_agensi, fn () => $this->penyedia->ping($agensi->kod_agensi));
            } catch (PenyediaTidakTersedia $e) {
                $this->liputan->gagal($agensi, $e->getMessage(), $by);
                $out['gagal'][] = $agensi->kod_agensi;

                continue;
            }
            $resync = $this->liputan->pulih($agensi);
            if ($resync) {
                $out['dipulihkan'][] = $agensi->kod_agensi;
            }
            $years = $resync ? [$tahun - 1, $tahun] : [$tahun];

            $failed = false;
            Obligasi::query()->withoutGlobalScope('access')->where('kod_agensi', $agensi->kod_agensi)->where('aktif', true)
                ->where('status_liputan_api', 'dalam')->orderBy('id')
                ->chunk(200, function ($chunk) use (&$out, &$failed, $agensi, $years, $by) {
                    /** @var Obligasi $o */
                    foreach ($chunk as $o) {
                        foreach ($years as $y) {
                            try {
                                $bills = $this->panggilan->call('semak_bil', $agensi->kod_agensi, fn () => $this->penyedia->semakBil([
                                    'kod_agensi' => $agensi->kod_agensi, 'jenis_cukai' => $o->jenis_cukai, 'no_akaun' => $o->no_akaun_cukai, 'tahun' => $y,
                                ]), ['no_akaun' => Masker::akaun($o->no_akaun_cukai), 'tahun' => $y, 'jenis_cukai' => $o->jenis_cukai], $o->id);
                            } catch (PenyediaTidakTersedia $e) {
                                $this->liputan->gagal($agensi, $e->getMessage(), $by);
                                $out['gagal'][] = $agensi->kod_agensi;
                                $failed = true;

                                return false;
                            }
                            foreach ($bills as $b) {
                                $out[$this->gabung($o, $b)]++;
                            }
                        }
                        $out['obligasi']++;
                        if (app()->bound(JobProgress::class)) {
                            app(JobProgress::class)->processed();
                        }
                    }

                    return true;
                });
            if (! $failed) {
                $agensi->forceFill(['semakan_terakhir' => now()])->save();
            }
        }
        $out['gagal'] = array_values(array_unique($out['gagal']));

        return $out;
    }

    /**
     * Merge one API bill into the register. Returns baharu | dikemas_kini | konflik | tanpa_ubah.
     *
     * @param  array{no_bil: string, tahun_taksiran: int, amaun_pokok: string, amaun_denda: string, tarikh_bil: string, tarikh_akhir: string, status_bayaran: string, tarikh_bayar: string|null, no_resit: string|null, rujukan_transaksi: string}  $api
     */
    public function gabung(Obligasi $o, array $api): string
    {
        return DB::transaction(function () use ($o, $api) {
            $existing = Cukai::query()->withoutGlobalScope('access')->where('obligasi_id', $o->id)->where('tahun_taksiran', $api['tahun_taksiran'])
                ->orderByRaw('CASE WHEN no_bil = ? THEN 0 ELSE 1 END', [$api['no_bil']])->lockForUpdate()->first();

            if ($existing === null) {
                $bil = $this->bil->cipta($o, $api, null, 'api');
                $this->resitApi($bil, $api);

                return 'baharu';
            }

            $apiVals = self::nilai($api);
            $diff = array_keys(array_filter($apiVals, fn ($v, $k) => $existing->nilaiSegerak()[$k] !== $v, ARRAY_FILTER_USE_BOTH));
            if ($existing->dibayar()) {
                // Paid here first; the issuer's system catches up later — not a difference.
                $diff = array_values(array_diff($diff, ['status_bayaran', 'amaun_denda']));
            }

            if ($existing->sumber_rekod !== 'api') {
                if ($diff === []) {
                    $existing->forceFill(['disegerak_pada' => now()])->save();

                    return 'tanpa_ubah';
                }
                $pending = Konflik::query()->withoutGlobalScope('access')->where('id_bil', $existing->id)->where('status_resolusi', 'menunggu')->first();
                if ($pending === null) {
                    $k = Konflik::query()->create([
                        'id_bil' => $existing->id, 'nilai_manual' => $existing->nilaiSegerak(), 'nilai_api' => $apiVals + ['tarikh_bayar' => $api['tarikh_bayar'], 'no_resit' => $api['no_resit']],
                        'medan_berkonflik' => $diff, 'kod_daerah' => $existing->kod_daerah,
                    ]);
                    $this->notifier->send('cki.konflik', $this->penerima->untuk('cki.konflik.kemas_kini', $existing->kod_daerah), [
                        'no_bil' => (string) $existing->no_bil, 'tahun' => (int) $existing->tahun_taksiran, 'medan' => implode(', ', $diff), 'kod_daerah' => (string) $existing->kod_daerah,
                    ], '/admin/cki/konflik?konflik='.$k->id, 'cki.konflik:'.$existing->id);
                } else {
                    $pending->forceFill(['nilai_api' => $apiVals + ['tarikh_bayar' => $api['tarikh_bayar'], 'no_resit' => $api['no_resit']], 'medan_berkonflik' => $diff])->save();
                }

                return 'konflik';
            }

            if ($diff === []) {
                $existing->forceFill(['disegerak_pada' => now()])->save();
                $this->resitApi($existing, $api);

                return 'tanpa_ubah';
            }
            $this->terapkan($existing, $api, null, __('cki::cki.lejar.kemas_kini_api'));
            $this->resitApi($existing, $api);

            return 'dikemas_kini';
        });
    }

    /**
     * Apply API values to a bill (automatic for API bills; after officer
     * confirmation for conflicts). Amount changes are posted to the ledger.
     *
     * @param  array<string, mixed>  $api
     */
    public function terapkan(Cukai $bil, array $api, ?User $user, string $keterangan): void
    {
        $oldPokok = Wang::norm($bil->amaun_pokok);
        $oldDenda = Wang::norm($bil->amaun_denda);
        $bil->no_bil = (string) $api['no_bil'];
        $bil->amaun_pokok = Wang::norm((string) $api['amaun_pokok']);
        $bil->amaun_denda = Wang::norm((string) $api['amaun_denda']);
        $bil->tarikh_akhir = Carbon::parse((string) $api['tarikh_akhir']);
        $bil->sumber_rekod = 'api';
        $bil->disegerak_pada = now();
        $bil->denda_dikenakan = $bil->denda_dikenakan || ! Wang::isZero($bil->amaun_denda);
        $bil->versi++;
        foreach (['pokok' => [$oldPokok, $bil->amaun_pokok, 'pelarasan'], 'denda' => [$oldDenda, $bil->amaun_denda, 'denda']] as [$old, $new, $jenis]) {
            $delta = Wang::sub($new, $old);
            if (! Wang::isZero($delta)) {
                $this->bil->lejar($bil, $jenis, Wang::cmp($delta, '0') > 0 ? $delta : '0', Wang::cmp($delta, '0') < 0 ? ltrim($delta, '-') : '0', $bil->no_bil, $keterangan, $user);
            }
        }
        if (($api['status_bayaran'] ?? null) === 'dibayar' && ! $bil->dibayar()) {
            $baki = $bil->baki();
            $bil->amaun_dibayar = Wang::add($bil->amaun_dibayar, $baki);
            $bil->tarikh_bayar = Carbon::parse((string) ($api['tarikh_bayar'] ?? Carbon::today()->format('Y-m-d')));
            $bil->no_resit = $api['no_resit'] ?? $bil->no_resit;
            $this->bil->lejar($bil, 'bayaran', '0', $baki, $bil->no_resit, __('cki::cki.lejar.bayaran_api'), $user, $bil->tarikh_bayar);
        }
        $this->bil->kemaskiniStatus($bil);
    }

    /** SRS-CKI-13: an electronic receipt from the API is recorded once per bill. */
    private function resitApi(Cukai $bil, array $api): void
    {
        if (($api['status_bayaran'] ?? null) !== 'dibayar' || empty($api['no_resit'])) {
            return;
        }
        if (Resit::query()->where('no_resit', $api['no_resit'])->where('id_bil', $bil->id)->exists()) {
            return;
        }
        Resit::query()->create([
            'id_bil' => $bil->id, 'no_resit' => $api['no_resit'], 'no_bil_rujukan' => $api['no_bil'], 'kod_agensi' => $bil->kod_agensi,
            'amaun' => $bil->amaun_dibayar, 'tarikh_resit' => $api['tarikh_bayar'] ?? Carbon::today()->format('Y-m-d'), 'sumber' => 'api',
            'kod_daerah' => $bil->kod_daerah, 'sintetik' => $bil->sintetik,
        ]);
    }

    /**
     * SRS-CKI-09: officer decision on a conflict. Both versions are kept on the
     * conflict row and written to the audit trail.
     */
    public function selesaikan(Konflik $k, string $keputusan, User $user, ?string $catatan = null): Konflik
    {
        if ($k->status_resolusi !== 'menunggu') {
            throw ValidationException::withMessages(['keputusan' => __('cki::cki.ralat.konflik_selesai')]);
        }
        $bil = Cukai::query()->withoutGlobalScope('access')->findOrFail($k->id_bil);
        DB::transaction(function () use ($k, $keputusan, $user, $catatan, $bil) {
            $changes = [];
            foreach ($k->medan_berkonflik as $f) {
                $changes[$f] = [$k->nilai_manual[$f] ?? null, $k->nilai_api[$f] ?? null];
            }
            if ($keputusan === 'terima_api') {
                $this->terapkan($bil, $k->nilai_api, $user, __('cki::cki.lejar.resolusi_konflik', ['id' => $k->id]));
                $this->resitApi($bil, $k->nilai_api);
            }
            $k->forceFill(['status_resolusi' => $keputusan, 'id_pegawai_pengesah' => $user->id, 'tarikh_resolusi' => now(), 'catatan' => $catatan])->save();
            $this->audit->record('resolusi_konflik', 'cki', 'cukai', $bil->id, reason: $catatan, changes: $changes, meta: [
                'keputusan' => $keputusan, 'id_konflik' => $k->id, 'versi_manual' => $k->nilai_manual, 'versi_api' => $k->nilai_api, 'klasifikasi' => 'dalaman',
            ]);
        });

        return $k->refresh();
    }

    /**
     * @param  array<string, mixed>  $api
     * @return array<string, string|null>
     */
    public static function nilai(array $api): array
    {
        return [
            'no_bil' => (string) $api['no_bil'],
            'amaun_pokok' => Wang::norm((string) $api['amaun_pokok']),
            'amaun_denda' => Wang::norm((string) $api['amaun_denda']),
            'tarikh_akhir' => Carbon::parse((string) $api['tarikh_akhir'])->format('Y-m-d'),
            'status_bayaran' => ($api['status_bayaran'] ?? 'belum_bayar') === 'dibayar' ? 'dibayar' : 'belum_bayar',
        ];
    }
}
