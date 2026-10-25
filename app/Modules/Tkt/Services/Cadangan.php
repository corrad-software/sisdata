<?php

namespace App\Modules\Tkt\Services;

use App\Models\User;
use App\Modules\Kba\Contracts\KnowledgeBase;
use App\Modules\Tkt\Models\CadanganKb;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * SRS-TKT-06/07: knowledge-base suggestions while the reporter types,
 * BEFORE the ticket is submitted (MOD-KBA, access-filtered by the caller),
 * and deflection tracking per form session: shown → opened → ticket still
 * submitted (or the user said it solved the problem).
 */
class Cadangan
{
    public function __construct(private KnowledgeBase $kb) {}

    /**
     * @return list<array{dokumen: string, versi: string|null, seksyen: string|null, petikan: string, skor: float, pautan: string, diklik: bool}>
     */
    public function cari(string $teks, string $sesi, User $user, ?string $modul, int $limit = 5): array
    {
        $teks = trim(Penyamar::teks($teks) ?? '');
        if (mb_strlen($teks) < 8) {
            return [];
        }
        try {
            $results = $this->kb->search($teks, $user, max(3, $limit));
        } catch (Throwable $e) {
            report($e);

            return [];
        }
        $out = [];
        foreach ($results as $i => $r) {
            $link = mb_substr((string) $r['pautan'], 0, 500);
            $row = CadanganKb::query()->firstOrCreate(
                ['id_tiket_draf' => $sesi, 'id_artikel_kb' => $link],
                ['id_pengguna' => $user->id, 'tajuk' => mb_substr(trim($r['dokumen'].' '.($r['seksyen'] ?? '')), 0, 300), 'skor_relevan' => (float) $r['skor'], 'kedudukan' => $i + 1, 'modul' => $modul, 'cap_masa' => now()],
            );
            $out[] = $r + ['diklik' => $row->diklik];
        }

        return $out;
    }

    /**
     * Grounded answer (optional "tanya pembantu"); never unsupported (SRS-KBA-05).
     *
     * @return array{jawapan: string, cukup_sumber: bool, sumber: list<array<string, mixed>>}
     */
    public function jawapan(string $teks, User $user): array
    {
        try {
            return $this->kb->answer(Penyamar::teks($teks) ?? '', $user);
        } catch (Throwable $e) {
            report($e);

            return ['jawapan' => (string) __('tkt::tkt.cadangan.tiada_jawapan'), 'cukup_sumber' => false, 'sumber' => []];
        }
    }

    public function klik(string $sesi, string $pautan, User $user): void
    {
        CadanganKb::query()->where('id_tiket_draf', $sesi)->where('id_artikel_kb', $pautan)->where('id_pengguna', $user->id)->update(['diklik' => true]);
    }

    /** The reporter decided: still submitting ($diteruskan) or solved by the article. */
    public function keputusan(string $sesi, User $user, bool $diteruskan, ?string $tiketId = null): int
    {
        return CadanganKb::query()->where('id_tiket_draf', $sesi)->where('id_pengguna', $user->id)->whereNull('id_tiket')
            ->update(['tiket_diteruskan' => $diteruskan, 'id_tiket' => $tiketId]);
    }

    /**
     * Deflection figures for a period: sessions shown suggestions, sessions
     * that still submitted, self-solved; articles shown/opened but the ticket
     * still went ahead (ineffective).
     *
     * @return array{sesi: int, diteruskan: int, diselesaikan_sendiri: int, tiada_keputusan: int, kadar_penyelesaian_kendiri: float|null, artikel_tidak_berkesan: list<array{pautan: string, tajuk: string|null, dipapar: int, diklik: int, tiket_diteruskan: int}>}
     */
    public function metrik(?string $dari = null, ?string $hingga = null): array
    {
        $base = DB::table('tkt_cadangan_kb')
            ->when($dari, fn ($q, $d) => $q->where('cap_masa', '>=', $d))
            ->when($hingga, fn ($q, $d) => $q->where('cap_masa', '<=', strlen($d) <= 10 ? $d.' 23:59:59' : $d));
        $sessions = (clone $base)->selectRaw('id_tiket_draf, bool_or(tiket_diteruskan) as terus, bool_and(tiket_diteruskan is false) as sendiri')
            ->groupBy('id_tiket_draf')->get();
        $total = $sessions->count();
        $continued = $sessions->filter(fn ($s) => (bool) $s->terus)->count();
        $self = $sessions->filter(fn ($s) => (bool) $s->sendiri)->count();
        $decided = $continued + $self;

        $articles = (clone $base)->selectRaw('id_artikel_kb as pautan, max(tajuk) as tajuk, count(*) as dipapar, sum(case when diklik then 1 else 0 end) as diklik, sum(case when tiket_diteruskan then 1 else 0 end) as diteruskan')
            ->groupBy('id_artikel_kb')->havingRaw('sum(case when tiket_diteruskan then 1 else 0 end) > 0')
            ->orderByRaw('sum(case when diklik and tiket_diteruskan then 1 else 0 end) desc, count(*) desc')->limit(10)->get();

        return [
            'sesi' => $total,
            'diteruskan' => $continued,
            'diselesaikan_sendiri' => $self,
            'tiada_keputusan' => $total - $decided,
            'kadar_penyelesaian_kendiri' => $decided > 0 ? round(100 * $self / $decided, 1) : null,
            'artikel_tidak_berkesan' => $articles->map(fn ($a) => ['pautan' => (string) $a->pautan, 'tajuk' => $a->tajuk, 'dipapar' => (int) $a->dipapar, 'diklik' => (int) $a->diklik, 'tiket_diteruskan' => (int) $a->diteruskan])->values()->all(),
        ];
    }
}
