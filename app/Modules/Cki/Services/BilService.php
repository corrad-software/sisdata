<?php

namespace App\Modules\Cki\Services;

use App\Models\User;
use App\Modules\Cki\Models\Cukai;
use App\Modules\Cki\Models\Lejar;
use App\Modules\Cki\Models\Obligasi;
use App\Modules\Cki\Models\Pelarasan;
use App\Modules\Cki\Models\Transaksi;
use App\Modules\Cki\Support\Kod;
use App\Modules\Cki\Support\Wang;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * SRS-CKI-02/07/15/16: bills (same fields whatever the source — API, manual
 * fallback, Excel import), their ledger and status. Bills are never deleted;
 * a correction is a Pelarasan with justification (kawalan keselamatan).
 */
class BilService
{
    public const MEDAN_PELARASAN = ['no_bil', 'amaun_pokok', 'amaun_denda', 'tarikh_bil', 'tarikh_akhir'];

    public function __construct(private DocumentStore $documents) {}

    /**
     * @param  array<string, mixed>  $data  obligasi_id, tahun_taksiran, no_bil, amaun_pokok, amaun_denda?, tarikh_bil?, tarikh_akhir, status_bayaran?, tarikh_bayar?, no_resit?
     */
    public function cipta(Obligasi $o, array $data, ?User $user, string $sumber = 'manual', ?UploadedFile $fail = null, bool $sintetik = false): Cukai
    {
        $tahun = (int) $data['tahun_taksiran'];
        $noBil = (string) $data['no_bil'];
        if (Cukai::query()->withoutGlobalScope('access')->where('obligasi_id', $o->id)->where('tahun_taksiran', $tahun)->where('no_bil', $noBil)->exists()) {
            throw ValidationException::withMessages(['no_bil' => __('cki::cki.ralat.bil_wujud')]);
        }

        return DB::transaction(function () use ($o, $data, $user, $sumber, $fail, $sintetik, $tahun, $noBil) {
            $paid = ($data['status_bayaran'] ?? 'belum_bayar') === 'dibayar';
            $pokok = Wang::norm((string) $data['amaun_pokok']);
            $denda = Wang::norm((string) ($data['amaun_denda'] ?? '0'));
            $bil = Cukai::query()->create([
                'lot_id' => $o->lot_id, 'obligasi_id' => $o->id, 'jenis' => $o->jenis_cukai, 'no_bil' => $noBil,
                'tahun_taksiran' => $tahun, 'pihak_pengeluar' => $o->pihak_pengeluar, 'kod_agensi' => $o->kod_agensi, 'kod_pbt' => $o->kod_pbt,
                'kod_daerah' => $o->kod_daerah, 'kod_mukim' => $o->kod_mukim, 'amaun_pokok' => $pokok, 'amaun_denda' => $denda,
                'jumlah' => Wang::add($pokok, $denda), 'amaun_dibayar' => $paid ? Wang::add($pokok, $denda) : '0.00',
                'tarikh_bil' => $data['tarikh_bil'] ?? null, 'tarikh_akhir' => $data['tarikh_akhir'],
                'tarikh_bayar' => $paid ? ($data['tarikh_bayar'] ?? $data['tarikh_akhir']) : null, 'no_resit' => $data['no_resit'] ?? null,
                'status_bayaran' => 'belum_bayar', 'sumber_rekod' => $sumber, 'denda_dikenakan' => ! Wang::isZero($denda),
                'disegerak_pada' => $sumber === 'api' ? now() : null, 'sintetik' => $sintetik || $o->sintetik, 'klasifikasi' => 'dalaman', 'id_pencipta' => $user?->id,
            ]);
            $this->lejar($bil, 'bil', $pokok, '0', $noBil, __('cki::cki.lejar.bil', ['tahun' => $tahun]), $user, $bil->tarikh_bil);
            if (! Wang::isZero($denda)) {
                $this->lejar($bil, 'denda', $denda, '0', $noBil, __('cki::cki.lejar.denda'), $user, $bil->tarikh_bil);
            }
            if ($paid) {
                $this->lejar($bil, 'bayaran', '0', $bil->amaun_dibayar, $bil->no_resit, __('cki::cki.lejar.bayaran_luar'), $user, $bil->tarikh_bayar);
            }
            $this->kemaskiniStatus($bil);
            if ($fail !== null && $user !== null) {
                $bil->id_dokumen_bil = $this->simpanDokumen($bil, $fail, 'bil', $user);
                $bil->save();
            }

            return $bil;
        });
    }

    /** Status rule: paid in full → dibayar; payment in progress → dalam_proses; past due → tertunggak; else belum_bayar. */
    public function kemaskiniStatus(Cukai $bil, ?Carbon $today = null): Cukai
    {
        $today ??= Carbon::today();
        $status = match (true) {
            Wang::cmp($bil->amaun_dibayar, $bil->jumlahPerlu()) >= 0 && ! Wang::isZero($bil->jumlahPerlu()) => 'dibayar',
            Transaksi::query()->withoutGlobalScope('access')->where('id_bil', $bil->id)->whereIn('status_transaksi', ['menunggu_kelulusan', 'diluluskan', 'dihantar_grp', 'dibayar'])->exists() => 'dalam_proses',
            $bil->tarikh_akhir !== null && $bil->tarikh_akhir->lt($today) => 'tertunggak',
            default => 'belum_bayar',
        };
        $bil->jumlah = $bil->jumlahPerlu();
        if ($bil->status_bayaran !== $status || $bil->isDirty()) {
            $bil->status_bayaran = $status;
            $bil->save();
        }

        return $bil;
    }

    /** Correction of one bill field with justification (separate append-only record). */
    public function pelarasan(Cukai $bil, string $medan, string $nilai, string $justifikasi, ?User $user): Pelarasan
    {
        if (! in_array($medan, self::MEDAN_PELARASAN, true)) {
            throw ValidationException::withMessages(['medan' => __('cki::cki.ralat.medan_pelarasan')]);
        }
        if ($bil->dibayar() && in_array($medan, ['amaun_pokok', 'amaun_denda'], true)) {
            throw ValidationException::withMessages(['medan' => __('cki::cki.ralat.bil_dibayar')]);
        }

        return DB::transaction(function () use ($bil, $medan, $nilai, $justifikasi, $user) {
            $lama = $bil->getAttribute($medan);
            $lama = $lama instanceof Carbon ? $lama->format('Y-m-d') : ($lama === null ? null : (string) $lama);
            $baru = in_array($medan, ['amaun_pokok', 'amaun_denda'], true) ? Wang::norm($nilai) : $nilai;
            $rec = Pelarasan::query()->create(['id_bil' => $bil->id, 'medan' => $medan, 'nilai_lama' => $lama, 'nilai_baharu' => $baru, 'justifikasi' => $justifikasi, 'id_pengguna' => $user?->id]);
            $bil->setAttribute($medan, $baru);
            $bil->versi++;
            if (in_array($medan, ['amaun_pokok', 'amaun_denda'], true)) {
                $delta = Wang::sub($baru, (string) ($lama ?? '0'));
                if (! Wang::isZero($delta)) {
                    $this->lejar($bil, 'pelarasan', Wang::cmp($delta, '0') > 0 ? $delta : '0', Wang::cmp($delta, '0') < 0 ? ltrim($delta, '-') : '0', 'PL-'.$rec->id, $justifikasi, $user);
                }
                if ($medan === 'amaun_denda') {
                    $bil->denda_dikenakan = ! Wang::isZero($baru);
                }
            }
            $this->kemaskiniStatus($bil);

            return $rec;
        });
    }

    public function lejar(Cukai $bil, string $jenis, string $debit, string $kredit, ?string $rujukan, ?string $keterangan, ?User $user, ?Carbon $tarikh = null): Lejar
    {
        return Lejar::query()->create([
            'id_bil' => $bil->id, 'tarikh' => ($tarikh ?? Carbon::today())->format('Y-m-d'), 'jenis' => $jenis,
            'debit' => Wang::norm($debit), 'kredit' => Wang::norm($kredit), 'rujukan' => $rujukan,
            'keterangan' => $keterangan === null ? null : mb_substr($keterangan, 0, 255), 'id_pengguna' => $user?->id,
        ]);
    }

    /**
     * A system-generated document (payment request, e-receipt copy): a DMS /
     * storage failure must not undo the payment step — it is reported and
     * the record keeps a null document id (the document can be attached later).
     *
     * @param  array<string, mixed>  $meta
     */
    public function cubaSimpanDokumen(Cukai $bil, UploadedFile $file, string $jenis, User $user, array $meta = []): ?string
    {
        try {
            return $this->simpanDokumen($bil, $file, $jenis, $user, $meta);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * SRS-CKI-16: attach a bill / voucher / receipt / proof / approval document through MOD-DMS (versioned there), linked to the bill and its lot.
     *
     * @param  array<string, mixed>  $meta
     *
     * @throws ValidationException
     */
    public function simpanDokumen(Cukai $bil, UploadedFile $file, string $jenis, User $user, array $meta = []): string
    {
        [, $category] = Kod::KATEGORI_DOKUMEN[$jenis] ?? Kod::KATEGORI_DOKUMEN['bil'];
        try {
            return $this->documents->store($file, Cukai::SUBJECT, $bil->id, $category, $user, 'dalaman', $meta + [
                'jenis_dokumen_cki' => $jenis, 'no_bil' => $bil->no_bil, 'tahun_taksiran' => $bil->tahun_taksiran, 'lot_id' => $bil->lot_id,
                'pautan_induk' => '/admin/cki/bil/'.$bil->id,
            ]);
        } catch (DocumentRejected $e) {
            throw ValidationException::withMessages(['fail' => $e->getMessage()]);
        }
    }
}
