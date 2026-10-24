<?php

namespace App\Modules\Per\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Integrations;
use App\Modules\Per\Contracts\WakafPortal;
use App\Modules\Per\Models\Pemerolehan;
use Illuminate\Support\Facades\Validator;

/**
 * SRS-PER-12/25: a public waqf registration from Portal Hartanah Wakaf
 * Selangor becomes a Wakaf case in status "permohonan awam menunggu
 * semakan". Nothing is written to lot_tanah (expected lot only) and the case
 * cannot progress until an officer accepts it. Idempotent per portal ref.
 */
class WakafIntake
{
    public const INTEGRATION = 'portal_wakaf';

    public function __construct(private CaseService $cases, private WakafPortal $portal) {}

    /** @param array<string, mixed> $submission */
    public function receive(array $submission, ?User $via = null): Pemerolehan
    {
        $data = Validator::make($submission, [
            'rujukan_portal' => ['required', 'string', 'max:100'],
            'nama_pewakaf' => ['required', 'string', 'max:200'],
            'no_pengenalan' => ['nullable', 'string', 'max:30'],
            'jenis_wakaf' => ['nullable', 'in:am,khas,irsod'],
            'tujuan_wakaf' => ['nullable', 'string', 'max:2000'],
            'no_lot' => ['nullable', 'string', 'max:50'],
            'kod_mukim' => ['nullable', 'regex:/^\d{6}$/'],
            'nilai_anggaran' => ['nullable', 'numeric', 'min:0'],
            'tarikh_permohonan' => ['nullable', 'date_format:Y-m-d'],
        ])->validate();

        $existing = Pemerolehan::query()->withoutGlobalScope('access')
            ->where('sumber_rekod', 'portal_wakaf')->where('rujukan_luaran', $data['rujukan_portal'])->first();
        if ($existing !== null) {
            return $existing;
        }

        $hasLot = ! empty($data['no_lot']) && ! empty($data['kod_mukim']);
        $case = $this->cases->create([
            'kod_saluran' => 'wakaf',
            'tajuk' => __('per::per.tajuk_portal_wakaf', ['rujukan' => $data['rujukan_portal']]),
            'no_lot' => $hasLot ? $data['no_lot'] : null,
            'kod_mukim' => $hasLot ? $data['kod_mukim'] : null,
            'tarikh_mula_kes' => $data['tarikh_permohonan'] ?? now()->toDateString(),
            'nilai_pasaran' => $data['nilai_anggaran'] ?? null,
            'rujukan_luaran' => $data['rujukan_portal'],
            'butiran' => array_filter([
                'nama_pewakaf' => $data['nama_pewakaf'],
                'jenis_wakaf' => $data['jenis_wakaf'] ?? null,
                'tujuan_wakaf_mawquf_alaih' => $data['tujuan_wakaf'] ?? null,
                'rujukan_portal' => $data['rujukan_portal'],
                'nilai_wakaf' => $data['nilai_anggaran'] ?? null,
            ], fn ($v) => $v !== null),
            'pihak' => [[
                'peranan_pihak' => 'pewakaf',
                'nama' => $data['nama_pewakaf'],
                'jenis_dokumen_pengenalan' => isset($data['no_pengenalan']) ? 'mykad' : null,
                'no_dokumen_pengenalan' => $data['no_pengenalan'] ?? null,
            ]],
        ], $via, 'portal_wakaf');

        $this->portal->acknowledge($data['rujukan_portal'], 'diterima_untuk_semakan', $case->no_kes);

        return $case;
    }

    /** Pull pending submissions from the portal adapter. Returns the number of new cases. */
    public function pull(): int
    {
        $created = 0;
        $started = microtime(true);
        $items = $this->portal->pending();
        foreach ($items as $submission) {
            $before = Pemerolehan::query()->withoutGlobalScope('access')->count();
            $this->receive($submission);
            $created += Pemerolehan::query()->withoutGlobalScope('access')->count() > $before ? 1 : 0;
        }

        if (app()->bound(Integrations::class)) {
            app(Integrations::class)->log(self::INTEGRATION, true, null, (int) ((microtime(true) - $started) * 1000), null, 'tarik_permohonan', count($items), direction: 'masuk');
        }

        return $created;
    }
}
