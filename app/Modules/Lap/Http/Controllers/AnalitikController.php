<?php

namespace App\Modules\Lap\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Lap\Models\CadanganAi;
use App\Modules\Lap\Models\HasilAnalitik;
use App\Modules\Lap\Models\InsightEksekutif;
use App\Modules\Lap\Services\Analitik;
use App\Modules\Lap\Services\Insight;
use App\Modules\Lap\Services\Skop;
use App\Modules\Lap\Support\SetData;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Analytics, recommendations and executive insight (SRS-LAP-15..21). Every
 * response carries the "system suggestion, not an official MAIS decision"
 * label (SRS-LAP-21). Results are row-scoped by daerah / mukim and by the
 * lot classification levels the user may see.
 */
class AnalitikController extends Controller
{
    public function __construct(private AccessControl $access, private Skop $skop) {}

    /** @param Builder<HasilAnalitik>|Builder<CadanganAi> $q */
    private function levels(Builder $q, User $user): void
    {
        $q->whereIn('klasifikasi', $this->skop->levels($user, SetData::get('lot')['tahap']));
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'jenis' => ['nullable', Rule::in(HasilAnalitik::JENIS)],
            'aras' => ['nullable', Rule::in(HasilAnalitik::ARAS)],
            'kod_daerah' => ['nullable', 'string', 'max:10'],
            'per_halaman' => ['nullable', 'integer', 'min:10', 'max:200'],
        ]);
        $q = HasilAnalitik::query()->where('semasa', true)
            ->when($data['jenis'] ?? null, fn ($w, $v) => $w->where('jenis_analitik', $v))
            ->when($data['aras'] ?? null, fn ($w, $v) => $w->where('aras_risiko', $v))
            ->when($data['kod_daerah'] ?? null, fn ($w, $v) => $w->where('kod_daerah', $v));
        $this->levels($q, $user);
        $page = $q->orderByDesc('skor')->orderBy('no_lot')->paginate((int) ($data['per_halaman'] ?? 50));
        $money = $this->access->allows($user, 'pro.rahsia.lihat');

        return response()->json([
            'data' => collect($page->items())->map(fn (HasilAnalitik $h) => $this->present($h, $money))->values(),
            'meta' => ['jumlah' => $page->total(), 'halaman' => $page->currentPage(), 'halaman_akhir' => $page->lastPage()],
            'label_cadangan' => __('lap::lap.label_cadangan'),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $q = HasilAnalitik::query()->where('semasa', true);
        $this->levels($q, $user);
        $rows = $q->selectRaw('jenis_analitik, aras_risiko, count(*) AS n, max(tarikh_kira) AS t')->groupBy('jenis_analitik', 'aras_risiko')->get();
        $out = [];
        foreach (HasilAnalitik::JENIS as $j) {
            $out[$j] = ['jenis' => $j, 'nama' => __('lap::lap.analitik.'.$j), 'aras' => array_fill_keys(HasilAnalitik::ARAS, 0), 'jumlah' => 0, 'dikira_pada' => null];
        }
        foreach ($rows as $r) {
            $out[$r->jenis_analitik]['aras'][$r->aras_risiko] = (int) $r->getAttribute('n');
            $out[$r->jenis_analitik]['jumlah'] += (int) $r->getAttribute('n');
            $out[$r->jenis_analitik]['dikira_pada'] = $r->getAttribute('t');
        }
        $c = CadanganAi::query()->where('status_keputusan', 'baharu');
        $this->levels($c, $user);

        return response()->json(['data' => array_values($out), 'cadangan_baharu' => $c->count(), 'versi_model' => Analitik::VERSI, 'label_cadangan' => __('lap::lap.label_cadangan')]);
    }

    public function run(Request $request, Analitik $analitik, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['jenis' => ['nullable', 'array'], 'jenis.*' => [Rule::in(HasilAnalitik::JENIS)]]);
        $counts = $analitik->run($data['jenis'] ?? null);
        $audit->record('jana', 'lap', 'lap_hasil_analitik', null, meta: ['analitik' => $counts, 'versi_model' => Analitik::VERSI]);

        return response()->json(['message' => __('lap::lap.analitik.siap'), 'data' => $counts]);
    }

    public function recommendations(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['baharu', 'diterima', 'ditolak'])],
            'jenis' => ['nullable', Rule::in(HasilAnalitik::JENIS)],
        ]);
        $q = CadanganAi::query()->with('pegawai:id,name')
            ->when($data['status'] ?? 'baharu', fn ($w, $v) => $w->where('status_keputusan', $v))
            ->when($data['jenis'] ?? null, fn ($w, $v) => $w->where('jenis_analitik', $v));
        $this->levels($q, $user);
        $page = $q->orderByDesc('created_at')->paginate(50);

        return response()->json([
            'data' => collect($page->items())->map(fn (CadanganAi $c) => $this->presentRec($c))->values(),
            'meta' => ['jumlah' => $page->total(), 'halaman' => $page->currentPage(), 'halaman_akhir' => $page->lastPage()],
            'label_cadangan' => __('lap::lap.label_cadangan'),
        ]);
    }

    /** SRS-LAP-19: an officer accepts or rejects with a recorded justification. */
    public function decide(Request $request, int $id, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'keputusan' => ['required', Rule::in(['diterima', 'ditolak'])],
            'justifikasi' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $q = CadanganAi::query()->whereKey($id);
        $this->levels($q, $user);
        $c = $q->firstOrFail();
        abort_if($c->status_keputusan !== 'baharu', 422, __('lap::lap.analitik.sudah_diputuskan'));
        $c->forceFill(['status_keputusan' => $data['keputusan'], 'justifikasi_pegawai' => $data['justifikasi'], 'id_pegawai' => $user->id, 'tarikh_keputusan' => now()])->save();
        $audit->record($data['keputusan'] === 'diterima' ? 'lulus' : 'tolak', 'lap', 'lap_cadangan_ai', $c->id, reason: $data['justifikasi'], meta: ['jenis_tindakan' => $c->jenis_tindakan, 'no_lot' => $c->no_lot]);

        return response()->json(['message' => __('lap::lap.analitik.keputusan_direkod'), 'data' => $this->presentRec($c->load('pegawai:id,name'))]);
    }

    public function insights(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $stateWide = $this->access->scope($user, 'daerah') === null && $this->access->scope($user, 'mukim') === null && $this->access->scope($user, 'bahagian') === null;
        $rows = InsightEksekutif::query()
            ->where(fn ($w) => $w->where('id_pengguna', $user->id)->when($stateWide, fn ($x) => $x->orWhereNull('id_pengguna')))
            ->orderByDesc('tarikh_jana')->limit(24)->get()
            ->map(fn (InsightEksekutif $i) => [
                'id' => $i->id, 'tempoh' => $i->tempoh, 'skop' => $i->skop, 'negeri' => $i->id_pengguna === null, 'isu_utama' => $i->isu_utama,
                'risiko' => $i->risiko, 'tindakan_segera' => $i->tindakan_segera, 'statistik' => $i->statistik, 'sumber' => $i->sumber,
                'tarikh_jana' => $i->tarikh_jana->toIso8601String(),
            ])->values();

        return response()->json(['data' => $rows, 'label_cadangan' => __('lap::lap.label_cadangan')]);
    }

    public function generateInsight(Request $request, Insight $insight, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $i = $insight->generate($user, 'manual');
        $audit->record('jana', 'lap', 'lap_insight_eksekutif', $i->id, meta: ['tempoh' => $i->tempoh]);

        return response()->json(['message' => __('lap::lap.insight.dijana'), 'data' => ['id' => $i->id]], 201);
    }

    /** @return array<string, mixed> */
    private function present(HasilAnalitik $h, bool $money): array
    {
        $f = (array) $h->faktor;
        if (! $money) {
            // Derived from Rahsia market valuations.
            unset($f['anggaran_hasil_tahunan']);
        }

        return [
            'id' => $h->id, 'jenis' => $h->jenis_analitik, 'jenis_nama' => __('lap::lap.analitik.'.$h->jenis_analitik), 'id_lot' => $h->id_lot, 'no_lot' => $h->no_lot,
            'kod_daerah' => $h->kod_daerah, 'kod_mukim' => $h->kod_mukim, 'skor' => $h->skor, 'aras' => $h->aras_risiko, 'faktor' => $f,
            'tarikh_kira' => $h->tarikh_kira->toIso8601String(), 'versi_model' => $h->versi_model, 'pautan' => '/admin/pro/lot/'.$h->id_lot,
        ];
    }

    /** @return array<string, mixed> */
    private function presentRec(CadanganAi $c): array
    {
        return [
            'id' => $c->id, 'id_lot' => $c->id_lot, 'no_lot' => $c->no_lot, 'kod_daerah' => $c->kod_daerah, 'jenis' => $c->jenis_analitik,
            'jenis_nama' => __('lap::lap.analitik.'.$c->jenis_analitik), 'tindakan' => $c->jenis_tindakan, 'tindakan_nama' => __('lap::lap.tindakan.'.$c->jenis_tindakan),
            'asas' => $c->asas_cadangan, 'status' => $c->status_keputusan, 'justifikasi' => $c->justifikasi_pegawai, 'pegawai' => $c->pegawai?->name,
            'tarikh_keputusan' => $c->tarikh_keputusan?->toIso8601String(), 'versi_model' => $c->versi_model, 'pautan' => '/admin/pro/lot/'.$c->id_lot,
            'dicipta' => $c->created_at?->toIso8601String(),
        ];
    }
}
