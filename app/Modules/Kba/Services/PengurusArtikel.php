<?php

namespace App\Modules\Kba\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Kba\Contracts\ArticleDrafts;
use App\Modules\Kba\Models\Artikel;
use App\Modules\Kba\Models\Keluaran;
use App\Modules\Kba\Models\VersiArtikel;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Articles (SRS-KBA-11..14): versioned content with owner and review dates;
 * review & publication go through MOD-WFE (flow "kba.terbit_artikel") — KBA
 * never decides approvals itself (SRS-GEN-03). Only the published version is
 * indexed; an edit after publication stays a draft until approved, while
 * users keep seeing the last published version.
 */
class PengurusArtikel implements ArticleDrafts
{
    public const ALIRAN = 'kba.terbit_artikel';

    public const SUBJEK = 'kba_artikel';

    public function __construct(
        private PengurusSumber $sumber,
        private WorkflowEngine $workflow,
        private Parameters $parameters,
        private Notifier $notifier,
    ) {}

    /** @param array<string, mixed> $data */
    public function cipta(array $data, ?User $user, string $asal = 'manual', ?string $idTiket = null): Artikel
    {
        return DB::transaction(function () use ($data, $user, $asal, $idTiket) {
            $a = Artikel::query()->create([
                'tajuk' => $data['tajuk'],
                'kategori' => $data['kategori'] ?? null,
                'modul' => isset($data['modul']) ? strtolower((string) $data['modul']) : null,
                'kandungan' => $data['kandungan'],
                'status' => 'draf',
                'versi' => 1,
                'klasifikasi' => $data['klasifikasi'] ?? 'dalaman',
                'senarai_peranan_dibenarkan' => array_values($data['senarai_peranan_dibenarkan'] ?? []),
                'id_pemilik' => $user?->id,
                'tarikh_semakan_seterusnya' => $data['tarikh_semakan_seterusnya'] ?? null,
                'sumber_asal' => $asal,
                'id_tiket_asal' => $idTiket,
                'versi_sistem_dirujuk' => $data['versi_sistem_dirujuk'] ?? null,
            ]);
            $this->simpanVersi($a, $user, $data['sebab_perubahan'] ?? __('kba::kba.artikel.versi_awal'));

            return $a;
        });
    }

    /** @param array<string, mixed> $data */
    public function kemasKini(Artikel $a, array $data, ?User $user): Artikel
    {
        if ($a->status === 'dalam_semakan') {
            throw new InvalidArgumentException(__('kba::kba.artikel.sedang_disemak'));
        }

        return DB::transaction(function () use ($a, $data, $user) {
            $changed = (isset($data['kandungan']) && $data['kandungan'] !== $a->kandungan) || (isset($data['tajuk']) && $data['tajuk'] !== $a->tajuk);
            $a->fill(array_intersect_key($data, array_flip(['tajuk', 'kategori', 'kandungan', 'klasifikasi', 'senarai_peranan_dibenarkan', 'tarikh_semakan_seterusnya', 'versi_sistem_dirujuk'])));
            if (array_key_exists('modul', $data)) {
                $a->modul = $data['modul'] !== null ? strtolower((string) $data['modul']) : null;
            }
            if ($changed) {
                $a->versi++;
                if ($a->status === 'diterbitkan' || $a->status === 'lapuk') {
                    $a->status = 'draf'; // new version must be reviewed again; last published one stays live
                }
            }
            $a->save();
            if ($changed) {
                $this->simpanVersi($a, $user, $data['sebab_perubahan'] ?? null);
            } elseif ($a->versi_terbit !== null && $a->status !== 'lapuk') {
                // metadata only (classification / roles) — applies to the live version at once
                $this->sumber->indeksArtikel($a);
            }

            return $a;
        });
    }

    /** SRS-KBA-13: submit the draft for review through MOD-WFE. */
    public function hantar(Artikel $a, User $user): Artikel
    {
        if (! in_array($a->status, ['draf'], true)) {
            throw new InvalidArgumentException(__('kba::kba.artikel.bukan_draf'));
        }
        $instance = $this->workflow->start(self::ALIRAN, self::SUBJEK, $a->id, $user, [
            'modul' => $a->modul, 'klasifikasi' => $a->klasifikasi, 'sumber_asal' => $a->sumber_asal, 'pautan' => '/admin/kba/artikel/'.$a->id,
        ], __('kba::kba.artikel.tajuk_aliran', ['tajuk' => mb_substr($a->tajuk, 0, 120)]));
        $a->forceFill(['status' => 'dalam_semakan', 'id_instans_aliran' => $instance, 'catatan_semakan' => null])->save();

        return $a;
    }

    /** WFE outcome (SRS-WFE-20): publish on approval, back to draft otherwise. */
    public function selesaiAliran(WorkflowCompleted $event): void
    {
        if ($event->flowCode !== self::ALIRAN || $event->subjectType !== self::SUBJEK) {
            return;
        }
        $a = Artikel::query()->find((int) $event->subjectId);
        if ($a === null || $a->status !== 'dalam_semakan') {
            return;
        }
        if ($event->outcome !== 'lulus') {
            $a->forceFill(['status' => 'draf', 'catatan_semakan' => $event->remarks !== null ? mb_substr($event->remarks, 0, 1000) : null, 'id_penyemak' => $event->decidedBy])->save();

            return;
        }

        $hari = max(1, (int) $this->parameters->get('kba.hari_semakan_artikel', 180));
        $seterusnya = $a->tarikh_semakan_seterusnya !== null && $a->tarikh_semakan_seterusnya->isFuture() ? $a->tarikh_semakan_seterusnya : now()->addDays($hari);
        $a->forceFill([
            'status' => 'diterbitkan',
            'versi_terbit' => $a->versi,
            'tarikh_terbit' => now(),
            'id_penyemak' => $event->decidedBy,
            'tarikh_semakan_akhir' => now()->toDateString(),
            'tarikh_semakan_seterusnya' => $seterusnya->toDateString(),
            'perlu_semakan' => false,
            'sebab_perlu_semakan' => null,
            'catatan_semakan' => $event->remarks !== null ? mb_substr($event->remarks, 0, 1000) : null,
        ])->save();
        $this->sumber->indeksArtikel($a);
    }

    public function lapukkan(Artikel $a): Artikel
    {
        $a->forceFill(['status' => 'lapuk'])->save();
        $this->sumber->lapukArtikel($a);

        return $a;
    }

    /**
     * SRS-KBA-12: a new system release flags published articles that refer to
     * an older system version for review, and tells their owners.
     */
    public function keluaran(string $versi, ?string $catatan, ?User $user): Keluaran
    {
        $flagged = 0;
        $owners = [];
        Artikel::query()->whereIn('status', ['diterbitkan', 'draf'])->whereNotNull('versi_sistem_dirujuk')->orderBy('id')
            ->each(function (Artikel $a) use ($versi, &$flagged, &$owners) {
                if (version_compare((string) $a->versi_sistem_dirujuk, $versi, '<')) {
                    $a->forceFill(['perlu_semakan' => true, 'sebab_perlu_semakan' => __('kba::kba.artikel.sebab_keluaran', ['lama' => $a->versi_sistem_dirujuk, 'baharu' => $versi])])->save();
                    $flagged++;
                    if ($a->id_pemilik !== null) {
                        $owners[$a->id_pemilik] = ($owners[$a->id_pemilik] ?? 0) + 1;
                    }
                }
            });

        $k = Keluaran::query()->create(['versi' => $versi, 'catatan' => $catatan, 'id_pengguna' => $user?->id, 'bilangan_artikel_ditanda' => $flagged]);

        foreach ($owners as $ownerId => $n) {
            $owner = User::query()->find($ownerId);
            if ($owner !== null) {
                try {
                    $this->notifier->send('kba.artikel_perlu_semakan', $owner, ['bil' => $n, 'versi' => $versi], '/admin/kba/artikel?tindakan=1', 'kba-keluaran-'.$versi);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        return $k;
    }

    public function fromTicket(string $ticketId, array $data, ?User $author = null): int
    {
        $kandungan = "## Masalah\n\n".trim($data['masalah'])."\n\n## Punca\n\n".trim($data['punca'])."\n\n## Penyelesaian\n\n".trim($data['penyelesaian']);
        $existing = Artikel::query()->where('id_tiket_asal', $ticketId)->where('status', 'draf')->orderByDesc('id')->first();
        $payload = [
            'tajuk' => mb_substr($data['tajuk'], 0, 250),
            'kandungan' => $kandungan,
            'modul' => $data['modul'] ?? null,
            'kategori' => $data['kategori'] ?? 'penyelesaian_tiket',
            'versi_sistem_dirujuk' => $data['versi_sistem'] ?? null,
            'sebab_perubahan' => __('kba::kba.artikel.dari_tiket', ['tiket' => $ticketId]),
        ];

        if ($existing !== null) {
            return $this->kemasKini($existing, $payload, $author)->id;
        }

        return $this->cipta($payload, $author, 'tiket', $ticketId)->id;
    }

    public function forTicket(string $ticketId): array
    {
        return Artikel::query()->where('id_tiket_asal', $ticketId)->orderBy('id')->get()
            ->map(fn (Artikel $a) => ['id' => $a->id, 'tajuk' => $a->tajuk, 'status' => $a->status, 'pautan' => '/admin/kba/artikel/'.$a->id])
            ->values()->all();
    }

    private function simpanVersi(Artikel $a, ?User $user, ?string $sebab): void
    {
        VersiArtikel::query()->create([
            'id_artikel' => $a->id,
            'nombor_versi' => $a->versi,
            'tajuk' => $a->tajuk,
            'kandungan' => $a->kandungan,
            'id_pengubah' => $user?->id,
            'sebab_perubahan' => $sebab !== null ? mb_substr($sebab, 0, 500) : null,
            'cap_masa' => now(),
        ]);
    }
}
