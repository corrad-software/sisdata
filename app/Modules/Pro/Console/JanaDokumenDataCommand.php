<?php

namespace App\Modules\Pro\Console;

use App\Modules\Pro\Services\KamusDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * php artisan pro:jana-dokumen-data [--keluaran=docs/data] [--tanpa-ddl]
 *
 * SRS-PRO-03/04/05, SRS-DAT-07: generates the logical model (Mermaid ERD),
 * the physical model (tables, columns, constraints, indexes incl. spatial,
 * views, functions), the data dictionary and the executable DDL from the
 * LIVE schema of the connected database, so the documents never drift.
 */
class JanaDokumenDataCommand extends Command
{
    protected $signature = 'pro:jana-dokumen-data {--keluaran=docs/data : Output directory} {--tanpa-ddl : Skip pg_dump DDL}';

    protected $description = 'Generate ERD, physical model, data dictionary and DDL documents from the live schema';

    public function handle(KamusDataService $dictionary): int
    {
        $dir = base_path((string) $this->option('keluaran'));
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $db = (string) DB::connection()->getDatabaseName();
        $stamp = now()->format('d/m/Y H:i');
        $header = fn (string $title) => "# {$title}\n\n> Dijana secara automatik oleh `php artisan pro:jana-dokumen-data` daripada skema pangkalan data `{$db}` pada {$stamp}. Jangan sunting dengan tangan — jana semula.\n\n";

        file_put_contents($dir.'/erd.md', $header('Model Data Logikal (ERD) — SISDATA').$this->erd($dictionary));
        file_put_contents($dir.'/model-fizikal.md', $header('Model Data Fizikal — SISDATA').$this->physical($dictionary));
        file_put_contents($dir.'/kamus-data.md', $header('Kamus Data — SISDATA').$this->dictionary($dictionary));
        $this->components->info('Ditulis: erd.md, model-fizikal.md, kamus-data.md');

        if (! $this->option('tanpa-ddl')) {
            $ddl = $this->ddl();
            if ($ddl !== null) {
                file_put_contents($dir.'/ddl.sql', "-- DDL boleh laksana (pg_dump --schema-only) daripada {$db}, {$stamp}\n".$ddl);
                $this->components->info('Ditulis: ddl.sql');
            } else {
                $this->components->warn('pg_dump tidak tersedia / gagal — ddl.sql tidak dijana.');
            }
        }

        return self::SUCCESS;
    }

    private function erd(KamusDataService $dictionary): string
    {
        $tables = collect($dictionary->tables())->where('jenis', 'jadual')->keyBy('nama_jadual');
        $columns = collect($dictionary->columns())->groupBy('nama_jadual');
        $fks = collect($dictionary->foreignKeys())->filter(fn ($f) => isset($tables[$f->dari], $tables[$f->ke]));

        // One diagram for the property core (lot_tanah and everything around it), one for everything.
        $core = $fks->filter(fn ($f) => $f->ke === 'lot_tanah' || $f->dari === 'lot_tanah' || in_array($f->ke, ['data_spatial', 'kes_mahkamah', 'sesi_import', 'daftar_stewardship_data'], true))
            ->flatMap(fn ($f) => [$f->dari, $f->ke])->push('lot_tanah')->unique()->reject(fn ($t) => $t === 'users')->values();

        $out = "Entiti teras SRS §4.1 berpusat pada `lot_tanah` (ADR-01): setiap rekod satelit memegang `lot_id`. Kardinaliti dibaca daripada kunci asing: `||--o{` satu-ke-banyak, `|o--o{` FK boleh null, `||--||` FK unik.\n\n";
        $out .= "## 1. Teras hartanah (lot_tanah dan satelit)\n\n".$this->mermaid($core->all(), $columns, $fks, true)."\n";
        $out .= "## 2. Keseluruhan skema (semua modul)\n\nAtribut ditunjukkan bagi kunci sahaja supaya rajah kekal boleh dibaca.\n\n".$this->mermaid($tables->keys()->reject(fn ($t) => str_starts_with((string) $t, 'telescope'))->values()->all(), $columns, $fks, false)."\n";

        return $out;
    }

    /**
     * @param  list<string>  $tables
     * @param  Collection<string, Collection<int, array<string, mixed>>>  $columns
     * @param  Collection<int, object{dari: string, medan: string, ke: string, ke_medan: string, boleh_null: bool, unik: bool}>  $fks
     */
    private function mermaid(array $tables, $columns, $fks, bool $allAttributes): string
    {
        $set = array_flip($tables);
        $m = "```mermaid\nerDiagram\n";
        foreach ($fks as $f) {
            if (! isset($set[$f->dari], $set[$f->ke])) {
                continue;
            }
            $card = $f->unik ? '||--||' : ($f->boleh_null ? '|o--o{' : '||--o{');
            $m .= "    {$f->ke} {$card} {$f->dari} : \"{$f->medan}\"\n";
        }
        foreach ($tables as $t) {
            $cols = ($columns[$t] ?? collect())->filter(fn ($c) => $allAttributes || $c['kunci'] !== []);
            $m .= "    {$t} {\n";
            foreach ($cols as $c) {
                $type = preg_replace('/[^A-Za-z0-9_]/', '_', strtok((string) $c['jenis_data'], '(')) ?: 'text';
                $keys = implode(',', array_map(fn ($k) => $k === 'UQ' ? 'UK' : $k, (array) $c['kunci']));
                $m .= "        {$type} {$c['nama_medan']}".($keys !== '' ? " {$keys}" : '')."\n";
            }
            $m .= "    }\n";
        }

        return $m."```\n";
    }

    private function physical(KamusDataService $dictionary): string
    {
        $model = $dictionary->physicalModel();
        $columns = collect($dictionary->columns(null, true))->groupBy('nama_jadual');
        $constraints = collect($model['kekangan'])->groupBy('nama_jadual');
        $indexes = collect($model['indeks'])->groupBy('nama_jadual');
        $esc = fn ($v) => str_replace(['|', "\n"], ['\|', ' '], (string) $v);

        $out = '**Ringkasan:** '.count($model['jadual']).' jadual/view, '.count($model['kekangan']).' kekangan, '.count($model['indeks']).' indeks ('.collect($model['indeks'])->where('spatial', true)->count().' spatial GiST), '.count($model['view']).' view, '.count($model['fungsi'])." fungsi/prosedur.\n\n";
        $out .= "Jenis kekangan: p = primary key, f = foreign key, u = unique, c = check.\n\n## Jadual\n\n";

        foreach ($model['jadual'] as $t) {
            if ($t['jenis'] !== 'jadual') {
                continue;
            }
            $out .= "### `{$t['nama_jadual']}`".($t['nama_perniagaan'] ? " — {$t['nama_perniagaan']}" : '')."\n\n";
            if ($t['definisi']) {
                $out .= $esc($t['definisi'])."\n\n";
            }
            $out .= "| Medan | Jenis data | Saiz | Lalai | Null | Kunci |\n| --- | --- | --- | --- | --- | --- |\n";
            foreach ($columns[$t['nama_jadual']] ?? [] as $c) {
                $out .= '| `'.$c['nama_medan'].'` | '.$esc($c['jenis_data']).' | '.($c['saiz'] ?? '').($c['skala'] ? ','.$c['skala'] : '').' | '.$esc($c['nilai_lalai'] ?? '').' | '.($c['wajib'] ? 'TIDAK' : 'YA').' | '.implode(', ', (array) $c['kunci'])." |\n";
            }
            if (isset($constraints[$t['nama_jadual']])) {
                $out .= "\n**Kekangan**\n\n";
                foreach ($constraints[$t['nama_jadual']] as $k) {
                    $out .= "- `{$k->nama}` ({$k->jenis}): `".$esc($k->definisi)."`\n";
                }
            }
            if (isset($indexes[$t['nama_jadual']])) {
                $out .= "\n**Indeks**\n\n";
                foreach ($indexes[$t['nama_jadual']] as $i) {
                    $out .= '- `'.$i->nama.'`'.($i->spatial ? ' *(spatial)*' : '').': `'.$esc($i->definisi)."`\n";
                }
            }
            $out .= "\n";
        }

        $out .= "## View dan materialized view\n\n";
        foreach ($model['view'] as $v) {
            $out .= "### `{$v->nama}` ({$v->jenis})\n\n```sql\n".trim((string) $v->definisi)."\n```\n\n";
        }
        $out .= "## Fungsi dan stored procedure\n\n";
        foreach ($model['fungsi'] as $f) {
            $out .= "### `{$f->nama}({$f->argumen})` → {$f->hasil} ({$f->bahasa})\n\n```sql\n".trim((string) $f->definisi)."\n```\n\n";
        }

        return $out;
    }

    private function dictionary(KamusDataService $dictionary): string
    {
        $esc = fn ($v) => str_replace(['|', "\n"], ['\|', ' '], (string) $v);
        $out = "Setiap medan: nama perniagaan, nama teknikal, definisi, jenis data, format, nilai dibenarkan, status wajib, data owner, data steward dan klasifikasi (SRS-PRO-05). Metadata perniagaan disunting dalam sistem (Kamus Data → Sunting); medan tanpa metadata dikurasi memaparkan komen skema.\n\n";
        foreach (collect($dictionary->columns())->groupBy('nama_jadual') as $table => $cols) {
            $out .= "## `{$table}`\n\n| Nama teknikal | Nama perniagaan | Definisi | Jenis | Format | Nilai dibenarkan | Wajib | Data owner | Data steward | Klasifikasi |\n| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |\n";
            foreach ($cols as $c) {
                $out .= '| `'.$c['nama_medan'].'` | '.$esc($c['nama_perniagaan'] ?? '').' | '.$esc($c['definisi'] ?? '').' | '.$esc($c['jenis_data']).' | '.$esc($c['format'] ?? '').' | '.$esc($c['nilai_dibenarkan'] ?? '').' | '.($c['wajib'] ? 'Ya' : 'Tidak').' | '.$esc($c['data_owner'] ?? '').' | '.$esc($c['data_steward'] ?? '').' | '.$c['klasifikasi']." |\n";
            }
            $out .= "\n";
        }

        return $out;
    }

    private function ddl(): ?string
    {
        $c = config('database.connections.'.config('database.default'));
        $binary = (string) config('pro.pg_dump', is_file('/opt/homebrew/opt/postgresql@17/bin/pg_dump') ? '/opt/homebrew/opt/postgresql@17/bin/pg_dump' : 'pg_dump');
        $process = new Process([$binary, '--schema-only', '--no-owner', '--no-privileges', '-h', (string) $c['host'], '-p', (string) $c['port'], '-U', (string) $c['username'], (string) $c['database']], null, ['PGPASSWORD' => (string) $c['password']]);
        $process->setTimeout(120);
        $process->run();

        return $process->isSuccessful() ? $process->getOutput() : null;
    }
}
