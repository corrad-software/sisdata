<?php

namespace App\Modules\Wfe\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Wfe\Models\Pemangku;
use App\Modules\Wfe\Support\WfeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * SRS-WFE-21: an approver names a pemangku for a date range; new tasks are
 * routed to the pemangku and both identities are recorded. Administrators
 * (wfe.pemangku.kemas_kini) may set one on behalf of any approver.
 */
class PemangkuController extends Controller
{
    public function __construct(private AccessControl $access, private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $all = $request->boolean('semua') && $this->access->allows($user, 'wfe.pemangku.kemas_kini');

        $rows = Pemangku::query()->with(['pengguna:id,name,email', 'pemangku:id,name,email'])
            ->when(! $all, fn ($q) => $q->where(fn ($w) => $w->where('id_pengguna', $user->id)->orWhere('id_pemangku', $user->id)))
            ->orderByDesc('tarikh_mula')->limit(200)->get()
            ->map(fn (Pemangku $p) => [
                'id' => $p->id,
                'pengguna' => ['id' => $p->id_pengguna, 'nama' => $p->pengguna->name],
                'pemangku' => ['id' => $p->id_pemangku, 'nama' => $p->pemangku->name],
                'tarikh_mula' => $p->tarikh_mula->toDateString(),
                'tarikh_tamat' => $p->tarikh_tamat->toDateString(),
                'sebab' => $p->sebab,
                'dibatalkan' => $p->dibatalkan_pada !== null,
                'berkuat_kuasa' => $p->dibatalkan_pada === null && $p->tarikh_mula->toDateString() <= now('Asia/Kuala_Lumpur')->toDateString()
                    && $p->tarikh_tamat->toDateString() >= now('Asia/Kuala_Lumpur')->toDateString(),
            ]);

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'id_pengguna' => ['nullable', 'integer', 'exists:users,id'],
            'id_pemangku' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'tarikh_mula' => ['required', 'date_format:Y-m-d'],
            'tarikh_tamat' => ['required', 'date_format:Y-m-d', 'after_or_equal:tarikh_mula'],
            'sebab' => ['nullable', 'string', 'max:255'],
        ]);

        $principal = (int) ($data['id_pengguna'] ?? $user->id);
        if ($principal !== $user->id && ! $this->access->allows($user, 'wfe.pemangku.kemas_kini')) {
            throw new WfeException(__('wfe::wfe.error.tiada_kebenaran'), 'tiada_kebenaran', 403);
        }
        if ($principal === (int) $data['id_pemangku']) {
            throw new WfeException(__('wfe::wfe.error.pemangku_sendiri'), 'pemangku_sendiri', 422);
        }
        $overlap = Pemangku::query()->where('id_pengguna', $principal)->whereNull('dibatalkan_pada')
            ->whereDate('tarikh_mula', '<=', $data['tarikh_tamat'])->whereDate('tarikh_tamat', '>=', $data['tarikh_mula'])->exists();
        if ($overlap) {
            throw new WfeException(__('wfe::wfe.error.pemangku_bertindih'), 'pemangku_bertindih', 422);
        }

        $delegation = Pemangku::create([
            'id_pengguna' => $principal,
            'id_pemangku' => $data['id_pemangku'],
            'tarikh_mula' => $data['tarikh_mula'],
            'tarikh_tamat' => $data['tarikh_tamat'],
            'sebab' => $data['sebab'] ?? null,
            'dicipta_oleh' => $user->id,
        ]);
        $this->audit->record('cipta_pemangku', 'wfe', 'wf_pemangku', $delegation->id, meta: [
            'pengguna' => $principal, 'pemangku' => (int) $data['id_pemangku'], 'dari' => $data['tarikh_mula'], 'hingga' => $data['tarikh_tamat'],
        ]);

        return response()->json(['message' => __('wfe::wfe.message.pemangku_disimpan'), 'data' => ['id' => $delegation->id]], 201);
    }

    public function destroy(Request $request, Pemangku $pemangku): JsonResponse
    {
        $user = $request->user();
        if ($pemangku->id_pengguna !== $user->id && ! $this->access->allows($user, 'wfe.pemangku.kemas_kini')) {
            throw new WfeException(__('wfe::wfe.error.tiada_kebenaran'), 'tiada_kebenaran', 403);
        }
        $pemangku->forceFill(['dibatalkan_pada' => now()])->save();

        return response()->json(['message' => __('wfe::wfe.message.pemangku_dibatalkan')]);
    }

    /** Minimal user lookup for choosing a pemangku or an individual approver. */
    public function users(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $rows = User::query()->where('is_active', true)
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'ilike', '%'.$term.'%')->orWhere('email', 'ilike', '%'.$term.'%')))
            ->orderBy('name')->limit(20)->get(['id', 'name', 'email']);

        return response()->json(['data' => $rows]);
    }
}
