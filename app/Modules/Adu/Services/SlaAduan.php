<?php

namespace App\Modules\Adu\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Models\EskalasiAduan;
use App\Modules\Adu\Models\JejakSla;
use App\Modules\Adu\Models\SlaKategori;
use App\Modules\Adu\Support\Akses;
use App\Modules\Adu\Support\Lalai;
use App\Modules\Adu\Support\Rujukan;
use App\Modules\Ntf\Contracts\Notifier;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

/**
 * SRS-ADU-12/13/14: SLA per category in working time (KalendarKerja),
 * remaining time for the officer's queue, and the tiered escalation path
 * run by `adu:semak-sla` (scheduled every 15 minutes).
 */
class SlaAduan
{
    public function __construct(
        private KalendarKerja $calendar,
        private Parameters $parameters,
        private Kronologi $kronologi,
        private Akses $akses,
        private Notifier $notifier,
        private Rujukan $ref,
    ) {}

    /** SLA in force for a category/subcategory on a date (subcategory-specific wins). */
    public function untuk(string $kategori, ?string $subkategori, ?DateTimeInterface $on = null): ?SlaKategori
    {
        $date = CarbonImmutable::instance($on ?? now())->toDateString();
        $find = fn (string $kat, ?string $sub) => SlaKategori::query()
            ->where('aktif', true)->where('kategori', $kat)
            ->when($sub === null, fn ($q) => $q->whereNull('subkategori'), fn ($q) => $q->where('subkategori', $sub))
            ->whereDate('tarikh_berkuat_kuasa', '<=', $date)
            ->orderByDesc('tarikh_berkuat_kuasa')->orderByDesc('id')->first();

        return ($subkategori !== null ? $find($kategori, $subkategori) : null) ?? $find($kategori, null) ?? $find('LAIN', null);
    }

    public function mula(Aduan $aduan, ?DateTimeInterface $at = null): JejakSla
    {
        $start = CarbonImmutable::instance($at ?? now());
        $sla = $this->untuk((string) $aduan->kategori, $aduan->subkategori, $start);
        $unit = $sla->unit_masa ?? 'hari_bekerja';
        $respons = $sla->tempoh_respons ?? (int) $this->parameters->get('adu.sla_respons_lalai', 3);
        $selesai = $sla->tempoh_penyelesaian ?? (int) $this->parameters->get('adu.sla_selesai_lalai', 30);

        $previous = JejakSla::query()->where('aduan_id', $aduan->id)->where('semasa', true)->first();
        $previous?->update(['semasa' => false]);

        return JejakSla::query()->create([
            'aduan_id' => $aduan->id,
            'no_rujukan_aduan' => $aduan->no_rujukan_aduan,
            'id_sla' => $sla?->id,
            'kitaran' => ($previous->kitaran ?? 0) + 1,
            'masa_mula' => $start,
            'masa_sasaran_respons' => $this->calendar->due($start, $respons, $unit),
            'masa_sasaran_selesai' => $this->calendar->due($start, $selesai, $unit),
            'status_sla' => 'dalam_tempoh',
            'semasa' => true,
        ]);
    }

    /** First officer response (assignment acknowledged / investigation started). */
    public function respons(Aduan $aduan): void
    {
        $jejak = $this->semasa($aduan);
        if ($jejak && $jejak->masa_respons_sebenar === null) {
            $jejak->update(['masa_respons_sebenar' => now()]);
        }
    }

    public function selesai(Aduan $aduan): void
    {
        $jejak = $this->semasa($aduan);
        if ($jejak && $jejak->masa_selesai_sebenar === null) {
            $now = now();
            $jejak->update([
                'masa_respons_sebenar' => $jejak->masa_respons_sebenar ?? $now,
                'masa_selesai_sebenar' => $now,
                'status_sla' => $now->lessThanOrEqualTo($jejak->masa_sasaran_selesai) ? 'dipenuhi' : 'tidak_dipenuhi',
            ]);
        }
    }

    public function semasa(Aduan $aduan): ?JejakSla
    {
        return JejakSla::query()->where('aduan_id', $aduan->id)->where('semasa', true)->first();
    }

    /**
     * Remaining time for display (SRS-ADU-12): working minutes / days left to each target.
     *
     * @return array<string, mixed>|null
     */
    public function baki(?JejakSla $jejak, ?DateTimeInterface $now = null): ?array
    {
        if ($jejak === null) {
            return null;
        }
        $now = CarbonImmutable::instance($now ?? now());
        $perDay = $this->calendar->minutesPerDay();
        $end = $jejak->masa_selesai_sebenar ?? $now;
        $bakiSelesai = $this->calendar->workingMinutesBetween($end, $jejak->masa_sasaran_selesai);
        $bakiRespons = $jejak->masa_respons_sebenar ? null : $this->calendar->workingMinutesBetween($now, $jejak->masa_sasaran_respons);

        // Live state for open cycles (adu:semak-sla persists it every 15 minutes).
        $pct = $this->peratus($jejak, 'selesai', $now);
        $status = $jejak->masa_selesai_sebenar !== null ? $jejak->status_sla
            : ($pct >= 100 ? 'dilepasi' : ($pct >= (int) $this->parameters->get('adu.ambang_hampir_tamat_peratus', 80) ? 'hampir_tamat' : 'dalam_tempoh'));

        return [
            'kitaran' => $jejak->kitaran,
            'status_sla' => $status,
            'status_sla_nama' => (string) __('adu::adu.status_sla.'.$status),
            'masa_mula' => $jejak->masa_mula->toIso8601String(),
            'masa_sasaran_respons' => $jejak->masa_sasaran_respons->toIso8601String(),
            'masa_sasaran_selesai' => $jejak->masa_sasaran_selesai->toIso8601String(),
            'masa_respons_sebenar' => $jejak->masa_respons_sebenar?->toIso8601String(),
            'masa_selesai_sebenar' => $jejak->masa_selesai_sebenar?->toIso8601String(),
            'baki_minit_respons' => $bakiRespons,
            'baki_minit_selesai' => $bakiSelesai,
            'baki_hari_selesai' => round($bakiSelesai / $perDay, 1),
            'peratus_digunakan' => $pct,
            'bilangan_eskalasi' => $jejak->bilangan_eskalasi,
        ];
    }

    /** Percentage of a target's working time elapsed. */
    public function peratus(JejakSla $jejak, string $sasaran, ?DateTimeInterface $now = null): int
    {
        $now = CarbonImmutable::instance($now ?? now());
        $target = $sasaran === 'respons' ? $jejak->masa_sasaran_respons : $jejak->masa_sasaran_selesai;
        $total = max(1, $this->calendar->workingMinutesBetween($jejak->masa_mula, $target));
        $used = $this->calendar->workingMinutesBetween($jejak->masa_mula, $now);

        return (int) floor(100 * $used / $total);
    }

    /**
     * Walk every open complaint's current SLA and fire the escalation path
     * steps whose threshold has been reached (each step once per cycle).
     *
     * @return array{disemak: int, eskalasi: int}
     */
    public function semak(?DateTimeInterface $now = null): array
    {
        $now = CarbonImmutable::instance($now ?? now());
        $checked = 0;
        $fired = 0;
        $warn = (int) $this->parameters->get('adu.ambang_hampir_tamat_peratus', 80);

        $aduan = Aduan::query()->withoutGlobalScope('access')->terbuka()
            ->whereNotIn('status', ['menunggu_kelulusan'])
            ->with(['slaSemasa.sla', 'pegawai'])->get();

        foreach ($aduan as $a) {
            $jejak = $a->slaSemasa;
            if ($jejak === null || $jejak->masa_selesai_sebenar !== null) {
                continue;
            }
            $checked++;
            $pctSelesai = $this->peratus($jejak, 'selesai', $now);
            $pctRespons = $jejak->masa_respons_sebenar ? 0 : $this->peratus($jejak, 'respons', $now);

            $status = $pctSelesai >= 100 ? 'dilepasi' : ($pctSelesai >= $warn ? 'hampir_tamat' : 'dalam_tempoh');
            if ($status !== $jejak->status_sla) {
                $jejak->update(['status_sla' => $status]);
            }

            $path = $jejak->sla->laluan_eskalasi ?? Lalai::LALUAN_ESKALASI;
            $done = EskalasiAduan::query()->where('aduan_id', $a->id)->where('jenis', 'automatik')
                ->where('tarikh_eskalasi', '>=', $jejak->masa_mula)->pluck('peringkat')->map(fn ($v) => (int) $v)->all();

            foreach ($path as $step) {
                $level = $step['peringkat'];
                $pct = $step['sasaran'] === 'respons' ? $pctRespons : $pctSelesai;
                if (in_array($level, $done, true) || $pct < $step['peratus']) {
                    continue;
                }
                $this->eskalasi($a, $jejak, $level, $step['penerima'], $step['sasaran'], $step['peratus']);
                $fired++;
            }
        }

        return ['disemak' => $checked, 'eskalasi' => $fired];
    }

    /** @param list<string> $targets */
    private function eskalasi(Aduan $aduan, JejakSla $jejak, int $level, array $targets, string $sasaran, int $pct): void
    {
        $recipients = $this->akses->penerima($aduan, $targets);
        $reason = (string) __('adu::adu.sebab_eskalasi.'.($pct >= 100 ? 'dilepasi' : 'hampir_tamat'), [
            'sasaran' => __('adu::adu.sasaran.'.$sasaran), 'peratus' => $pct,
        ]);
        $first = collect($recipients)->first(fn (User $u) => $u->id !== $aduan->id_pegawai);

        EskalasiAduan::query()->create([
            'aduan_id' => $aduan->id,
            'no_rujukan_aduan' => $aduan->no_rujukan_aduan,
            'peringkat' => $level,
            'jenis' => 'automatik',
            'id_pegawai_asal' => $aduan->id_pegawai,
            'id_pegawai_eskalasi' => $first?->id,
            'penerima' => array_map(fn (User $u) => $u->id, $recipients),
            'sebab_eskalasi' => $reason,
            'tarikh_eskalasi' => now(),
        ]);
        $jejak->update(['bilangan_eskalasi' => $jejak->bilangan_eskalasi + 1]);
        $this->kronologi->rekod($aduan, 'eskalasi', $reason, null, data: ['peringkat' => $level, 'sasaran' => $sasaran, 'penerima' => array_map(fn (User $u) => $u->id, $recipients)]);

        $type = $pct >= 100 ? 'adu.sla_dilepasi' : 'adu.sla_hampir_tamat';
        try {
            $this->notifier->send($type, $recipients, [
                'no_rujukan' => $aduan->no_rujukan_aduan,
                'kategori' => $this->ref->namaKategori($aduan->kategori),
                'peringkat' => $level,
                'sasaran' => (string) __('adu::adu.sasaran.'.$sasaran),
                'tarikh_sasaran' => ($sasaran === 'respons' ? $jejak->masa_sasaran_respons : $jejak->masa_sasaran_selesai)->setTimezone(KalendarKerja::TZ)->format('d/m/Y H:i'),
            ], '/admin/adu/aduan/'.$aduan->id, 'adu:eskalasi:'.$aduan->id.':'.$jejak->kitaran.':'.$level);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
