<?php

namespace App\Modules\Tkt\Services;

use App\Models\User;
use App\Modules\Tkt\Models\Eskalasi;
use App\Modules\Tkt\Models\SlaKonfigurasi;
use App\Modules\Tkt\Models\Tiket;
use App\Modules\Tkt\Support\Lalai;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * SRS-TKT-08/09, §18.2/18.5: SLA per severity in service minutes. Targets =
 * opening time + threshold + minutes paused while waiting for the reporter
 * (the clock stops in menunggu_pelapor / selesai). `semak()` (scheduled
 * every 5 minutes) warns the assignee at the configured % of the period and
 * escalates along the configured hierarchy once a target is missed.
 */
class SlaTiket
{
    public function __construct(private KalendarSokongan $kalendar, private Penerima $penerima, private Jejak $jejak) {}

    public function konfigurasi(Tiket $tiket): ?SlaKonfigurasi
    {
        if ($tiket->id_sla_konfigurasi !== null) {
            $row = SlaKonfigurasi::query()->find($tiket->id_sla_konfigurasi);
            if ($row !== null && $row->keterukan === $tiket->keterukan) {
                return $row;
            }
        }

        return SlaKonfigurasi::semasa($tiket->keterukan);
    }

    /** Sets (or recomputes after a severity change / pause) both targets. */
    public function kira(Tiket $tiket, bool $semula = false): void
    {
        $cfg = $semula || $tiket->id_sla_konfigurasi === null ? SlaKonfigurasi::semasa($tiket->keterukan) : $this->konfigurasi($tiket);
        if ($cfg === null) {
            return;
        }
        $tiket->id_sla_konfigurasi = $cfg->id;
        $tiket->waktu_perkhidmatan = $cfg->waktu_perkhidmatan;
        $start = CarbonImmutable::instance($tiket->tarikh_buka);
        $tiket->tarikh_sasaran_respons = Carbon::instance($this->kalendar->tambah($start, $cfg->ambang_respons + $tiket->sla_jeda_minit, $cfg->waktu_perkhidmatan));
        $tiket->tarikh_sasaran_selesai = Carbon::instance($this->kalendar->tambah($start, $cfg->ambang_penyelesaian + $tiket->sla_jeda_minit, $cfg->waktu_perkhidmatan));
    }

    public function jeda(Tiket $tiket): void
    {
        if ($tiket->sla_jeda_mula === null) {
            $tiket->sla_jeda_mula = now();
        }
    }

    public function sambung(Tiket $tiket): void
    {
        if ($tiket->sla_jeda_mula === null) {
            return;
        }
        $minutes = max(0, $this->kalendar->minitAntara($tiket->sla_jeda_mula, now(), $tiket->waktu_perkhidmatan));
        $tiket->sla_jeda_minit += $minutes;
        $tiket->sla_jeda_mula = null;
        $this->kira($tiket);
    }

    /**
     * SLA picture for the UI / reports.
     *
     * @return array{waktu_perkhidmatan: string, dijeda: bool, jeda_minit: int, respons: array<string, mixed>, selesai: array<string, mixed>, peratus_digunakan: int|null, status: string, status_nama: string}
     */
    public function gambaran(Tiket $tiket): array
    {
        $now = CarbonImmutable::now();
        $cfg = $this->konfigurasi($tiket);
        $paused = $tiket->sla_jeda_mula !== null;
        $mode = $tiket->waktu_perkhidmatan;
        $part = function (?Carbon $target, ?Carbon $actual, bool $missed) use ($now, $mode, $paused, $tiket) {
            if ($target === null) {
                return ['sasaran' => null, 'sebenar' => null, 'baki_minit' => null, 'status' => 'tiada'];
            }
            if ($actual !== null) {
                return ['sasaran' => $target->toIso8601String(), 'sebenar' => $actual->toIso8601String(), 'baki_minit' => null, 'status' => $actual->lessThanOrEqualTo($target) && ! $missed ? 'dipenuhi' : 'tidak_dipenuhi'];
            }
            $reference = $paused ? CarbonImmutable::instance($tiket->sla_jeda_mula) : $now;
            $left = $this->kalendar->minitAntara($reference, $target, $mode);

            return ['sasaran' => $target->toIso8601String(), 'sebenar' => null, 'baki_minit' => $left, 'status' => $left < 0 || $missed ? 'dilepasi' : 'dalam_tempoh'];
        };
        $respons = $part($tiket->tarikh_sasaran_respons, $tiket->tarikh_respons_pertama, $tiket->respons_dilepasi);
        $selesai = $part($tiket->tarikh_sasaran_selesai, $tiket->tarikh_selesai, $tiket->selesai_dilepasi);

        $pct = null;
        if ($cfg !== null && $tiket->tarikh_selesai === null && $tiket->tarikh_sasaran_selesai !== null) {
            $pct = (int) floor(100 * $this->digunakan($tiket, $cfg->ambang_penyelesaian));
        }
        $status = match (true) {
            $selesai['status'] === 'dilepasi' || $respons['status'] === 'dilepasi' => 'dilepasi',
            $selesai['status'] === 'tidak_dipenuhi' => 'tidak_dipenuhi',
            $selesai['status'] === 'dipenuhi' => 'dipenuhi',
            $paused => 'dijeda',
            $cfg !== null && $pct !== null && $pct >= $cfg->ambang_amaran_peratus => 'hampir_tamat',
            default => 'dalam_tempoh',
        };

        return [
            'waktu_perkhidmatan' => $mode,
            'dijeda' => $paused,
            'jeda_minit' => $tiket->sla_jeda_minit,
            'respons' => $respons,
            'selesai' => $selesai,
            'peratus_digunakan' => $pct,
            'status' => $status,
            'status_nama' => (string) __('tkt::tkt.sla_status.'.$status),
        ];
    }

    /**
     * Fraction (0..n) of the period used so far, pause excluded.
     */
    public function digunakan(Tiket $tiket, int $threshold): float
    {
        if ($threshold <= 0) {
            return 1.0;
        }
        $until = $tiket->sla_jeda_mula ?? now();
        $elapsed = $this->kalendar->minitAntara($tiket->tarikh_buka, $until, $tiket->waktu_perkhidmatan) - $tiket->sla_jeda_minit;

        return max(0, $elapsed) / $threshold;
    }

    /**
     * Scheduled check. Returns counts of warnings and escalations sent.
     *
     * @return array{amaran: int, eskalasi: int}
     */
    public function semak(): array
    {
        $out = ['amaran' => 0, 'eskalasi' => 0];
        $now = CarbonImmutable::now();
        Tiket::query()->whereIn('status', Lalai::TERBUKA)->whereNull('sla_jeda_mula')->whereNotNull('tarikh_sasaran_selesai')
            ->orderBy('tarikh_buka')->chunkById(200, function ($rows) use (&$out, $now) {
                foreach ($rows as $tiket) {
                    /** @var Tiket $tiket */
                    $cfg = $this->konfigurasi($tiket);
                    if ($cfg === null) {
                        continue;
                    }
                    $checks = [];
                    if ($tiket->tarikh_respons_pertama === null && $tiket->tarikh_sasaran_respons !== null) {
                        $checks['respons'] = [$tiket->tarikh_sasaran_respons, $cfg->ambang_respons];
                    }
                    if ($tiket->tarikh_selesai === null && $tiket->tarikh_sasaran_selesai !== null) {
                        $checks['selesai'] = [$tiket->tarikh_sasaran_selesai, $cfg->ambang_penyelesaian];
                    }
                    foreach ($checks as $sasaran => [$target, $threshold]) {
                        if ($now->greaterThanOrEqualTo($target)) {
                            $field = $sasaran === 'respons' ? 'respons_dilepasi' : 'selesai_dilepasi';
                            if (! $tiket->{$field}) {
                                $tiket->forceFill([$field => true])->save();
                            }
                            $after = $this->kalendar->minitAntara($target, $now, $tiket->waktu_perkhidmatan);
                            foreach ($cfg->hierarki_eskalasi as $level) {
                                if ($after >= (int) $level['selepas_minit'] && ! $this->sudah($tiket, $sasaran, 'lepas_sla', (int) $level['peringkat'])) {
                                    $this->eskalasi($tiket, (int) $level['peringkat'], (string) $level['penerima'], $sasaran, 'lepas_sla', null, null);
                                    $out['eskalasi']++;
                                }
                            }
                        } elseif (100 * $this->digunakan($tiket, $threshold) >= $cfg->ambang_amaran_peratus && ! $this->sudah($tiket, $sasaran, 'hampir_sla', 0)) {
                            $this->eskalasi($tiket, 0, 'juruteknik', $sasaran, 'hampir_sla', null, null);
                            $out['amaran']++;
                        }
                    }
                }
            });

        return $out;
    }

    private function sudah(Tiket $tiket, string $sasaran, string $sebab, int $peringkat): bool
    {
        return Eskalasi::query()->where('id_tiket', $tiket->id)->where('sasaran', $sasaran)->where('sebab', $sebab)
            ->where('peringkat', $peringkat)->where('kitaran', $tiket->bilangan_buka_semula)->exists();
    }

    /**
     * Records one warning / escalation (a row per recipient) and notifies.
     * $sebab: hampir_sla | lepas_sla | manual.
     */
    public function eskalasi(Tiket $tiket, int $peringkat, string $jenisPenerima, string $sasaran, string $sebab, ?string $catatan, ?User $oleh): int
    {
        $recipients = $this->penerima->untuk($tiket, $jenisPenerima);
        $base = [
            'id_tiket' => $tiket->id, 'peringkat' => $peringkat, 'sasaran' => $sasaran, 'jenis_penerima' => $jenisPenerima,
            'sebab' => $sebab, 'kitaran' => $tiket->bilangan_buka_semula, 'catatan' => $catatan, 'id_pengguna' => $oleh?->id, 'cap_masa' => now(),
        ];
        if ($recipients === []) {
            Eskalasi::query()->create($base + ['id_penerima' => null, 'catatan' => $catatan ?? (string) __('tkt::tkt.eskalasi.tiada_penerima')]);
        }
        foreach ($recipients as $user) {
            Eskalasi::query()->create($base + ['id_penerima' => $user->id]);
        }
        if ($peringkat > $tiket->peringkat_eskalasi) {
            $tiket->forceFill(['peringkat_eskalasi' => $peringkat])->save();
        }

        $target = $sasaran === 'respons' ? $tiket->tarikh_sasaran_respons : $tiket->tarikh_sasaran_selesai;
        $extra = [
            'peringkat' => $peringkat,
            'sasaran' => (string) __('tkt::tkt.sasaran.'.$sasaran),
            'tarikh_sasaran' => $target?->setTimezone(KalendarSokongan::TZ)->format('d/m/Y H:i'),
        ];
        $this->jejak->catat($tiket, 'eskalasi', $oleh, $catatan, null, null, [
            'peringkat' => $peringkat, 'sebab' => $sebab, 'sasaran' => $sasaran, 'jenis_penerima' => $jenisPenerima,
            'penerima' => array_map(fn (User $u) => $u->name, $recipients),
        ]);
        $type = match ($sebab) {
            'hampir_sla' => 'tkt.sla_amaran',
            'lepas_sla' => 'tkt.sla_eskalasi',
            default => 'tkt.eskalasi_manual',
        };
        $this->jejak->hantar($type, $recipients, $tiket, $extra + ['sebab' => $catatan], "tkt:{$tiket->id}:{$sebab}:{$sasaran}:{$peringkat}:{$tiket->bilangan_buka_semula}");

        return count($recipients);
    }
}
