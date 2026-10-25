<?php

namespace App\Modules\Pws\Integrasi;

use App\Modules\Per\Contracts\WakafPortal;
use App\Modules\Pws\Models\Permohonan;
use Illuminate\Support\Facades\DB;

/**
 * The real WakafPortal for MOD-PER (replaces Per\Integration\MockWakafPortal
 * when MOD-PWS is enabled). Waqf registrations submitted through the public
 * API are pushed at once to PER's portal endpoint; any that fail stay in
 * pws_permohonan and are offered here to PER's scheduled pull
 * (`per:portal-wakaf`, every 15 minutes). PER creates a Wakaf case
 * "permohonan awam menunggu semakan" and reports
 * every review decision back through acknowledge() — that is the status the
 * portal shows (two-way, SRS-PWS-21).
 *
 * It DECORATES whatever adapter was bound before (PER's mock in dev/tests):
 * pending() returns the inner adapter's items plus this outbox, and an
 * acknowledgement for a reference this outbox doesn't know is passed on.
 *
 * Personal data leaves the outbox once PER confirms receipt (SRS-PWS-20).
 */
class PwsWakafPortal implements WakafPortal
{
    public function __construct(private ?WakafPortal $inner = null) {}

    /** PER status → public label key (lang pws::pws.status.*). */
    public const STATUS = [
        'diterima_untuk_semakan' => 'menunggu_semakan',
        'diterima' => 'diterima',
        'ditolak' => 'ditolak',
        'selesai' => 'selesai',
    ];

    public function pending(): array
    {
        $out = $this->inner?->pending() ?? [];
        $rows = Permohonan::query()->where('jenis', 'wakaf')->whereIn('status_penghantaran', ['menunggu', 'gagal'])
            ->where('bilangan_cuba', '<', 10)->orderBy('id')->limit(100)->get();
        foreach ($rows as $row) {
            $data = $row->kandungan ?? [];
            DB::table('pws_permohonan')->where('id', $row->id)->update([
                'status_penghantaran' => 'dihantar', 'bilangan_cuba' => $row->bilangan_cuba + 1, 'updated_at' => now(),
            ]);
            $out[] = array_filter([
                'rujukan_portal' => $row->no_rujukan,
                'nama_pewakaf' => (string) ($data['nama_pewakaf'] ?? ''),
                'no_pengenalan' => $data['no_pengenalan'] ?? null,
                'jenis_wakaf' => $data['jenis_wakaf'] ?? null,
                'tujuan_wakaf' => $data['tujuan_wakaf'] ?? null,
                'no_lot' => $data['no_lot'] ?? null,
                'kod_mukim' => $data['kod_mukim'] ?? null,
                'keluasan' => $data['keluasan'] ?? null,
                'nilai_anggaran' => $data['nilai_anggaran'] ?? null,
                'tarikh_permohonan' => $row->created_at->toDateString(),
            ], fn ($v) => $v !== null && $v !== '');
        }

        return $out;
    }

    public function acknowledge(string $portalReference, string $status, ?string $caseNumber = null): void
    {
        $row = Permohonan::query()->where('jenis', 'wakaf')->where('no_rujukan', $portalReference)->first();
        if ($row === null) {
            $this->inner?->acknowledge($portalReference, $status, $caseNumber);

            return;
        }
        $public = self::STATUS[$status] ?? $status;
        if ($row->status_penghantaran !== 'diterima') {
            $row->forceFill(['status_penghantaran' => 'diterima', 'kandungan' => null, 'ralat_terakhir' => null])->save();
        }
        if ($caseNumber !== null && $row->rujukan_sisdata !== $caseNumber) {
            $row->forceFill(['rujukan_sisdata' => $caseNumber])->save();
        }
        $row->catatStatus($public, __('pws::pws.status.'.$public, [], 'ms'));
    }
}
