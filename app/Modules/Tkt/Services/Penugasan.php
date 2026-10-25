<?php

namespace App\Modules\Tkt\Services;

use App\Models\User;
use App\Modules\Tkt\Models\AhliPasukan;
use App\Modules\Tkt\Models\Pasukan;
use App\Modules\Tkt\Models\PeraturanPenugasan;
use App\Modules\Tkt\Models\Tiket;
use App\Modules\Tkt\Support\Lalai;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * SRS-TKT-10: automatic assignment to a team / technician by module,
 * category and severity; manual reassignment with a recorded reason; hand
 * over from the L1 MAIS helpdesk to the L2 vendor queue (vendor + vendor
 * reference recorded).
 */
class Penugasan
{
    public function __construct(private Jejak $jejak) {}

    /** Picks team + technician from the most specific matching rule. */
    public function auto(Tiket $tiket): void
    {
        $rule = $this->peraturan($tiket);
        $team = $rule->pasukan ?? Pasukan::query()->where('aktif', true)->where('aras', 'L1')->orderBy('id')->first();
        if ($team === null) {
            return;
        }
        $tech = null;
        if ($rule?->id_juruteknik !== null && $this->ahliAktif($team->id, $rule->id_juruteknik)) {
            $tech = $rule->id_juruteknik;
        }
        $tech ??= $this->palingRingan($team->id);
        $tiket->forceFill([
            'id_pasukan' => $team->id,
            'aras_sokongan' => $team->aras,
            'id_juruteknik' => $tech,
            'nama_vendor' => $team->jenis === 'vendor' ? $team->nama_vendor : $tiket->nama_vendor,
        ]);
    }

    public function peraturan(Tiket $tiket): ?PeraturanPenugasan
    {
        /** @var Collection<int, PeraturanPenugasan> $rules */
        $rules = PeraturanPenugasan::query()->with('pasukan')->where('aktif', true)->get()
            ->filter(fn (PeraturanPenugasan $r) => $r->pasukan?->aktif
                && ($r->modul === null || $r->modul === $tiket->modul)
                && ($r->kategori === null || $r->kategori === $tiket->kategori)
                && ($r->keterukan === null || $r->keterukan === $tiket->keterukan));

        return $rules->sortByDesc(fn (PeraturanPenugasan $r) => [
            (int) ($r->modul !== null) + (int) ($r->kategori !== null) + (int) ($r->keterukan !== null),
            $r->keutamaan,
        ])->first();
    }

    /** Least-loaded active technician of the team (open tickets), or null. */
    public function palingRingan(int $teamId): ?int
    {
        $members = AhliPasukan::query()->where('id_pasukan', $teamId)->where('aktif', true)->where('peranan', 'juruteknik')
            ->whereHas('pengguna', fn ($q) => $q->where('is_active', true))->pluck('id_pengguna')->map(fn ($id) => (int) $id)->all();
        if ($members === []) {
            return null;
        }
        $load = Tiket::query()->whereIn('id_juruteknik', $members)->whereIn('status', Lalai::TERBUKA)
            ->selectRaw('id_juruteknik, count(*) as n')->groupBy('id_juruteknik')->pluck('n', 'id_juruteknik')->all();
        usort($members, fn (int $a, int $b) => [(int) ($load[$a] ?? 0), $a] <=> [(int) ($load[$b] ?? 0), $b]);

        return $members[0];
    }

    public function ahliAktif(int $teamId, int $userId): bool
    {
        return AhliPasukan::query()->where('id_pasukan', $teamId)->where('id_pengguna', $userId)->where('aktif', true)->exists();
    }

    /**
     * Technicians who may take this ticket: active members of active teams
     * that cover its module.
     *
     * @return list<array{id: int, nama: string, pasukan: string, id_pasukan: int, aras: string, peranan: string, beban: int}>
     */
    public function layak(Tiket $tiket): array
    {
        $rows = AhliPasukan::query()->with(['pengguna', 'pasukan'])->where('aktif', true)
            ->whereHas('pasukan', fn ($q) => $q->where('aktif', true))
            ->whereHas('pengguna', fn ($q) => $q->where('is_active', true))->get()
            ->filter(fn (AhliPasukan $a) => $a->pasukan !== null && $a->pasukan->liputi($tiket->modul));
        $ids = $rows->pluck('id_pengguna')->unique()->all();
        $load = Tiket::query()->whereIn('id_juruteknik', $ids)->whereIn('status', Lalai::TERBUKA)
            ->selectRaw('id_juruteknik, count(*) as n')->groupBy('id_juruteknik')->pluck('n', 'id_juruteknik')->all();

        return $rows->map(fn (AhliPasukan $a) => [
            'id' => $a->id_pengguna,
            'nama' => (string) $a->pengguna?->name,
            'pasukan' => (string) $a->pasukan?->nama,
            'id_pasukan' => $a->id_pasukan,
            'aras' => (string) $a->pasukan?->aras,
            'peranan' => $a->peranan,
            'beban' => (int) ($load[$a->id_pengguna] ?? 0),
        ])->values()->all();
    }

    /** Manual (re)assignment — the reason is mandatory and kept in the timeline. */
    public function tugasSemula(Tiket $tiket, int $teamId, ?int $techId, string $sebab, User $by): void
    {
        $team = Pasukan::query()->where('aktif', true)->find($teamId);
        if ($team === null) {
            throw ValidationException::withMessages(['id_pasukan' => __('tkt::tkt.ralat.pasukan_tidak_sah')]);
        }
        if ($techId !== null && ! $this->ahliAktif($team->id, $techId)) {
            throw ValidationException::withMessages(['id_juruteknik' => __('tkt::tkt.ralat.bukan_ahli_pasukan')]);
        }
        $from = ['pasukan' => $tiket->pasukan?->nama, 'juruteknik' => $tiket->juruteknik?->name];
        $tiket->forceFill([
            'id_pasukan' => $team->id,
            'aras_sokongan' => $team->aras,
            'id_juruteknik' => $techId ?? $this->palingRingan($team->id),
            'nama_vendor' => $team->jenis === 'vendor' ? $team->nama_vendor : $tiket->nama_vendor,
        ])->save();
        $tiket->load(['pasukan', 'juruteknik']);
        $this->jejak->catat($tiket, 'tugasan', $by, $sebab, null, null, [
            'dari' => $from,
            'ke' => ['pasukan' => $team->nama, 'juruteknik' => $tiket->juruteknik?->name],
            'manual' => true,
        ]);
        $this->jejak->hantar('tkt.tiket_ditugaskan', $tiket->juruteknik, $tiket);
    }

    /** L1 → L2 vendor hand-over (or back), with the vendor's own reference. */
    public function serahAras(Tiket $tiket, int $teamId, ?string $rujukanVendor, string $catatan, User $by): void
    {
        $team = Pasukan::query()->where('aktif', true)->find($teamId);
        if ($team === null) {
            throw ValidationException::withMessages(['id_pasukan' => __('tkt::tkt.ralat.pasukan_tidak_sah')]);
        }
        $from = $tiket->aras_sokongan;
        $tiket->forceFill([
            'id_pasukan' => $team->id,
            'aras_sokongan' => $team->aras,
            'id_juruteknik' => $this->palingRingan($team->id),
            'nama_vendor' => $team->jenis === 'vendor' ? $team->nama_vendor : $tiket->nama_vendor,
            'rujukan_vendor' => $rujukanVendor ?? $tiket->rujukan_vendor,
            'tarikh_serah_vendor' => $team->jenis === 'vendor' ? now() : $tiket->tarikh_serah_vendor,
        ])->save();
        $tiket->load(['pasukan', 'juruteknik']);
        $this->jejak->catat($tiket, 'tugasan', $by, $catatan, null, null, [
            'aras_dari' => $from, 'aras_ke' => $team->aras, 'pasukan' => $team->nama, 'vendor' => $tiket->nama_vendor, 'rujukan_vendor' => $tiket->rujukan_vendor,
        ]);
        $this->jejak->hantar('tkt.tiket_ditugaskan', $tiket->juruteknik ?? $this->ketua($team->id), $tiket);
    }

    /** @return list<User> */
    private function ketua(int $teamId): array
    {
        return AhliPasukan::query()->with('pengguna')->where('id_pasukan', $teamId)->where('aktif', true)->where('peranan', 'penyelia')->get()
            ->map(fn (AhliPasukan $a) => $a->pengguna)->filter()->values()->all();
    }
}
