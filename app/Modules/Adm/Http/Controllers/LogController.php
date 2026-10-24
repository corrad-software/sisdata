<?php

namespace App\Modules\Adm\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Adm\Models\LogIntegrasi;
use App\Modules\Adm\Models\LogRalat;
use App\Modules\Aud\Contracts\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SRS-ADM-11 error log viewer, SRS-ADM-14 log management (view, search,
 * export by period) for application and integration logs. Viewing and
 * exporting logs is itself audited (security control in SRS Modul 17).
 */
class LogController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function errors(Request $request): JsonResponse
    {
        $filters = $this->errorFilters($request);
        $this->audit->record('lihat', 'adm', 'adm_log_ralat', null, meta: ['tapisan' => $filters]);

        return response()->json($this->errorQuery($filters)
            ->select(['id', 'id_korelasi', 'tarikh_masa', 'aras_keterukan', 'saluran', 'modul', 'kod_ralat', 'mesej', 'id_pengguna', 'alamat_ip'])
            ->paginate(50));
    }

    public function error(LogRalat $log): JsonResponse
    {
        $this->audit->record('lihat', 'adm', 'adm_log_ralat', $log->id);

        return response()->json(['data' => $log]);
    }

    public function exportErrors(Request $request): StreamedResponse
    {
        $filters = $this->errorFilters($request);
        $this->audit->record('eksport', 'adm', 'adm_log_ralat', null, meta: ['tapisan' => $filters, 'format' => 'csv']);

        return $this->csv('log-ralat-'.now()->format('Ymd-His').'.csv',
            ['id', 'tarikh_masa', 'aras_keterukan', 'saluran', 'modul', 'kod_ralat', 'id_korelasi', 'id_pengguna', 'alamat_ip', 'mesej'],
            $this->errorQuery($filters));
    }

    public function integrations(Request $request): JsonResponse
    {
        $filters = $this->integrationFilters($request);
        $this->audit->record('lihat', 'adm', 'adm_log_integrasi', null, meta: ['tapisan' => $filters]);

        return response()->json($this->integrationQuery($filters)->paginate(50));
    }

    public function exportIntegrations(Request $request): StreamedResponse
    {
        $filters = $this->integrationFilters($request);
        $this->audit->record('eksport', 'adm', 'adm_log_integrasi', null, meta: ['tapisan' => $filters, 'format' => 'csv']);

        return $this->csv('log-integrasi-'.now()->format('Ymd-His').'.csv',
            ['id', 'tarikh_masa', 'kod_integrasi', 'arah', 'operasi', 'berjaya', 'status_http', 'tempoh_ms', 'bilangan_rekod', 'id_korelasi', 'mesej'],
            $this->integrationQuery($filters));
    }

    /** @return array<string, mixed> */
    private function errorFilters(Request $request): array
    {
        return $request->validate([
            'dari' => ['nullable', 'date'],
            'hingga' => ['nullable', 'date'],
            'modul' => ['nullable', 'string', 'max:20'],
            'aras' => ['nullable', 'array'],
            'aras.*' => ['in:debug,info,notice,warning,error,critical,alert,emergency'],
            'kod_ralat' => ['nullable', 'string', 'max:120'],
            'id_pengguna' => ['nullable', 'integer'],
            'id_korelasi' => ['nullable', 'string', 'max:64'],
            'saluran' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:200'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Builder<LogRalat>
     */
    private function errorQuery(array $f): Builder
    {
        return LogRalat::query()
            ->when($f['dari'] ?? null, fn ($q, $d) => $q->where('tarikh_masa', '>=', $d))
            ->when($f['hingga'] ?? null, fn ($q, $d) => $q->where('tarikh_masa', '<=', strlen($d) <= 10 ? $d.' 23:59:59' : $d))
            ->when($f['modul'] ?? null, fn ($q, $m) => $q->where('modul', $m))
            ->when($f['aras'] ?? null, fn ($q, $a) => $q->whereIn('aras_keterukan', $a))
            ->when($f['kod_ralat'] ?? null, fn ($q, $k) => $q->where('kod_ralat', 'ilike', addcslashes($k, '%_\\').'%'))
            ->when($f['id_pengguna'] ?? null, fn ($q, $u) => $q->where('id_pengguna', $u))
            ->when($f['id_korelasi'] ?? null, fn ($q, $c) => $q->where('id_korelasi', $c))
            ->when($f['saluran'] ?? null, fn ($q, $s) => $q->where('saluran', $s))
            ->when($f['q'] ?? null, fn ($q, $s) => $q->where('mesej', 'ilike', '%'.addcslashes($s, '%_\\').'%'))
            ->orderByDesc('tarikh_masa')->orderByDesc('id');
    }

    /** @return array<string, mixed> */
    private function integrationFilters(Request $request): array
    {
        return $request->validate([
            'dari' => ['nullable', 'date'],
            'hingga' => ['nullable', 'date'],
            'kod_integrasi' => ['nullable', 'string', 'max:60'],
            'berjaya' => ['nullable', 'boolean'],
            'id_korelasi' => ['nullable', 'string', 'max:64'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Builder<LogIntegrasi>
     */
    private function integrationQuery(array $f): Builder
    {
        return LogIntegrasi::query()
            ->when($f['dari'] ?? null, fn ($q, $d) => $q->where('tarikh_masa', '>=', $d))
            ->when($f['hingga'] ?? null, fn ($q, $d) => $q->where('tarikh_masa', '<=', strlen($d) <= 10 ? $d.' 23:59:59' : $d))
            ->when($f['kod_integrasi'] ?? null, fn ($q, $k) => $q->where('kod_integrasi', $k))
            ->when(isset($f['berjaya']), fn ($q) => $q->where('berjaya', filter_var($f['berjaya'], FILTER_VALIDATE_BOOLEAN)))
            ->when($f['id_korelasi'] ?? null, fn ($q, $c) => $q->where('id_korelasi', $c))
            ->orderByDesc('tarikh_masa')->orderByDesc('id');
    }

    /**
     * @param  list<string>  $columns
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    private function csv(string $filename, array $columns, Builder $query): StreamedResponse
    {
        return response()->streamDownload(function () use ($columns, $query) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $columns, escape: '\\');
            $query->limit(50000)->get($columns)->each(function ($row) use ($out, $columns) {
                $values = [];
                foreach ($columns as $c) {
                    $v = $row->getAttribute($c);
                    $values[] = $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : (is_bool($v) ? ($v ? '1' : '0') : (string) $v);
                }
                fputcsv($out, $values, escape: '\\');
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
