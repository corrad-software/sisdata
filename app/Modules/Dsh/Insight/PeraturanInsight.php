<?php

namespace App\Modules\Dsh\Insight;

use App\Models\User;
use App\Modules\Dsh\Contracts\InsightGenerator;
use Illuminate\Support\Str;

/**
 * Default InsightGenerator: deterministic rules, no model, nothing leaves
 * the server (SRS-DSH-18). Insights come from traffic-light status, trend
 * direction and dominant slices; prompts are parsed with BM/EN keyword
 * matching against the KPIs the user may see, district names and years.
 */
class PeraturanInsight implements InsightGenerator
{
    /** Extra words that point at a KPI (matched on whole-word prefixes, lower-case, no accents). */
    private const SINONIM = [
        'pro.bilangan_lot' => ['lot', 'tanah', 'aset', 'land', 'lots', 'asset', 'portfolio', 'hartanah'],
        'pro.keluasan' => ['keluasan', 'luas', 'hektar', 'area', 'hectare', 'land area', 'keluasan tanah', 'luas tanah'],
        'pro.lot_bahagian' => ['bahagian', 'division', 'wakaf', 'baitulmal'],
        'pro.nilai_portfolio' => ['nilai', 'value', 'valuation', 'penilaian', 'worth'],
        'hkm.hak_milik' => ['hak milik', 'geran', 'title', 'titles'],
        'hkm.status_tanah' => ['status tanah', 'land status', 'kosong', 'vacant'],
        'hkm.hampir_tamat' => ['tamat tempoh', 'hampir tamat', 'expir', 'expiring'],
        'hkm.belum_disahkan' => ['belum disahkan', 'unverified', 'pengesahan'],
        'per.pemerolehan_tahunan' => ['pemerolehan', 'acquisition', 'acquisitions', 'saluran', 'channel'],
        'per.dalam_proses' => ['kes pemerolehan', 'dalam proses', 'in progress'],
        'per.melebihi_tempoh' => ['melebihi tempoh', 'lewat', 'overdue', 'late'],
        'wfe.tugasan_tertunggak' => ['tugasan', 'tindakan tertunggak', 'pending', 'task', 'tasks', 'kelulusan', 'approval'],
        'wfe.tugasan_lewat_sla' => ['lewat sla', 'sla', 'breach'],
        'wfe.dalam_sla' => ['dalam sla', 'within sla', 'prestasi sla'],
        'wfe.tugasan_saya' => ['tugasan saya', 'my tasks', 'inbox'],
        'ntf.belum_dibaca' => ['notifikasi', 'notification', 'notifications'],
        'dms.storan' => ['storan', 'storage', 'dokumen', 'documents'],
        'kes.aktif' => ['kes mahkamah', 'mahkamah', 'court', 'litigation', 'perundangan'],
        'dsh.hasil_kos' => ['hasil', 'kos', 'revenue', 'cost', 'pendapatan'],
        'pjk.kadar_kutipan' => ['kutipan', 'collection', 'kadar kutipan'],
        'pjk.tunggakan' => ['tunggakan sewa', 'rent arrears', 'tunggakan pajakan'],
        'pjk.kadar_penghunian' => ['penghunian', 'occupancy', 'premis kosong'],
        'pjk.kontrak_hampir_tamat' => ['pajakan', 'sewaan', 'lease', 'leases', 'kontrak'],
        'cki.jumlah_cukai_tahunan' => ['cukai', 'tax', 'cukai tanah'],
        'cki.tertunggak' => ['cukai tertunggak', 'tunggakan cukai', 'tax arrears', 'arrears', 'tunggakan'],
        'cki.penalti' => ['penalti', 'denda', 'penalty'],
        'cki.peratus_bayar_tepat_masa' => ['tepat masa', 'on time'],
        'adu.aduan_terbuka' => ['aduan', 'complaint', 'complaints'],
        'adu.dalam_sla' => ['aduan sla'],
        'adu.purata_masa_selesai' => ['masa selesai', 'resolution time'],
    ];

    public function engine(): string
    {
        return 'peraturan';
    }

    public function insights(User $user, array $kpis): array
    {
        $out = [];
        foreach ($kpis as $k) {
            if (! empty($k['ralat']) || $k['nilai'] === null) {
                continue;
            }
            $label = (string) $k['label'];
            $nilai = $this->fmt($k['nilai'], (string) $k['unit']);
            if (($k['lampu'] ?? null) === 'merah') {
                $out[] = ['tahap' => 'kritikal', 'kpi' => $k['key'], 'teks' => __('dsh::dsh.insight.merah', ['kpi' => $label, 'nilai' => $nilai])];
            } elseif (($k['lampu'] ?? null) === 'kuning') {
                $out[] = ['tahap' => 'perhatian', 'kpi' => $k['key'], 'teks' => __('dsh::dsh.insight.kuning', ['kpi' => $label, 'nilai' => $nilai])];
            }

            $trend = array_values(array_filter((array) ($k['trend'] ?? []), fn ($t) => is_numeric($t['nilai'] ?? null)));
            if (count($trend) >= 2) {
                $last = (float) $trend[count($trend) - 1]['nilai'];
                $prev = (float) $trend[count($trend) - 2]['nilai'];
                if ($prev != 0.0) {
                    $change = ($last - $prev) * 100 / abs($prev);
                    if (abs($change) >= 10) {
                        $out[] = ['tahap' => 'maklumat', 'kpi' => $k['key'], 'teks' => __($change > 0 ? 'dsh::dsh.insight.naik' : 'dsh::dsh.insight.turun', [
                            'kpi' => $label, 'peratus' => number_format(abs($change), 1), 'tempoh' => (string) $trend[count($trend) - 1]['tempoh'],
                        ])];
                    }
                }
            }

            $slices = array_values(array_filter((array) ($k['pecahan'] ?? []), fn ($p) => is_numeric($p['nilai'] ?? null) && (float) $p['nilai'] > 0));
            $sum = array_sum(array_map(fn ($p) => (float) $p['nilai'], $slices));
            if (count($slices) >= 3 && $sum > 0 && ($k['unit'] ?? '') !== '%') {
                usort($slices, fn ($a, $b) => (float) $b['nilai'] <=> (float) $a['nilai']);
                $share = (float) $slices[0]['nilai'] * 100 / $sum;
                if ($share >= 50) {
                    $out[] = ['tahap' => 'maklumat', 'kpi' => $k['key'], 'teks' => __('dsh::dsh.insight.dominan', [
                        'pecahan' => (string) $slices[0]['label'], 'peratus' => number_format($share, 0), 'kpi' => $label,
                    ])];
                }
            }
        }

        $rank = ['kritikal' => 0, 'perhatian' => 1, 'maklumat' => 2, 'baik' => 3];
        usort($out, fn ($a, $b) => $rank[$a['tahap']] <=> $rank[$b['tahap']]);
        $out = array_slice($out, 0, 8);

        if ($out === [] && $kpis !== []) {
            $out[] = ['tahap' => 'baik', 'teks' => __('dsh::dsh.insight.tiada_isu')];
        }

        return $out;
    }

    public function interpret(User $user, string $prompt, array $catalogue, array $districts): array
    {
        $text = ' '.$this->normalise($prompt).' ';
        $penapis = [];

        if (preg_match('/\b(19|20)\d{2}\b/', $text, $m)) {
            $penapis['tahun'] = (int) $m[0];
        }
        foreach ($districts as $kod => $nama) {
            $n = $this->normalise($nama);
            if ($n !== '' && str_contains($text, ' '.$n.' ')) {
                $penapis['kod_daerah'] = (string) $kod;
                break;
            }
        }

        $layout = null;
        if (preg_match('/\b(tambah|letak|papar|add|show|pin)\b.*\b(widget|dashboard|papan)\b/', $text)) {
            $layout = 'tambah';
        } elseif (preg_match('/\b(buang|sembunyi|sorok|keluarkan|remove|hide)\b/', $text)) {
            $layout = 'buang';
        }

        $scores = [];
        foreach ($catalogue as $item) {
            $score = 0;
            foreach (self::SINONIM[$item['key']] ?? [] as $word) {
                if (preg_match('/\b'.preg_quote($this->normalise($word), '/').'/', $text)) {
                    $score += 2 + substr_count($word, ' ') * 2;
                }
            }
            foreach (preg_split('/\s+/', $this->normalise($item['label'])) ?: [] as $token) {
                if (mb_strlen($token) >= 4 && str_contains($text, ' '.$token)) {
                    $score++;
                }
            }
            if ($score > 0) {
                $scores[$item['key']] = $score;
            }
        }
        arsort($scores);
        $many = preg_match('/\b(dan|serta|and|vs|berbanding)\b/', $text) === 1;
        $kpi = array_slice(array_keys($scores), 0, $many ? 3 : 1);

        if ($kpi === []) {
            return ['niat' => 'tidak_difahami', 'kpi' => [], 'penapis' => $penapis];
        }

        if ($layout !== null) {
            return ['niat' => 'susun_atur', 'kpi' => $kpi, 'penapis' => $penapis, 'susun_atur' => ['tindakan' => $layout, 'kpi' => $kpi]];
        }

        $niat = 'nilai';
        $visual = 'stat';
        if (preg_match('/\b(trend|aliran|bulanan|tahunan|monthly|yearly|over time|sejarah)\b/', $text)) {
            [$niat, $visual] = ['trend', 'line'];
        } elseif (preg_match('/\b(senarai|list|rekod|records|siapa|which)\b/', $text)) {
            [$niat, $visual] = ['senarai', 'jadual'];
        } elseif (preg_match('/\b(ikut|mengikut|pecahan|by|breakdown|setiap|per)\b/', $text)) {
            $visual = 'bar';
        } elseif (preg_match('/\b(peratus|bahagian|share|carta pai|pie)\b/', $text)) {
            $visual = 'pie';
        }

        return ['niat' => $niat, 'kpi' => $kpi, 'penapis' => $penapis, 'visual' => $visual];
    }

    private function normalise(string $s): string
    {
        $s = Str::lower(Str::ascii($s));

        return trim((string) preg_replace('/[^a-z0-9 ]+/', ' ', $s));
    }

    private function fmt(int|float $v, string $unit): string
    {
        $n = number_format((float) $v, floor((float) $v) == (float) $v ? 0 : 1, '.', ',');

        return match ($unit) {
            'RM' => 'RM '.$n,
            '%' => $n.'%',
            default => trim($n.' '.$unit),
        };
    }
}
