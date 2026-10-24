<?php

namespace App\Modules\Pjk\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Pjk\Models\AgihanBayaran;
use App\Modules\Pjk\Models\Bayaran;
use App\Modules\Pjk\Models\DepositCagaran;
use App\Modules\Pjk\Models\JadualCaj;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\Resit;
use App\Modules\Pjk\Support\Kod;
use App\Modules\Pjk\Support\Penomboran;
use App\Modules\Pjk\Support\Wang;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * SRS-PJK-19/20 (+ the crediting half of SRS-PJK-16/17): payments.
 * Offline payments (counter, EFT, bank transfer, cheque) are recorded with
 * proof and confirmed by an authorised Finance officer who is NOT the
 * recorder; online payments are confirmed by the gateway. Only a confirmed
 * payment is allocated to charges (manual match or the configured priority),
 * credited to the ledger exactly once, and receipted.
 */
class BayaranService
{
    public const KEUTAMAAN_LALAI = ['penalti', 'sewa', 'lain', 'deposit'];

    public function __construct(
        private LejarService $lejar,
        private ResitService $resit,
        private DocumentStore $documents,
        private Parameters $parameters,
    ) {}

    /** @param array<string, mixed> $input */
    public function recordOffline(Kontrak $kontrak, array $input, ?UploadedFile $proof, User $user): Bayaran
    {
        $data = Validator::make($input, [
            'saluran' => ['required', Rule::in(Kod::SALURAN_LUAR_TALIAN)],
            'amaun' => ['required', 'decimal:0,2', 'gt:0', 'max:99999999'],
            'tarikh_bayaran' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'rujukan_luar' => ['nullable', 'required_unless:saluran,kaunter', 'string', 'max:80'],
            'nama_pembayar' => ['nullable', 'string', 'max:200'],
            'emel_pembayar' => ['nullable', 'email', 'max:150'],
            'agihan' => ['nullable', 'array'],
            'agihan.*.caj_id' => ['required', 'integer'],
            'agihan.*.amaun' => ['required', 'decimal:0,2', 'gt:0'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ], [], ['saluran' => 'kaedah bayaran', 'amaun' => 'amaun', 'tarikh_bayaran' => 'tarikh bayaran', 'rujukan_luar' => 'rujukan bayaran'])->validate();

        if ($proof === null && $data['saluran'] !== 'kaunter') {
            throw ValidationException::withMessages(['bukti' => __('pjk::pjk.ralat.bukti_wajib')]);
        }
        $manual = $this->validateManual($kontrak, $data['agihan'] ?? [], Wang::sen((string) $data['amaun']));

        $docId = null;
        if ($proof !== null) {
            try {
                $docId = $this->documents->store($proof, Kontrak::SUBJECT, $kontrak->id, 'bukti_bayaran', $user, 'sulit', ['no_kontrak' => $kontrak->no_kontrak, 'saluran' => $data['saluran']]);
            } catch (DocumentRejected $e) {
                throw ValidationException::withMessages(['bukti' => $e->getMessage()]);
            }
        }

        return DB::transaction(fn () => Bayaran::query()->create([
            'no_bayaran' => Penomboran::next('bayaran'),
            'kontrak_id' => $kontrak->id,
            'saluran' => $data['saluran'],
            'amaun' => Wang::norm((string) $data['amaun']),
            'tarikh_bayaran' => $data['tarikh_bayaran'],
            'rujukan_luar' => $data['rujukan_luar'] ?? null,
            'id_dokumen_bukti' => $docId,
            'agihan_manual' => $manual,
            'status' => 'menunggu_pengesahan',
            'nama_pembayar' => $data['nama_pembayar'] ?? $kontrak->penyewa,
            'emel_pembayar' => $data['emel_pembayar'] ?? null,
            'id_perekod' => $user->id,
            'catatan' => $data['catatan'] ?? null,
        ]));
    }

    /** Finance confirmation (segregation of duties: never the recorder). */
    public function confirm(Bayaran $bayaran, User $user, bool $accept, ?string $note = null): ?Resit
    {
        if ($bayaran->status !== 'menunggu_pengesahan') {
            throw ValidationException::withMessages(['status' => __('pjk::pjk.ralat.bayaran_bukan_menunggu')]);
        }
        if ($bayaran->id_perekod === $user->id) {
            throw ValidationException::withMessages(['pengesah' => __('pjk::pjk.ralat.pengesah_sama')]);
        }
        if (! $accept) {
            $bayaran->forceFill(['status' => 'ditolak', 'id_pengesah' => $user->id, 'tarikh_pengesahan' => now(), 'catatan' => trim(($bayaran->catatan ?? '').' '.($note ?? ''))])->save();

            return null;
        }

        return $this->settle($bayaran, $user->id);
    }

    /**
     * Confirmed payment → allocation, ledger credit (once) and receipt.
     */
    public function settle(Bayaran $bayaran, ?int $userId, bool $deliver = true): Resit
    {
        $resit = DB::transaction(function () use ($bayaran, $userId) {
            /** @var Bayaran $locked */
            $locked = Bayaran::query()->whereKey($bayaran->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'disahkan') {
                return Resit::query()->where('bayaran_id', $locked->id)->where('jenis', 'resit')->firstOrFail();
            }
            $locked->forceFill(['status' => 'disahkan', 'id_pengesah' => $userId ?? $locked->id_pengesah, 'tarikh_pengesahan' => $locked->tarikh_pengesahan ?? now()])->save();
            $kontrak = Kontrak::query()->withoutGlobalScope('access')->findOrFail($locked->kontrak_id);

            $this->allocate($locked, $kontrak);
            $resit = $this->resit->issue($locked, $kontrak);
            $this->lejar->post($kontrak, 'bayaran', 0, Wang::sen($locked->amaun), [
                'kaedah_bayaran' => $locked->saluran,
                'no_resit' => $resit->no_resit,
                'bayaran_id' => $locked->id,
                'transaksi_gateway_id' => $locked->transaksi_gateway_id,
                'keterangan' => __('pjk::pjk.lejar.bayaran', ['saluran' => Kod::SALURAN_BAYARAN[$locked->saluran] ?? $locked->saluran, 'no' => $locked->no_bayaran]),
                'id_pegawai' => $userId,
            ], Carbon::parse($locked->tarikh_bayaran));
            $bayaran->setRawAttributes($locked->getAttributes(), true);

            return $resit;
        });

        if ($deliver) {
            $this->resit->deliver($resit);
        }

        return $resit;
    }

    /**
     * SRS-PJK-20: manual match first, then outstanding charges in the
     * configured priority (pjk.keutamaan_agihan, by kod_caj) and oldest due
     * date first; any remainder pre-pays the next scheduled rent periods.
     */
    public function allocate(Bayaran $bayaran, Kontrak $kontrak): void
    {
        $left = Wang::sen($bayaran->amaun);
        foreach ($bayaran->agihan_manual ?? [] as $m) {
            $caj = JadualCaj::query()->where('kontrak_id', $kontrak->id)->lockForUpdate()->find($m['caj_id']);
            if ($caj === null) {
                continue;
            }
            $left -= $this->apply($bayaran, $caj, min($left, Wang::sen($m['amaun']), $caj->bakiSen()));
        }

        $priority = array_values((array) $this->parameters->get('pjk.keutamaan_agihan', self::KEUTAMAAN_LALAI));
        $order = 'CASE kod_caj '.implode(' ', array_map(fn ($k, $i) => 'WHEN '.DB::getPdo()->quote((string) $k).' THEN '.$i, $priority, array_keys($priority))).' ELSE 99 END';
        $due = JadualCaj::query()->where('kontrak_id', $kontrak->id)->where('dikeluarkan', true)->whereIn('status_caj', ['belum_bayar', 'separa'])
            ->orderByRaw($order)->orderBy('tarikh_akhir')->orderBy('id')->lockForUpdate()->get();
        $future = JadualCaj::query()->where('kontrak_id', $kontrak->id)->where('dikeluarkan', false)->where('status_caj', 'dijadualkan')
            ->where('kod_caj', 'sewa')->orderBy('tarikh_caj')->lockForUpdate()->get();

        foreach ([...$due, ...$future] as $caj) {
            if ($left <= 0) {
                break;
            }
            $left -= $this->apply($bayaran, $caj, min($left, $caj->bakiSen()));
        }
    }

    private function apply(Bayaran $bayaran, JadualCaj $caj, int $amount): int
    {
        if ($amount <= 0) {
            return 0;
        }
        AgihanBayaran::query()->create(['bayaran_id' => $bayaran->id, 'caj_id' => $caj->id, 'amaun' => Wang::dec($amount)]);
        $caj->amaun_dibayar = Wang::dec(Wang::sen($caj->amaun_dibayar) + $amount);
        $caj->refreshStatus();
        $caj->save();

        if ($caj->kod_caj === 'deposit' && $caj->bakiSen() <= 0) {
            DepositCagaran::query()->where('caj_id', $caj->id)->where('status', 'belum_diterima')
                ->update(['status' => 'dipegang', 'tarikh_terima' => Carbon::parse($bayaran->tarikh_bayaran)->format('Y-m-d')]);
        }

        return $amount;
    }

    /** Undo a payment's allocations (receipt cancellation). */
    public function unallocate(Bayaran $bayaran): void
    {
        foreach (AgihanBayaran::query()->where('bayaran_id', $bayaran->id)->where('dibalikkan', false)->lockForUpdate()->get() as $a) {
            $caj = JadualCaj::query()->lockForUpdate()->findOrFail($a->caj_id);
            $caj->amaun_dibayar = Wang::dec(max(0, Wang::sen($caj->amaun_dibayar) - Wang::sen($a->amaun)));
            $caj->refreshStatus();
            $caj->save();
            $a->forceFill(['dibalikkan' => true])->save();
            if ($caj->kod_caj === 'deposit') {
                DepositCagaran::query()->where('caj_id', $caj->id)->where('status', 'dipegang')->update(['status' => 'belum_diterima', 'tarikh_terima' => null]);
            }
        }
    }

    /**
     * @param  list<array{caj_id: int|string, amaun: string|int}>  $rows
     * @return list<array{caj_id: int, amaun: string}>|null
     */
    public function validateManual(Kontrak $kontrak, array $rows, int $total): ?array
    {
        if ($rows === []) {
            return null;
        }
        $sum = 0;
        $out = [];
        foreach ($rows as $i => $r) {
            $caj = JadualCaj::query()->where('kontrak_id', $kontrak->id)->find((int) $r['caj_id']);
            $amount = Wang::sen((string) $r['amaun']);
            if ($caj === null || in_array($caj->status_caj, ['dibayar', 'dikecualikan', 'dibatalkan'], true)) {
                throw ValidationException::withMessages(["agihan.{$i}.caj_id" => __('pjk::pjk.ralat.caj_tidak_sah')]);
            }
            if ($amount > $caj->bakiSen()) {
                throw ValidationException::withMessages(["agihan.{$i}.amaun" => __('pjk::pjk.ralat.agihan_lebih_baki')]);
            }
            $sum += $amount;
            $out[] = ['caj_id' => $caj->id, 'amaun' => Wang::dec($amount)];
        }
        if ($sum > $total) {
            throw ValidationException::withMessages(['agihan' => __('pjk::pjk.ralat.agihan_lebih_amaun')]);
        }

        return $out;
    }
}
