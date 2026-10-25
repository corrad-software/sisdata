<?php

namespace App\Modules\Kba\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Kba\Models\LogPrompt;
use App\Modules\Kba\Models\MaklumBalas;
use App\Modules\Kba\Services\Analitik;
use App\Modules\Kba\Services\Eksport;
use App\Modules\Kba\Services\Sokongan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Analytics, gap report, prompt audit, feedback queue, export, support view (SRS-KBA-09/16/17/18/20). */
class PentadbiranController extends Controller
{
    public function analitik(Request $request, Analitik $analitik): JsonResponse
    {
        $data = $request->validate(['dari' => ['nullable', 'date'], 'hingga' => ['nullable', 'date']]);
        [$dari, $hingga] = Analitik::tempoh($data['dari'] ?? null, $data['hingga'] ?? null);

        return response()->json(['data' => $analitik->ringkasan($dari, $hingga)]);
    }

    public function jurang(Request $request, Analitik $analitik): JsonResponse
    {
        $data = $request->validate(['dari' => ['nullable', 'date'], 'hingga' => ['nullable', 'date']]);
        [$dari, $hingga] = Analitik::tempoh($data['dari'] ?? null, $data['hingga'] ?? null);

        return response()->json(['data' => $analitik->jurang($dari, $hingga, 200), 'tempoh' => ['dari' => $dari->toDateString(), 'hingga' => $hingga->toDateString()]]);
    }

    /** SRS-KBA-16: prompt audit (security review) — every user's prompts. Read-only; viewing is itself audited. */
    public function logPrompt(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'id_pengguna' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['berjaya', 'tiada_sumber', 'ditolak'])],
            'dari' => ['nullable', 'date'],
            'hingga' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:200'],
            'halaman' => ['nullable', 'integer', 'min:1'],
        ]);
        $q = LogPrompt::query()->from('kba_log_prompt as l')->leftJoin('users as u', 'u.id', '=', 'l.id_pengguna')
            ->when(isset($data['id_pengguna']), fn ($b) => $b->where('l.id_pengguna', $data['id_pengguna']))
            ->when(isset($data['status']), fn ($b) => $b->where('l.status', $data['status']))
            ->when(isset($data['dari']), fn ($b) => $b->where('l.cap_masa', '>=', $data['dari']))
            ->when(isset($data['hingga']), fn ($b) => $b->whereRaw('l.cap_masa < (?::date + 1)', [$data['hingga']]))
            ->when(isset($data['q']), fn ($b) => $b->where('l.prompt', 'ilike', '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $data['q']).'%'))
            ->orderByDesc('l.id')
            ->select(['l.id', 'l.id_pengguna', 'u.name as nama_pengguna', 'l.peranan', 'l.alamat_ip', 'l.saluran', 'l.prompt', 'l.status', 'l.mod_jawapan',
                'l.model_digunakan', 'l.model_embedding', 'l.bilangan_token', 'l.masa_respons_ms', 'l.cap_masa', 'l.senarai_sumber_dipetik', 'l.senarai_id_segmen_dicapai', 'l.sebab']);
        $page = $q->paginate(50, ['*'], 'halaman', (int) ($data['halaman'] ?? 1));
        $audit->record('lihat', 'kba', 'kba_log_prompt', null, meta: ['penapis' => $data, 'bilangan' => $page->count()]);

        return response()->json(['data' => $page->items(), 'meta' => ['jumlah' => $page->total(), 'halaman' => $page->currentPage(), 'halaman_akhir' => $page->lastPage()]]);
    }

    public function logPromptSatu(int $id, AuditLogger $audit): JsonResponse
    {
        $log = LogPrompt::query()->with('maklumBalas')->findOrFail($id);
        $segmen = DB::table('kba_segmen as s')->join('kba_sumber_dokumen as d', 'd.id', '=', 's.id_sumber')
            ->whereIn('s.id', $log->senarai_id_segmen_dicapai ?: [0])
            ->get(['s.id', 's.id_sumber', 'd.tajuk as dokumen', 's.nombor_seksyen', 's.tajuk_seksyen', 's.klasifikasi', 's.status']);
        $audit->record('lihat', 'kba', 'kba_log_prompt', $log->id);

        return response()->json(['data' => $log->toArray() + ['segmen_dicapai' => $segmen, 'nama_pengguna' => $log->id_pengguna !== null ? User::query()->whereKey($log->id_pengguna)->value('name') : null]]);
    }

    public function maklumBalas(Request $request): JsonResponse
    {
        $q = MaklumBalas::query()->with(['log:id,prompt,jawapan,status,senarai_sumber_dipetik,cap_masa'])
            ->when($request->filled('status_tindakan'), fn ($b) => $b->where('status_tindakan', $request->string('status_tindakan')))
            ->when($request->filled('penilaian'), fn ($b) => $b->where('penilaian', $request->string('penilaian')))
            ->orderByRaw("CASE status_tindakan WHEN 'baharu' THEN 0 WHEN 'dalam_tindakan' THEN 1 ELSE 2 END")->orderByDesc('id')->limit(300);

        return response()->json(['data' => $q->get()]);
    }

    public function kemasKiniMaklumBalas(Request $request, int $id): JsonResponse
    {
        $mb = MaklumBalas::query()->findOrFail($id);
        $data = $request->validate([
            'status_tindakan' => ['required', Rule::in(MaklumBalas::STATUS)],
            'catatan_tindakan' => ['nullable', 'string', 'max:1000'],
        ]);
        $mb->fill($data + ['id_pentadbir' => $request->user()?->id])->save();

        return response()->json(['data' => $mb, 'message' => __('kba::kba.maklum_balas.dikemas_kini')]);
    }

    /** SRS-KBA-20: everything, in open formats. Audited as an export. */
    public function eksport(Request $request, Eksport $eksport, AuditLogger $audit): BinaryFileResponse
    {
        /** @var User $user */
        $user = $request->user();
        $format = $request->validate(['format' => ['nullable', Rule::in(['markdown', 'html'])]])['format'] ?? 'markdown';
        $zip = $eksport->zip($user, $format);
        $audit->record('eksport', 'kba', 'kba_sumber_dokumen', null, meta: ['format' => $format, 'bilangan_fail' => $zip['bilangan'], 'nama_fail' => $zip['nama']]);

        return response()->download($zip['path'], $zip['nama'], ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    public function sokonganSenarai(Request $request, Sokongan $sokongan): JsonResponse
    {
        return response()->json(['data' => $sokongan->senaraiModul($request->user())]);
    }

    public function sokongan(Request $request, string $modul, Sokongan $sokongan): JsonResponse
    {
        abort_unless((bool) preg_match('/^[a-z]{3}$/i', $modul), 404);

        return response()->json(['data' => $sokongan->modul($modul, $request->user())]);
    }
}
