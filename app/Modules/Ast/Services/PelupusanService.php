<?php

namespace App\Modules\Ast\Services;

use App\Models\User;
use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Models\AsetPelupusan;
use App\Modules\Ast\Models\RekodPenyelenggaraan;
use App\Modules\Ast\Support\AliranAst;
use App\Modules\Ast\Support\Lalai;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Disposal / write-off / demolition (flow step 12) — decided only through
 * MOD-WFE (SRS-GEN-03). On approval the asset (and its sub-assets) is
 * archived, never deleted, and stays referable for audit; open work is
 * cancelled. Acquisition approvals (ast.kelulusan_perolehan) are applied here too.
 */
class PelupusanService
{
    public function __construct(private WorkflowEngine $workflow, private AsetService $aset, private Notifier $notifier) {}

    /** @param array{jenis: string, sebab: string, kaedah?: string|null, nilai_pelupusan?: string|float|null, tarikh_dicadang?: string|null} $d */
    public function mohon(AsetFizikal $a, array $d, User $user): AsetPelupusan
    {
        if ($a->status_arkib) {
            throw ValidationException::withMessages(['aset' => __('ast::ast.aset_diarkib')]);
        }
        if (AsetPelupusan::query()->withoutGlobalScope('access')->where('id_aset', $a->id)->where('status', 'menunggu')->exists()) {
            throw ValidationException::withMessages(['aset' => __('ast::ast.pelupusan_sedang_berjalan')]);
        }

        return DB::transaction(function () use ($a, $d, $user) {
            $p = new AsetPelupusan;
            $p->forceFill([
                'id_aset' => $a->id, 'jenis' => $d['jenis'], 'sebab' => $d['sebab'], 'kaedah' => $d['kaedah'] ?? null,
                'nilai_pelupusan' => $d['nilai_pelupusan'] ?? null, 'tarikh_dicadang' => $d['tarikh_dicadang'] ?? null, 'status' => 'menunggu',
                'id_pemohon' => $user->id, 'kod_daerah' => $a->kod_daerah, 'kod_mukim' => $a->kod_mukim, 'klasifikasi' => $a->klasifikasi,
            ])->save();
            try {
                $id = $this->workflow->start(AliranAst::PELUPUSAN, AliranAst::SUBJEK_PELUPUSAN, (string) $p->id, $user, [
                    'kod_daerah' => $a->kod_daerah, 'jenis' => $d['jenis'], 'nilai_semasa' => (float) ($a->nilai_semasa ?? 0), 'pautan' => '/admin/ast/aset/'.$a->id,
                ], Lalai::JENIS_PELUPUSAN[$d['jenis']].' aset '.$a->no_rujukan_aset.' — '.$a->nama_aset);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages(['jenis' => __('ast::ast.aliran_tiada')]);
            }
            $p->forceFill(['id_aliran' => $id])->save();

            return $p->refresh();
        });
    }

    public function keputusan(AsetPelupusan $p, string $outcome, ?int $by, ?string $remarks): void
    {
        if ($p->status !== 'menunggu') {
            return;
        }
        DB::transaction(function () use ($p, $outcome, $by, $remarks) {
            $status = match ($outcome) {
                'lulus' => 'lulus', 'tolak' => 'tolak', default => 'ditarik_balik',
            };
            $p->forceFill(['status' => $status, 'id_pelulus' => $by, 'tarikh_keputusan' => now(), 'ulasan' => $remarks])->save();
            $a = AsetFizikal::query()->withoutGlobalScope('access')->find($p->id_aset);
            if ($a === null) {
                return;
            }
            if ($status === 'lulus') {
                $sebab = Lalai::JENIS_PELUPUSAN[$p->jenis].' diluluskan: '.$p->sebab;
                $baharu = Lalai::STATUS_SELEPAS_PELUPUSAN[$p->jenis];
                $this->aset->arkib($a, $baharu, $sebab);
                foreach (AsetFizikal::query()->withoutGlobalScope('access')->where('id_aset_induk', $a->id)->where('status_arkib', false)->get() as $sub) {
                    $this->aset->arkib($sub, $baharu, 'Aset induk '.$a->no_rujukan_aset.' — '.$sebab);
                }
                RekodPenyelenggaraan::query()->withoutGlobalScope('access')->where('id_aset', $a->id)->whereIn('status', Lalai::KERJA_TERBUKA)
                    ->update(['status' => 'dibatalkan', 'catatan_penyelesaian' => 'Dibatalkan — aset diarkibkan ('.Lalai::STATUS_ASET[$baharu].')', 'updated_at' => now()]);
                DB::table('jadual_penyelenggaraan')->where('id_aset', $a->id)->update(['status' => 'tidak_aktif', 'updated_at' => now()]);
            }
            $this->maklum($p->id_pemohon, Lalai::JENIS_PELUPUSAN[$p->jenis], $status, $a, $remarks, '/admin/ast/aset/'.$a->id);
        });
    }

    /** WFE outcome of ast.kelulusan_perolehan. */
    public function keputusanPerolehan(AsetFizikal $a, string $outcome, ?string $remarks): void
    {
        if ($a->status_kelulusan !== 'menunggu') {
            return;
        }
        if ($outcome === 'lulus') {
            $a->forceFill(['status_kelulusan' => 'lulus'])->save();
        } elseif ($outcome === 'tolak') {
            $a->forceFill(['status_kelulusan' => 'ditolak'])->save();
            $this->aset->arkib($a, 'tidak_aktif', 'Perolehan ditolak'.($remarks ? ': '.$remarks : ''));
        } else {
            $a->forceFill(['status_kelulusan' => 'ditolak'])->save();
            $this->aset->arkib($a, 'tidak_aktif', 'Permohonan perolehan ditarik balik');
        }
        $this->maklum($a->id_pencipta, 'Perolehan aset', $outcome === 'lulus' ? 'lulus' : 'tolak', $a, $remarks, '/admin/ast/aset/'.$a->id);
    }

    private function maklum(?int $userId, string $perkara, string $status, AsetFizikal $a, ?string $remarks, string $link): void
    {
        $u = $userId ? User::query()->find($userId) : null;
        if ($u === null) {
            return;
        }
        $this->notifier->send('ast.keputusan_kelulusan', $u, [
            'perkara' => $perkara, 'keputusan' => ['lulus' => 'diluluskan', 'tolak' => 'ditolak'][$status] ?? 'ditarik balik',
            'nama_aset' => $a->nama_aset, 'no_rujukan' => $a->no_rujukan_aset, 'ulasan' => mb_substr((string) $remarks, 0, 200),
        ], $link);
    }
}
