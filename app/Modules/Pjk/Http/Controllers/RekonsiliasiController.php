<?php

namespace App\Modules\Pjk\Http\Controllers;

use App\Models\User;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\RekonsiliasiLarian;
use App\Modules\Pjk\Models\SisihanRekonsiliasi;
use App\Modules\Pjk\Services\RekonsiliasiService;
use App\Modules\Pjk\Support\Kod;
use App\Modules\Pjk\Support\Wang;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** SRS-PJK-18/21: reconciliation runs, uploaded statements and the discrepancy work list. */
class RekonsiliasiController
{
    public function __construct(private RekonsiliasiService $service) {}

    public function index(Request $request): JsonResponse
    {
        $runs = RekonsiliasiLarian::query()->orderByDesc('id')->limit(100)->get();
        $visible = Kontrak::query()->select('kontrak_pajakan.id');
        $diffs = SisihanRekonsiliasi::query()->with('larian')
            ->where(fn ($q) => $q->whereIn('kontrak_id', $visible)->orWhereNull('kontrak_id'))
            ->when($request->filled('larian'), fn ($q) => $q->where('larian_id', $request->query('larian')))
            ->when($request->filled('status_tindakan'), fn ($q) => $q->where('status_tindakan', $request->query('status_tindakan')), fn ($q) => $request->filled('larian') ? $q : $q->where('status_tindakan', '!=', 'selesai'))
            ->orderByDesc('id')->limit(500)->get();

        return response()->json([
            'larian' => $runs->map(fn (RekonsiliasiLarian $r) => ['id' => $r->id, 'sumber' => $r->sumber, 'tarikh' => $r->getAttribute('tarikh')?->format('Y-m-d'), 'bil_padan' => $r->bil_padan, 'bil_sisihan' => $r->bil_sisihan, 'status' => $r->status, 'mesej' => $r->getAttribute('mesej')])->all(),
            'sisihan' => $diffs->map(fn (SisihanRekonsiliasi $s) => [
                'id' => $s->id, 'larian_id' => $s->larian_id, 'sumber' => $s->larian->sumber, 'tarikh' => $s->larian->getAttribute('tarikh')?->format('Y-m-d'),
                'jenis' => $s->jenis, 'jenis_nama' => Kod::JENIS_SISIHAN[$s->jenis] ?? $s->jenis, 'rujukan' => $s->rujukan, 'kontrak_id' => $s->kontrak_id,
                'amaun_sistem' => $s->amaun_sistem !== null ? Wang::rm($s->amaun_sistem) : null, 'amaun_luar' => $s->amaun_luar !== null ? Wang::rm($s->amaun_luar) : null,
                'status_tindakan' => $s->status_tindakan, 'catatan' => $s->catatan,
            ])->all(),
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = Validator::make($request->all(), [
            'sumber' => ['required', Rule::in(RekonsiliasiService::SUMBER)],
            'tarikh' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ])->validate();
        $run = $this->service->run($data['sumber'], CarbonImmutable::parse($data['tarikh']), null, $user);

        return response()->json(['data' => ['id' => $run->id, 'status' => $run->status, 'bil_padan' => $run->bil_padan, 'bil_sisihan' => $run->bil_sisihan, 'mesej' => $run->getAttribute('mesej')]], 201);
    }

    /** Upload a settlement / statement file (CSV) and reconcile against it (acceptance #4). */
    public function upload(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = Validator::make($request->all(), [
            'sumber' => ['required', Rule::in(RekonsiliasiService::SUMBER)],
            'tarikh' => ['required', 'date_format:Y-m-d'],
            'fail' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ], [], ['fail' => 'fail penyelesaian'])->validate();
        $date = CarbonImmutable::parse($data['tarikh']);
        $file = $this->service->upload($data['sumber'], $date, $request->file('fail'), $user);
        $run = $this->service->run($data['sumber'], $date, $file, $user);

        return response()->json(['data' => ['id' => $run->id, 'status' => $run->status, 'bil_padan' => $run->bil_padan, 'bil_sisihan' => $run->bil_sisihan]], 201);
    }

    public function resolve(Request $request, SisihanRekonsiliasi $sisihan): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($sisihan->kontrak_id !== null) {
            Kontrak::query()->findOrFail($sisihan->kontrak_id);
        }
        $data = Validator::make($request->all(), [
            'status_tindakan' => ['required', Rule::in(['dalam_tindakan', 'selesai'])],
            'catatan' => ['required', 'string', 'min:5', 'max:2000'],
        ], [], ['catatan' => 'catatan tindakan'])->validate();
        $sisihan->forceFill($data + ['id_pegawai' => $user->id])->save();

        return response()->json(['message' => __('pjk::pjk.mesej.disimpan')]);
    }
}
