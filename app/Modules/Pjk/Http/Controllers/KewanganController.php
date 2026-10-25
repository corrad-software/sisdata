<?php

namespace App\Modules\Pjk\Http\Controllers;

use App\Models\User;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Pjk\Contracts\PaymentGateway;
use App\Modules\Pjk\Http\Present;
use App\Modules\Pjk\Integration\MockPaymentGateway;
use App\Modules\Pjk\Models\Bayaran;
use App\Modules\Pjk\Models\DepositCagaran;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\Resit;
use App\Modules\Pjk\Models\TransaksiGateway;
use App\Modules\Pjk\Services\BayaranService;
use App\Modules\Pjk\Services\DepositService;
use App\Modules\Pjk\Services\GatewayService;
use App\Modules\Pjk\Services\PelarasanService;
use App\Modules\Pjk\Services\ResitService;
use App\Modules\Pjk\Support\Kod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** SRS-PJK-13..20, 28: payments, receipts, online payments, adjustments and deposits. */
class KewanganController
{
    /** SRS-PJK-19: offline payment with proof → Finance confirmation. */
    public function recordOffline(Request $request, Kontrak $kontrak, BayaranService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $input = $request->all();
        if (is_string($input['agihan'] ?? null)) {
            $input['agihan'] = json_decode($input['agihan'], true) ?: [];
        }
        $file = $request->file('bukti');
        $b = $service->recordOffline($kontrak, $input, $file instanceof UploadedFile ? $file : null, $user);

        return response()->json(['data' => Present::bayaran($b), 'message' => __('pjk::pjk.mesej.bayaran_direkod')], 201);
    }

    /** Finance queue: payments awaiting confirmation (within the user's contract scope). */
    public function pending(Request $request): JsonResponse
    {
        $rows = Bayaran::query()->whereIn('kontrak_id', Kontrak::query()->select('kontrak_pajakan.id'))
            ->where('status', $request->query('status', 'menunggu_pengesahan'))
            ->with(['kontrak' => fn ($q) => $q->select('id', 'no_kontrak', 'penyewa')])->orderBy('created_at')->limit(500)->get();

        return response()->json(['data' => $rows->map(fn ($b) => Present::bayaran($b))->all()]);
    }

    public function confirm(Request $request, Bayaran $bayaran, BayaranService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Kontrak::query()->findOrFail($bayaran->kontrak_id); // scope
        $data = Validator::make($request->all(), ['terima' => ['required', 'boolean'], 'catatan' => ['nullable', 'required_if:terima,false', 'string', 'max:1000']], [], ['catatan' => 'catatan'])->validate();
        $resit = $service->confirm($bayaran, $user, (bool) $data['terima'], $data['catatan'] ?? null);

        return response()->json(['data' => Present::bayaran($bayaran->refresh()->load('resit')), 'resit' => $resit ? Present::resit($resit) : null,
            'message' => $resit ? __('pjk::pjk.mesej.bayaran_disahkan', ['no' => $resit->no_resit]) : __('pjk::pjk.mesej.bayaran_ditolak')]);
    }

    /** SRS-PJK-17: receipt PDF (DMS copy if stored, else regenerated from the immutable record). */
    public function receipt(Request $request, Resit $resit, DocumentStore $documents, ResitService $service): Response|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $kontrak = Kontrak::query()->findOrFail($resit->kontrak_id);
        if ($resit->id_dokumen !== null && ($url = $documents->temporaryUrl($resit->id_dokumen, $user)) !== null) {
            return response()->json(['data' => ['url' => $url]]);
        }

        return response($service->pdf($resit, $kontrak), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.str_replace('/', '-', $resit->no_resit).'.pdf"']);
    }

    /** SRS-PJK-14/15: start an online payment (FPX / card). Header Idempotency-Key makes retries safe. */
    public function initiate(Request $request, Kontrak $kontrak, GatewayService $gateway): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $key = $request->header('Idempotency-Key');
        $tx = $gateway->initiate($kontrak, $request->all(), is_string($key) && $key !== '' ? mb_substr($key, 0, 100) : null, $user);

        return response()->json(['data' => Present::gateway($tx)], $tx->wasRecentlyCreated ? 201 : 200);
    }

    public function gatewayStatus(TransaksiGateway $transaksi): JsonResponse
    {
        Kontrak::query()->findOrFail($transaksi->kontrak_id);

        return response()->json(['data' => Present::gateway($transaksi)]);
    }

    /**
     * Test gateway page action (mock adapter only): the payer "completes" the
     * payment; the signed callback goes through the same webhook path.
     */
    public function simulate(Request $request, string $noRujukan, PaymentGateway $gateway, GatewayService $service): JsonResponse
    {
        abort_unless($gateway instanceof MockPaymentGateway, 404);
        $tx = TransaksiGateway::query()->where('no_rujukan', $noRujukan)->firstOrFail();
        Kontrak::query()->findOrFail($tx->kontrak_id);
        $data = Validator::make($request->all(), ['keputusan' => ['required', Rule::in(['berjaya', 'gagal', 'dibatalkan', 'tiada_panggilan_balik', 'tamat_masa'])]])->validate();
        $callback = $gateway->simulate($noRujukan, $data['keputusan'], null, $tx->rujukan_gateway === null ? null : [
            'no_rujukan' => $tx->no_rujukan, 'rujukan_gateway' => $tx->rujukan_gateway, 'amaun' => $tx->amaun, 'saluran' => $tx->saluran,
            'tarikh' => $tx->masa_mula->format('Y-m-d'),
        ]);
        $result = null;
        if ($callback !== null) {
            [, $result] = $service->callback($callback['body'], $callback['headers'], $request->ip());
        }

        return response()->json(['data' => Present::gateway($tx->refresh()), 'panggilan_balik' => $result]);
    }

    public function testPage(string $noRujukan, PaymentGateway $gateway): JsonResponse
    {
        abort_unless($gateway instanceof MockPaymentGateway, 404);
        $tx = TransaksiGateway::query()->where('no_rujukan', $noRujukan)->firstOrFail();
        $kontrak = Kontrak::query()->findOrFail($tx->kontrak_id);

        return response()->json(['data' => Present::gateway($tx) + ['no_kontrak' => $kontrak->no_kontrak, 'nama_pembayar' => $tx->nama_pembayar]]);
    }

    /** SRS-PJK-28: request an adjustment / cancellation / waiver (second-officer approval in WFE). */
    public function requestAdjustment(Request $request, Kontrak $kontrak, PelarasanService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => Present::pelarasan($service->request($kontrak, $request->all(), $user)), 'message' => __('pjk::pjk.mesej.dihantar_kelulusan')], 201);
    }

    /** SRS-PJK-13: deposit settlement request (Finance approval in WFE). */
    public function settleDeposit(Request $request, DepositCagaran $deposit, DepositService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Kontrak::query()->findOrFail($deposit->kontrak_id);

        return response()->json(['data' => Present::deposit($service->requestSettlement($deposit, $request->all(), $user)), 'message' => __('pjk::pjk.mesej.dihantar_kelulusan')]);
    }

    /** @return array<string, string> */
    public static function channels(): array
    {
        return Kod::SALURAN_BAYARAN;
    }
}
