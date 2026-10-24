<?php

namespace App\Modules\Pjk\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Pjk\Contracts\GatewayException;
use App\Modules\Pjk\Contracts\InvalidGatewaySignature;
use App\Modules\Pjk\Contracts\PaymentGateway;
use App\Modules\Pjk\Models\Bayaran;
use App\Modules\Pjk\Models\JadualCaj;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\PanggilanBalikGateway;
use App\Modules\Pjk\Models\TransaksiGateway;
use App\Modules\Pjk\Support\Kod;
use App\Modules\Pjk\Support\Luaran;
use App\Modules\Pjk\Support\Penomboran;
use App\Modules\Pjk\Support\Wang;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * SRS-PJK-14/15/16: online payments through the PaymentGateway adapter.
 *
 *  - initiate(): unique reference linked to contract + charges; idempotent by
 *    Idempotency-Key (a retried request returns the same transaction).
 *  - callback(): signature verified; each gateway event processed once.
 *  - pollPending(): scheduled status query for transactions without a
 *    callback; unresolved ones end as tamat_masa.
 *  The ledger is credited only when the gateway confirms "berjaya" for the
 *  exact amount, and at most once (also enforced by a unique index).
 */
class GatewayService
{
    public function __construct(
        private PaymentGateway $gateway,
        private BayaranService $bayaran,
        private LejarService $lejar,
        private Parameters $parameters,
    ) {}

    /** @param array<string, mixed> $input */
    public function initiate(Kontrak $kontrak, array $input, ?string $idempotencyKey, ?User $user, string $source = 'pegawai'): TransaksiGateway
    {
        if ($idempotencyKey !== null && ($existing = TransaksiGateway::query()->where('kunci_idempotensi', $idempotencyKey)->first()) !== null) {
            if ($existing->kontrak_id !== $kontrak->id) {
                throw ValidationException::withMessages(['kunci_idempotensi' => __('pjk::pjk.ralat.kunci_idempotensi')]);
            }

            return $existing;
        }

        $data = Validator::make($input, [
            'saluran' => ['required', Rule::in(Kod::SALURAN_DALAM_TALIAN)],
            'amaun' => ['nullable', 'decimal:0,2', 'gt:0', 'max:99999999'],
            'caj_ids' => ['nullable', 'array'],
            'caj_ids.*' => ['integer'],
            'nama_pembayar' => ['nullable', 'string', 'max:200'],
            'emel_pembayar' => ['nullable', 'email', 'max:150'],
        ], [], ['saluran' => 'saluran bayaran', 'amaun' => 'amaun'])->validate();

        if (! in_array($kontrak->status, [...Kod::BERKUAT_KUASA, 'tamat_tempoh', 'ditamatkan', 'diperbaharui'], true)) {
            throw ValidationException::withMessages(['kontrak' => __('pjk::pjk.ralat.kontrak_belum_aktif')]);
        }

        $cajIds = array_values(array_map('intval', $data['caj_ids'] ?? []));
        if ($cajIds !== []) {
            $charges = JadualCaj::query()->where('kontrak_id', $kontrak->id)->whereIn('id', $cajIds)->whereIn('status_caj', ['belum_bayar', 'separa', 'dijadualkan'])->get();
            if ($charges->count() !== count(array_unique($cajIds))) {
                throw ValidationException::withMessages(['caj_ids' => __('pjk::pjk.ralat.caj_tidak_sah')]);
            }
            $amount = $charges->sum(fn (JadualCaj $c) => $c->bakiSen());
        } elseif (isset($data['amaun'])) {
            $amount = Wang::sen((string) $data['amaun']);
        } else {
            $amount = $this->lejar->bakiSen($kontrak->id);
        }
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amaun' => __('pjk::pjk.ralat.tiada_baki')]);
        }

        try {
            $tx = DB::transaction(fn () => TransaksiGateway::query()->create([
                'no_rujukan' => Penomboran::next('gateway'),
                'kontrak_id' => $kontrak->id,
                'caj_ids' => $cajIds ?: null,
                'saluran' => $data['saluran'],
                'amaun' => Wang::dec($amount),
                'status' => 'menunggu',
                'masa_mula' => now(),
                'kunci_idempotensi' => $idempotencyKey,
                'nama_pembayar' => $data['nama_pembayar'] ?? $kontrak->penyewa,
                'emel_pembayar' => $data['emel_pembayar'] ?? null,
                'sumber' => $source,
                'id_pemula' => $user?->id,
            ]));
        } catch (UniqueConstraintViolationException) {
            return TransaksiGateway::query()->where('kunci_idempotensi', $idempotencyKey)->firstOrFail(); // concurrent retry
        }

        try {
            $res = Luaran::call('pjk_gateway', 'initiate', fn () => $this->gateway->initiate([
                'no_rujukan' => $tx->no_rujukan, 'amaun' => $tx->amaun, 'saluran' => $tx->saluran,
                'keterangan' => __('pjk::pjk.gateway.keterangan', ['no' => $kontrak->no_kontrak]),
                'nama_pembayar' => $tx->nama_pembayar, 'emel_pembayar' => $tx->emel_pembayar,
            ]), ['no_rujukan' => $tx->no_rujukan]);
        } catch (GatewayException) {
            $tx->forceFill(['status' => 'gagal', 'kod_respons' => 'GW_TIADA', 'masa_pengesahan' => now()])->save();
            throw ValidationException::withMessages(['gateway' => __('pjk::pjk.ralat.gateway_tiada')]);
        }
        $tx->forceFill(['rujukan_gateway' => $res['rujukan_gateway'], 'url_bayaran' => $res['url_bayaran']])->save();

        return $tx;
    }

    /**
     * Webhook entry. Returns [http status, keputusan].
     *
     * @param  array<string, string>  $headers  lower-case
     * @return array{0: int, 1: string}
     */
    public function callback(string $raw, array $headers, ?string $ip = null): array
    {
        try {
            $event = $this->gateway->parseCallback($raw, $headers);
        } catch (InvalidGatewaySignature $e) {
            PanggilanBalikGateway::query()->create(['muatan' => ['ralat' => $e->getMessage(), 'saiz' => strlen($raw)], 'tandatangan_sah' => false, 'keputusan' => 'ditolak', 'ip' => $ip]);
            Luaran::log('pjk_gateway', false, 'panggilan_balik', null, $e->getMessage(), [], 'masuk');

            return [401, 'ditolak'];
        }

        $tx = TransaksiGateway::query()->where('no_rujukan', $event['no_rujukan'])->first();
        if (PanggilanBalikGateway::query()->where('id_acara', $event['id_acara'])->exists()) {
            return [200, 'pendua'];
        }
        try {
            // Savepoint, so a concurrent duplicate doesn't abort an outer transaction.
            $log = DB::transaction(fn () => PanggilanBalikGateway::query()->create([
                'id_acara' => $event['id_acara'], 'transaksi_gateway_id' => $tx?->id, 'muatan' => $event,
                'tandatangan_sah' => true, 'keputusan' => $tx === null ? 'tidak_dikenali' : 'diproses', 'ip' => $ip,
            ]));
        } catch (UniqueConstraintViolationException) {
            return [200, 'pendua']; // same event delivered again — already handled (idempotent)
        }
        Luaran::log('pjk_gateway', true, 'panggilan_balik', null, null, ['no_rujukan' => $event['no_rujukan']], 'masuk');
        if ($tx === null) {
            return [404, 'tidak_dikenali'];
        }

        $result = $this->resolve($tx, $event['status'], $event['amaun'], $event['rujukan_gateway'], $event['kod_respons'], 'panggilan_balik');
        if ($result === 'pendua') {
            $log->forceFill(['keputusan' => 'pendua'])->save();
        }

        return [200, $result];
    }

    /**
     * Move a transaction to its final state (once). berjaya + matching amount
     * → payment confirmed, ledger credited, receipt issued.
     */
    public function resolve(TransaksiGateway $tx, string $status, ?string $amount, ?string $gatewayRef, ?string $code, string $via): string
    {
        $outcome = DB::transaction(function () use ($tx, $status, $amount, $gatewayRef, $code, $via) {
            /** @var TransaksiGateway $locked */
            $locked = TransaksiGateway::query()->whereKey($tx->id)->lockForUpdate()->firstOrFail();
            if ($locked->isFinal()) {
                return ['pendua', null];
            }
            if ($status === 'menunggu') {
                return ['menunggu', null];
            }
            if ($status === 'berjaya' && $amount !== null && Wang::sen($amount) !== Wang::sen($locked->amaun)) {
                $locked->forceFill(['kod_respons' => 'AMAUN_BERBEZA', 'rujukan_gateway' => $gatewayRef ?? $locked->rujukan_gateway])->save();

                return ['amaun_berbeza', null]; // never credit a mismatched amount — reconciliation reports it
            }
            $final = in_array($status, ['berjaya', 'gagal', 'dibatalkan', 'tamat_masa'], true) ? $status : 'gagal';
            $locked->forceFill([
                'status' => $final, 'masa_pengesahan' => now(), 'kod_respons' => $code,
                'rujukan_gateway' => $gatewayRef ?? $locked->rujukan_gateway, 'disahkan_melalui' => $via,
            ])->save();
            if ($final !== 'berjaya') {
                return [$final, null];
            }

            $kontrak = Kontrak::query()->withoutGlobalScope('access')->findOrFail($locked->kontrak_id);
            $manual = [];
            foreach (JadualCaj::query()->where('kontrak_id', $kontrak->id)->whereIn('id', $locked->caj_ids ?? [])->get() as $c) {
                if ($c->bakiSen() > 0) {
                    $manual[] = ['caj_id' => $c->id, 'amaun' => Wang::dec($c->bakiSen())];
                }
            }
            $bayaran = Bayaran::query()->create([
                'no_bayaran' => Penomboran::next('bayaran'),
                'kontrak_id' => $kontrak->id,
                'saluran' => $locked->saluran,
                'amaun' => $locked->amaun,
                'tarikh_bayaran' => Carbon::today()->format('Y-m-d'),
                'rujukan_luar' => $locked->rujukan_gateway,
                'agihan_manual' => $manual ?: null,
                'status' => 'menunggu_pengesahan',
                'transaksi_gateway_id' => $locked->id,
                'nama_pembayar' => $locked->nama_pembayar,
                'emel_pembayar' => $locked->emel_pembayar,
                'id_perekod' => $locked->id_pemula,
            ]);

            return ['berjaya', $bayaran];
        });

        if ($outcome[1] instanceof Bayaran) {
            $this->bayaran->settle($outcome[1], null);
        }

        return $outcome[0];
    }

    /**
     * SRS-PJK-16 scheduled status query.
     *
     * @return array{disemak: int, diselesaikan: int, tamat_masa: int}
     */
    public function pollPending(): array
    {
        $after = (int) $this->parameters->get('pjk.gateway_minit_semak', 15);
        $timeout = (int) $this->parameters->get('pjk.gateway_minit_tamat_masa', 60);
        $stats = ['disemak' => 0, 'diselesaikan' => 0, 'tamat_masa' => 0];

        TransaksiGateway::query()->where('status', 'menunggu')->where('masa_mula', '<=', now()->subMinutes($after))
            ->orderBy('masa_mula')->limit(500)->get()->each(function (TransaksiGateway $tx) use (&$stats, $timeout) {
                $stats['disemak']++;
                try {
                    $res = Luaran::call('pjk_gateway', 'semak_status', fn () => $this->gateway->query($tx->no_rujukan), ['no_rujukan' => $tx->no_rujukan]);
                } catch (GatewayException) {
                    $res = null;
                }
                $tx->forceFill(['bil_semakan_status' => $tx->bil_semakan_status + 1, 'semakan_terakhir' => now()])->save();

                if ($res !== null && $res['status'] !== 'menunggu') {
                    $r = $this->resolve($tx, $res['status'], $res['amaun'], $res['rujukan_gateway'], $res['kod_respons'], 'semakan_status');
                    if ($r !== 'amaun_berbeza') {
                        $stats['diselesaikan']++;

                        return;
                    }
                }
                if ($tx->masa_mula->lte(now()->subMinutes($timeout))) {
                    $this->resolve($tx, 'tamat_masa', null, null, 'TAMAT_MASA', 'semakan_status');
                    $stats['tamat_masa']++;
                }
            });

        return $stats;
    }
}
