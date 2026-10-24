<?php

namespace App\Modules\Per\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Integrations;
use App\Modules\Aud\Contracts\AuditScope;
use App\Modules\Per\Contracts\EFaraid;
use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Per\Models\PihakBerkaitan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * SRS-PER-13: verify a Faraid case's heirs against e-Faraid MAIS. Fills the
 * deceased / share fields and replaces the e-Faraid-sourced heirs with the
 * verified list (status "disahkan").
 */
class FaraidVerifier
{
    public const INTEGRATION = 'e_faraid';

    public function __construct(private EFaraid $faraid) {}

    public function verify(Pemerolehan $case, User $user): Pemerolehan
    {
        if ($case->kodSaluran() !== 'faraid') {
            throw new HttpException(422, __('per::per.bukan_faraid'));
        }
        $number = $case->butiran_saluran['no_kes_faraid'] ?? null;
        if (! is_string($number) || $number === '') {
            throw new HttpException(422, __('per::per.tiada_no_kes_faraid'));
        }

        $started = microtime(true);
        $result = $this->faraid->lookup($number);
        $this->log($result !== null, $started);
        if ($result === null) {
            throw new HttpException(404, __('per::per.e_faraid_tiada', ['no' => $number]));
        }

        DB::transaction(function () use ($case, $result) {
            AuditScope::withReason(__('per::per.sebab_e_faraid'), function () use ($case, $result) {
                $case->forceFill([
                    'butiran_saluran' => array_merge($case->butiran_saluran ?? [], array_filter([
                        'tarikh_kematian' => $result['tarikh_kematian'],
                        'bahagian_mais' => $result['bahagian_mais'],
                        'rujukan_e_faraid' => $result['rujukan'],
                        'status_pengesahan_waris' => 'disahkan',
                    ], fn ($v) => $v !== null)),
                    'butiran_peribadi' => array_merge($case->butiran_peribadi ?? [], ['nama_si_mati' => $result['nama_si_mati']]),
                ])->save();

                PihakBerkaitan::query()->where('id_pemerolehan', $case->id)->where('sumber_pengesahan', 'e_faraid')->delete();
                foreach ($result['waris'] as $heir) {
                    $case->pihak()->create([
                        'peranan_pihak' => 'waris',
                        'nama' => $heir['nama'],
                        'hubungan' => $heir['hubungan'],
                        'jenis_dokumen_pengenalan' => $heir['no_pengenalan'] ? 'mykad' : null,
                        'no_dokumen_pengenalan' => $heir['no_pengenalan'],
                        'bahagian_pusaka' => $heir['bahagian'],
                        'status_pengesahan' => 'disahkan',
                        'sumber_pengesahan' => 'e_faraid',
                    ]);
                }
            });
        });

        return $case->refresh();
    }

    private function log(bool $ok, float $started): void
    {
        if (app()->bound(Integrations::class)) {
            app(Integrations::class)->log(self::INTEGRATION, $ok, null, (int) ((microtime(true) - $started) * 1000), $ok ? null : 'tiada rekod', 'semak_waris', $ok ? 1 : 0);
        }
    }
}
