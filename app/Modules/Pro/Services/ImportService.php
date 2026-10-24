<?php

namespace App\Modules\Pro\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Pro\Models\SesiImport;
use App\Modules\Pro\Support\NoLot;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use Throwable;

/**
 * Bulk import (SRS-PRO-15..17, SRS-DAT-03), in two steps:
 *
 *  1. upload → the file is kept in DMS, columns are mapped (saved or
 *     automatic mapping), every row is validated with the SAME quality rules
 *     as the form (types, mandatory, reference codes, uniqueness, duplicates
 *     in the file and against existing records) and a preview is returned;
 *  2. commit → only rows that passed are written; the session report lists
 *     read / success / rejected / ignored counts and every rejected row with
 *     its row number and reason.
 */
class ImportService
{
    /** Target fields per entity, in template order. */
    public const FIELDS = [
        'lot_tanah' => ['no_lot', 'kod_mukim', 'kod_daerah', 'keluasan', 'unit_keluasan', 'kategori_guna_tanah', 'zoning', 'syarat_nyata', 'bahagian_pemilik', 'status_lot', 'klasifikasi', 'kunci_asal', 'tag'],
        'data_spatial' => ['kod_mukim', 'no_lot', 'format', 'geometri', 'srid', 'sumber_data', 'tarikh_perolehan_data', 'rujukan_fail_sumber'],
    ];

    public const REQUIRED = [
        'lot_tanah' => ['no_lot', 'kod_mukim', 'keluasan', 'bahagian_pemilik'],
        'data_spatial' => ['kod_mukim', 'no_lot', 'geometri', 'sumber_data'],
    ];

    /** Header aliases (normalised) → field. */
    private const ALIASES = [
        'no lot' => 'no_lot', 'nombor lot' => 'no_lot', 'lot' => 'no_lot',
        'mukim' => 'kod_mukim', 'kod mukim' => 'kod_mukim',
        'daerah' => 'kod_daerah', 'kod daerah' => 'kod_daerah',
        'luas' => 'keluasan', 'keluasan' => 'keluasan', 'unit' => 'unit_keluasan',
        'kategori' => 'kategori_guna_tanah', 'kategori tanah' => 'kategori_guna_tanah',
        'bahagian' => 'bahagian_pemilik', 'pemilik' => 'bahagian_pemilik',
        'status' => 'status_lot', 'id asal' => 'kunci_asal', 'kunci asal' => 'kunci_asal',
        'wkt' => 'geometri', 'geojson' => 'geometri', 'sumber' => 'sumber_data',
    ];

    public function __construct(
        private PeraturanKualiti $rules,
        private LotService $lots,
        private SpatialService $spatial,
        private LotQuery $lotQuery,
        private DocumentStore $documents,
        private PenghalaKualiti $router,
        private Parameters $parameters,
    ) {}

    /**
     * @param  array<string, string>|null  $mapping  file column header => target field
     */
    public function upload(UploadedFile $file, string $entity, User $user, ?array $mapping = null): SesiImport
    {
        if (! isset(self::FIELDS[$entity])) {
            throw ValidationException::withMessages(['entiti' => [__('pro::pro.import.entiti_tidak_sah')]]);
        }
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'xlsx', 'xls'], true)) {
            throw ValidationException::withMessages(['fail' => [__('pro::pro.import.format_tidak_sah')]]);
        }

        [$headers, $rows] = $this->read($file->getRealPath(), $ext);
        $max = (int) $this->parameters->get('pro.import_baris_maks', 5000);
        if (count($rows) > $max) {
            throw ValidationException::withMessages(['fail' => [__('pro::pro.import.terlalu_banyak', ['maks' => $max])]]);
        }

        $session = SesiImport::query()->create([
            'entiti_sasaran' => $entity,
            'nama_fail' => $file->getClientOriginalName(),
            'format_fail' => $ext,
            'id_pengguna' => $user->id,
            'lajur_fail' => $headers,
            'status_sesi' => 'pratonton',
        ]);

        // SRS-PRO-17: keep the original file for audit — through DMS only (SRS-GEN-04).
        try {
            $docId = $this->documents->store($file, 'sesi_import', $session->id, 'lain', $user, 'dalaman', ['tajuk' => 'Fail import '.$file->getClientOriginalName()]);
            $session->update(['lokasi_fail_asal' => $docId]);
        } catch (DocumentRejected $e) {
            $session->update(['catatan_fail_asal' => Str::limit(__('pro::pro.import.fail_asal_ditolak', ['sebab' => $e->getMessage()]), 250)]);
        } catch (Throwable $e) {
            report($e);
            $session->update(['catatan_fail_asal' => Str::limit(__('pro::pro.import.fail_asal_gagal'), 250)]);
        }

        $now = now();
        foreach (array_chunk($rows, 500, true) as $chunk) {
            DB::table('baris_import')->insert(array_map(fn ($row, $no) => [
                'id_sesi' => $session->id, 'no_baris' => $no, 'data' => json_encode(['asal' => $row]), 'status' => 'sah',
                'created_at' => $now, 'updated_at' => $now,
            ], $chunk, array_keys($chunk)));
        }

        return $this->validateSession($session, $mapping ?? $this->autoMapping($entity, $headers), $user);
    }

    /**
     * (Re)map and validate every row of a preview session.
     *
     * @param  array<string, string>  $mapping
     */
    public function validateSession(SesiImport $session, array $mapping, User $user): SesiImport
    {
        if ($session->status_sesi !== 'pratonton') {
            throw ValidationException::withMessages(['sesi' => [__('pro::pro.import.sudah_dikomit')]]);
        }
        $entity = (string) $session->entiti_sasaran;
        $fields = self::FIELDS[$entity];
        $mapping = array_filter($mapping, fn ($target) => in_array($target, $fields, true));

        $missing = array_diff(self::REQUIRED[$entity], array_values($mapping));
        if ($missing !== []) {
            throw ValidationException::withMessages(['pemetaan' => [__('pro::pro.import.pemetaan_tidak_lengkap', ['medan' => implode(', ', array_map(fn ($f) => $this->rules->label($f), $missing))])]]);
        }

        DB::table('ralat_import')->where('id_sesi', $session->id)->delete();
        $seen = [];
        $counts = ['sah' => 0, 'ditolak' => 0, 'diabaikan' => 0];
        $errorsToInsert = [];

        foreach (DB::table('baris_import')->where('id_sesi', $session->id)->orderBy('no_baris')->get() as $row) {
            $raw = (array) (json_decode((string) $row->data, true)['asal'] ?? []);
            $values = [];
            foreach ($mapping as $column => $target) {
                $values[$target] = isset($raw[$column]) ? (is_string($raw[$column]) ? trim($raw[$column]) : $raw[$column]) : null;
            }

            if (array_filter($values, fn ($v) => $v !== null && $v !== '') === []) {
                $status = 'diabaikan';
                $issues = [];
            } else {
                [$values, $issues] = $entity === 'lot_tanah'
                    ? $this->checkLotRow($values, $user, $seen, (int) $row->no_baris)
                    : $this->checkSpatialRow($values, $user, $seen, (int) $row->no_baris);
                $status = PeraturanKualiti::errors($issues) === [] ? 'sah' : 'ditolak';
            }

            $counts[$status]++;
            DB::table('baris_import')->where('id', $row->id)->update([
                'data' => json_encode(['asal' => $raw, 'nilai' => $values, 'amaran' => array_values(array_filter($issues, fn ($i) => $i['tahap'] !== 'ralat'))]),
                'status' => $status,
                'updated_at' => now(),
            ]);
            foreach (PeraturanKualiti::errors($issues) as $issue) {
                $errorsToInsert[] = [
                    'id_sesi' => $session->id,
                    'no_baris' => $row->no_baris,
                    'medan' => $issue['medan'],
                    'nilai_diberi' => is_scalar($issue['nilai'] ?? null) ? Str::limit((string) $issue['nilai'], 500) : null,
                    'kod_ralat' => $issue['kod'],
                    'sebab_penolakan' => Str::limit($issue['label'].': '.$issue['mesej'], 495),
                ];
            }
        }
        foreach (array_chunk($errorsToInsert, 500) as $chunk) {
            DB::table('ralat_import')->insert($chunk);
        }

        $session->update([
            'pemetaan' => $mapping,
            'bil_baris_dibaca' => array_sum($counts),
            'bil_sah' => $counts['sah'],
            'bil_ditolak' => $counts['ditolak'],
            'bil_diabaikan' => $counts['diabaikan'],
        ]);

        return $session->refresh();
    }

    /** Commit only the rows that passed (SRS-PRO-16). */
    public function commit(SesiImport $session, User $user): SesiImport
    {
        if ($session->status_sesi !== 'pratonton') {
            throw ValidationException::withMessages(['sesi' => [__('pro::pro.import.sudah_dikomit')]]);
        }

        $success = 0;
        $rejectedNow = 0;
        foreach (DB::table('baris_import')->where('id_sesi', $session->id)->where('status', 'sah')->orderBy('no_baris')->get() as $row) {
            $values = (array) (json_decode((string) $row->data, true)['nilai'] ?? []);
            try {
                $id = DB::transaction(fn () => $session->entiti_sasaran === 'lot_tanah'
                    ? $this->commitLot($values, $session, $user)
                    : $this->commitSpatial($values, $session, $user));
                DB::table('baris_import')->where('id', $row->id)->update(['status' => 'dikomit', 'id_rekod' => $id, 'updated_at' => now()]);
                $success++;
            } catch (ValidationException $e) {
                $rejectedNow++;
                DB::table('baris_import')->where('id', $row->id)->update(['status' => 'ditolak', 'updated_at' => now()]);
                foreach ($e->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        DB::table('ralat_import')->insert([
                            'id_sesi' => $session->id, 'no_baris' => $row->no_baris, 'medan' => $field,
                            'nilai_diberi' => is_scalar($values[$field] ?? null) ? (string) $values[$field] : null,
                            'kod_ralat' => 'komit', 'sebab_penolakan' => Str::limit($message, 495),
                        ]);
                    }
                }
            }
        }

        $session->update([
            'status_sesi' => 'dikomit',
            'tarikh_komit' => now(),
            'bil_berjaya' => $success,
            'bil_ditolak' => (int) $session->bil_ditolak + $rejectedNow,
            'bil_sah' => $success,
        ]);

        // SRS-PRO-12: rejected rows are a data-quality failure for the entity's steward.
        if ((int) $session->bil_ditolak > 0) {
            $this->router->route('sesi_import', (string) $session->id, [[
                'medan' => 'baris', 'label' => (string) __('pro::pro.import.tajuk_sesi', ['fail' => $session->nama_fail]),
                'kod' => 'ditolak', 'mesej' => (string) __('pro::pro.import.baris_ditolak', ['n' => $session->bil_ditolak]), 'tahap' => 'ralat',
            ]], null, 'import');
        }

        return $session->refresh();
    }

    /**
     * @return array{ringkasan: array<string, mixed>, ditolak: list<array<string, mixed>>}
     */
    public function report(SesiImport $session): array
    {
        $errors = DB::table('ralat_import')->where('id_sesi', $session->id)->orderBy('no_baris')->orderBy('id')->get();
        $user = $session->id_pengguna ? User::query()->find($session->id_pengguna) : null;

        return [
            'ringkasan' => [
                'id' => $session->id,
                'entiti_sasaran' => $session->entiti_sasaran,
                'nama_fail' => $session->nama_fail,
                'format_fail' => $session->format_fail,
                'pengguna' => $user?->name,
                'tarikh_masa' => $session->created_at?->toIso8601String(),
                'tarikh_komit' => $session->tarikh_komit?->toIso8601String(),
                'status_sesi' => $session->status_sesi,
                'bil_baris_dibaca' => (int) $session->bil_baris_dibaca,
                'bil_sah' => (int) $session->bil_sah,
                'bil_berjaya' => (int) $session->bil_berjaya,
                'bil_ditolak' => (int) $session->bil_ditolak,
                'bil_diabaikan' => (int) $session->bil_diabaikan,
                'lokasi_fail_asal' => $session->lokasi_fail_asal,
                'catatan_fail_asal' => $session->catatan_fail_asal,
                'pemetaan' => $session->pemetaan,
                'lajur_fail' => $session->lajur_fail,
            ],
            'ditolak' => $errors->map(fn ($e) => [
                'no_baris' => (int) $e->no_baris,
                'medan' => $e->medan,
                'label_medan' => $e->medan ? $this->rules->label((string) $e->medan) : null,
                'nilai_diberi' => $e->nilai_diberi,
                'kod_ralat' => $e->kod_ralat,
                'sebab_penolakan' => $e->sebab_penolakan,
            ])->values()->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function previewRows(SesiImport $session, int $limit = 200, ?string $status = null): array
    {
        $errors = DB::table('ralat_import')->where('id_sesi', $session->id)->get()->groupBy('no_baris');

        return DB::table('baris_import')->where('id_sesi', $session->id)
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->orderBy('no_baris')->limit($limit)->get()
            ->map(function ($r) use ($errors) {
                $data = (array) json_decode((string) $r->data, true);

                return [
                    'no_baris' => (int) $r->no_baris,
                    'status' => $r->status,
                    'nilai' => $data['nilai'] ?? [],
                    'ralat' => ($errors[$r->no_baris] ?? collect())->map(fn ($e) => ['medan' => $e->medan, 'sebab' => $e->sebab_penolakan])->values()->all(),
                    'amaran' => array_map(fn ($a) => $a['label'].': '.$a['mesej'], (array) ($data['amaran'] ?? [])),
                    'id_rekod' => $r->id_rekod,
                ];
            })->all();
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, string>
     */
    public function autoMapping(string $entity, array $headers): array
    {
        $fields = self::FIELDS[$entity];
        $mapping = [];
        foreach ($headers as $header) {
            $norm = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', ' ', $header)));
            $snake = str_replace(' ', '_', $norm);
            $target = in_array($snake, $fields, true) ? $snake : (self::ALIASES[$norm] ?? null);
            if ($target !== null && in_array($target, $fields, true) && ! in_array($target, $mapping, true)) {
                $mapping[$header] = $target;
            }
        }

        return $mapping;
    }

    /**
     * @return array{0: list<string>, 1: array<int, array<string, mixed>>} headers and rows keyed by spreadsheet row number
     */
    public function read(string $path, string $ext): array
    {
        try {
            $reader = IOFactory::createReader($ext === 'csv' ? 'Csv' : ($ext === 'xls' ? 'Xls' : 'Xlsx'));
            if ($reader instanceof Csv) {
                $reader->setInputEncoding('UTF-8');
                $reader->setDelimiter($this->sniffDelimiter($path));
            }
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($path)->getActiveSheet();
            $table = $sheet->toArray(null, true, false, true);
        } catch (Throwable) {
            throw ValidationException::withMessages(['fail' => [__('pro::pro.import.tidak_boleh_dibaca')]]);
        }

        $first = array_key_first($table);
        if ($first === null) {
            throw ValidationException::withMessages(['fail' => [__('pro::pro.import.kosong')]]);
        }
        $headerRow = array_map(fn ($h) => trim((string) $h), $table[$first]);
        $headerRow = array_filter($headerRow, fn ($h) => $h !== '');
        $headers = array_values($headerRow);

        $rows = [];
        foreach ($table as $no => $cells) {
            if ($no === $first) {
                continue;
            }
            $row = [];
            foreach ($headerRow as $col => $header) {
                $value = $cells[$col] ?? null;
                $row[$header] = is_float($value) && floor($value) === $value && abs($value) < 1e15 ? (string) (int) $value : $value;
            }
            $rows[(int) $no] = $row;
        }

        return [$headers, $rows];
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, int>  $seen
     * @return array{0: array<string, mixed>, 1: list<array{medan: string, label: string, kod: string, mesej: string, tahap: string, nilai: mixed}>}
     */
    private function checkLotRow(array $values, User $user, array &$seen, int $rowNo): array
    {
        $tag = $values['tag'] ?? null;
        unset($values['tag']);
        $kunci = $values['kunci_asal'] ?? null;
        unset($values['kunci_asal']);

        $data = $this->rules->prepareLot($values) + ['unit_keluasan' => 'm2', 'status_lot' => 'aktif', 'klasifikasi' => 'dalaman'];
        $issues = [...$this->rules->check('lot_tanah', $data), ...$this->lots->scopeIssues($user, $data)];

        if (! empty($data['no_lot']) && ! empty($data['kod_mukim'])) {
            $key = $data['kod_mukim'].'|'.NoLot::key((string) $data['no_lot']);
            if (isset($seen[$key])) {
                $issues[] = ['medan' => 'no_lot', 'label' => $this->rules->label('no_lot'), 'kod' => 'duplikasi_fail', 'mesej' => (string) __('pro::pro.import.duplikasi_fail', ['baris' => $seen[$key]]), 'tahap' => 'ralat', 'nilai' => $data['no_lot']];
            } else {
                $seen[$key] = $rowNo;
            }
        }
        if (is_string($tag) && $tag !== '') {
            $data['tag'] = array_values(array_filter(array_map('trim', explode(',', $tag))));
        }
        $data['kunci_asal'] = $kunci;

        return [$data, $issues];
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, int>  $seen
     * @return array{0: array<string, mixed>, 1: list<array{medan: string, label: string, kod: string, mesej: string, tahap: string, nilai: mixed}>}
     */
    private function checkSpatialRow(array $values, User $user, array &$seen, int $rowNo): array
    {
        $issues = [];
        $values['no_lot'] = isset($values['no_lot']) ? (NoLot::canonical((string) $values['no_lot']) ?? $values['no_lot']) : null;
        $values['format'] = strtolower((string) ($values['format'] ?? '')) ?: (str_starts_with(trim((string) ($values['geometri'] ?? '')), '{') ? 'geojson' : 'wkt');
        $values['srid'] = (int) ($values['srid'] ?? 4326) ?: 4326;
        $values['sumber_data'] = strtolower((string) ($values['sumber_data'] ?? ''));

        foreach (PeraturanKualiti::errors($this->rules->check('data_spatial', ['sumber_data' => $values['sumber_data'], 'srid_asal' => $values['srid']])) as $issue) {
            $issues[] = $issue;
        }

        $lot = $this->lotQuery->for($user)->where('kod_mukim', (string) ($values['kod_mukim'] ?? ''))->where('no_lot', (string) $values['no_lot'])->first();
        if ($lot === null) {
            $issues[] = ['medan' => 'no_lot', 'label' => $this->rules->label('no_lot'), 'kod' => 'rujukan', 'mesej' => (string) __('pro::pro.import.lot_tiada'), 'tahap' => 'ralat', 'nilai' => $values['no_lot']];
        } else {
            $values['lot_id'] = $lot->id;
            $key = $lot->id;
            if (isset($seen[$key])) {
                $issues[] = ['medan' => 'no_lot', 'label' => $this->rules->label('no_lot'), 'kod' => 'duplikasi_fail', 'mesej' => (string) __('pro::pro.import.duplikasi_fail', ['baris' => $seen[$key]]), 'tahap' => 'ralat', 'nilai' => $values['no_lot']];
            }
            $seen[$key] ??= $rowNo;
        }

        try {
            $this->spatial->parse($values['format'], (string) ($values['geometri'] ?? ''), $values['srid']);
        } catch (ValidationException $e) {
            $issues[] = ['medan' => 'geometri', 'label' => $this->rules->label('geometri'), 'kod' => 'format', 'mesej' => (string) collect($e->errors())->flatten()->first(), 'tahap' => 'ralat', 'nilai' => Str::limit((string) ($values['geometri'] ?? ''), 60)];
        }

        return [$values, $issues];
    }

    /** @param array<string, mixed> $values */
    private function commitLot(array $values, SesiImport $session, User $user): string
    {
        $kunci = $values['kunci_asal'] ?? null;
        unset($values['kunci_asal']);
        $lot = $this->lots->create($values, $user, [
            'id_sesi_import' => $session->id,
            'sumber_sistem' => 'import:'.$session->format_fail,
            'fail_asal' => $session->nama_fail,
            'kunci_asal' => $kunci !== null && $kunci !== '' ? (string) $kunci : null,
            'catatan_versi' => __('pro::pro.import.catatan_versi', ['fail' => $session->nama_fail]),
        ]);

        $warnings = array_values(array_filter($this->rules->checkLot($lot), fn ($i) => $i['tahap'] === 'amaran'));
        $this->router->route('lot_tanah', $lot->id, $warnings, $lot, 'import');

        return $lot->id;
    }

    /** @param array<string, mixed> $values */
    private function commitSpatial(array $values, SesiImport $session, User $user): string
    {
        $lot = LotTanah::query()->findOrFail((string) $values['lot_id']);
        $geometry = $this->spatial->parse((string) $values['format'], (string) $values['geometri'], (int) $values['srid']);
        $result = $this->spatial->store($lot, $geometry, [
            'sumber_data' => $values['sumber_data'],
            'rujukan_fail_sumber' => $values['rujukan_fail_sumber'] ?? $session->nama_fail,
            'tarikh_perolehan_data' => $values['tarikh_perolehan_data'] ?? null,
            'sumber_sistem' => 'import:'.$session->format_fail,
            'id_dokumen_sumber' => $session->lokasi_fail_asal,
        ], $user);

        return (string) $result['spatial']->id;
    }

    private function sniffDelimiter(string $path): string
    {
        $line = (string) fgets(fopen($path, 'r') ?: throw ValidationException::withMessages(['fail' => [__('pro::pro.import.tidak_boleh_dibaca')]]));

        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }
}
