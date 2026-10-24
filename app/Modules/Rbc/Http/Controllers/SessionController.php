<?php

namespace App\Modules\Rbc\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Rbc\Models\Sesi;
use App\Modules\Rbc\Services\SecurityPolicy;
use App\Modules\Rbc\Services\SessionManager;
use App\Modules\Rbc\Support\Presenters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** SRS-RBC-18/19: own session timer, admin view & force-terminate. */
class SessionController extends Controller
{
    public function __construct(private SessionManager $sessions, private SecurityPolicy $policy) {}

    /** Remaining idle time — polled by the frontend warning; does not extend the session. */
    public function status(Request $request): JsonResponse
    {
        $sesi = $request->attributes->get('rbc.sesi');

        return response()->json(['data' => [
            'baki_saat' => $sesi instanceof Sesi ? $this->sessions->remainingSeconds($sesi) : null,
            'tamat_minit' => $this->policy->int('sesi_tamat_minit'),
            'amaran_saat' => $this->policy->int('sesi_amaran_saat'),
        ]]);
    }

    /** "Teruskan sesi": any non-passive request touches the session; this one exists for the button. */
    public function extend(Request $request): JsonResponse
    {
        return $this->status($request);
    }

    public function index(Request $request): JsonResponse
    {
        $current = $request->attributes->get('rbc.sesi');
        $cutoff = now()->subSeconds($this->sessions->idleTimeoutSeconds());

        $query = Sesi::query()->with('user:id,name,email')
            ->when($request->boolean('sejarah'),
                fn ($q) => $q->whereNotNull('tarikh_tamat')->latest('tarikh_tamat'),
                fn ($q) => $q->aktif()->where('tarikh_aktiviti_akhir', '>=', $cutoff)->latest('tarikh_aktiviti_akhir'))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.mb_strtolower($request->string('search')->toString()).'%';
                $q->whereHas('user', fn ($u) => $u->whereRaw('lower(name) like ?', [$term])->orWhereRaw('lower(email) like ?', [$term]));
            });

        $page = $query->paginate(20);

        return response()->json([
            'data' => collect($page->items())->map(fn (Sesi $s) => Presenters::session($s, $current instanceof Sesi ? $current->id : null)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage()],
        ]);
    }

    public function destroy(Request $request, Sesi $sesi): JsonResponse
    {
        $this->sessions->terminate($sesi, SessionManager::PAKSA, $request->user());

        return response()->json(['data' => Presenters::session($sesi->refresh()->load('user')), 'message' => __('rbc::rbc.sesi.ditamatkan', ['n' => 1])]);
    }
}
