<?php

namespace App\Modules\Lap\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Lap\Jobs\JanaLaporan;
use App\Modules\Lap\Models\DefinisiLaporan;
use App\Modules\Lap\Models\KatalogLaporan;
use App\Modules\Lap\Models\LogPenjanaan;
use App\Modules\Lap\Support\SetData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Report generation, export and logging (SRS-LAP-04/07/10/11). Every run —
 * on screen, export, background or scheduled — goes through here so it is
 * authorised for the reader, executed under the reader's scope, written to
 * lap_log_penjanaan and recorded in the audit trail with its parameters and
 * record count.
 *
 * A "source" is one of:
 *   ['katalog' => 'KEW-01']      standard / composite report
 *   ['definisi' => '<uuid>']     saved ad hoc definition (own or shared)
 *   ['adhoc' => [...definition]] unsaved builder / AI-prompt definition
 */
class Penjana
{
    public function __construct(
        private Enjin $enjin,
        private Katalog $katalog,
        private Eksport $eksport,
        private AuditLogger $audit,
        private Parameters $parameters,
    ) {}

    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $params  runtime: tempoh{dari,hingga,pratetap}, penapis[], kod_daerah
     * @return array{tajuk: string, kod_laporan: string|null, id_definisi: string|null, bahagian: list<array{kod: string|null, tajuk: string, def: array<string, mixed>}>, komposit: bool}
     */
    public function resolve(User $user, array $source, array $params = []): array
    {
        if (isset($source['katalog'])) {
            $this->katalog->sync();
            $entry = KatalogLaporan::query()->where('kod_laporan', (string) $source['katalog'])->first();
            if (! $entry || $entry->jenis === 'modul') {
                throw new NotFoundHttpException(__('lap::lap.ralat.tiada_laporan'));
            }
            if (! $this->katalog->canOpen($user, $entry)) {
                $this->denied($user, $entry->kod_laporan, $entry->nama);
            }
            $parts = $entry->jenis === 'komposit' ? $this->katalog->sections($user, $entry) : [$entry];

            return [
                'tajuk' => $entry->nama, 'kod_laporan' => $entry->kod_laporan, 'id_definisi' => null, 'komposit' => $entry->jenis === 'komposit',
                'bahagian' => array_map(fn (KatalogLaporan $e) => ['kod' => $e->kod_laporan, 'tajuk' => $e->nama, 'def' => $this->enjin->normalise($this->apply((array) $e->definisi, $params), $user)], $parts),
            ];
        }
        if (isset($source['definisi'])) {
            $def = DefinisiLaporan::query()->find((string) $source['definisi']);
            if (! $def) {
                throw new NotFoundHttpException(__('lap::lap.ralat.tiada_laporan'));
            }
            if (! $this->katalog->canOpenDefinition($user, $def)) {
                $this->denied($user, null, $def->nama);
            }

            return ['tajuk' => $def->nama, 'kod_laporan' => $def->kod_laporan, 'id_definisi' => $def->id, 'komposit' => false,
                'bahagian' => [['kod' => null, 'tajuk' => $def->nama, 'def' => $this->enjin->normalise($this->apply([...$def->pertanyaan, ...(array) $def->parameter_lalai], $params), $user)]]];
        }
        $raw = (array) ($source['adhoc'] ?? []);
        if (! $this->katalog->canUseSet($user, (string) ($raw['set'] ?? '')) && SetData::get((string) ($raw['set'] ?? '')) !== null) {
            $this->denied($user, null, (string) $raw['set']);
        }
        $def = $this->enjin->normalise($this->apply($raw, $params), $user);

        return ['tajuk' => (string) ($source['tajuk'] ?? __('lap::lap.laporan_ad_hoc', ['set' => SetData::get($def['set'])['label']])), 'kod_laporan' => null, 'id_definisi' => null, 'komposit' => false,
            'bahagian' => [['kod' => null, 'tajuk' => (string) ($source['tajuk'] ?? SetData::get($def['set'])['label']), 'def' => $def]]];
    }

    /**
     * Runtime parameters over a stored definition.
     *
     * @param  array<string, mixed>  $def
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function apply(array $def, array $params): array
    {
        if (! empty($params['tempoh']) && is_array($params['tempoh'])) {
            $t = array_filter((array) $params['tempoh'], fn ($v) => $v !== null && $v !== '');
            if ($t !== []) {
                $def['tempoh'] = ['medan' => $def['tempoh']['medan'] ?? null, ...$t];
                if (isset($t['dari']) || isset($t['hingga'])) {
                    unset($def['tempoh']['pratetap']);
                }
                $def['tempoh'] = array_filter($def['tempoh'], fn ($v) => $v !== null);
            }
        }
        $extra = array_values(array_filter((array) ($params['penapis'] ?? []), 'is_array'));
        $set = SetData::get((string) ($def['set'] ?? ''));
        foreach (['kod_daerah', 'kod_mukim'] as $k) {
            if (! empty($params[$k]) && is_string($params[$k]) && isset($set['medan'][$k])) {
                $extra[] = ['medan' => $k, 'op' => 'sama', 'nilai' => $params[$k]];
            }
        }
        if ($extra !== []) {
            $def['penapis'] = [...(array) ($def['penapis'] ?? []), ...$extra];
        }

        return $def;
    }

    private function denied(User $user, ?string $kod, string $nama): never
    {
        $this->audit->record('jana', 'lap', 'lap_katalog_laporan', $kod, 'ditolak', __('lap::lap.ralat.tiada_akses'), meta: ['laporan' => $nama]);

        throw new AccessDeniedHttpException(__('lap::lap.ralat.tiada_akses'));
    }

    // ---------------------------------------------------------------- on screen

    /**
     * Run for display and log it.
     *
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $opts  halaman, per_halaman, drill
     * @return array<string, mixed>
     */
    public function papar(User $user, array $source, array $params = [], array $opts = []): array
    {
        $r = $this->resolve($user, $source, $params);
        $sections = [];
        $count = 0;
        foreach ($r['bahagian'] as $i => $b) {
            $res = $this->enjin->run($user, $b['def'], $r['komposit'] ? [] : $opts);
            $count += $res['mod'] === 'rekod' ? count($res['baris']) : (int) $res['bilangan_rekod'];
            $sections[] = ['kod' => $b['kod'], 'tajuk' => $b['tajuk'], 'definisi' => $b['def'], 'hasil' => $res];
        }
        $log = $this->log($user, $r, $source, $params, 'paparan', 'segerak', $count, 'berjaya', $this->level($sections), $opts);
        $this->audit->record('jana', 'lap', 'lap_log_penjanaan', $log->id, meta: $this->auditMeta($r, $params, 'paparan', $count, $opts));

        return ['tajuk' => $r['tajuk'], 'kod_laporan' => $r['kod_laporan'], 'id_definisi' => $r['id_definisi'], 'komposit' => $r['komposit'], 'bahagian' => $sections, 'id_log' => $log->id,
            'label_cadangan' => $this->isAnalytic($r) ? __('lap::lap.label_cadangan') : null];
    }

    // ---------------------------------------------------------------- export

    /**
     * Export now, or queue when large (SRS-LAP-07). Returns ['segerak', body, mime, filename, log] or ['latar', log].
     *
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $params
     * @return array{0: string, 1: LogPenjanaan, 2?: string, 3?: string, 4?: string}
     */
    public function eksport(User $user, array $source, array $params, string $format, bool $background = false): array
    {
        $r = $this->resolve($user, $source, $params);
        if (! $background) {
            $threshold = (int) $this->parameters->get('lap.had_segerak', 5000);
            foreach ($r['bahagian'] as $b) {
                if ($this->isRecordMode($b['def'])) {
                    $probe = $this->enjin->run($user, $b['def'], ['per_halaman' => 1]);
                    if ($probe['bilangan_rekod'] > $threshold) {
                        $background = true;
                    }
                }
            }
        }
        if ($background) {
            $log = $this->log($user, $r, $source, $params, $format, 'latar', 0, 'beratur', 'dalaman');
            dispatch(new JanaLaporan($log->id));

            return ['latar', $log->refresh()];
        }
        [$body, $count, $level] = $this->produce($user, $r, $format);
        $log = $this->log($user, $r, $source, $params, $format, 'segerak', $count, 'berjaya', $level);
        $log->forceFill(['saiz_bait' => strlen($body), 'checksum' => hash('sha256', $body), 'tamat_pada' => now()])->save();
        $this->audit->record('eksport', 'lap', 'lap_log_penjanaan', $log->id, meta: $this->auditMeta($r, $params, $format, $count) + ['checksum' => $log->checksum]);

        return ['segerak', $log, $body, Eksport::MIME[$format], $this->filename($r['tajuk'], $format)];
    }

    /**
     * Generate a file for a queued / scheduled log and store it (job + scheduler).
     */
    public function toFile(LogPenjanaan $log, User $user): LogPenjanaan
    {
        $log->forceFill(['status' => 'dalam_proses'])->save();
        $started = microtime(true);
        try {
            $p = (array) $log->parameter_digunakan;
            $r = $this->resolve($user, (array) ($p['sumber'] ?? []), (array) ($p['parameter'] ?? []));
            [$body, $count, $level] = $this->produce($user, $r, $log->format_eksport);
            $path = 'lap/'.Carbon::now()->format('Y/m').'/'.$log->id.'.'.$log->format_eksport;
            Storage::disk((string) config('lap.disk', 'local'))->put($path, $body);
            $log->forceFill([
                'status' => 'berjaya', 'bilangan_rekod' => $count, 'klasifikasi' => $level, 'laluan_fail' => $path, 'saiz_bait' => strlen($body),
                'checksum' => hash('sha256', $body), 'tamat_pada' => now(), 'tempoh_ms' => (int) round((microtime(true) - $started) * 1000),
            ])->save();
            $this->audit->record('eksport', 'lap', 'lap_log_penjanaan', $log->id, meta: $this->auditMeta($r, (array) ($p['parameter'] ?? []), $log->format_eksport, $count) + ['mod' => $log->mod, 'checksum' => $log->checksum]);
        } catch (AccessDeniedHttpException $e) {
            $log->forceFill(['status' => 'ditolak', 'ralat' => $e->getMessage(), 'tamat_pada' => now()])->save();
        } catch (Throwable $e) {
            report($e);
            $log->forceFill(['status' => 'gagal', 'ralat' => __('lap::lap.ralat.penjanaan_gagal'), 'tamat_pada' => now()])->save();
            $this->audit->record('eksport', 'lap', 'lap_log_penjanaan', $log->id, 'gagal', __('lap::lap.ralat.penjanaan_gagal'));
        }

        return $log;
    }

    /**
     * Create a queued log for a scheduled run (recipient = owner of the log).
     *
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $params
     */
    public function queuedLog(User $user, array $source, array $params, string $format, string $mode, string $tajuk, ?int $jadualId = null): LogPenjanaan
    {
        return LogPenjanaan::query()->create([
            'id_definisi' => $source['definisi'] ?? null, 'kod_laporan' => $source['katalog'] ?? null, 'id_jadual' => $jadualId, 'tajuk' => $tajuk,
            'parameter_digunakan' => ['sumber' => $source, 'parameter' => $params], 'format_eksport' => $format, 'mod' => $mode,
            'id_pengguna' => $user->id, 'cap_masa' => now(), 'status' => 'beratur', 'klasifikasi' => 'dalaman',
        ]);
    }

    /** @return array{0: string, 1: int, 2: string} body, record count, highest classification */
    private function produce(User $user, array $r, string $format): array
    {
        $sections = [];
        $metaLines = null;
        $count = 0;
        $results = [];
        foreach ($r['bahagian'] as $b) {
            $res = $this->enjin->run($user, $b['def'], ['semua' => true]);
            $results[] = ['hasil' => $res];
            $count += $res['mod'] === 'rekod' ? count($res['baris']) : (int) $res['bilangan_rekod'];
            $section = $this->eksport->section($b['tajuk'], $res);
            if ($r['komposit']) {
                $section['catatan'] = implode(' · ', array_slice($this->eksport->meta($user, $res), 1, 3));
            }
            $sections[] = $section;
            $metaLines ??= $this->eksport->meta($user, $res);
        }
        $level = $this->level($results);
        $meta = $metaLines ?? [];
        if ($this->isAnalytic($r)) {
            $meta[] = __('lap::lap.label_cadangan');
        }
        $body = $this->eksport->build($format, $r['tajuk'], $meta, $sections, $this->eksport->watermark($user), $level);

        return [$body, $count, $level];
    }

    /** @param list<array{hasil: array<string, mixed>}> $sections */
    private function level(array $sections): string
    {
        $order = ['awam' => 1, 'dalaman' => 2, 'sulit' => 3, 'rahsia' => 4];
        $max = 'awam';
        foreach ($sections as $s) {
            $l = $s['hasil']['meta']['klasifikasi_tertinggi'] ?? 'dalaman';
            if (($order[$l] ?? 2) > $order[$max]) {
                $max = $l;
            }
        }

        return $max;
    }

    private function isRecordMode(array $def): bool
    {
        return $def['kumpulan'] === [] && $def['hierarki'] === [];
    }

    private function isAnalytic(array $r): bool
    {
        foreach ($r['bahagian'] as $b) {
            if ($b['def']['set'] === 'analitik') {
                return true;
            }
        }

        return false;
    }

    private function log(User $user, array $r, array $source, array $params, string $format, string $mode, int $count, string $status, string $level, array $opts = []): LogPenjanaan
    {
        return LogPenjanaan::query()->create([
            'id_definisi' => $r['id_definisi'], 'kod_laporan' => $r['kod_laporan'], 'tajuk' => mb_substr($r['tajuk'], 0, 250),
            'set_data' => $r['bahagian'][0]['def']['set'] ?? null,
            'parameter_digunakan' => ['sumber' => $source, 'parameter' => $params, 'pilihan' => array_intersect_key($opts, array_flip(['halaman', 'drill']))],
            'format_eksport' => $format, 'mod' => $mode, 'bilangan_rekod' => $count, 'id_pengguna' => $user->id, 'cap_masa' => now(), 'status' => $status,
            'klasifikasi' => $level,
        ]);
    }

    /** @return array<string, mixed> */
    private function auditMeta(array $r, array $params, string $format, int $count, array $opts = []): array
    {
        return [
            'laporan' => $r['tajuk'], 'kod_laporan' => $r['kod_laporan'], 'id_definisi' => $r['id_definisi'], 'format' => $format,
            'bilangan_rekod' => $count, 'parameter' => $params, 'definisi' => array_map(fn ($b) => array_diff_key($b['def'], ['medan_disembunyikan' => 1]), $r['bahagian']),
            'drill' => $opts['drill'] ?? null,
        ];
    }

    public function filename(string $title, string $format): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($title)), '_');

        return 'laporan_'.mb_substr($slug, 0, 60).'_'.Carbon::now()->format('Ymd_His').'.'.$format;
    }
}
