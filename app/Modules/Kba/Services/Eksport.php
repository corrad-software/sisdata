<?php

namespace App\Modules\Kba\Services;

use App\Models\User;
use App\Modules\Kba\Models\Artikel;
use App\Modules\Kba\Models\PemetaanKebenaran;
use App\Modules\Kba\Models\SumberDokumen;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * SRS-KBA-20: the whole knowledge base — articles with every version,
 * indexed sources (re-assembled from their active segments, by section),
 * metadata, classification–role mapping and source links — as an open ZIP
 * of Markdown or HTML files readable without any vendor software. Content
 * the exporting user may not open is left out by the same Capaian filter.
 */
class Eksport
{
    public function __construct(private Capaian $capaian) {}

    /** @return array{path: string, nama: string, bilangan: int} */
    public function zip(User $user, string $format = 'markdown'): array
    {
        $html = $format === 'html';
        $ext = $html ? 'html' : 'md';
        $path = tempnam(sys_get_temp_dir(), 'kba-eksport-');
        if ($path === false) {
            throw new RuntimeException('Fail sementara tidak dapat dicipta.');
        }
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $count = 0;
        $index = ['# Eksport Pangkalan Pengetahuan SISDATA', '', 'Dijana: '.now()->toIso8601String().' · Hak milik MAIS (SRS-KBA-20)', '', '## Sumber diindeks', ''];

        $acc = $this->capaian->predicate($user);
        foreach (SumberDokumen::query()->orderBy('asal')->orderBy('tajuk')->get() as $s) {
            $segments = DB::select("SELECT s.* FROM kba_segmen s WHERE s.id_sumber = ? AND s.status = 'aktif' AND {$acc['sql']} ORDER BY s.urutan", [$s->id, ...$acc['bindings']]);
            if ($segments === []) {
                continue;
            }
            $md = ["# {$s->tajuk}", '', '| Medan | Nilai |', '| --- | --- |',
                '| Jenis | '.$s->jenis_dokumen.' |', '| Versi | '.$s->versiPaparan().' |', '| Asal | '.$s->asal.' |',
                '| Klasifikasi | '.$s->klasifikasi.' |', '| Peranan dibenarkan | '.(implode(', ', $s->senarai_peranan_dibenarkan) ?: '—').' |',
                '| Dokumen DMS | '.($s->id_dokumen_dms ?? '—').' |', '| Diindeks | '.($s->tarikh_indeks_akhir?->toIso8601String() ?? '—').' |', ''];
            $last = null;
            foreach ($segments as $seg) {
                if ($seg->laluan_seksyen !== $last && $seg->laluan_seksyen !== null) {
                    $md[] = '## '.$seg->laluan_seksyen;
                    $md[] = '';
                    $last = $seg->laluan_seksyen;
                }
                $md[] = $seg->kandungan_teks;
                $md[] = '';
            }
            $file = 'sumber/'.$s->id.'-'.Str::slug(Str::limit($s->tajuk, 60, '')).'.'.$ext;
            $zip->addFromString($file, $this->render(implode("\n", $md), $s->tajuk, $html));
            $index[] = "- [{$s->tajuk}]({$file}) — {$s->jenis_dokumen}, {$s->versiPaparan()}, {$s->klasifikasi}, ".count($segments).' segmen';
            $count++;
        }

        $index[] = '';
        $index[] = '## Artikel';
        $index[] = '';
        $levels = $this->capaian->levels($user);
        foreach (Artikel::query()->with('sejarahVersi')->whereIn('klasifikasi', $levels)->orderBy('id')->get() as $a) {
            $md = ["# {$a->tajuk}", '', '| Medan | Nilai |', '| --- | --- |',
                '| Status | '.$a->status.' |', '| Versi | '.$a->versi.' (diterbitkan: '.($a->versi_terbit ?? '—').') |', '| Kategori | '.($a->kategori ?? '—').' |',
                '| Modul | '.($a->modul ?? '—').' |', '| Klasifikasi | '.$a->klasifikasi.' |', '| Pemilik | '.($a->id_pemilik ?? '—').' |',
                '| Penyemak | '.($a->id_penyemak ?? '—').' |', '| Tarikh terbit | '.($a->tarikh_terbit?->toIso8601String() ?? '—').' |',
                '| Semakan akhir | '.($a->tarikh_semakan_akhir?->toDateString() ?? '—').' |', '| Semakan seterusnya | '.($a->tarikh_semakan_seterusnya?->toDateString() ?? '—').' |',
                '| Sumber asal | '.$a->sumber_asal.($a->id_tiket_asal ? ' (tiket '.$a->id_tiket_asal.')' : '').' |', '', $a->kandungan, '', '## Sejarah versi', ''];
            foreach ($a->sejarahVersi as $v) {
                $md[] = "### Versi {$v->nombor_versi} — {$v->cap_masa->toIso8601String()}";
                $md[] = '';
                $md[] = 'Sebab: '.($v->sebab_perubahan ?? '—');
                $md[] = '';
                $md[] = $v->kandungan;
                $md[] = '';
            }
            $file = 'artikel/'.$a->id.'-'.Str::slug(Str::limit($a->tajuk, 60, '')).'.'.$ext;
            $zip->addFromString($file, $this->render(implode("\n", $md), $a->tajuk, $html));
            $index[] = "- [{$a->tajuk}]({$file}) — {$a->status}, v{$a->versi}";
            $count++;
        }

        $map = ['# Pemetaan klasifikasi–peranan', '', '| Klasifikasi | Peranan | Capaian | Berkuat kuasa |', '| --- | --- | --- | --- |'];
        foreach (PemetaanKebenaran::query()->orderBy('klasifikasi')->orderBy('peranan')->get() as $p) {
            $map[] = "| {$p->klasifikasi} | {$p->peranan} | {$p->jenis_capaian} | {$p->tarikh_berkuat_kuasa->toDateString()} |";
        }
        $zip->addFromString('pemetaan-kebenaran.'.$ext, $this->render(implode("\n", $map), 'Pemetaan klasifikasi–peranan', $html));
        $zip->addFromString('INDEKS.'.$ext, $this->render(implode("\n", $index), 'Eksport Pangkalan Pengetahuan', $html));
        $zip->close();

        return ['path' => $path, 'nama' => 'kba-eksport-'.now()->format('Ymd-His').'-'.$format.'.zip', 'bilangan' => $count];
    }

    private function render(string $markdown, string $title, bool $html): string
    {
        if (! $html) {
            return $markdown."\n";
        }
        $body = Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
        $body = str_replace('.md)', '.html)', $body);

        return '<!doctype html><html lang="ms"><head><meta charset="utf-8"><title>'.e($title).'</title>'
            .'<style>body{font-family:system-ui,sans-serif;max-width:900px;margin:2rem auto;padding:0 1rem;line-height:1.5}table{border-collapse:collapse}td,th{border:1px solid #ccc;padding:4px 8px}pre{white-space:pre-wrap}</style>'
            .'</head><body>'.str_replace('.md"', '.html"', $body).'</body></html>';
    }
}
