<?php

namespace App\Modules\Rbc\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Rbc\Exports\TabularExport;
use App\Modules\Rbc\Models\LogLogMasuk;
use App\Modules\Rbc\Support\Presenters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** SRS-RBC-21 / acceptance #5: searchable and exportable login log. */
class LoginLogController extends Controller
{
    private const EXPORT_LIMIT = 10000;

    public function index(Request $request): JsonResponse
    {
        $page = $this->query($request)->with('user:id,name,email')->latest('id')->paginate(min(100, max(10, $request->integer('per_page', 25))));

        return response()->json([
            'data' => collect($page->items())->map(fn (LogLogMasuk $log) => Presenters::loginLog($log)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage()],
        ]);
    }

    public function export(Request $request, AuditLogger $audit): Response
    {
        $format = $request->query('format') === 'pdf' ? 'pdf' : 'xlsx';
        $logs = $this->query($request)->with('user:id,name,email')->latest('id')->limit(self::EXPORT_LIMIT)->get();

        $rows = $logs->map(fn (LogLogMasuk $log) => [
            $log->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i:s'),
            $log->user->name ?? '-',
            $log->pengecam,
            $log->kaedah === 'sso' ? 'SSO MAIS' : __('rbc::rbc.log_masuk.kaedah_kata_laluan'),
            $log->berjaya ? __('rbc::rbc.ya') : __('rbc::rbc.tidak'),
            $log->sebab ? __('rbc::rbc.sebab.'.$log->sebab) : '',
            $log->alamat_ip,
            $log->lokasi,
            trim($log->pelayar.' / '.$log->platform.' / '.$log->peranti, ' /'),
            $log->mencurigakan ? __('rbc::rbc.ya') : '',
        ])->all();

        $audit->record('eksport', 'rbc', 'log_log_masuk', null, meta: ['format' => $format, 'bilangan' => count($rows), 'penapis' => $request->query()]);

        return TabularExport::download($format, 'log-log-masuk-'.now()->format('Ymd-His'), __('rbc::rbc.log_masuk.tajuk'), [[
            'tajuk' => __('rbc::rbc.log_masuk.tajuk'),
            'lajur' => array_values(__('rbc::rbc.log_masuk.lajur')),
            'baris' => $rows,
        ]], $this->generatedBy($request));
    }

    /** @return Builder<LogLogMasuk> */
    private function query(Request $request): Builder
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'alamat_ip' => ['nullable', 'string', 'max:45'],
            'berjaya' => ['nullable', 'in:0,1,true,false'],
            'mencurigakan' => ['nullable', 'in:0,1,true,false'],
            'kaedah' => ['nullable', 'in:kata_laluan,sso'],
            'user_id' => ['nullable', 'integer'],
            'dari' => ['nullable', 'date'],
            'hingga' => ['nullable', 'date'],
        ]);

        return LogLogMasuk::query()
            ->when($data['search'] ?? null, function (Builder $q, string $search) {
                $term = '%'.mb_strtolower($search).'%';
                $q->where(fn (Builder $w) => $w->whereRaw('lower(pengecam) like ?', [$term])
                    ->orWhereHas('user', fn (Builder $u) => $u->whereRaw('lower(name) like ?', [$term])));
            })
            ->when($data['alamat_ip'] ?? null, fn (Builder $q, string $ip) => $q->where('alamat_ip', $ip))
            ->when(isset($data['berjaya']), fn (Builder $q) => $q->where('berjaya', filter_var($data['berjaya'], FILTER_VALIDATE_BOOL)))
            ->when(isset($data['mencurigakan']), fn (Builder $q) => $q->where('mencurigakan', filter_var($data['mencurigakan'], FILTER_VALIDATE_BOOL)))
            ->when($data['kaedah'] ?? null, fn (Builder $q, string $k) => $q->where('kaedah', $k))
            ->when($data['user_id'] ?? null, fn (Builder $q, int $id) => $q->where('user_id', $id))
            ->when($data['dari'] ?? null, fn (Builder $q, string $d) => $q->where('created_at', '>=', $d))
            ->when($data['hingga'] ?? null, fn (Builder $q, string $d) => $q->where('created_at', '<', now()->parse($d)->addDay()->startOfDay()));
    }

    private function generatedBy(Request $request): string
    {
        return __('rbc::rbc.dijana_oleh', ['nama' => $request->user()?->name, 'tarikh' => now()->format('d/m/Y H:i')]);
    }
}
