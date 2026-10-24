<?php

namespace App\Modules\Adm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Adm\Models\KodRujukan;
use App\Modules\Adm\Services\KodRujukanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/** SRS-ADM-02 reference codes + SRS-ADM-05 bulk import. */
class KodRujukanController extends Controller
{
    public function __construct(private KodRujukanService $codes) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'jenis' => ['nullable', 'string', 'max:60'],
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:aktif,tidak_aktif'],
            'sah_pada' => ['nullable', 'date'],
        ]);

        $q = $request->input('q');
        $page = KodRujukan::query()
            ->when($request->input('jenis'), fn ($b, $j) => $b->where('jenis_kod', $j))
            ->when($request->input('status'), fn ($b, $s) => $b->where('status', $s))
            ->when($q, fn ($b) => $b->where(fn ($w) => $w->where('kod', 'ilike', '%'.addcslashes((string) $q, '%_\\').'%')
                ->orWhere('keterangan', 'ilike', '%'.addcslashes((string) $q, '%_\\').'%')))
            ->when($request->input('sah_pada'), fn ($b, $d) => $b->whereDate('tarikh_mula', '<=', $d)
                ->where(fn ($w) => $w->whereNull('tarikh_tamat')->orWhereDate('tarikh_tamat', '>=', $d)))
            ->orderBy('jenis_kod')->orderBy('susunan')->orderBy('kod')
            ->paginate((int) min(100, max(10, (int) $request->input('per_page', 25))));

        return response()->json($page);
    }

    public function types(): JsonResponse
    {
        $counts = KodRujukan::query()->selectRaw('jenis_kod, count(*) as jumlah, sum(case when status = ? then 1 else 0 end) as aktif', [KodRujukan::AKTIF])
            ->groupBy('jenis_kod')->get()->keyBy('jenis_kod');

        $types = array_values(array_unique([...KodRujukanService::JENIS_LAZIM, ...$counts->keys()->all()]));
        $labels = (array) __('adm::adm.kod.types');

        return response()->json(['data' => array_map(fn (string $t) => [
            'jenis_kod' => $t,
            'label' => $labels[$t] ?? $t,
            'induk' => KodRujukanService::INDUK[$t] ?? null,
            'jumlah' => (int) ($counts[$t]->jumlah ?? 0),
            'aktif' => (int) ($counts[$t]->aktif ?? 0),
        ], $types)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->codes->validate($request->all());

        return response()->json(['message' => __('adm::adm.saved'), 'data' => $this->codes->create($data, $request->user())], 201);
    }

    public function update(Request $request, KodRujukan $kod): JsonResponse
    {
        $data = $this->codes->validate($request->except('jenis_kod'), $kod);

        return response()->json(['message' => __('adm::adm.saved'), 'data' => $this->codes->update($kod, $data, $request->user())]);
    }

    /** "Delete" = deactivate; the code stays for history (SRS-ADM-02). */
    public function deactivate(Request $request, KodRujukan $kod): JsonResponse
    {
        $data = $request->validate([
            'tarikh_tamat' => ['nullable', 'date', 'after_or_equal:'.$kod->tarikh_mula->toDateString()],
            'justifikasi' => ['nullable', 'string', 'max:1000'],
        ]);

        return response()->json(['message' => __('adm::adm.kod.deactivated'), 'data' => $this->codes->deactivate($kod, $data['tarikh_tamat'] ?? null, $data['justifikasi'] ?? null, $request->user())]);
    }

    public function activate(Request $request, KodRujukan $kod): JsonResponse
    {
        $data = $request->validate(['justifikasi' => ['nullable', 'string', 'max:1000']]);

        return response()->json(['message' => __('adm::adm.saved'), 'data' => $this->codes->activate($kod, $data['justifikasi'] ?? null, $request->user())]);
    }

    public function history(KodRujukan $kod): JsonResponse
    {
        return response()->json(['data' => [
            'semasa' => $kod,
            'versi' => $kod->sejarah()->orderByDesc('versi')->get(),
        ]]);
    }

    /**
     * Bulk import: JSON {jenis_kod, rekod: [...]} or a CSV file (`fail`) with
     * header kod,keterangan[,kod_induk,susunan,tarikh_mula,tarikh_tamat].
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'jenis_kod' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/'],
            'kemas_kini_sedia_ada' => ['nullable', 'boolean'],
            'rekod' => ['required_without:fail', 'array', 'max:5000'],
            'rekod.*' => ['array'],
            'fail' => ['required_without:rekod', 'file', 'mimes:csv,txt', 'max:5120'],
        ], [], (array) __('adm::adm.attributes'));

        $rows = $request->hasFile('fail') ? $this->csv($request->file('fail')) : array_values($request->input('rekod', []));

        $report = $this->codes->import($request->input('jenis_kod'), $rows, $request->boolean('kemas_kini_sedia_ada'), $request->user());

        return response()->json(['message' => __('adm::adm.kod.imported', ['diterima' => $report['diterima'] + $report['dikemas_kini'], 'ditolak' => $report['ditolak']]), 'data' => $report]);
    }

    /** @return list<array<string, string>> */
    private function csv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            throw ValidationException::withMessages(['fail' => __('adm::adm.kod.csv_unreadable')]);
        }

        $header = fgetcsv($handle, escape: '\\');
        if (! $header) {
            throw ValidationException::withMessages(['fail' => __('adm::adm.kod.csv_unreadable')]);
        }
        $header = array_map(fn ($h) => strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);

        $rows = [];
        while (($line = fgetcsv($handle, escape: '\\')) !== false) {
            if ($line === [null]) {
                continue;
            }
            $rows[] = array_combine($header, array_pad(array_slice($line, 0, count($header)), count($header), ''));
            if (count($rows) > 5000) {
                break;
            }
        }
        fclose($handle);

        return $rows;
    }
}
