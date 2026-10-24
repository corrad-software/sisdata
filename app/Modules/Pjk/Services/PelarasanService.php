<?php

namespace App\Modules\Pjk\Services;

use App\Models\User;
use App\Modules\Pjk\Models\Bayaran;
use App\Modules\Pjk\Models\JadualCaj;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\LejarTransaksi;
use App\Modules\Pjk\Models\PelarasanLejar;
use App\Modules\Pjk\Models\Resit;
use App\Modules\Pjk\Support\Aliran;
use App\Modules\Pjk\Support\Kod;
use App\Modules\Pjk\Support\Penomboran;
use App\Modules\Pjk\Support\Wang;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * SRS-PJK-28: ledger adjustments, payment / receipt cancellations and charge
 * waivers need a second officer's approval (WFE `pjk.pelarasan_lejar`) with
 * written justification before they take effect. The ledger itself is never
 * edited — an approved request appends rows. As defence in depth, an
 * approval decided by the requester is refused here even if WFE allowed it.
 */
class PelarasanService
{
    public function __construct(
        private WorkflowEngine $workflow,
        private LejarService $lejar,
        private BayaranService $bayaran,
        private ResitService $resit,
        private KontrakService $kontrak,
    ) {}

    /** @param array<string, mixed> $input */
    public function request(Kontrak $kontrak, array $input, User $user): PelarasanLejar
    {
        $data = Validator::make($input, [
            'jenis' => ['required', Rule::in(array_keys(Kod::JENIS_PELARASAN))],
            'amaun' => ['nullable', 'required_unless:jenis,pembatalan', 'decimal:0,2', 'gt:0', 'max:99999999'],
            'caj_id' => ['nullable', 'required_if:jenis,pengecualian', 'integer'],
            'lejar_id_sasaran' => ['nullable', 'required_if:jenis,pembatalan', 'integer'],
            'justifikasi' => ['required', 'string', 'min:10', 'max:5000'],
        ], [], ['jenis' => 'jenis pelarasan', 'amaun' => 'amaun', 'caj_id' => 'caj', 'lejar_id_sasaran' => 'transaksi lejar', 'justifikasi' => 'justifikasi'])->validate();

        $amount = isset($data['amaun']) ? Wang::sen((string) $data['amaun']) : 0;
        if (! empty($data['caj_id'])) {
            $caj = JadualCaj::query()->where('kontrak_id', $kontrak->id)->find($data['caj_id']);
            if ($caj === null || ! $caj->dikeluarkan || in_array($caj->status_caj, ['dibatalkan', 'dikecualikan'], true)) {
                throw ValidationException::withMessages(['caj_id' => __('pjk::pjk.ralat.caj_tidak_sah')]);
            }
            if ($data['jenis'] !== 'pelarasan_debit' && $amount > $caj->bakiSen()) {
                throw ValidationException::withMessages(['amaun' => __('pjk::pjk.ralat.agihan_lebih_baki')]);
            }
        }
        if ($data['jenis'] === 'pembatalan') {
            $row = LejarTransaksi::query()->where('kontrak_id', $kontrak->id)->where('jenis', 'bayaran')->find($data['lejar_id_sasaran']);
            if ($row === null || LejarTransaksi::query()->where('id_transaksi_asal', $row->id)->exists()) {
                throw ValidationException::withMessages(['lejar_id_sasaran' => __('pjk::pjk.ralat.lejar_tidak_boleh_batal')]);
            }
            $amount = Wang::sen($row->kredit);
        }
        if (PelarasanLejar::query()->where('kontrak_id', $kontrak->id)->where('status', 'menunggu')
            ->when(! empty($data['lejar_id_sasaran']), fn ($q) => $q->where('lejar_id_sasaran', $data['lejar_id_sasaran']))
            ->when(! empty($data['caj_id']), fn ($q) => $q->where('caj_id', $data['caj_id']))
            ->when(empty($data['lejar_id_sasaran']) && empty($data['caj_id']), fn ($q) => $q->whereNull('caj_id')->whereNull('lejar_id_sasaran')->where('jenis', $data['jenis']))
            ->exists()) {
            throw ValidationException::withMessages(['pelarasan' => __('pjk::pjk.ralat.pelarasan_menunggu')]);
        }

        return DB::transaction(function () use ($kontrak, $data, $amount, $user) {
            $p = PelarasanLejar::query()->create([
                'kontrak_id' => $kontrak->id, 'jenis' => $data['jenis'], 'lejar_id_sasaran' => $data['lejar_id_sasaran'] ?? null,
                'caj_id' => $data['caj_id'] ?? null, 'amaun' => Wang::dec($amount), 'justifikasi' => $data['justifikasi'],
                'status' => 'menunggu', 'id_pemohon' => $user->id,
            ]);
            $instance = Aliran::start($this->workflow, Aliran::PELARASAN, $p->id, $user, [
                'jenis' => $data['jenis'], 'amaun' => $amount / 100, 'kod_daerah' => $kontrak->kod_daerah,
                'pautan' => '/admin/pjk/kontrak/'.$kontrak->id.'?tab=lejar',
            ], __('pjk::pjk.aliran.tajuk_pelarasan', ['jenis' => Kod::JENIS_PELARASAN[$data['jenis']], 'no' => $kontrak->no_kontrak]));
            $p->forceFill(['id_instans_aliran' => $instance])->save();

            return $p;
        });
    }

    public function onWorkflowCompleted(WorkflowCompleted $event): void
    {
        if ($event->flowCode !== Aliran::PELARASAN) {
            return;
        }
        $p = PelarasanLejar::query()->find($event->subjectId);
        if ($p === null || $p->status !== 'menunggu') {
            return;
        }
        if ($event->outcome === 'lulus' && ($event->decidedBy === null || $event->decidedBy === $p->id_pemohon)) {
            $p->forceFill(['status' => 'tolak', 'id_pelulus' => $event->decidedBy, 'tarikh_keputusan' => now(), 'ulasan' => __('pjk::pjk.ralat.pelulus_sama')])->save();

            return;
        }

        $receipt = DB::transaction(function () use ($p, $event) {
            $p->forceFill(['status' => $event->outcome === 'lulus' ? 'lulus' : 'tolak', 'id_pelulus' => $event->decidedBy, 'tarikh_keputusan' => now(), 'ulasan' => $event->remarks])->save();

            return $event->outcome === 'lulus' ? $this->apply($p, (int) $event->decidedBy) : null;
        });
        if ($receipt !== null) {
            $this->resit->deliver($receipt);
        }

        $pemohon = User::query()->find($p->id_pemohon);
        $kontrak = Kontrak::query()->withoutGlobalScope('access')->find($p->kontrak_id);
        $this->kontrak->notify('pjk.keputusan_kelulusan', $pemohon ? [$pemohon] : [], [
            'perkara' => Kod::JENIS_PELARASAN[$p->jenis], 'no_kontrak' => (string) $kontrak?->no_kontrak, 'keputusan' => __('pjk::pjk.keputusan.'.$event->outcome),
        ], '/admin/pjk/kontrak/'.$p->kontrak_id.'?tab=lejar');
    }

    private function apply(PelarasanLejar $p, int $approver): ?Resit
    {
        $kontrak = Kontrak::query()->withoutGlobalScope('access')->findOrFail($p->kontrak_id);
        $amount = Wang::sen($p->amaun);
        $base = ['pelarasan_id' => $p->id, 'status_pelarasan' => 'diluluskan', 'id_pegawai' => $approver, 'keterangan' => mb_substr(Kod::JENIS_PELARASAN[$p->jenis].': '.$p->justifikasi, 0, 500)];

        switch ($p->jenis) {
            case 'pelarasan_debit':
                $caj = JadualCaj::query()->create([
                    'kontrak_id' => $kontrak->id, 'no_caj' => Penomboran::next('caj'), 'kod_caj' => 'lain', 'tempoh' => 'PL-'.$p->id,
                    'tarikh_caj' => Carbon::today()->format('Y-m-d'), 'tarikh_akhir' => Carbon::today()->addDays($kontrak->hari_tempoh_bayar)->format('Y-m-d'),
                    'amaun' => Wang::dec($amount), 'status_caj' => 'belum_bayar', 'dikeluarkan' => true, 'keterangan' => mb_substr($p->justifikasi, 0, 300),
                ]);
                $this->lejar->post($kontrak, 'pelarasan_debit', $amount, 0, $base + ['kod_caj' => 'lain', 'caj_id' => $caj->id]);

                return null;

            case 'pelarasan_kredit':
            case 'pengecualian':
                if ($p->caj_id !== null) {
                    $caj = JadualCaj::query()->lockForUpdate()->findOrFail($p->caj_id);
                    $caj->amaun_dikecualikan = Wang::dec(Wang::sen($caj->amaun_dikecualikan) + min($amount, max(0, $caj->bakiSen())));
                    $caj->refreshStatus();
                    $caj->save();
                }
                $this->lejar->post($kontrak, $p->jenis, 0, $amount, $base + ['caj_id' => $p->caj_id]);

                return null;

            default: // pembatalan
                $row = LejarTransaksi::query()->findOrFail($p->lejar_id_sasaran);
                $bayaran = Bayaran::query()->lockForUpdate()->findOrFail($row->bayaran_id);
                $this->bayaran->unallocate($bayaran);
                $bayaran->forceFill(['status' => 'dibatalkan'])->save();
                $original = Resit::query()->where('bayaran_id', $bayaran->id)->where('jenis', 'resit')->firstOrFail();
                $credit = $this->resit->credit($original);
                $this->lejar->post($kontrak, 'pembatalan', Wang::sen($row->kredit), 0, $base + [
                    'id_transaksi_asal' => $row->id, 'bayaran_id' => $bayaran->id, 'no_resit' => $credit->no_resit, 'kaedah_bayaran' => $bayaran->saluran,
                ]);

                return $credit;
        }
    }
}
