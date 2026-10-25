<?php

namespace App\Modules\Kba\Services;

use App\Models\User;
use App\Modules\Kba\Models\Artikel;
use Illuminate\Support\Facades\DB;

/**
 * SRS-KBA-09: the support team's per-module view — system design sections,
 * integration flows, configuration parameters mentioned in the design
 * documents and the problem history (solution articles from tickets and
 * unanswered questions) — so a new technician can resolve tickets without
 * the original developers. Every passage goes through the Capaian filter.
 */
class Sokongan
{
    public function __construct(private Capaian $capaian) {}

    /** @return list<array{modul: string, segmen: int, artikel: int}> */
    public function senaraiModul(User $user): array
    {
        $acc = $this->capaian->predicate($user);
        $seg = collect(DB::select("SELECT s.modul, COUNT(*) AS n FROM kba_segmen s WHERE s.status = 'aktif' AND s.modul IS NOT NULL AND {$acc['sql']} GROUP BY s.modul", $acc['bindings']))
            ->pluck('n', 'modul');
        $art = Artikel::query()->where('status', 'diterbitkan')->whereNotNull('modul')->selectRaw('modul, COUNT(*) AS n')->groupBy('modul')->pluck('n', 'modul');

        return $seg->keys()->merge($art->keys())->unique()->sort()->values()
            ->map(fn ($m) => ['modul' => (string) $m, 'segmen' => (int) ($seg[$m] ?? 0), 'artikel' => (int) ($art[$m] ?? 0)])->all();
    }

    /** @return array<string, mixed> */
    public function modul(string $modul, User $user): array
    {
        $modul = strtolower($modul);
        $acc = $this->capaian->predicate($user);
        $rows = DB::select("SELECT s.id, s.id_sumber, s.nombor_seksyen, s.tajuk_seksyen, s.laluan_seksyen, s.kandungan_teks, d.tajuk AS dokumen, d.versi, d.no_versi, d.jenis_dokumen
            FROM kba_segmen s JOIN kba_sumber_dokumen d ON d.id = s.id_sumber
            WHERE s.status = 'aktif' AND d.aktif = true AND (s.modul = ? OR d.modul = ?) AND {$acc['sql']}
            ORDER BY d.jenis_dokumen, d.tajuk, s.urutan LIMIT 400", [$modul, $modul, ...$acc['bindings']]);

        $reka = [];
        $integrasi = [];
        $parameter = [];
        foreach ($rows as $r) {
            $item = [
                'id_segmen' => (int) $r->id, 'dokumen' => (string) $r->dokumen, 'versi' => $r->versi ?? 'v'.$r->no_versi,
                'seksyen' => Penjawab::seksyen(['nombor_seksyen' => $r->nombor_seksyen, 'tajuk_seksyen' => $r->tajuk_seksyen]),
                'laluan' => $r->laluan_seksyen, 'ringkasan' => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $r->kandungan_teks) ?? ''), 0, 220),
                'pautan' => Pencari::pautan((int) $r->id_sumber, (int) $r->id),
            ];
            $text = mb_strtolower($r->laluan_seksyen.' '.$r->kandungan_teks);
            if (str_contains($text, 'integrasi') || str_contains($text, 'integration') || str_contains($text, 'antara muka')) {
                $integrasi[] = $item;
            } else {
                $reka[] = $item;
            }
            if (preg_match_all('/\b'.preg_quote($modul, '/').'\.[a-z][a-z0-9_]{2,}\b/', (string) $r->kandungan_teks, $m)) {
                foreach ($m[0] as $key) {
                    $parameter[$key] ??= $item['pautan'];
                }
            }
        }

        $levels = $this->capaian->levels($user);
        $masalah = Artikel::query()->where('modul', $modul)->where('status', 'diterbitkan')->whereIn('klasifikasi', $levels)
            ->orderByDesc('tarikh_terbit')->limit(50)
            ->get(['id', 'tajuk', 'sumber_asal', 'id_tiket_asal', 'tarikh_terbit', 'versi_sistem_dirujuk'])
            ->map(fn (Artikel $a) => [
                'id' => $a->id, 'tajuk' => $a->tajuk, 'sumber_asal' => $a->sumber_asal, 'id_tiket_asal' => $a->id_tiket_asal,
                'pautan_tiket' => $a->id_tiket_asal !== null ? '/admin/tkt/tiket/'.$a->id_tiket_asal : null,
                'tarikh_terbit' => $a->tarikh_terbit?->toIso8601String(), 'versi_sistem' => $a->versi_sistem_dirujuk, 'pautan' => '/admin/kba/artikel/'.$a->id,
            ])->values()->all();

        $jurang = DB::table('kba_log_prompt')->where('modul', $modul)->where('status', 'tiada_sumber')
            ->selectRaw('prompt_dinormalkan AS soalan, COUNT(*) AS bilangan')->groupBy('prompt_dinormalkan')->orderByDesc('bilangan')->limit(10)->get();

        ksort($parameter);

        return [
            'modul' => $modul,
            'reka_bentuk' => array_slice($reka, 0, 150),
            'integrasi' => array_slice($integrasi, 0, 80),
            'parameter' => array_map(fn ($k, $v) => ['kunci' => $k, 'pautan' => $v], array_keys($parameter), array_values($parameter)),
            'sejarah_masalah' => $masalah,
            'soalan_tanpa_jawapan' => $jurang,
        ];
    }
}
