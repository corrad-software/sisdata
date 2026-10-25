<?php

namespace App\Modules\Pro\Http\Controllers;

use App\Modules\Pro\Models\PemetaanImport;
use App\Modules\Pro\Models\SesiImport;
use App\Modules\Pro\Services\ImportService;
use App\Modules\Pro\Services\Pengeksport;
use App\Modules\Pro\Services\PeraturanKualiti;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** SRS-PRO-15..17, SRS-DAT-03: templates, mapping, preview, commit, report. */
class ImportController extends ProController
{
    public function __construct(private ImportService $import, private PeraturanKualiti $rules) {}

    /** Target fields with Malay labels, for the mapping step. */
    public function fields(string $entity): JsonResponse
    {
        abort_unless(isset(ImportService::FIELDS[$entity]), 404);

        return response()->json(['data' => array_map(fn ($f) => [
            'medan' => $f,
            'label' => $this->rules->label($f),
            'wajib' => in_array($f, ImportService::REQUIRED[$entity], true),
        ], ImportService::FIELDS[$entity])]);
    }

    /** Downloadable template per core entity (SRS-PRO-15). */
    public function template(Request $request, string $entity): Response
    {
        abort_unless(isset(ImportService::FIELDS[$entity]), 404);
        $format = (string) ($request->validate(['format' => ['nullable', Rule::in(['csv', 'xlsx'])]])['format'] ?? 'xlsx');
        $fields = ImportService::FIELDS[$entity];
        $example = $entity === 'lot_tanah'
            ? ['Lot 12345', '100803', '1008', '1.2500', 'hektar', 'PERTANIAN', 'Pertanian', 'Tanaman kekal', 'wakaf', 'aktif', 'dalaman', 'SUMBER-0001', 'contoh']
            : ['100803', 'Lot 12345', 'wkt', 'POLYGON((101.50 3.10, 101.51 3.10, 101.51 3.11, 101.50 3.11, 101.50 3.10))', '4326', 'juruukur', '2026-01-15', 'PA 12345'];

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($fields, $example) {
                $out = fopen('php://output', 'w');
                if ($out === false) {
                    return;
                }
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, $fields, escape: '');
                fputcsv($out, $example, escape: '');
                fclose($out);
            }, 'templat-'.$entity.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Data');
        $sheet->fromArray($fields, null, 'A1');
        $sheet->fromArray($example, null, 'A2', true);
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $guide = $book->createSheet();
        $guide->setTitle('Panduan');
        $guide->fromArray(['Medan', 'Label', 'Wajib'], null, 'A1');
        foreach ($fields as $i => $f) {
            $guide->fromArray([$f, $this->rules->label($f), in_array($f, ImportService::REQUIRED[$entity], true) ? 'Ya' : 'Tidak'], null, 'A'.($i + 2));
        }

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), 'templat-'.$entity.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function index(Request $request, AccessControl $access): JsonResponse
    {
        $user = $this->user($request);
        $page = SesiImport::query()
            ->when(! $access->allows($user, 'pro.kualiti.lihat'), fn ($q) => $q->where('id_pengguna', $user->id))
            ->orderByDesc('created_at')->paginate(min(200, max(1, $request->integer('per_page', 20))));

        return response()->json([
            'data' => collect($page->items())->map(fn (SesiImport $s) => $this->import->report($s)['ringkasan'])->values(),
            'meta' => $this->meta($page),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fail' => ['required', 'file', 'max:20480'],
            'entiti' => ['required', Rule::in(array_keys(ImportService::FIELDS))],
            'pemetaan' => ['nullable'],
            'id_pemetaan' => ['nullable', 'integer'],
        ], [], (array) __('pro::pro.medan'));

        $mapping = $this->mapping($data);
        /** @var UploadedFile $file */
        $file = $request->file('fail');
        $session = $this->import->upload($file, $data['entiti'], $this->user($request), $mapping);

        return response()->json(['data' => $this->payload($session)], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(['sah', 'ditolak', 'diabaikan', 'dikomit'])]])['status'] ?? null;

        return response()->json(['data' => $this->payload($this->session($request, $id), $status)]);
    }

    public function remap(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['pemetaan' => ['nullable'], 'id_pemetaan' => ['nullable', 'integer']]);
        $session = $this->session($request, $id);
        $mapping = $this->mapping($data) ?? $this->import->autoMapping((string) $session->entiti_sasaran, (array) $session->lajur_fail);

        return response()->json(['data' => $this->payload($this->import->validateSession($session, $mapping, $this->user($request)))]);
    }

    public function commit(Request $request, string $id): JsonResponse
    {
        $session = $this->import->commit($this->session($request, $id), $this->user($request));

        return response()->json(['message' => __('pro::pro.import.dikomit', ['n' => $session->bil_berjaya]), 'data' => $this->payload($session)]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $session = $this->session($request, $id);
        abort_if($session->status_sesi !== 'pratonton', 422, __('pro::pro.import.sudah_dikomit'));
        $session->update(['status_sesi' => 'dibatalkan']);

        return response()->json(['message' => __('pro::pro.import.dibatalkan')]);
    }

    /** SRS-PRO-17: exportable import report. */
    public function report(Request $request, string $id, Pengeksport $exporter): Response
    {
        $format = (string) $request->validate(['format' => ['required', Rule::in(Pengeksport::FORMATS)]])['format'];
        $report = $this->import->report($this->session($request, $id));
        $s = $report['ringkasan'];
        $summary = [
            ['no_baris' => '', 'label_medan' => 'Ringkasan', 'nilai_diberi' => '', 'sebab_penolakan' => sprintf('Fail %s · dibaca %d · berjaya %d · ditolak %d · diabaikan %d · oleh %s pada %s',
                $s['nama_fail'], $s['bil_baris_dibaca'], $s['bil_berjaya'], $s['bil_ditolak'], $s['bil_diabaikan'], $s['pengguna'] ?? '-', $s['tarikh_masa'])],
        ];

        if ($format === 'json') {
            return response()->json($report);
        }

        return $exporter->download([...$summary, ...$report['ditolak']], [
            'no_baris' => 'No. Baris', 'label_medan' => 'Medan', 'nilai_diberi' => 'Nilai Diberi', 'sebab_penolakan' => 'Sebab Penolakan',
        ], $format, 'laporan-import', (string) __('pro::pro.eksport.tajuk_import', ['fail' => $s['nama_fail']]));
    }

    public function mappings(Request $request): JsonResponse
    {
        $entity = $request->query('entiti');

        return response()->json(['data' => PemetaanImport::query()->when(is_string($entity), fn ($q) => $q->where('entiti_sasaran', $entity))->orderBy('nama')->get()]);
    }

    public function storeMapping(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entiti_sasaran' => ['required', Rule::in(array_keys(ImportService::FIELDS))],
            'nama' => ['required', 'string', 'max:100'],
            'pemetaan' => ['required', 'array'],
            'pemetaan.*' => ['string'],
        ]);
        $mapping = PemetaanImport::query()->updateOrCreate(
            ['entiti_sasaran' => $data['entiti_sasaran'], 'nama' => $data['nama']],
            ['pemetaan' => $data['pemetaan'], 'id_pencipta' => $this->user($request)->id],
        );

        return response()->json(['message' => __('pro::pro.import.pemetaan_disimpan'), 'data' => $mapping], 201);
    }

    private function session(Request $request, string $id): SesiImport
    {
        $session = SesiImport::query()->find($id);
        $user = $this->user($request);
        if ($session === null || ($session->id_pengguna !== $user->id && ! app(AccessControl::class)->allows($user, 'pro.kualiti.lihat'))) {
            throw new NotFoundHttpException(__('pro::pro.import.tiada'));
        }

        return $session;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    private function mapping(array $data): ?array
    {
        if (! empty($data['id_pemetaan'])) {
            return (array) PemetaanImport::query()->findOrFail($data['id_pemetaan'])->pemetaan;
        }
        $mapping = $data['pemetaan'] ?? null;
        if (is_string($mapping)) {
            $mapping = json_decode($mapping, true);
        }

        return is_array($mapping) && $mapping !== [] ? array_map('strval', $mapping) : null;
    }

    /** @return array<string, mixed> */
    private function payload(SesiImport $session, ?string $status = null): array
    {
        $report = $this->import->report($session);

        return [
            ...$report,
            'baris' => $this->import->previewRows($session, 500, $status),
            'medan_sasaran' => array_map(fn ($f) => ['medan' => $f, 'label' => $this->rules->label($f), 'wajib' => in_array($f, ImportService::REQUIRED[(string) $session->entiti_sasaran], true)], ImportService::FIELDS[(string) $session->entiti_sasaran]),
        ];
    }
}
