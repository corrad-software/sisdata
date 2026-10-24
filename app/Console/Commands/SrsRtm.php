<?php

namespace App\Console\Commands;

use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Generates the requirements traceability matrix (SRS Bab 19.6) from test
 * names instead of by hand: any test method named test_SRS_<MOD>_<NN>_… is
 * evidence for requirement SRS-<MOD>-<NN>. Runs PHPUnit with a JUnit log to
 * get pass/fail per test.
 */
class SrsRtm extends Command
{
    protected $signature = 'srs:rtm {--output=docs/RTM.md} {--no-run : Only list mapped tests, do not run PHPUnit}';

    protected $description = 'Build the SRS requirement → test → result matrix';

    public function handle(): int
    {
        $junit = storage_path('framework/testing/rtm-junit.xml');
        $results = [];

        if (! $this->option('no-run')) {
            File::ensureDirectoryExists(dirname($junit));
            $this->info('Running test suite…');
            // Laravel putenv()s .env into this process; a child inherits it and
            // phpunit.xml's <env> never overrides an existing variable — so the
            // suite would run with the dev DB/queue/cache settings. Drop them.
            $envFile = base_path('.env');
            $dotenvKeys = is_file($envFile) ? array_keys(Dotenv::parse((string) file_get_contents($envFile))) : [];

            Process::timeout(1800)->path(base_path())
                ->env(array_fill_keys($dotenvKeys, false))
                ->run([PHP_BINARY, 'vendor/bin/phpunit', '--log-junit', $junit]);
            $results = $this->parseJunit($junit);
        }

        $rows = [];
        foreach (File::allFiles(base_path('tests')) as $file) {
            if (! preg_match_all('/function\s+(test_SRS_([A-Z]{3})_(\d{2})\w*)\s*\(/', $file->getContents(), $m, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($m as [, $method, $mod, $num]) {
                $rows["SRS-{$mod}-{$num}"][] = [
                    'test' => str_replace(base_path().'/', '', $file->getPathname()).'::'.$method,
                    'status' => $results[$method] ?? ($this->option('no-run') ? '—' : 'not run'),
                ];
            }
        }

        $catalog = $this->catalog();
        $codes = array_unique([...array_keys($catalog), ...array_keys($rows)]);
        sort($codes);

        $byModule = [];
        foreach ($codes as $code) {
            $byModule[substr($code, 4, 3)][] = $code;
        }

        $md = "# RTM — SISDATA\n\n_Dijana oleh `php artisan srs:rtm` pada ".now()->toDateTimeString().". Jangan sunting dengan tangan._\n\n";
        $md .= "| Modul | Keperluan | Ada ujian | Liputan |\n| --- | --- | --- | --- |\n";
        foreach ($byModule as $module => $moduleCodes) {
            $covered = count(array_filter($moduleCodes, fn ($c) => isset($rows[$c])));
            $md .= sprintf("| %s | %d | %d | %d%% |\n", $module, count($moduleCodes), $covered, intdiv($covered * 100, count($moduleCodes)));
        }

        $md .= "\n## Butiran\n\n| Keperluan | Keutamaan | Ujian | Status |\n| --- | --- | --- | --- |\n";
        foreach ($codes as $code) {
            $priority = $catalog[$code] ?? '?';
            foreach ($rows[$code] ?? [['test' => '—', 'status' => 'tiada ujian']] as $t) {
                $test = $t['test'] === '—' ? '—' : "`{$t['test']}`";
                $md .= "| {$code} | {$priority} | {$test} | {$t['status']} |\n";
            }
        }
        $md .= "\n**".count($rows).' daripada '.count($codes)." keperluan** mempunyai sekurang-kurangnya satu ujian.\n";

        File::put(base_path($this->option('output')), $md);
        $this->info(count($rows).' requirements mapped → '.$this->option('output'));

        return self::SUCCESS;
    }

    /**
     * Requirement code → priority, from docs/srs/requirements.csv (extracted from the SRS).
     *
     * @return array<string, string>
     */
    private function catalog(): array
    {
        $path = base_path('docs/srs/requirements.csv');
        if (! is_file($path)) {
            return [];
        }

        $out = [];
        $handle = fopen($path, 'r');
        fgetcsv($handle, escape: '');
        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $out[$row[0]] = $row[2] ?? '';
        }
        fclose($handle);

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function parseJunit(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $out = [];
        foreach (simplexml_load_file($path)->xpath('//testcase') as $case) {
            $name = preg_replace('/\s.*$/', '', (string) $case['name']);
            $out[$name] = match (true) {
                isset($case->failure), isset($case->error) => 'GAGAL',
                isset($case->skipped) => 'langkau',
                default => 'LULUS',
            };
        }

        return $out;
    }
}
