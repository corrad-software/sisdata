<?php

namespace App\Modules\Per\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Per\Models\BayaranNotis5a;
use App\Modules\Per\Models\Pemerolehan;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * SRS-PER-04/05: amounts due on a 5A notice, payments recorded against it,
 * confirmation by Bahagian Kewangan and the outstanding balance. Only
 * confirmed payments count — progress to title receipt is blocked until the
 * confirmed total covers everything due.
 */
class Payments5a
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @return array{jumlah_perlu_dibayar: string, jumlah_disahkan: string, jumlah_belum_disahkan: string, baki_tertunggak: string, lengkap: bool, tarikh_akhir_bayaran: string|null}
     */
    public function summary(Pemerolehan $case): array
    {
        $b = $case->butiran_saluran ?? [];
        $due = 0.0;
        foreach (['jumlah_premium', 'cukai_tahun_pertama', 'bayaran_ukur', 'bayaran_lain'] as $k) {
            $due += (float) ($b[$k] ?? 0);
        }

        $payments = BayaranNotis5a::query()->where('id_pemerolehan', $case->id)->get();
        $confirmed = (float) $payments->where('status_pengesahan', 'disahkan')->sum(fn ($p) => (float) $p->jumlah_dibayar);
        $pending = (float) $payments->where('status_pengesahan', 'belum_disahkan')->sum(fn ($p) => (float) $p->jumlah_dibayar);
        $balance = max(0.0, round($due - $confirmed, 2));

        return [
            'jumlah_perlu_dibayar' => number_format($due, 2, '.', ''),
            'jumlah_disahkan' => number_format($confirmed, 2, '.', ''),
            'jumlah_belum_disahkan' => number_format($pending, 2, '.', ''),
            'baki_tertunggak' => number_format($balance, 2, '.', ''),
            'lengkap' => $due > 0 && $balance <= 0.0,
            'tarikh_akhir_bayaran' => $b['tarikh_akhir_bayaran'] ?? null,
        ];
    }

    /** @param array{jumlah_dibayar: numeric-string|float, tarikh_bayaran: string, mod_bayaran: string, no_resit: string, catatan?: string|null} $data */
    public function record(Pemerolehan $case, array $data, User $user): BayaranNotis5a
    {
        $this->assert5a($case);

        return BayaranNotis5a::query()->create([
            'id_pemerolehan' => $case->id,
            'jumlah_dibayar' => $data['jumlah_dibayar'],
            'tarikh_bayaran' => $data['tarikh_bayaran'],
            'mod_bayaran' => $data['mod_bayaran'],
            'no_resit' => $data['no_resit'],
            'catatan' => $data['catatan'] ?? null,
            'status_pengesahan' => 'belum_disahkan',
            'id_pegawai_rekod' => $user->id,
        ]);
    }

    /** Kewangan confirms or rejects a payment (explicitly audited as "sah"). */
    public function confirm(Pemerolehan $case, BayaranNotis5a $payment, bool $accept, User $user, ?string $note = null): BayaranNotis5a
    {
        $this->assert5a($case);
        if ($payment->status_pengesahan !== 'belum_disahkan') {
            throw new HttpException(409, __('per::per.bayaran_sudah_diproses'));
        }
        // Segregation of duties: whoever recorded a payment does not confirm it.
        if ($payment->id_pegawai_rekod === $user->id) {
            throw new HttpException(403, __('per::per.bayaran_sod'));
        }

        $payment->forceFill([
            'status_pengesahan' => $accept ? 'disahkan' : 'ditolak',
            'id_pegawai_pengesah' => $user->id,
            'tarikh_pengesahan' => now(),
            'catatan' => $note ?? $payment->catatan,
        ])->save();

        $this->audit->record($accept ? 'sah' : 'tolak', 'per', 'bayaran_notis_5a', $payment->id, reason: $note, meta: [
            'no_kes' => $case->no_kes, 'no_resit' => $payment->no_resit, 'klasifikasi' => 'sulit',
        ]);

        return $payment;
    }

    private function assert5a(Pemerolehan $case): void
    {
        if ($case->kodSaluran() !== 'pemberimilikan_5a') {
            throw new HttpException(422, __('per::per.bukan_5a'));
        }
    }
}
