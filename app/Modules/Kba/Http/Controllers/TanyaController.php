<?php

namespace App\Modules\Kba\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Kba\Contracts\EmbeddingProvider;
use App\Modules\Kba\Contracts\LlmGateway;
use App\Modules\Kba\Models\LogPrompt;
use App\Modules\Kba\Models\MaklumBalas;
use App\Modules\Kba\Models\Perbualan;
use App\Modules\Kba\Models\SumberDokumen;
use App\Modules\Kba\Services\Capaian;
use App\Modules\Kba\Services\Pencari;
use App\Modules\Kba\Services\Penjawab;
use App\Modules\Kba\Services\Teks;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Ntf\Contracts\RecipientDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

/** Q&A for every user (SRS-KBA-03/04/05/10/17): ask, search, open sources, history, feedback. */
class TanyaController extends Controller
{
    public function tanya(Request $request, Penjawab $penjawab): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'soalan' => ['required', 'string', 'min:2', 'max:1000'],
            'id_perbualan' => ['nullable', 'uuid'],
        ]);
        $r = $penjawab->jawab($data['soalan'], $user, ['saluran' => 'aplikasi', 'id_perbualan' => $data['id_perbualan'] ?? null, 'alamat_ip' => $request->ip()]);

        if ($r['status'] === 'ditolak') {
            return response()->json(['message' => $r['jawapan'], 'code' => 'had_kadar', 'data' => $r], 429)
                ->header('Retry-After', (string) ($r['cuba_lagi_saat'] ?? 60));
        }

        return response()->json(['data' => $r]);
    }

    /** SRS-KBA-10: conventional keyword search with filters. */
    public function carian(Request $request, Pencari $pencari): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:300'],
            'modul' => ['nullable', 'string', 'max:10'],
            'jenis_dokumen' => ['nullable', 'string', 'max:30'],
            'versi' => ['nullable', 'string', 'max:40'],
            'dari' => ['nullable', 'date'],
            'hingga' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['menunggu', 'sedang', 'selesai', 'gagal', 'tiada_teks'])],
            'pemilik' => ['nullable', 'integer'],
            'had' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $terms = Teks::contentTokens($data['q']);
        $hits = $pencari->cari($data['q'], $request->user(), (int) ($data['had'] ?? 20), $data, semantik: false);

        return response()->json(['data' => array_map(fn (array $p) => [
            'id_segmen' => $p['id_segmen'], 'id_sumber' => $p['id_sumber'], 'dokumen' => $p['dokumen'], 'versi' => $p['versi'],
            'jenis_dokumen' => $p['jenis_dokumen'], 'modul' => $p['modul'], 'seksyen' => Penjawab::seksyen($p), 'laluan' => $p['laluan_seksyen'],
            'petikan' => Penjawab::petikan($p['kandungan'], $terms, 260), 'skor' => $p['skor'], 'pautan' => $p['pautan'], 'klasifikasi' => $p['klasifikasi'],
        ], $hits)]);
    }

    /** SRS-KBA-04: open the full source at the cited section — only the segments the user may read. */
    public function kandungan(Request $request, int $id, Capaian $capaian, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $sumber = SumberDokumen::query()->findOrFail($id);
        $focus = $request->integer('segmen') ?: null;
        $acc = $capaian->predicate($user);
        $rows = DB::select("SELECT s.id, s.urutan, s.nombor_seksyen, s.tajuk_seksyen, s.laluan_seksyen, s.kandungan_teks, s.klasifikasi, s.status, s.no_versi_sumber
            FROM kba_segmen s WHERE s.id_sumber = ? AND (s.status = 'aktif' OR s.id = ?) AND {$acc['sql']} ORDER BY s.status, s.urutan",
            [$sumber->id, $focus ?? 0, ...$acc['bindings']]);
        abort_if($rows === [], 404, __('kba::kba.sumber.tiada_capaian'));

        $classified = array_values(array_unique(array_filter(array_map(fn ($r) => $r->klasifikasi, $rows), fn ($k) => in_array($k, ['sulit', 'rahsia'], true))));
        if ($classified !== []) {
            $audit->record('lihat', 'kba', 'kba_sumber_dokumen', $sumber->id, meta: ['klasifikasi' => in_array('rahsia', $classified, true) ? 'rahsia' : 'sulit', 'segmen' => $focus]);
        }

        return response()->json(['data' => [
            'id' => $sumber->id,
            'tajuk' => $sumber->tajuk,
            'versi' => $sumber->versiPaparan(),
            'no_versi' => $sumber->no_versi,
            'jenis_dokumen' => $sumber->jenis_dokumen,
            'asal' => $sumber->asal,
            'modul' => $sumber->modul,
            'klasifikasi' => $sumber->klasifikasi,
            'tarikh_indeks_akhir' => $sumber->tarikh_indeks_akhir?->toIso8601String(),
            'pautan_dms' => $sumber->id_dokumen_dms !== null ? '/admin/dms/'.$sumber->id_dokumen_dms : null,
            'pautan_artikel' => str_starts_with($sumber->kod, 'artikel:') ? '/admin/kba/artikel/'.substr($sumber->kod, 8) : null,
            'segmen_fokus' => $focus,
            'segmen' => array_map(fn ($r) => [
                'id' => (int) $r->id, 'urutan' => (int) $r->urutan, 'seksyen' => Penjawab::seksyen(['nombor_seksyen' => $r->nombor_seksyen, 'tajuk_seksyen' => $r->tajuk_seksyen]),
                'laluan' => $r->laluan_seksyen, 'kandungan' => $r->kandungan_teks, 'klasifikasi' => $r->klasifikasi,
                'lapuk' => $r->status !== 'aktif', 'no_versi' => (int) $r->no_versi_sumber,
            ], $rows),
        ]]);
    }

    /** Conversation history — the user's own only. */
    public function perbualan(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => Perbualan::query()->where('id_pengguna', $user->id)->where('disembunyikan', false)
            ->withCount('log')->orderByDesc('updated_at')->limit(100)->get(['id', 'tajuk', 'created_at', 'updated_at'])]);
    }

    public function perbualanSatu(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $p = Perbualan::query()->where('id', $id)->where('id_pengguna', $user->id)->where('disembunyikan', false)->firstOrFail();

        return response()->json(['data' => [
            'id' => $p->id, 'tajuk' => $p->tajuk,
            'mesej' => $p->log()->with('maklumBalas')->get()->map(fn (LogPrompt $l) => Penjawab::dariLog($l))->values(),
        ]]);
    }

    public function sembunyiPerbualan(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $p = Perbualan::query()->where('id', $id)->where('id_pengguna', $user->id)->firstOrFail();
        $p->forceFill(['disembunyikan' => true])->save();

        return response()->json(['message' => __('kba::kba.perbualan.disembunyikan')]);
    }

    /** SRS-KBA-17: helpful / not helpful / wrong source + comment, on the user's own answer. */
    public function maklumBalas(Request $request, int $id, Notifier $notifier, RecipientDirectory $directory): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $log = LogPrompt::query()->where('id', $id)->where('id_pengguna', $user->id)->firstOrFail();
        $data = $request->validate([
            'penilaian' => ['required', Rule::in(MaklumBalas::PENILAIAN)],
            'ulasan' => ['nullable', 'string', 'max:2000'],
        ]);
        $mb = MaklumBalas::query()->updateOrCreate(['id_log_prompt' => $log->id], [
            'penilaian' => $data['penilaian'], 'ulasan' => $data['ulasan'] ?? null, 'id_pengguna' => $user->id, 'cap_masa' => now(),
        ]);

        if ($data['penilaian'] !== 'membantu') {
            try {
                $admins = $directory->resolve(RecipientDirectory::KEBENARAN, 'kba.maklum_balas.lihat');
                $notifier->send('kba.maklum_balas_negatif', $admins, ['penilaian' => __('kba::kba.penilaian.'.$data['penilaian']), 'id_log' => $log->id], '/admin/kba/maklum-balas', 'kba-mb-'.$log->id);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return response()->json(['data' => $mb, 'message' => __('kba::kba.maklum_balas.terima_kasih')], 201);
    }

    /** What the Q&A screen needs to know: answer mode, models, thresholds (no secrets). */
    public function status(Penjawab $penjawab, LlmGateway $llm, EmbeddingProvider $embedder): JsonResponse
    {
        return response()->json(['data' => [
            'mod_jawapan' => $penjawab->mode(),
            'model_llm' => $llm->model(),
            'llm_tersedia' => $llm->available(),
            'model_embedding' => $embedder->model(),
        ]]);
    }
}
