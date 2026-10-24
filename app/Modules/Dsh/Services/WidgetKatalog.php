<?php

namespace App\Modules\Dsh\Services;

use App\Models\User;
use App\Modules\Dsh\Models\DefinisiWidget;
use App\Modules\Dsh\Models\SusunAturPengguna;
use App\Modules\Rbc\Contracts\AccessControl;

/**
 * Widget catalogue per dashboard level (SRS-DSH-01/09) and the user's
 * personal layout over it (SRS-DSH-16). Defaults come from code — one widget
 * per registered KPI the user may see, plus system widgets (map, traffic
 * lights, task inbox, insights); admins override title / visual / level /
 * order in dsh_definisi_widget; users reorder, resize, hide and switch the
 * visual of their own copy in dsh_susun_atur_pengguna.
 */
class WidgetKatalog
{
    public const VISUALS = ['auto', 'stat', 'bar', 'pie', 'line', 'progress', 'heatmap', 'lampu', 'peta', 'senarai', 'insight'];

    /** Domains shown on each level. */
    private const DOMAIN_ARAS = [
        'portfolio' => [Aras::EKSEKUTIF, Aras::PENGURUSAN],
        'hasil' => [Aras::EKSEKUTIF, Aras::PENGURUSAN],
        'risiko' => [Aras::EKSEKUTIF, Aras::PENGURUSAN, Aras::OPERASI],
        'pematuhan' => [Aras::EKSEKUTIF, Aras::PENGURUSAN, Aras::OPERASI],
        'operasi' => [Aras::PENGURUSAN, Aras::OPERASI],
    ];

    /** Personal KPIs only make sense on the Operasi level. */
    private const KPI_ARAS = [
        'wfe.tugasan_saya' => [Aras::OPERASI],
        'ntf.belum_dibaca' => [Aras::OPERASI],
        'dsh.draf_saya' => [Aras::OPERASI],
        'dsh.hasil_kos' => [Aras::EKSEKUTIF, Aras::PENGURUSAN],
    ];

    /** @var array<string, array{tajuk: string, jenis_visual: string, aras: list<string>, saiz: int, permission: string|null}> */
    private const SISTEM = [
        'sistem:lampu' => ['tajuk' => 'Penunjuk lampu isyarat', 'jenis_visual' => 'lampu', 'aras' => Aras::ALL, 'saiz' => 2, 'permission' => null],
        'sistem:peta' => ['tajuk' => 'Peta portfolio hartanah', 'jenis_visual' => 'peta', 'aras' => [Aras::EKSEKUTIF, Aras::PENGURUSAN, Aras::OPERASI], 'saiz' => 4, 'permission' => 'gis.peta.lihat'],
        'sistem:insight' => ['tajuk' => 'Pemerhatian automatik', 'jenis_visual' => 'insight', 'aras' => Aras::ALL, 'saiz' => 2, 'permission' => 'dsh.ai.lihat'],
        'sistem:tugasan' => ['tajuk' => 'Inbox tugasan', 'jenis_visual' => 'senarai', 'aras' => [Aras::PENGURUSAN, Aras::OPERASI], 'saiz' => 2, 'permission' => null],
    ];

    public function __construct(private KpiService $kpis, private AccessControl $access) {}

    /**
     * Widgets for $aras after admin overrides (not yet the user's layout).
     *
     * @return list<array{kod: string, tajuk: string, jenis_visual: string, sumber: string, saiz: int, susunan: int, aras: list<string>, kpi: string|null}>
     */
    public function defaults(User $user, string $aras): array
    {
        $widgets = [];
        $i = 0;
        foreach (self::SISTEM as $kod => $w) {
            if ($w['permission'] !== null && ! $this->access->allows($user, $w['permission'])) {
                continue;
            }
            $widgets[$kod] = ['kod' => $kod, 'tajuk' => $w['tajuk'], 'jenis_visual' => $w['jenis_visual'], 'sumber' => $kod, 'saiz' => $w['saiz'], 'susunan' => $i++ * 10, 'aras' => $w['aras'], 'kpi' => null];
        }
        foreach ($this->kpis->visible($user) as $key => $provider) {
            $levels = self::KPI_ARAS[$key] ?? self::DOMAIN_ARAS[$provider->domain()] ?? Aras::ALL;
            $kod = 'kpi:'.$key;
            $widgets[$kod] = ['kod' => $kod, 'tajuk' => $provider->label(), 'jenis_visual' => 'auto', 'sumber' => $kod, 'saiz' => 1, 'susunan' => 100 + $i++ * 10, 'aras' => $levels, 'kpi' => $key];
        }

        foreach ($this->overrides() as $o) {
            $base = $widgets[$o->kod_widget] ?? null;
            if ($base === null) {
                // An admin-defined widget over a KPI the user may see.
                $key = str_starts_with($o->pertanyaan_sumber, 'kpi:') ? substr($o->pertanyaan_sumber, 4) : null;
                if ($key === null || $this->kpis->find($user, $key) === null) {
                    continue;
                }
                $base = ['kod' => $o->kod_widget, 'tajuk' => $o->tajuk ?? $key, 'jenis_visual' => $o->jenis_visual, 'sumber' => $o->pertanyaan_sumber, 'saiz' => $o->saiz, 'susunan' => $o->susunan, 'aras' => $o->aras_dashboard, 'kpi' => $key];
            }
            if (! $o->aktif) {
                unset($widgets[$o->kod_widget]);

                continue;
            }
            $widgets[$o->kod_widget] = array_merge($base, [
                'tajuk' => $o->tajuk ?: $base['tajuk'],
                'jenis_visual' => $o->jenis_visual,
                'saiz' => $o->saiz,
                'susunan' => $o->susunan,
                'aras' => $o->aras_dashboard,
            ]);
        }

        $out = array_values(array_filter($widgets, fn ($w) => in_array($aras, $w['aras'], true)));
        usort($out, fn ($a, $b) => $a['susunan'] <=> $b['susunan']);

        return $out;
    }

    /**
     * The user's layout for $aras: defaults reordered / resized / hidden by
     * their saved rows.
     *
     * @return list<array<string, mixed>>
     */
    public function layout(User $user, string $aras): array
    {
        $widgets = $this->defaults($user, $aras);
        $saved = SusunAturPengguna::query()->where('id_pengguna', $user->id)->where('aras', $aras)->get()->keyBy('kod_widget');

        $out = [];
        foreach ($widgets as $i => $w) {
            $s = $saved[$w['kod']] ?? null;
            $out[] = $w + [
                'posisi' => $s ? $s->posisi : 1000 + $i,
                'saiz' => $w['saiz'],
                'tersembunyi' => $s ? $s->tersembunyi : false,
            ];
            if ($s) {
                $last = array_key_last($out);
                $out[$last]['saiz'] = $s->saiz;
                if ($s->jenis_visual) {
                    $out[$last]['jenis_visual'] = $s->jenis_visual;
                }
            }
        }
        usort($out, fn ($a, $b) => $a['posisi'] <=> $b['posisi']);

        return $out;
    }

    /**
     * Save the user's layout for $aras: ordered widget list with size /
     * hidden / visual. Unknown widget codes are ignored.
     *
     * @param  list<array{kod: string, saiz?: int, tersembunyi?: bool, jenis_visual?: string|null}>  $items
     */
    public function save(User $user, string $aras, array $items): void
    {
        $known = array_column($this->defaults($user, $aras), 'kod');
        $pos = 0;
        foreach ($items as $item) {
            if (! in_array($item['kod'], $known, true)) {
                continue;
            }
            $visual = $item['jenis_visual'] ?? null;
            SusunAturPengguna::query()->updateOrCreate(
                ['id_pengguna' => $user->id, 'aras' => $aras, 'kod_widget' => $item['kod']],
                [
                    'posisi' => $pos++,
                    'saiz' => max(1, min(4, (int) ($item['saiz'] ?? 1))),
                    'tersembunyi' => (bool) ($item['tersembunyi'] ?? false),
                    'jenis_visual' => in_array($visual, self::VISUALS, true) ? $visual : null,
                ],
            );
        }
    }

    public function reset(User $user, string $aras): void
    {
        SusunAturPengguna::query()->where('id_pengguna', $user->id)->where('aras', $aras)->get()->each->delete();
    }

    /** @return iterable<DefinisiWidget> */
    private function overrides(): iterable
    {
        return DefinisiWidget::query()->orderBy('susunan')->get();
    }
}
