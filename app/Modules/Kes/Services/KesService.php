<?php

namespace App\Modules\Kes\Services;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditScope;
use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Kes\Models\KosGuaman;
use App\Modules\Kes\Models\Kronologi;
use App\Modules\Kes\Models\PegawaiSejarah;
use App\Modules\Kes\Models\Peguam;
use App\Modules\Kes\Models\Pihak;
use App\Modules\Kes\Models\Prosiding;
use App\Modules\Kes\Models\StatusSejarah;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Pro\Contracts\LotRegistry;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Case lifecycle (aliran proses 1–4, 11): register, link lots, parties and
 * counsel, assign the responsible officer, status stages with history,
 * archive. Keeps the lot legal-action status (SRS-KES-06) and the
 * denormalised totals (kos_guaman, tarikh_prosiding_berikutnya) in sync.
 */
class KesService
{
    public const SUBJECT = 'kes_mahkamah';

    public const FLOW_PEMFAILAN = 'kes.pemfailan';

    public function __construct(
        private LotRegistry $lots,
        private Rujukan $ref,
        private WorkflowEngine $workflow,
        private Notifier $notifier,
        private StatusLot $statusLot,
    ) {}

    /**
     * SRS-KES-02..05: register a case with its parties, counsel and lots.
     *
     * @param  array<string, mixed>  $d  validated
     */
    public function daftar(array $d, User $by, string $sumber = 'manual', ?string $rujukanSumber = null, bool $sintetik = false): KesMahkamah
    {
        if ($rujukanSumber !== null) {
            $existing = KesMahkamah::query()->withoutGlobalScope('access')->where('sumber', $sumber)->where('rujukan_sumber', $rujukanSumber)->first();
            if ($existing !== null) {
                return $existing;
            }
        }
        $lotIds = array_values(array_unique(array_map('strval', (array) ($d['lot_ids'] ?? []))));
        $lots = $this->lotYangSah($lotIds, $sumber === 'manual' ? $by : null);

        $kes = DB::transaction(function () use ($d, $by, $sumber, $rujukanSumber, $lots, $sintetik) {
            $seq = (int) DB::selectOne("SELECT nextval('kes_no_rujukan_seq') AS n")->n;
            $first = $lots === [] ? null : reset($lots);
            $kes = new KesMahkamah;
            $kes->forceFill([
                'no_rujukan' => sprintf('KES/%s/%05d', now()->format('Y'), $seq),
                'no_kes' => (string) ($d['no_kes'] ?? '') !== '' ? (string) $d['no_kes'] : 'BELUM-DIFAILKAN-'.$seq,
                'tajuk' => $d['tajuk'] ?? null,
                'jenis' => $d['jenis'] ?? null,
                'mahkamah' => $d['mahkamah'] ?? null,
                'peringkat_mahkamah' => $d['peringkat_mahkamah'] ?? null,
                'tarikh_pemfailan' => $d['tarikh_pemfailan'] ?? null,
                'ringkasan_tuntutan' => $d['ringkasan_tuntutan'] ?? null,
                'nilai_tuntutan' => isset($d['nilai_tuntutan']) && $d['nilai_tuntutan'] !== '' ? Wang::normal((string) $d['nilai_tuntutan']) : null,
                'peranan_mais' => $d['peranan_mais'] ?? null,
                'status' => 'didaftar',
                'tarikh_status' => now(),
                'kos_guaman' => '0.00',
                'klasifikasi' => 'rahsia',
                'id_pencipta' => $by->id,
                'id_pegawai_bertanggungjawab' => $d['id_pegawai_bertanggungjawab'] ?? null,
                'kod_daerah' => $first['kod_daerah'] ?? ($d['kod_daerah'] ?? null),
                'kod_mukim' => $first['kod_mukim'] ?? ($d['kod_mukim'] ?? null),
                'sumber' => $sumber,
                'rujukan_sumber' => $rujukanSumber,
                'sintetik' => $sintetik,
            ])->save();

            StatusSejarah::query()->create(['kes_mahkamah_id' => $kes->id, 'status_lama' => null, 'status_baharu' => 'didaftar', 'tarikh_perubahan' => now(), 'id_pengguna' => $by->id, 'catatan' => 'Kes didaftarkan']);
            if ($kes->id_pegawai_bertanggungjawab !== null) {
                PegawaiSejarah::query()->create(['kes_mahkamah_id' => $kes->id, 'id_pegawai_lama' => null, 'id_pegawai_baharu' => $kes->id_pegawai_bertanggungjawab, 'tarikh_tukar' => now(), 'justifikasi' => 'Penugasan semasa pendaftaran', 'id_pengguna' => $by->id]);
            }
            $this->simpanPautan($kes, $lots, 'subjek', $by);
            $pihak = [];
            foreach (array_values((array) ($d['pihak'] ?? [])) as $p) {
                $pihak[] = $this->tambahPihak($kes, (array) $p, $sintetik);
            }
            foreach ((array) ($d['peguam'] ?? []) as $p) {
                $p = (array) $p;
                if (isset($p['pihak_indeks']) && isset($pihak[(int) $p['pihak_indeks']])) {
                    $p['kes_pihak_id'] = $pihak[(int) $p['pihak_indeks']]->id;
                }
                $this->tambahPeguam($kes, $p, $by, $sintetik);
            }

            return $kes;
        });

        $this->statusLot->segar(array_keys($lots));
        if ($kes->id_pegawai_bertanggungjawab !== null && ! $sintetik) {
            $this->maklumTugasan($kes);
        }

        return $kes;
    }

    /** @param array<string, mixed> $d validated header fields */
    public function kemasKini(KesMahkamah $kes, array $d): KesMahkamah
    {
        if (array_key_exists('nilai_tuntutan', $d)) {
            $d['nilai_tuntutan'] = $d['nilai_tuntutan'] === null || $d['nilai_tuntutan'] === '' ? null : Wang::normal((string) $d['nilai_tuntutan']);
        }
        $kes->fill(array_intersect_key($d, array_flip(['no_kes', 'tajuk', 'jenis', 'mahkamah', 'peringkat_mahkamah', 'tarikh_pemfailan', 'ringkasan_tuntutan', 'nilai_tuntutan', 'peranan_mais'])))->save();

        return $kes;
    }

    /** SRS-KES-07: change stage with dated history; terminal stage lifts the lots' legal status (SRS-KES-06). */
    public function tukarStatus(KesMahkamah $kes, string $status, ?User $by, ?string $catatan = null): void
    {
        if (! isset($this->ref->status()[$status])) {
            throw ValidationException::withMessages(['status' => __('kes::kes.ralat.status_tidak_sah')]);
        }
        if ($kes->status === $status) {
            return;
        }
        if ($kes->status_arkib) {
            throw ValidationException::withMessages(['status' => __('kes::kes.ralat.diarkib')]);
        }
        $lama = $kes->status;
        AuditScope::withReason($catatan ?? 'Perubahan status kes', function () use ($kes, $status) {
            $kes->forceFill(['status' => $status, 'tarikh_status' => now()])->save();
        });
        StatusSejarah::query()->create(['kes_mahkamah_id' => $kes->id, 'status_lama' => $lama, 'status_baharu' => $status, 'tarikh_perubahan' => now(), 'id_pengguna' => $by?->id, 'catatan' => $catatan]);
        $this->statusLot->segar($this->lotIds($kes));
    }

    /**
     * SRS-KES-05: link more lots (N:N). Lots the user may not see are refused.
     *
     * @param  list<string>  $lotIds
     */
    public function pautLot(KesMahkamah $kes, array $lotIds, string $peranan, User $by): void
    {
        $lots = $this->lotYangSah($lotIds, $by);
        DB::transaction(function () use ($kes, $lots, $peranan, $by) {
            $this->simpanPautan($kes, $lots, $peranan, $by);
            if ($kes->kod_daerah === null && $lots !== []) {
                $first = reset($lots);
                $kes->forceFill(['kod_daerah' => $first['kod_daerah'], 'kod_mukim' => $first['kod_mukim']])->save();
            }
        });
        $this->statusLot->segar(array_keys($lots));
    }

    public function nyahpautLot(KesMahkamah $kes, string $lotId, User $by): void
    {
        $n = DB::table('kes_mahkamah_lot')->where('kes_mahkamah_id', $kes->id)->where('lot_id', $lotId)->delete();
        if ($n === 0) {
            throw ValidationException::withMessages(['lot_id' => __('kes::kes.ralat.lot_tiada')]);
        }
        Kronologi::query()->create(['kes_mahkamah_id' => $kes->id, 'tarikh' => now(), 'jenis' => 'sistem', 'keterangan' => 'Pautan lot dibuang', 'sumber' => 'kes', 'id_pengguna' => $by->id]);
        $this->statusLot->segar([$lotId]);
    }

    /** SRS-KES-20: (re)assign the responsible officer with dated justification. */
    public function tugaskan(KesMahkamah $kes, User $pegawai, string $justifikasi, User $by): void
    {
        if ($kes->id_pegawai_bertanggungjawab === $pegawai->id) {
            throw ValidationException::withMessages(['id_pegawai' => __('kes::kes.ralat.pegawai_sama')]);
        }
        $lama = $kes->id_pegawai_bertanggungjawab;
        AuditScope::withReason($justifikasi, fn () => $kes->forceFill(['id_pegawai_bertanggungjawab' => $pegawai->id])->save());
        PegawaiSejarah::query()->create(['kes_mahkamah_id' => $kes->id, 'id_pegawai_lama' => $lama, 'id_pegawai_baharu' => $pegawai->id, 'tarikh_tukar' => now(), 'justifikasi' => $justifikasi, 'id_pengguna' => $by->id]);
        $kes->unsetRelation('pegawai');
        $this->maklumTugasan($kes);
    }

    /** Aliran proses 11: closure is archiving with an audit trail, never deletion. */
    public function arkib(KesMahkamah $kes, string $sebab, User $by): void
    {
        if (! $kes->terminal()) {
            throw ValidationException::withMessages(['status' => __('kes::kes.ralat.arkib_belum_selesai')]);
        }
        if ($kes->status_arkib) {
            return;
        }
        AuditScope::withReason($sebab, fn () => $kes->forceFill(['status_arkib' => true, 'tarikh_arkib' => now(), 'sebab_arkib' => $sebab])->save());
        Kronologi::query()->create(['kes_mahkamah_id' => $kes->id, 'tarikh' => now(), 'jenis' => 'sistem', 'keterangan' => 'Kes diarkibkan: '.$sebab, 'sumber' => 'kes', 'id_pengguna' => $by->id]);
    }

    /** Decision to file (MAIS as plaintiff / applicant) goes through MOD-WFE before the case becomes active. */
    public function mohonPemfailan(KesMahkamah $kes, User $by): string
    {
        if ($kes->status !== 'didaftar') {
            throw ValidationException::withMessages(['status' => __('kes::kes.ralat.pemfailan_status')]);
        }
        if ($kes->id_instans_aliran !== null && $this->workflow->status($kes->id_instans_aliran) === 'berjalan') {
            throw ValidationException::withMessages(['status' => __('kes::kes.ralat.aliran_berjalan')]);
        }
        try {
            $id = $this->workflow->start(self::FLOW_PEMFAILAN, self::SUBJECT, $kes->id, $by, [
                'kod_daerah' => $kes->kod_daerah, 'jenis' => $kes->jenis, 'peranan_mais' => $kes->peranan_mais,
                'nilai' => (float) ($kes->nilai_tuntutan ?? 0), 'pautan' => '/admin/kes/kes/'.$kes->id,
            ], 'Kelulusan pemfailan kes '.$kes->no_rujukan);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['aliran' => __('kes::kes.ralat.tiada_aliran')]);
        }
        $kes->forceFill(['id_instans_aliran' => $id])->save();

        return $id;
    }

    public function selesaiPemfailan(KesMahkamah $kes, string $outcome, ?User $by, ?string $ulasan): void
    {
        if ($outcome === 'lulus') {
            $this->tukarStatus($kes, 'aktif', $by, 'Pemfailan diluluskan'.($ulasan ? ': '.$ulasan : ''));
        } else {
            Kronologi::query()->create(['kes_mahkamah_id' => $kes->id, 'tarikh' => now(), 'jenis' => 'sistem', 'keterangan' => 'Pemfailan tidak diluluskan ('.$outcome.')'.($ulasan ? ': '.$ulasan : ''), 'sumber' => 'wfe', 'id_pengguna' => $by?->id]);
        }
    }

    /** @param array<string, mixed> $p */
    public function tambahPihak(KesMahkamah $kes, array $p, bool $sintetik = false): Pihak
    {
        $pihak = new Pihak;
        $pihak->forceFill([
            'kes_mahkamah_id' => $kes->id,
            'nama_pihak' => (string) $p['nama_pihak'],
            'cincangan_nama' => Pihak::cincang((string) $p['nama_pihak']),
            'jenis_pihak' => $p['jenis_pihak'] ?? 'individu',
            'peranan' => $p['peranan'],
            'no_pengenalan' => $p['no_pengenalan'] ?? null,
            'maklumat_perhubungan' => $p['maklumat_perhubungan'] ?? null,
            'status_wakilan' => $p['status_wakilan'] ?? 'diwakili',
            'adalah_mais' => (bool) ($p['adalah_mais'] ?? (($p['jenis_pihak'] ?? null) === 'mais')),
            'sintetik' => $sintetik,
        ])->save();

        return $pihak;
    }

    /** @param array<string, mixed> $p */
    public function kemasKiniPihak(Pihak $pihak, array $p): Pihak
    {
        $pihak->fill(array_intersect_key($p, array_flip(['nama_pihak', 'jenis_pihak', 'peranan', 'no_pengenalan', 'maklumat_perhubungan', 'status_wakilan', 'adalah_mais'])));
        if (isset($p['nama_pihak'])) {
            $pihak->cincangan_nama = Pihak::cincang((string) $p['nama_pihak']);
        }
        $pihak->save();

        return $pihak;
    }

    /** @param array<string, mixed> $p a non-panel counsel; a panel firm goes through PanelService::lantik() */
    public function tambahPeguam(KesMahkamah $kes, array $p, User $by, bool $sintetik = false): Peguam
    {
        if (! empty($p['kes_pihak_id']) && ! Pihak::query()->where('kes_mahkamah_id', $kes->id)->whereKey($p['kes_pihak_id'])->exists()) {
            throw ValidationException::withMessages(['kes_pihak_id' => __('kes::kes.ralat.pihak_tiada')]);
        }
        $peguam = new Peguam;
        $peguam->forceFill([
            'kes_mahkamah_id' => $kes->id,
            'kes_pihak_id' => $p['kes_pihak_id'] ?? null,
            'nama_peguam' => (string) $p['nama_peguam'],
            'nama_firma' => $p['nama_firma'] ?? null,
            'pegawai_penghubung' => $p['pegawai_penghubung'] ?? null,
            'no_telefon' => $p['no_telefon'] ?? null,
            'emel' => $p['emel'] ?? null,
            'indikator_panel_mais' => false,
            'status_lantikan' => 'aktif',
            'sintetik' => $sintetik,
        ])->save();

        return $peguam;
    }

    /** Sum of non-rejected cost items, with bcmath (SRS-KES-15). */
    public function kiraKos(KesMahkamah $kes): string
    {
        $total = '0.00';
        foreach (KosGuaman::query()->where('kes_mahkamah_id', $kes->id)->where('status_bayaran', '!=', 'ditolak')->pluck('amaun') as $amaun) {
            $total = bcadd($total, (string) $amaun, 2);
        }
        $kes->forceFill(['kos_guaman' => $total])->saveQuietly();

        return $total;
    }

    /** Next scheduled proceeding date (calendar / "kes tanpa tarikh berikutnya"). */
    public function segarTarikhBerikutnya(KesMahkamah $kes): void
    {
        $next = Prosiding::query()->where('kes_mahkamah_id', $kes->id)->where('status', 'dijadualkan')
            ->where('tarikh_prosiding', '>=', now()->startOfDay())->min('tarikh_prosiding');
        $kes->forceFill(['tarikh_prosiding_berikutnya' => $next === null ? null : substr((string) $next, 0, 10)])->saveQuietly();
    }

    /** @return list<string> */
    public function lotIds(KesMahkamah $kes): array
    {
        return DB::table('kes_mahkamah_lot')->where('kes_mahkamah_id', $kes->id)->pluck('lot_id')->map(fn ($v) => (string) $v)->all();
    }

    /**
     * @param  list<string>  $lotIds
     * @return array<string, array{id: string, no_lot: string, kod_mukim: string, kod_daerah: string, kategori_guna_tanah: ?string}>
     */
    private function lotYangSah(array $lotIds, ?User $user): array
    {
        if ($lotIds === []) {
            return [];
        }
        $found = $this->lots->summaries($lotIds, $user);
        $missing = array_diff($lotIds, array_keys($found));
        if ($missing !== []) {
            throw ValidationException::withMessages(['lot_ids' => __('kes::kes.ralat.lot_tidak_sah', ['n' => count($missing)])]);
        }

        return $found;
    }

    /** @param array<string, array{id: string, no_lot: string, kod_mukim: string, kod_daerah: string, kategori_guna_tanah: ?string}> $lots */
    private function simpanPautan(KesMahkamah $kes, array $lots, string $peranan, User $by): void
    {
        foreach ($lots as $lot) {
            DB::table('kes_mahkamah_lot')->insertOrIgnore([
                'kes_mahkamah_id' => $kes->id, 'lot_id' => $lot['id'], 'peranan_lot' => $peranan, 'tarikh_paut' => now(), 'id_pengguna' => $by->id,
                'no_lot' => $lot['no_lot'], 'kod_daerah' => $lot['kod_daerah'], 'kod_mukim' => $lot['kod_mukim'], 'kategori_guna_tanah' => $lot['kategori_guna_tanah'],
            ]);
        }
    }

    private function maklumTugasan(KesMahkamah $kes): void
    {
        $pegawai = $kes->pegawai;
        if ($pegawai === null) {
            return;
        }
        $this->notifier->send('kes.kes_ditugaskan', $pegawai, [
            'no_rujukan' => $kes->no_rujukan, 'jenis' => $this->ref->namaJenis($kes->jenis),
        ], '/admin/kes/kes/'.$kes->id, 'kes_ditugaskan:'.$kes->id.':'.$pegawai->id);
    }
}
