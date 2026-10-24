<?php

namespace App\Modules\Kes\Services;

use App\Models\User;
use App\Modules\Kes\Contracts\CourtFilingSystem;
use App\Modules\Kes\Contracts\CourtSystemUnavailable;
use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Kes\Models\Kronologi;
use App\Modules\Kes\Models\Prosiding;
use App\Modules\Kes\Models\TarikhPenting;
use App\Modules\Kes\Support\Kod;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * SRS-KES-07/08/09: proceedings — date, type, outcome, court directions and
 * the next date (which is scheduled as the next proceeding so it lands in
 * the legal calendar and in the 14/7/1-day reminders), plus other important
 * dates. Court e-Filing sync through the CourtFilingSystem adapter.
 */
class ProsidingService
{
    /** Proceeding type → case stage it implies, when the user asks for it. */
    private const STATUS_IKUT_JENIS = ['sebutan' => 'sebutan', 'bicara' => 'dalam_bicara', 'keputusan' => 'keputusan', 'rayuan' => 'rayuan'];

    public function __construct(private KesService $kes, private Rujukan $ref, private CourtFilingSystem $court) {}

    /** @param array<string, mixed> $d validated */
    public function rekod(KesMahkamah $kes, array $d, User $by, bool $sintetik = false): Prosiding
    {
        $p = new Prosiding;
        $p->forceFill([
            'kes_mahkamah_id' => $kes->id,
            'tarikh_prosiding' => $d['tarikh_prosiding'],
            'jenis_prosiding' => $d['jenis_prosiding'],
            'status' => $d['status'] ?? 'dijadualkan',
            'hasil' => $d['hasil'] ?? null,
            'arahan_mahkamah' => $d['arahan_mahkamah'] ?? null,
            'tarikh_berikutnya' => $d['tarikh_berikutnya'] ?? null,
            'jenis_berikutnya' => $d['jenis_berikutnya'] ?? null,
            'catatan' => $d['catatan'] ?? null,
            'sumber' => $d['sumber'] ?? 'manual',
            'id_pegawai_rekod' => $by->id,
            'sintetik' => $sintetik,
        ])->save();
        $this->susulan($kes, $p, $d, $by, $sintetik);

        return $p;
    }

    /** @param array<string, mixed> $d validated */
    public function kemasKini(KesMahkamah $kes, Prosiding $p, array $d, User $by): Prosiding
    {
        $p->fill(array_intersect_key($d, array_flip(['tarikh_prosiding', 'jenis_prosiding', 'status', 'hasil', 'arahan_mahkamah', 'tarikh_berikutnya', 'jenis_berikutnya', 'catatan'])))->save();
        $this->susulan($kes, $p, $d, $by, false);

        return $p;
    }

    /** @param array<string, mixed> $d */
    private function susulan(KesMahkamah $kes, Prosiding $p, array $d, User $by, bool $sintetik): void
    {
        // The next date becomes a scheduled proceeding (once).
        if ($p->tarikh_berikutnya !== null && $p->status !== 'dijadualkan') {
            $jenis = $p->jenis_berikutnya ?? $p->jenis_prosiding;
            $exists = Prosiding::query()->where('kes_mahkamah_id', $kes->id)->whereDate('tarikh_prosiding', $p->tarikh_berikutnya->toDateString())->where('jenis_prosiding', $jenis)->exists();
            if (! $exists) {
                Prosiding::query()->create([
                    'kes_mahkamah_id' => $kes->id, 'tarikh_prosiding' => $p->tarikh_berikutnya->copy()->setTime(9, 0), 'jenis_prosiding' => $jenis,
                    'status' => 'dijadualkan', 'catatan' => 'Dijadualkan daripada prosiding '.$p->tarikh_prosiding->format('d/m/Y'), 'sumber' => 'manual', 'id_pegawai_rekod' => $by->id, 'sintetik' => $sintetik,
                ]);
            }
        }
        if (! empty($d['status_kes'])) {
            $this->kes->tukarStatus($kes, (string) $d['status_kes'], $by, 'Prosiding '.$p->tarikh_prosiding->format('d/m/Y'));
        } elseif (! empty($d['ikut_jenis']) && isset(self::STATUS_IKUT_JENIS[$p->jenis_prosiding]) && isset($this->ref->status()[self::STATUS_IKUT_JENIS[$p->jenis_prosiding]])) {
            $this->kes->tukarStatus($kes, self::STATUS_IKUT_JENIS[$p->jenis_prosiding], $by, 'Prosiding '.$p->tarikh_prosiding->format('d/m/Y'));
        } elseif ($p->status === 'ditangguh' && isset($this->ref->status()['tangguh']) && ! $kes->terminal()) {
            $this->kes->tukarStatus($kes, 'tangguh', $by, 'Prosiding ditangguhkan');
        }
        $this->kes->segarTarikhBerikutnya($kes);
    }

    /** @param array<string, mixed> $d validated */
    public function tarikhPenting(KesMahkamah $kes, array $d, User $by, bool $sintetik = false): TarikhPenting
    {
        $t = new TarikhPenting;
        $t->forceFill(['kes_mahkamah_id' => $kes->id, 'jenis' => $d['jenis'], 'tarikh' => $d['tarikh'], 'keterangan' => $d['keterangan'] ?? null, 'status' => 'belum', 'id_pengguna' => $by->id, 'sintetik' => $sintetik])->save();

        return $t;
    }

    /**
     * Import hearing dates the court system holds (adapter). On failure the
     * officer keeps entering them manually (manual fallback).
     *
     * @return array{sistem: string, baharu: int, status_mahkamah: string|null}
     */
    public function segerakMahkamah(KesMahkamah $kes, User $by): array
    {
        try {
            $info = $this->court->caseInfo($kes->no_kes, $kes->mahkamah);
        } catch (CourtSystemUnavailable $e) {
            throw ValidationException::withMessages(['mahkamah' => __('kes::kes.ralat.sistem_mahkamah', ['sebab' => $e->getMessage()])]);
        }
        if ($info === null) {
            throw ValidationException::withMessages(['mahkamah' => __('kes::kes.ralat.kes_tiada_di_mahkamah')]);
        }
        $baharu = 0;
        foreach ($info['prosiding'] as $row) {
            $tarikh = Carbon::parse($row['tarikh']);
            $jenis = array_key_exists($row['jenis'], Kod::JENIS_PROSIDING) ? $row['jenis'] : 'lain';
            $exists = Prosiding::query()->where('kes_mahkamah_id', $kes->id)->whereDate('tarikh_prosiding', $tarikh->toDateString())->where('jenis_prosiding', $jenis)->exists();
            if ($exists) {
                continue;
            }
            Prosiding::query()->create([
                'kes_mahkamah_id' => $kes->id, 'tarikh_prosiding' => $tarikh, 'jenis_prosiding' => $jenis, 'status' => $tarikh->isPast() ? 'selesai' : 'dijadualkan',
                'catatan' => $row['catatan'] ?? 'Diimport daripada sistem mahkamah', 'sumber' => 'efiling', 'id_pegawai_rekod' => $by->id,
            ]);
            $baharu++;
        }
        Kronologi::query()->create(['kes_mahkamah_id' => $kes->id, 'tarikh' => now(), 'jenis' => 'efiling', 'keterangan' => "Segerak sistem mahkamah ({$this->court->name()}): {$baharu} tarikh baharu", 'sumber' => $this->court->name(), 'id_pengguna' => $by->id]);
        $this->kes->segarTarikhBerikutnya($kes);

        return ['sistem' => $this->court->name(), 'baharu' => $baharu, 'status_mahkamah' => $info['status']];
    }
}
