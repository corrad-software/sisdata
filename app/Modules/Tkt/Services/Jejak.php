<?php

namespace App\Modules\Tkt\Services;

use App\Models\User;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Tkt\Models\Kronologi;
use App\Modules\Tkt\Models\Tiket;
use App\Modules\Tkt\Support\Lalai;
use Illuminate\Contracts\Foundation\Application;
use Throwable;

/**
 * Timeline writer (SRS-TKT-11) and notification sender (through MOD-NTF
 * only). Notifications never carry ticket content — reference, category,
 * severity and a link; the ticket page enforces access (SRS-NTF-19).
 */
class Jejak
{
    public function __construct(private Application $app) {}

    /** @param array<string, mixed> $meta */
    public function catat(Tiket $tiket, string $jenis, ?User $user, ?string $kandungan = null, ?string $dari = null, ?string $ke = null, array $meta = []): Kronologi
    {
        return Kronologi::query()->create([
            'id_tiket' => $tiket->id,
            'jenis_catatan' => $jenis,
            'id_pengguna' => $user?->id,
            'kandungan' => $kandungan,
            'status_dari' => $dari,
            'status_ke' => $ke,
            'meta' => $meta === [] ? null : $meta,
            'cap_masa' => now(),
        ]);
    }

    /**
     * @param  User|iterable<User>|null  $to
     * @param  array<string, scalar|null>  $extra
     */
    public function hantar(string $type, User|iterable|null $to, Tiket $tiket, array $extra = [], ?string $dedupe = null): void
    {
        $recipients = [];
        foreach ($to instanceof User ? [$to] : ($to ?? []) as $user) {
            if ($user->is_active !== false) {
                $recipients[$user->id] = $user;
            }
        }
        if ($recipients === [] && ! in_array($type, ['tkt.tiket_baharu', 'tkt.laporan_bulanan', 'tkt.penyegerakan_gagal'], true)) {
            return;
        }
        try {
            $this->app->make(Notifier::class)->send($type, array_values($recipients), [
                'nombor_rujukan' => $tiket->nombor_rujukan,
                'kategori' => Lalai::KATEGORI[$tiket->kategori] ?? $tiket->kategori,
                'keterukan' => Lalai::KETERUKAN_RINGKAS[$tiket->keterukan] ?? (string) $tiket->keterukan,
                'modul' => Lalai::MODUL[$tiket->modul ?? 'umum'] ?? $tiket->modul,
                ...$extra,
            ], '/admin/tkt/tiket/'.$tiket->id, $dedupe);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
