<?php

namespace App\Modules\Tkt\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Ntf\Contracts\RecipientDirectory;
use App\Modules\Ntf\Contracts\SupervisorResolver;
use App\Modules\Tkt\Models\AhliPasukan;
use App\Modules\Tkt\Models\Tiket;
use Illuminate\Contracts\Foundation\Application;

/**
 * Resolves the escalation hierarchy (§18.5): juruteknik → penyelia sokongan
 * (team supervisors, else MOD-NTF's SupervisorResolver for the assignee) →
 * pengurus projek (team managers, else holders of role parameter
 * tkt.peranan_pengurus_projek) → `peranan:<kod>` (e.g. the MAIS Project
 * Committee) through MOD-NTF's RecipientDirectory.
 */
class Penerima
{
    public function __construct(private Application $app, private Parameters $parameters) {}

    /** @return list<User> */
    public function untuk(Tiket $tiket, string $jenis): array
    {
        $users = match (true) {
            $jenis === 'juruteknik' => $tiket->id_juruteknik !== null ? array_filter([User::query()->find($tiket->id_juruteknik)]) : $this->ahli($tiket, 'penyelia'),
            $jenis === 'penyelia' => $this->ahli($tiket, 'penyelia') ?: $this->penyeliaJuruteknik($tiket),
            $jenis === 'pengurus' => $this->ahli($tiket, 'pengurus') ?: $this->peranan((string) $this->parameters->get('tkt.peranan_pengurus_projek', 'pengurus_projek')),
            str_starts_with($jenis, 'peranan:') => $this->peranan(substr($jenis, 8)),
            default => [],
        };

        $out = [];
        foreach ($users as $user) {
            if ($user->is_active !== false) {
                $out[$user->id] = $user;
            }
        }

        return array_values($out);
    }

    /** @return list<User> active members with $peranan in the ticket's team (else in any team of the same level). */
    private function ahli(Tiket $tiket, string $peranan): array
    {
        $query = AhliPasukan::query()->with('pengguna')->where('aktif', true)->where('peranan', $peranan);
        $rows = $tiket->id_pasukan !== null ? (clone $query)->where('id_pasukan', $tiket->id_pasukan)->get() : collect();
        if ($rows->isEmpty()) {
            $rows = $query->whereHas('pasukan', fn ($q) => $q->where('aktif', true)->where('aras', $tiket->aras_sokongan))->get();
        }

        return $rows->map(fn (AhliPasukan $a) => $a->pengguna)->filter()->values()->all();
    }

    /** @return list<User> */
    private function penyeliaJuruteknik(Tiket $tiket): array
    {
        if ($tiket->id_juruteknik === null || ! $this->app->bound(SupervisorResolver::class)) {
            return [];
        }
        $tech = User::query()->find($tiket->id_juruteknik);

        return $tech === null ? [] : $this->app->make(SupervisorResolver::class)->supervisorsOf($tech);
    }

    /** @return list<User> */
    private function peranan(string $kod): array
    {
        if ($kod === '' || ! $this->app->bound(RecipientDirectory::class)) {
            return [];
        }

        return $this->app->make(RecipientDirectory::class)->resolve(RecipientDirectory::PERANAN, $kod);
    }
}
