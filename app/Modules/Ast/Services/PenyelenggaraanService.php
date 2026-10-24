<?php

namespace App\Modules\Ast\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Models\AsetKategori;
use App\Modules\Ast\Models\AsetKontraktor;
use App\Modules\Ast\Models\JadualPenyelenggaraan;
use App\Modules\Ast\Models\RekodPenyelenggaraan;
use App\Modules\Ast\Support\AliranAst;
use App\Modules\Ast\Support\Lalai;
use App\Modules\Ast\Support\SusutNilai;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Ptn\Contracts\MaintenanceNeeds;
use App\Modules\Wfe\Contracts\ApproverDirectory;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Maintenance planning and work (SRS-AST-14..17):
 *  - preventive schedules generated from the category cycle (one per asset × type),
 *    their due date rolling forward when the work is completed;
 *  - work orders (preventive from a due schedule, corrective, from an inspection,
 *    or from MOD-PTN's MaintenanceNeeds — linked back with link());
 *  - estimates above ast.ambang_kelulusan_penyelenggaraan_rm go through MOD-WFE;
 *  - overdue schedules escalate to the officer, their supervisors and the Ketua Unit.
 * Costs are DECIMAL (bcmath) and reach the lot's 360° cost chart via FinancialSeries.
 */
class PenyelenggaraanService
{
    public function __construct(
        private Parameters $parameters,
        private WorkflowEngine $workflow,
        private Notifier $notifier,
        private ApproverDirectory $directory,
        private MaintenanceNeeds $needs,
    ) {}

    /** SRS-AST-15: create the category's preventive schedules missing for this asset. Returns how many were created. */
    public function janaJadualUntuk(AsetFizikal $a, ?AsetKategori $kategori = null, ?Carbon $mula = null): int
    {
        $kategori ??= AsetKategori::query()->find($a->id_kategori);
        if ($kategori === null || $a->status_arkib) {
            return 0;
        }
        $n = 0;
        $amaran = (int) $this->parameters->get('ast.tempoh_amaran_hari', 14);
        foreach ($kategori->jenis_penyelenggaraan_lalai ?: [] as $jenis) {
            $exists = JadualPenyelenggaraan::query()->withoutGlobalScope('access')->where('id_aset', $a->id)->where('jenis_penyelenggaraan', $jenis)->exists();
            if ($exists) {
                continue;
            }
            $kitaran = max(1, (int) $kategori->kitaran_penyelenggaraan_bulan);
            JadualPenyelenggaraan::query()->create([
                'id_aset' => $a->id, 'jenis_penyelenggaraan' => $jenis, 'kitaran_bulan' => $kitaran,
                'tarikh_jatuh_tempoh' => ($mula ?? now())->copy()->addMonthsNoOverflow($kitaran)->toDateString(), 'tempoh_amaran_hari' => $amaran,
                'status' => 'aktif', 'id_pegawai_bertanggungjawab' => $a->id_pegawai_bertanggungjawab, 'dijana_automatik' => true,
                'kod_daerah' => $a->kod_daerah, 'kod_mukim' => $a->kod_mukim, 'klasifikasi' => $a->klasifikasi, 'sintetik' => $a->sintetik,
            ]);
            $n++;
        }

        return $n;
    }

    /**
     * @param  array<string, mixed>  $d
     */
    public function ciptaKerja(AsetFizikal $a, array $d, User $user, string $sumber = 'manual'): RekodPenyelenggaraan
    {
        if ($a->status_arkib) {
            throw ValidationException::withMessages(['id_aset' => __('ast::ast.aset_diarkib')]);
        }
        $kontraktor = isset($d['id_kontraktor']) && $d['id_kontraktor'] ? AsetKontraktor::query()->where('status_aktif', true)->find((int) $d['id_kontraktor']) : null;
        if (isset($d['id_kontraktor']) && $d['id_kontraktor'] && $kontraktor === null) {
            throw ValidationException::withMessages(['id_kontraktor' => __('ast::ast.kontraktor_tidak_sah')]);
        }

        return DB::transaction(function () use ($a, $d, $user, $sumber, $kontraktor) {
            $k = new RekodPenyelenggaraan;
            $k->forceFill([
                'no_rujukan' => $this->noRujukan(), 'id_aset' => $a->id, 'id_jadual' => $d['id_jadual'] ?? null,
                'jenis_kerja' => $d['jenis_kerja'] ?? ($sumber === 'jadual' ? 'pencegahan' : 'pembaikan'), 'jenis_penyelenggaraan' => $d['jenis_penyelenggaraan'] ?? 'lain',
                'keterangan' => $d['keterangan'], 'keutamaan' => $d['keutamaan'] ?? 'sederhana', 'tarikh_dijadual' => $d['tarikh_dijadual'] ?? null,
                'kos_anggaran' => isset($d['kos_anggaran']) ? SusutNilai::bundar((string) $d['kos_anggaran']) : null,
                'id_kontraktor' => $kontraktor?->id, 'nama_syarikat_pelaksana' => $d['nama_syarikat_pelaksana'] ?? $kontraktor?->nama_syarikat,
                'status' => 'dirancang', 'id_keperluan_ptn' => $d['id_keperluan_ptn'] ?? null, 'id_pemeriksaan' => $d['id_pemeriksaan'] ?? null,
                'sumber' => $sumber, 'id_pegawai' => $d['id_pegawai'] ?? $a->id_pegawai_bertanggungjawab ?? $user->id,
                'kod_daerah' => $a->kod_daerah, 'kod_mukim' => $a->kod_mukim, 'klasifikasi' => $a->klasifikasi, 'sintetik' => (bool) ($d['sintetik'] ?? $a->sintetik),
            ])->save();
            $this->mohonKelulusanJikaPerlu($k, $user);
            $this->maklumTugasan($k, $a);

            return $k->refresh();
        });
    }

    /** Estimate ≥ threshold → WFE approval before work may start (SRS-GEN-03). */
    public function mohonKelulusanJikaPerlu(RekodPenyelenggaraan $k, User $user): void
    {
        $ambang = (string) $this->parameters->get('ast.ambang_kelulusan_penyelenggaraan_rm', 20000);
        if ($k->kos_anggaran === null || bccomp((string) $k->kos_anggaran, $ambang, 2) < 0 || ! in_array($k->status, ['dirancang', 'ditolak'], true)) {
            return;
        }
        try {
            $id = $this->workflow->start(AliranAst::PENYELENGGARAAN, AliranAst::SUBJEK_KERJA, $k->id, $user, [
                'kod_daerah' => $k->kod_daerah, 'kos_anggaran' => (float) $k->kos_anggaran, 'jenis_kerja' => $k->jenis_kerja, 'pautan' => '/admin/ast/kerja/'.$k->id,
            ], 'Kerja penyelenggaraan '.$k->no_rujukan.' (RM '.number_format((float) $k->kos_anggaran, 2).')');
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['kos_anggaran' => __('ast::ast.aliran_tiada')]);
        }
        $k->forceFill(['status' => 'menunggu_kelulusan', 'id_aliran' => $id])->save();
    }

    /**
     * Status transitions. Contractors may only move their own work through
     * diluluskan → dalam_pelaksanaan → selesai (with dates, cost, notes).
     *
     * @param  array<string, mixed>  $d
     */
    public function kemaskiniKerja(RekodPenyelenggaraan $k, array $d, User $user): RekodPenyelenggaraan
    {
        if (in_array($k->status, ['selesai', 'dibatalkan'], true)) {
            throw ValidationException::withMessages(['status' => __('ast::ast.kerja_ditutup')]);
        }

        return DB::transaction(function () use ($k, $d, $user) {
            $fields = array_intersect_key($d, array_flip(['keterangan', 'keutamaan', 'tarikh_dijadual', 'tarikh_mula', 'tarikh_siap', 'kos', 'kos_anggaran', 'id_kontraktor', 'nama_syarikat_pelaksana', 'catatan_penyelesaian', 'jenis_penyelenggaraan', 'id_gambar_sebelum', 'id_gambar_selepas']));
            foreach (['kos', 'kos_anggaran'] as $m) {
                if (isset($fields[$m])) {
                    $fields[$m] = SusutNilai::bundar((string) $fields[$m]);
                }
            }
            if (isset($fields['id_kontraktor']) && $fields['id_kontraktor'] && ! AsetKontraktor::query()->where('status_aktif', true)->whereKey((int) $fields['id_kontraktor'])->exists()) {
                throw ValidationException::withMessages(['id_kontraktor' => __('ast::ast.kontraktor_tidak_sah')]);
            }
            $kontraktorLama = $k->id_kontraktor;
            $k->fill($fields);
            if (isset($fields['id_kontraktor']) && $fields['id_kontraktor'] && empty($fields['nama_syarikat_pelaksana'])) {
                $k->nama_syarikat_pelaksana = AsetKontraktor::query()->whereKey((int) $fields['id_kontraktor'])->value('nama_syarikat');
            }
            $status = $d['status'] ?? null;
            if ($status !== null && $status !== $k->status) {
                $this->peralihan($k, (string) $status);
            }
            $k->save();
            if ($k->wasChanged('kos_anggaran')) {
                $this->mohonKelulusanJikaPerlu($k, $user);
            }
            if ($k->status === 'selesai') {
                $this->selepasSelesai($k);
            }
            if ($k->status === 'dibatalkan' && $k->id_keperluan_ptn) {
                $this->pautPtn($k, 'dikenal_pasti');
            }
            if ($k->id_kontraktor && (int) $kontraktorLama !== (int) $k->id_kontraktor && ($a = AsetFizikal::query()->withoutGlobalScope('access')->find($k->id_aset))) {
                $this->maklumTugasan($k, $a);
            }

            return $k->refresh();
        });
    }

    private function peralihan(RekodPenyelenggaraan $k, string $baharu): void
    {
        $sah = [
            'dirancang' => ['diluluskan', 'dalam_pelaksanaan', 'dibatalkan', 'selesai'],
            'ditolak' => ['dibatalkan', 'dirancang'],
            'diluluskan' => ['dalam_pelaksanaan', 'selesai', 'dibatalkan'],
            'dalam_pelaksanaan' => ['selesai', 'dibatalkan'],
            'menunggu_kelulusan' => ['dibatalkan'],
        ];
        if (! in_array($baharu, $sah[$k->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => __('ast::ast.peralihan_tidak_sah', ['dari' => Lalai::STATUS_KERJA[$k->status] ?? $k->status, 'ke' => Lalai::STATUS_KERJA[$baharu] ?? $baharu])]);
        }
        // Above the cost threshold the work cannot start or finish before WFE approval.
        $ambang = (string) $this->parameters->get('ast.ambang_kelulusan_penyelenggaraan_rm', 20000);
        if ($k->status === 'dirancang' && in_array($baharu, ['dalam_pelaksanaan', 'selesai', 'diluluskan'], true) && $k->kos_anggaran !== null && bccomp((string) $k->kos_anggaran, $ambang, 2) >= 0) {
            throw ValidationException::withMessages(['status' => __('ast::ast.perlu_kelulusan')]);
        }
        if ($baharu === 'dalam_pelaksanaan' && $k->tarikh_mula === null) {
            $k->tarikh_mula = now();
        }
        if ($baharu === 'selesai') {
            $k->tarikh_siap ??= now();
            $k->tarikh_mula ??= $k->tarikh_siap;
            if ($k->kos === null) {
                throw ValidationException::withMessages(['kos' => __('ast::ast.kos_wajib')]);
            }
        }
        $k->status = $baharu;
    }

    /** Completion: roll the schedule forward, mark the MOD-PTN need done, restore the asset status. */
    private function selepasSelesai(RekodPenyelenggaraan $k): void
    {
        if ($k->id_jadual && ($j = JadualPenyelenggaraan::query()->withoutGlobalScope('access')->find($k->id_jadual))) {
            $siap = $k->tarikh_siap ?? now();
            $j->forceFill([
                'tarikh_terakhir_dilaksana' => $siap->toDateString(),
                'tarikh_jatuh_tempoh' => $siap->copy()->addMonthsNoOverflow(max(1, $j->kitaran_bulan))->toDateString(),
                'tarikh_eskalasi' => null,
            ])->save();
        }
        if ($k->id_keperluan_ptn) {
            $this->pautPtn($k, 'selesai');
        }
        $a = AsetFizikal::query()->withoutGlobalScope('access')->find($k->id_aset);
        if ($a && $a->status_aset === 'dalam_penyelenggaraan' && ! RekodPenyelenggaraan::query()->withoutGlobalScope('access')->where('id_aset', $a->id)->where('status', 'dalam_pelaksanaan')->exists()) {
            $a->forceFill(['status_aset' => 'aktif'])->save();
        }
    }

    /** WFE outcome for ast.kelulusan_penyelenggaraan. */
    public function keputusan(RekodPenyelenggaraan $k, string $outcome, ?int $by, ?string $remarks): void
    {
        if ($k->status !== 'menunggu_kelulusan') {
            return;
        }
        $k->forceFill(['status' => $outcome === 'lulus' ? 'diluluskan' : ($outcome === 'tolak' ? 'ditolak' : 'dirancang'), 'catatan_penyelesaian' => $remarks ? trim(($k->catatan_penyelesaian ? $k->catatan_penyelesaian."\n" : '').'Keputusan kelulusan: '.$remarks) : $k->catatan_penyelesaian])->save();
        $a = AsetFizikal::query()->withoutGlobalScope('access')->find($k->id_aset);
        $pegawai = $k->id_pegawai ? User::query()->find($k->id_pegawai) : null;
        if ($pegawai && $a) {
            $this->notifier->send('ast.keputusan_kelulusan', $pegawai, [
                'perkara' => 'Kerja penyelenggaraan '.$k->no_rujukan, 'keputusan' => $outcome === 'lulus' ? 'diluluskan' : ($outcome === 'tolak' ? 'ditolak' : 'ditarik balik'),
                'nama_aset' => $a->nama_aset, 'no_rujukan' => $a->no_rujukan_aset, 'ulasan' => mb_substr((string) $remarks, 0, 200),
            ], '/admin/ast/kerja/'.$k->id);
        }
    }

    /**
     * Daily (`ast:semak-harian`): a work order for every active schedule due
     * within its warning period that has no open work yet.
     */
    public function janaKerjaJadual(?Carbon $today = null): int
    {
        $today ??= now()->startOfDay();
        $n = 0;
        JadualPenyelenggaraan::query()->withoutGlobalScope('access')->with(['aset' => fn ($q) => $q->withoutGlobalScope('access')])->where('status', 'aktif')
            ->whereRaw('tarikh_jatuh_tempoh <= (?::date + tempoh_amaran_hari)', [$today->toDateString()])
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('rekod_penyelenggaraan as r')->whereColumn('r.id_jadual', 'jadual_penyelenggaraan.id')->whereIn('r.status', Lalai::KERJA_TERBUKA))
            ->orderBy('id')->chunkById(500, function ($rows) use (&$n) {
                foreach ($rows as $j) {
                    $a = $j->aset;
                    if ($a === null || $a->status_arkib) {
                        continue;
                    }
                    $by = $j->pegawai ?? ($a->id_pegawai_bertanggungjawab ? User::query()->find($a->id_pegawai_bertanggungjawab) : null) ?? User::query()->orderBy('id')->first();
                    if ($by === null) {
                        continue;
                    }
                    $this->ciptaKerja($a, [
                        'id_jadual' => $j->id, 'jenis_kerja' => 'pencegahan', 'jenis_penyelenggaraan' => $j->jenis_penyelenggaraan, 'tarikh_dijadual' => $j->tarikh_jatuh_tempoh->toDateString(),
                        'keterangan' => 'Penyelenggaraan berjadual: '.(Lalai::JENIS_PENYELENGGARAAN[$j->jenis_penyelenggaraan] ?? $j->jenis_penyelenggaraan).' (kitaran '.$j->kitaran_bulan.' bulan)',
                        'id_pegawai' => $j->id_pegawai_bertanggungjawab, 'sintetik' => $j->sintetik,
                    ], $by, 'jadual');
                    $n++;
                }
            });

        return $n;
    }

    /**
     * SRS-AST-16: overdue schedules escalate to the responsible officer, their
     * supervisors and the Ketua Unit Aset role — once a day (dedupe key).
     *
     * @return array{tertunggak: int, dimaklumkan: int}
     */
    public function eskalasiTertunggak(?Carbon $today = null): array
    {
        $today ??= now()->startOfDay();
        $count = 0;
        $sent = 0;
        $heads = [];
        foreach (array_filter(array_map('trim', explode(',', (string) $this->parameters->get('ast.peranan_ketua_unit', 'ketua_unit_aset')))) as $role) {
            foreach ($this->directory->usersWithRole($role) as $u) {
                $heads[$u->id] = $u;
            }
        }
        JadualPenyelenggaraan::query()->withoutGlobalScope('access')->with(['pegawai', 'aset' => fn ($q) => $q->withoutGlobalScope('access')])->where('status', 'aktif')
            ->whereDate('tarikh_jatuh_tempoh', '<', $today->toDateString())->orderBy('id')
            ->chunkById(500, function ($rows) use (&$count, &$sent, $heads, $today) {
                foreach ($rows as $j) {
                    $a = $j->aset;
                    if ($a === null || $a->status_arkib) {
                        continue;
                    }
                    $count++;
                    $to = [];
                    if ($j->pegawai) {
                        $to[$j->pegawai->id] = $j->pegawai;
                        foreach (array_slice($this->directory->supervisorsOf($j->pegawai), 0, 2) as $s) {
                            $to[$s->id] = $s;
                        }
                    }
                    foreach ($heads as $id => $h) {
                        $to[$id] = $h;
                    }
                    try {
                        $this->notifier->send('ast.penyelenggaraan_tertunggak', array_values($to), [
                            'jenis' => Lalai::JENIS_PENYELENGGARAAN[$j->jenis_penyelenggaraan] ?? $j->jenis_penyelenggaraan, 'nama_aset' => $a->nama_aset,
                            'no_rujukan' => $a->no_rujukan_aset, 'tarikh' => $j->tarikh_jatuh_tempoh->format('d/m/Y'), 'hari_lewat' => (int) $j->tarikh_jatuh_tempoh->diffInDays($today),
                        ], '/admin/ast/penyelenggaraan?status=tertunggak', 'ast.tertunggak:'.$j->id.':'.$today->toDateString());
                        $sent++;
                    } catch (Throwable $e) {
                        report($e);
                    }
                    if ($j->tarikh_eskalasi === null) {
                        DB::table('jadual_penyelenggaraan')->where('id', $j->id)->update(['tarikh_eskalasi' => $today->toDateString(), 'updated_at' => now()]);
                    }
                }
            });

        return ['tertunggak' => $count, 'dimaklumkan' => $sent];
    }

    /**
     * MOD-PTN integration (SRS-PTN-21): pull the open maintenance needs of every
     * lot carrying assets, turn each into a corrective work order on the matching
     * asset and link() it back as "dijadual". A need is matched to the asset PTN
     * already named, else to the lot's only active asset; needs on lots with
     * several assets wait for an officer to pick one (senaraiKeperluanPtn / pautKeperluan).
     *
     * @return array{dicipta: int, perlu_dipadankan: int}
     */
    public function tarikKeperluanPtn(?User $by = null, ?array $lotIds = null): array
    {
        $by ??= User::query()->where('role', 'admin')->orderBy('id')->first() ?? User::query()->orderBy('id')->first();
        $created = 0;
        $pending = 0;
        if ($by === null) {
            return ['dicipta' => 0, 'perlu_dipadankan' => 0];
        }
        $lots = $lotIds ?? AsetFizikal::query()->withoutGlobalScope('access')->where('status_arkib', false)->distinct()->pluck('lot_id')->all();
        foreach ($lots as $lotId) {
            $assets = null;
            foreach ($this->needs->forLot((string) $lotId) as $need) {
                if ($need['status'] !== 'dikenal_pasti' || RekodPenyelenggaraan::query()->withoutGlobalScope('access')->where('id_keperluan_ptn', $need['id'])->exists()) {
                    continue;
                }
                $assets ??= AsetFizikal::query()->withoutGlobalScope('access')->where('lot_id', $lotId)->where('status_arkib', false)->get();
                $a = $need['id_aset_fizikal'] ? $assets->firstWhere('id', $need['id_aset_fizikal']) : ($assets->count() === 1 ? $assets->first() : null);
                if ($a === null) {
                    $pending++;

                    continue;
                }
                $this->kerjaDariKeperluan($a, $need, $by);
                $created++;
            }
        }

        return ['dicipta' => $created, 'perlu_dipadankan' => $pending];
    }

    /**
     * @param  array{id: int, lot_id: string, jenis: string, keterangan: string, keutamaan: string, status: string, tarikh_jadual: string|null, id_aset_fizikal: string|null, id_lawatan: string|null, tarikh_dikenal_pasti: string}  $need
     */
    public function kerjaDariKeperluan(AsetFizikal $a, array $need, User $by): RekodPenyelenggaraan
    {
        $k = $this->ciptaKerja($a, [
            'jenis_kerja' => $need['keutamaan'] === 'kritikal' ? 'kecemasan' : 'pembaikan',
            'jenis_penyelenggaraan' => array_key_exists($need['jenis'], Lalai::JENIS_PENYELENGGARAAN) ? $need['jenis'] : 'lain',
            'keterangan' => 'Keperluan daripada pemantauan tanah (MOD-PTN #'.$need['id'].', '.$need['tarikh_dikenal_pasti'].'): '.$need['keterangan'],
            'keutamaan' => array_key_exists($need['keutamaan'], Lalai::KEUTAMAAN) ? $need['keutamaan'] : 'sederhana',
            'tarikh_dijadual' => $need['tarikh_jadual'], 'id_keperluan_ptn' => $need['id'],
        ], $by, 'ptn');
        $this->pautPtn($k, 'dijadual');

        return $k;
    }

    /**
     * Open needs of in-scope lots that are not yet a work order (for the "padankan" screen).
     *
     * @return list<array<string, mixed>>
     */
    public function senaraiKeperluanPtn(User $user, ?string $lotId = null): array
    {
        $q = AsetFizikal::query()->untuk($user)->where('status_arkib', false);
        if ($lotId) {
            $q->where('lot_id', $lotId);
        }
        $lots = $q->distinct()->pluck('lot_id')->all();
        $done = RekodPenyelenggaraan::query()->withoutGlobalScope('access')->whereNotNull('id_keperluan_ptn')->pluck('id_keperluan_ptn')->map(fn ($v) => (int) $v)->all();
        $out = [];
        foreach ($lots as $l) {
            foreach ($this->needs->forLot((string) $l) as $n) {
                if ($n['status'] === 'dikenal_pasti' && ! in_array((int) $n['id'], $done, true)) {
                    $out[] = $n + ['jenis_nama' => Lalai::JENIS_PENYELENGGARAAN[$n['jenis']] ?? $n['jenis']];
                }
            }
        }

        return $out;
    }

    public function pautKeperluan(int $needId, AsetFizikal $a, User $by): RekodPenyelenggaraan
    {
        $need = collect($this->needs->forLot($a->lot_id))->firstWhere('id', $needId);
        if ($need === null || $need['status'] !== 'dikenal_pasti') {
            throw ValidationException::withMessages(['id_keperluan' => __('ast::ast.keperluan_tidak_sah')]);
        }
        if (RekodPenyelenggaraan::query()->withoutGlobalScope('access')->where('id_keperluan_ptn', $needId)->exists()) {
            throw ValidationException::withMessages(['id_keperluan' => __('ast::ast.keperluan_sudah_dipaut')]);
        }

        return $this->kerjaDariKeperluan($a, $need, $by);
    }

    private function pautPtn(RekodPenyelenggaraan $k, string $status): void
    {
        try {
            $this->needs->link((int) $k->id_keperluan_ptn, $k->id_aset, $status);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function maklumTugasan(RekodPenyelenggaraan $k, AsetFizikal $a): void
    {
        $to = [];
        if ($k->id_kontraktor && ($uid = AsetKontraktor::query()->whereKey($k->id_kontraktor)->value('id_pengguna')) && ($u = User::query()->find($uid))) {
            $to[] = $u;
        }
        if ($k->id_pegawai && ($p = User::query()->find($k->id_pegawai))) {
            $to[] = $p;
        }
        if ($to === [] || $k->sintetik) {
            return;
        }
        $this->notifier->send('ast.kerja_ditugaskan', $to, [
            'no_rujukan' => $k->no_rujukan, 'jenis' => Lalai::JENIS_PENYELENGGARAAN[$k->jenis_penyelenggaraan] ?? $k->jenis_penyelenggaraan,
            'nama_aset' => $a->nama_aset, 'tarikh' => $k->tarikh_dijadual?->format('d/m/Y') ?? '—',
        ], '/admin/ast/kerja/'.$k->id, 'ast.kerja:'.$k->id.':'.($k->id_kontraktor ?? 0));
    }

    private function noRujukan(): string
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['ast_no_kerja']);
        $prefix = 'KP-'.now()->year.'-';
        $last = DB::table('rekod_penyelenggaraan')->where('no_rujukan', 'like', $prefix.'%')->max('no_rujukan');
        $n = $last ? ((int) substr((string) $last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }
}
