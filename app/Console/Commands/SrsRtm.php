<?php

namespace App\Console\Commands;

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
            Process::timeout(1800)->path(base_path())
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

        ksort($rows);

        $md = "# RTM — SISDATA\n\n_Dijana oleh `php artisan srs:rtm` pada ".now()->toDateTimeString().". Jangan sunting dengan tangan._\n\n";
        $md .= "| Keperluan | Ujian | Status |\n| --- | --- | --- |\n";
        foreach ($rows as $code => $tests) {
            foreach ($tests as $t) {
                $md .= "| {$code} | `{$t['test']}` | {$t['status']} |\n";
            }
        }
        $md .= "\n**".count($rows)." keperluan** mempunyai sekurang-kurangnya satu ujian.\n";

        File::put(base_path($this->option('output')), $md);
        $this->info(count($rows).' requirements mapped → '.$this->option('output'));

        return self::SUCCESS;
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
