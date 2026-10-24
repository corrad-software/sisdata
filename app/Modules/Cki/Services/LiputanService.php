<?php

namespace App\Modules\Cki\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Cki\Models\Liputan;
use App\Modules\Cki\Models\ModOperasi;
use App\Modules\Cki\Models\Obligasi;
use App\Modules\Cki\Support\Penerima;
use App\Modules\Ntf\Contracts\Notifier;
use Illuminate\Support\Facades\DB;

/**
 * SRS-CKI-06: integration coverage register (per Pejabat Tanah daerah and
 * PBT) and the in/out-of-coverage flag of every obligation. SRS-CKI-08:
 * automatic switch to fallback mode on failure, officer notification,
 * escalation to the system administrator on persistent failure, and switch
 * back when the API recovers. Every mode change is audited.
 */
class LiputanService
{
    public function __construct(private Parameters $parameters, private AuditLogger $audit, private Notifier $notifier, private Penerima $penerima) {}

    /**
     * Seed one register row per district land office and per PBT from MOD-REF
     * (idempotent). New rows default to "covered" for enquiry and payment —
     * the central provider covers all of Selangor (SRS-CKI-04); admins switch
     * individual agencies off.
     */
    public function lalai(): int
    {
        $n = 0;
        foreach (DB::table('ref_daerah')->where('aktif', true)->whereNull('sah_hingga')->orderBy('kod')->get(['kod', 'nama']) as $d) {
            $row = Liputan::query()->firstOrCreate(['kod_agensi' => 'PTD-'.$d->kod], [
                'jenis_agensi' => 'ptd', 'kod_ptd' => $d->kod, 'nama_agensi' => 'Pejabat Tanah Daerah '.$d->nama,
                'perkhidmatan_semakan' => true, 'perkhidmatan_bayaran' => true, 'tarikh_kemas_kini' => now(),
            ]);
            $n += (int) $row->wasRecentlyCreated;
        }
        foreach (DB::table('ref_pbt')->where('aktif', true)->whereNull('sah_hingga')->orderBy('kod')->get(['kod', 'nama']) as $p) {
            $row = Liputan::query()->firstOrCreate(['kod_agensi' => (string) $p->kod], [
                'jenis_agensi' => 'pbt', 'kod_pbt' => $p->kod, 'nama_agensi' => $p->nama,
                'perkhidmatan_semakan' => true, 'perkhidmatan_bayaran' => true, 'tarikh_kemas_kini' => now(),
            ]);
            $n += (int) $row->wasRecentlyCreated;
        }

        return $n;
    }

    /** Agency for an obligation: the district land office, or the PBT for assessment tax. */
    public function agensiUntuk(string $jenisCukai, string $kodDaerah, ?string $kodPbt): ?Liputan
    {
        if ($jenisCukai === 'taksiran_pbt') {
            return $kodPbt ? Liputan::query()->where('kod_agensi', $kodPbt)->first() : null;
        }

        return Liputan::query()->where('kod_agensi', 'PTD-'.$kodDaerah)->first();
    }

    /** First PBT whose area includes the district (MOD-REF ref_pbt.daerah_kod). */
    public function pbtLalai(string $kodDaerah): ?string
    {
        $kod = DB::table('ref_pbt')->where('aktif', true)->whereNull('sah_hingga')
            ->whereRaw('daerah_kod::jsonb @> ?::jsonb', [json_encode([$kodDaerah])])->orderBy('kod')->value('kod');

        return $kod === null ? null : (string) $kod;
    }

    /** @param array<string, mixed> $data */
    public function kemaskini(Liputan $liputan, array $data): Liputan
    {
        $liputan->fill(array_intersect_key($data, array_flip(['nama_agensi', 'perkhidmatan_semakan', 'perkhidmatan_bayaran', 'catatan'])));
        $liputan->tarikh_kemas_kini = now();
        $liputan->save();
        $this->segerakBendera($liputan);

        return $liputan;
    }

    /** Re-flag every obligation of the agency as in / out of API coverage. */
    public function segerakBendera(Liputan $liputan): int
    {
        return Obligasi::query()->withoutGlobalScope('access')->where('kod_agensi', $liputan->kod_agensi)
            ->update(['status_liputan_api' => $liputan->perkhidmatan_semakan ? 'dalam' : 'luar', 'updated_at' => now()]);
    }

    /** A call to the agency failed after retries: fallback mode + notifications (SRS-CKI-08/10). */
    public function gagal(Liputan $liputan, string $sebab, ?User $by = null): void
    {
        $liputan->kegagalan_berturut++;
        $liputan->semakan_terakhir = now();
        $switched = false;
        if (! $liputan->dalamSandaran()) {
            $liputan->mod_semasa = 'sandaran';
            $switched = true;
        }
        $liputan->save();

        if ($switched) {
            ModOperasi::query()->create(['kod_agensi' => $liputan->kod_agensi, 'mod' => 'sandaran', 'tarikh_mula' => now(), 'sebab' => mb_substr($sebab, 0, 255), 'id_pegawai_maklum' => $by?->id]);
            $this->audit->record('tukar_mod', 'cki', 'daftar_liputan_integrasi', $liputan->id, reason: $sebab, changes: ['mod_semasa' => ['api', 'sandaran']], meta: ['kod_agensi' => $liputan->kod_agensi]);
            $this->notifier->send('cki.mod_sandaran', $this->penerima->untuk('cki.bil.cipta', $liputan->kod_ptd), [
                'agensi' => $liputan->nama_agensi, 'sebab' => mb_substr($sebab, 0, 200), 'masa' => now()->format('d/m/Y H:i'),
            ], '/admin/cki/tugasan', 'cki.mod_sandaran:'.$liputan->kod_agensi.':'.now()->format('YmdH'));
        }

        if ($liputan->kegagalan_berturut >= max(1, (int) $this->parameters->get('cki.ambang_eskalasi', 3))) {
            $this->notifier->send('cki.kegagalan_integrasi', $this->penerima->untuk('cki.integrasi.kemas_kini'), [
                'agensi' => $liputan->nama_agensi, 'bil_kegagalan' => $liputan->kegagalan_berturut, 'sebab' => mb_substr($sebab, 0, 200),
            ], '/admin/cki/integrasi', 'cki.kegagalan_integrasi:'.$liputan->kod_agensi.':'.now()->format('Ymd'));
        }
    }

    /** The agency answered again. Returns true when it was in fallback mode (caller re-synchronises). */
    public function pulih(Liputan $liputan): bool
    {
        $was = $liputan->dalamSandaran();
        $liputan->kegagalan_berturut = 0;
        $liputan->semakan_terakhir = now();
        $liputan->mod_semasa = 'api';
        $liputan->save();
        if ($was) {
            ModOperasi::query()->where('kod_agensi', $liputan->kod_agensi)->whereNull('tarikh_tamat')->update(['tarikh_tamat' => now(), 'updated_at' => now()]);
            $this->audit->record('tukar_mod', 'cki', 'daftar_liputan_integrasi', $liputan->id, changes: ['mod_semasa' => ['sandaran', 'api']], meta: ['kod_agensi' => $liputan->kod_agensi]);
            $this->notifier->send('cki.mod_pulih', $this->penerima->untuk('cki.bil.cipta', $liputan->kod_ptd), [
                'agensi' => $liputan->nama_agensi, 'masa' => now()->format('d/m/Y H:i'),
            ], '/admin/cki/konflik', 'cki.mod_pulih:'.$liputan->kod_agensi.':'.now()->format('YmdH'));
        }

        return $was;
    }
}
