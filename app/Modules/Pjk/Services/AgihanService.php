<?php

namespace App\Modules\Pjk\Services;

use App\Models\User;
use App\Modules\Pjk\Models\DasarAgihanManfaat;
use App\Modules\Pjk\Models\KiraanAgihanManfaat;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Pjk\Support\Aliran;
use App\Modules\Pjk\Support\Kod;
use App\Modules\Pjk\Support\Wang;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * SRS-PJK-22..25: Dasar Agihan Manfaat. Rates are versioned per property
 * category × acquisition status (wakaf / belian), approved in WFE
 * `pjk.dasar_agihan`, and applied by date. A calculation takes the rent
 * collected for a premise in an accounting month (deposits excluded),
 * applies the policy rate, splits it among beneficiaries, and is stored as
 * an auditable record linked to the policy version (draf → disahkan).
 * Simulation runs the same arithmetic with proposed rates without storing.
 *
 * The formula is an assumption pending MAIS's policy document (PLAN §14 #10):
 * amaun_agihan = hasil_kutipan × kadar_agihan%; each beneficiary gets its
 * peratus of amaun_agihan; rounding remainder goes to the last beneficiary.
 */
class AgihanService
{
    public function __construct(private WorkflowEngine $workflow, private SaluranPemerolehan $saluran) {}

    /** @param array<string, mixed> $input */
    public function createPolicy(array $input, User $user): DasarAgihanManfaat
    {
        $data = Validator::make($input, [
            'kategori_hartanah' => ['required', Rule::in(array_keys(Kod::KATEGORI_HARTANAH))],
            'status_perolehan' => ['required', Rule::in(array_keys(Kod::STATUS_PEROLEHAN))],
            'kadar_agihan' => ['required', 'decimal:0,4', 'min:0', 'max:100'],
            'penerima_manfaat' => ['required', 'array', 'min:1', 'max:20'],
            'penerima_manfaat.*.kod' => ['required', 'string', 'max:40'],
            'penerima_manfaat.*.nama' => ['required', 'string', 'max:200'],
            'penerima_manfaat.*.peratus' => ['required', 'decimal:0,4', 'gt:0', 'max:100'],
            'tarikh_mula_kuasa' => ['required', 'date_format:Y-m-d'],
            'tarikh_tamat' => ['nullable', 'date_format:Y-m-d', 'after:tarikh_mula_kuasa'],
            'rujukan_kelulusan' => ['nullable', 'string', 'max:120'],
        ], [], ['kategori_hartanah' => 'kategori hartanah', 'status_perolehan' => 'status perolehan', 'kadar_agihan' => 'kadar agihan', 'penerima_manfaat' => 'penerima manfaat', 'tarikh_mula_kuasa' => 'tarikh mula berkuat kuasa'])->validate();

        $sum = array_sum(array_map(fn ($p) => Wang::scaled((string) $p['peratus']), $data['penerima_manfaat']));
        if ($sum !== 100 * 10000) {
            throw ValidationException::withMessages(['penerima_manfaat' => __('pjk::pjk.ralat.peratus_penerima')]);
        }
        $versi = (int) DasarAgihanManfaat::query()->where('kategori_hartanah', $data['kategori_hartanah'])->where('status_perolehan', $data['status_perolehan'])->max('versi_dasar') + 1;

        return DasarAgihanManfaat::query()->create([
            ...$data,
            'kadar_agihan' => $this->pct((string) $data['kadar_agihan']),
            'penerima_manfaat' => array_map(fn ($p) => ['kod' => (string) $p['kod'], 'nama' => (string) $p['nama'], 'peratus' => $this->pct((string) $p['peratus'])], array_values($data['penerima_manfaat'])),
            'versi_dasar' => $versi,
            'status' => 'draf',
            'id_pencipta' => $user->id,
        ]);
    }

    public function submit(DasarAgihanManfaat $dasar, User $user): DasarAgihanManfaat
    {
        if ($dasar->status !== 'draf') {
            throw ValidationException::withMessages(['status' => __('pjk::pjk.ralat.bukan_draf')]);
        }

        return DB::transaction(function () use ($dasar, $user) {
            $instance = Aliran::start($this->workflow, Aliran::DASAR, $dasar->id, $user, [
                'kategori_hartanah' => $dasar->kategori_hartanah, 'status_perolehan' => $dasar->status_perolehan,
                'kadar_agihan' => (float) $dasar->kadar_agihan, 'pautan' => '/admin/pjk/agihan',
            ], __('pjk::pjk.aliran.tajuk_dasar', ['kategori' => Kod::KATEGORI_HARTANAH[$dasar->kategori_hartanah], 'status' => Kod::STATUS_PEROLEHAN[$dasar->status_perolehan], 'versi' => $dasar->versi_dasar]));
            $dasar->forceFill(['status' => 'dalam_kelulusan', 'id_instans_aliran' => $instance])->save();

            return $dasar;
        });
    }

    public function onWorkflowCompleted(WorkflowCompleted $event): void
    {
        if ($event->flowCode !== Aliran::DASAR) {
            return;
        }
        $dasar = DasarAgihanManfaat::query()->find($event->subjectId);
        if ($dasar === null || $dasar->status !== 'dalam_kelulusan') {
            return;
        }
        DB::transaction(function () use ($dasar, $event) {
            if ($event->outcome !== 'lulus') {
                $dasar->forceFill(['status' => $event->outcome === 'tolak' ? 'ditolak' : 'draf'])->save();

                return;
            }
            $this->activate($dasar);
        });
    }

    /** New version supersedes the running one from its start date. */
    public function activate(DasarAgihanManfaat $dasar): void
    {
        $start = Carbon::parse($dasar->tarikh_mula_kuasa);
        DasarAgihanManfaat::query()->where('id', '!=', $dasar->id)->where('kategori_hartanah', $dasar->kategori_hartanah)
            ->where('status_perolehan', $dasar->status_perolehan)->where('status', 'aktif')
            ->where(fn ($q) => $q->whereNull('tarikh_tamat')->orWhereDate('tarikh_tamat', '>=', $start))
            ->get()->each(fn (DasarAgihanManfaat $old) => $old->forceFill(['status' => 'digantikan', 'tarikh_tamat' => $start->copy()->subDay()->format('Y-m-d')])->save());
        $dasar->forceFill(['status' => 'aktif'])->save();
    }

    /** SRS-PJK-23: the policy that applies to a category/status on a date. */
    public function applicable(string $kategori, string $status, Carbon $date): ?DasarAgihanManfaat
    {
        return DasarAgihanManfaat::query()->where('kategori_hartanah', $kategori)->where('status_perolehan', $status)
            ->whereIn('status', ['aktif', 'digantikan'])->whereDate('tarikh_mula_kuasa', '<=', $date)
            ->where(fn ($q) => $q->whereNull('tarikh_tamat')->orWhereDate('tarikh_tamat', '>=', $date))
            ->orderByDesc('versi_dasar')->first();
    }

    /**
     * SRS-PJK-24/25: calculate (or simulate) one accounting month.
     *
     * @param  list<int>|null  $premisIds  null = every premise with collections that month
     * @param  array<string, string>|null  $simulasi  "kategori:status" => proposed kadar (simulation only, nothing stored)
     * @return list<array<string, mixed>>
     */
    public function calculate(string $tempoh, ?array $premisIds, User $user, ?array $simulasi = null): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $tempoh)) {
            throw ValidationException::withMessages(['tempoh' => __('pjk::pjk.ralat.tempoh_tidak_sah')]);
        }
        $from = Carbon::parse($tempoh.'-01');
        $to = $from->copy()->endOfMonth();
        $collected = $this->collections($from, $to, $premisIds);
        $out = [];

        foreach (PremisAset::query()->whereIn('id', array_keys($collected))->get() as $premis) {
            $hasil = $collected[$premis->id]['sen'];
            $status = $this->saluran->statusFor($premis);
            $dasar = $this->applicable($premis->kategori_hartanah, $status, $to);
            if ($dasar === null) {
                $out[] = ['premis_id' => $premis->id, 'kod_premis' => $premis->kod_premis, 'tempoh_perakaunan' => $tempoh, 'hasil_kutipan' => Wang::dec($hasil), 'ralat' => __('pjk::pjk.agihan.tiada_dasar')];

                continue;
            }
            $kadar = $simulasi[$premis->kategori_hartanah.':'.$status] ?? $dasar->kadar_agihan;
            $kadar = $this->pct((string) $kadar);
            $amount = Wang::percent($hasil, $kadar);
            $split = $this->split($amount, $dasar->penerima_manfaat);
            $row = [
                'premis_id' => $premis->id, 'kontrak_id' => $collected[$premis->id]['kontrak_id'], 'tempoh_perakaunan' => $tempoh,
                'hasil_kutipan' => Wang::dec($hasil), 'id_dasar_digunakan' => $dasar->id, 'versi_dasar' => $dasar->versi_dasar,
                'kategori_hartanah' => $premis->kategori_hartanah, 'status_perolehan' => $status, 'kadar_agihan' => $kadar,
                'amaun_agihan' => Wang::dec($amount), 'pecahan_penerima' => $split, 'kod_daerah' => $premis->kod_daerah,
            ];
            if ($simulasi !== null) {
                $out[] = $row + ['kod_premis' => $premis->kod_premis, 'simulasi' => true];

                continue;
            }
            $existing = KiraanAgihanManfaat::query()->where('premis_id', $premis->id)->where('tempoh_perakaunan', $tempoh)->first();
            if ($existing?->status_pengesahan === 'disahkan') {
                $out[] = $existing->toArray() + ['kod_premis' => $premis->kod_premis, 'dikunci' => true];

                continue;
            }
            $saved = KiraanAgihanManfaat::query()->updateOrCreate(['premis_id' => $premis->id, 'tempoh_perakaunan' => $tempoh], $row + ['status_pengesahan' => 'draf', 'id_pengira' => $user->id]);
            $out[] = $saved->toArray() + ['kod_premis' => $premis->kod_premis];
        }

        return $out;
    }

    /** @param list<int> $ids */
    public function confirm(array $ids, User $user): int
    {
        $rows = KiraanAgihanManfaat::query()->whereIn('id', $ids)->where('status_pengesahan', 'draf')->get();
        foreach ($rows as $r) {
            if ($r->id_pengira === $user->id) {
                throw ValidationException::withMessages(['pengesah' => __('pjk::pjk.ralat.pengesah_sama')]);
            }
        }
        $rows->each(fn (KiraanAgihanManfaat $r) => $r->forceFill(['status_pengesahan' => 'disahkan', 'id_pengesah' => $user->id, 'tarikh_pengesahan' => now()])->save());

        return $rows->count();
    }

    /**
     * Rent collected per premise in a period (confirmed allocations to non-deposit charges, net of reversals).
     *
     * @param  list<int>|null  $premisIds
     * @return array<int, array{sen: int, kontrak_id: string|null}>
     */
    public function collections(Carbon $from, Carbon $to, ?array $premisIds = null): array
    {
        $rows = DB::table('agihan_bayaran as a')
            ->join('bayaran as b', 'b.id', '=', 'a.bayaran_id')
            ->join('jadual_caj as c', 'c.id', '=', 'a.caj_id')
            ->join('kontrak_pajakan as k', 'k.id', '=', 'c.kontrak_id')
            ->where('a.dibalikkan', false)->where('b.status', 'disahkan')->where('c.kod_caj', '!=', 'deposit')
            ->whereBetween('b.tarikh_bayaran', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->whereNotNull('k.premis_id')
            ->when($premisIds !== null, fn ($q) => $q->whereIn('k.premis_id', $premisIds))
            ->groupBy('k.premis_id')
            ->selectRaw('k.premis_id, sum(a.amaun)::text as jumlah, max(k.id::text) as kontrak_id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->premis_id] = ['sen' => Wang::sen((string) $r->jumlah), 'kontrak_id' => $r->kontrak_id];
        }

        return $out;
    }

    /**
     * @param  list<array{kod: string, nama: string, peratus: string}>  $recipients
     * @return list<array{kod: string, nama: string, peratus: string, amaun: string}>
     */
    public function split(int $amount, array $recipients): array
    {
        $out = [];
        $given = 0;
        $last = count($recipients) - 1;
        foreach ($recipients as $i => $p) {
            $share = $i === $last ? $amount - $given : Wang::percent($amount, (string) $p['peratus']);
            $given += $share;
            $out[] = ['kod' => $p['kod'], 'nama' => $p['nama'], 'peratus' => (string) $p['peratus'], 'amaun' => Wang::dec($share)];
        }

        return $out;
    }

    private function pct(string $v): string
    {
        $s = Wang::scaled($v);

        return intdiv($s, 10000).'.'.str_pad((string) ($s % 10000), 4, '0', STR_PAD_LEFT);
    }
}
