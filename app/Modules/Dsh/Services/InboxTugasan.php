<?php

namespace App\Modules\Dsh\Services;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Support\Carbon;

/**
 * The user's task inbox (SRS-DSH-23): open MOD-WFE tasks routed to them
 * (personally, by role, position or approver group), with deadline and SLA
 * status, plus their latest unread MOD-NTF notifications and their drafts.
 * Read-only aggregation — actions are taken on the owning module's page
 * (MOD-WFE enforces MFA for approvals, SRS-DSH-21).
 */
class InboxTugasan
{
    public function __construct(private AccessControl $access) {}

    /**
     * Assignee keys the user holds personally (MOD-WFE's "<jenis>:<nilai>"
     * convention). Delegations (pemangku) are shown on MOD-WFE's own board.
     *
     * @return list<string>
     */
    public function keys(User $user): array
    {
        $keys = ['individu:'.$user->id];
        foreach ($this->access->roles($user) as $role) {
            $keys[] = 'peranan:'.$role;
        }
        if (! empty($user->jawatan)) {
            $keys[] = 'jawatan:'.$user->jawatan;
        }
        foreach (Baca::db()->select('SELECT k.kod FROM wf_kumpulan k JOIN wf_kumpulan_ahli a ON a.id_kumpulan = k.id WHERE a.id_pengguna = ?', [$user->id]) as $g) {
            $keys[] = 'kumpulan:'.$g->kod;
        }

        return array_values(array_unique($keys));
    }

    /** @return array{tugasan: list<array<string, mixed>>, notifikasi: list<array<string, mixed>>, ringkasan: array<string, int>} */
    public function for(User $user, int $limit = 50): array
    {
        $tasks = [];
        if ($this->access->allows($user, 'wfe.tugasan.lihat')) {
            $keys = $this->keys($user);
            $now = Carbon::now();
            $rows = Baca::db()->select('SELECT t.id, t.tajuk, t.jenis, t.status, t.tarikh_akhir_sla, t.created_at, i.modul, i.tajuk AS tajuk_instans, i.pautan
                FROM wf_tugasan t JOIN wf_instans i ON i.id = t.id_instans
                WHERE t.status IN (\'menunggu\', \'ditangguh\') AND t.penerima_kunci IN ('.implode(',', array_fill(0, count($keys), '?')).')
                ORDER BY t.tarikh_akhir_sla NULLS LAST, t.id LIMIT ?', [...$keys, $limit]);
            foreach ($rows as $r) {
                $due = $r->tarikh_akhir_sla ? Carbon::parse((string) $r->tarikh_akhir_sla) : null;
                $tasks[] = [
                    'id' => (int) $r->id,
                    'tajuk' => $r->tajuk_instans ?: $r->tajuk,
                    'langkah' => $r->tajuk,
                    'jenis' => $r->jenis,
                    'modul' => $r->modul,
                    'status' => $r->status,
                    'tarikh_akhir' => $due?->toIso8601String(),
                    'status_sla' => $due === null ? 'tiada' : ($due->isPast() ? 'lewat' : ($due->lte($now->copy()->addDay()) ? 'hampir' : 'dalam_tempoh')),
                    'diterima_pada' => Carbon::parse((string) $r->created_at)->toIso8601String(),
                    'pautan' => '/admin/wfe/tugasan/'.$r->id,
                    'pautan_rekod' => is_string($r->pautan) && str_starts_with($r->pautan, '/') ? $r->pautan : null,
                ];
            }
        }

        $notes = [];
        foreach (Baca::db()->select('SELECT m.id, m.subjek, m.keutamaan, m.modul, m.pautan, m.created_at FROM ntf_status_pengguna s JOIN ntf_mesej m ON m.id = s.mesej_id
            WHERE s.pengguna_id = ? AND s.dibaca_pada IS NULL AND s.dipadam_pada IS NULL AND s.diarkib_pada IS NULL ORDER BY m.created_at DESC LIMIT 10', [$user->id]) as $n) {
            $notes[] = [
                'id' => (int) $n->id,
                'subjek' => $n->subjek,
                'keutamaan' => $n->keutamaan,
                'modul' => $n->modul,
                'pautan' => is_string($n->pautan) && str_starts_with($n->pautan, '/') ? $n->pautan : null,
                'tarikh' => Carbon::parse((string) $n->created_at)->toIso8601String(),
            ];
        }

        return [
            'tugasan' => $tasks,
            'notifikasi' => $notes,
            'ringkasan' => [
                'tugasan' => count($tasks),
                'lewat' => count(array_filter($tasks, fn ($t) => $t['status_sla'] === 'lewat')),
                'hampir' => count(array_filter($tasks, fn ($t) => $t['status_sla'] === 'hampir')),
                'notifikasi' => count($notes),
            ],
        ];
    }
}
