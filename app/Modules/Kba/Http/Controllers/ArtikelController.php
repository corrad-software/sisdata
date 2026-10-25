<?php

namespace App\Modules\Kba\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Kba\Models\Artikel;
use App\Modules\Kba\Models\Keluaran;
use App\Modules\Kba\Models\SumberDokumen;
use App\Modules\Kba\Models\VersiArtikel;
use App\Modules\Kba\Services\Capaian;
use App\Modules\Kba\Services\PengurusArtikel;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Articles (SRS-KBA-11..14). Readers see only published articles they may
 * read (and only the published version); editors see drafts and history.
 */
class ArtikelController extends Controller
{
    public const EDITOR = 'kba.artikel.kemas_kini';

    public function __construct(private Capaian $capaian, private AccessControl $access) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $editor = $user->can(self::EDITOR);
        $q = Artikel::query()
            ->when(! $editor, fn (Builder $b) => $this->readable($b, $user))
            ->when($editor && $request->filled('status'), fn (Builder $b) => $b->where('status', $request->string('status')))
            ->when($editor && $request->boolean('tindakan'), fn (Builder $b) => $b->where(fn (Builder $w) => $w->where('perlu_semakan', true)
                ->orWhere(fn (Builder $x) => $x->where('status', 'diterbitkan')->whereDate('tarikh_semakan_seterusnya', '<', now()->toDateString()))
                ->orWhere(fn (Builder $x) => $x->where('sumber_asal', 'tiket')->whereIn('status', ['draf', 'dalam_semakan']))))
            ->when($request->filled('modul'), fn (Builder $b) => $b->where('modul', strtolower((string) $request->string('modul'))))
            ->when($request->filled('q'), fn (Builder $b) => $b->where('tajuk', 'ilike', '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $request->string('q')).'%'))
            ->orderByDesc('updated_at')->limit(200);

        return response()->json(['data' => $q->get()->map(fn (Artikel $a) => $this->present($a, $editor, false))->values()]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $editor = $user->can(self::EDITOR);
        $q = Artikel::query()->whereKey($id);
        if (! $editor) {
            $this->readable($q, $user);
        }
        $a = $q->firstOrFail();

        return response()->json(['data' => $this->present($a, $editor, true)]);
    }

    public function store(Request $request, PengurusArtikel $artikel): JsonResponse
    {
        $data = $request->validate($this->rules(true));
        $a = $artikel->cipta($data, $request->user(), ($data['sumber_asal'] ?? 'manual') === 'ai' ? 'ai' : 'manual');

        return response()->json(['data' => $this->present($a, true, true), 'message' => __('kba::kba.artikel.dicipta')], 201);
    }

    public function update(Request $request, int $id, PengurusArtikel $artikel): JsonResponse
    {
        $a = Artikel::query()->findOrFail($id);
        $data = $request->validate($this->rules(false));
        try {
            $a = $artikel->kemasKini($a, $data, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'status_tidak_sah'], 422);
        }

        return response()->json(['data' => $this->present($a, true, true), 'message' => __('kba::kba.artikel.dikemas_kini')]);
    }

    public function hantar(Request $request, int $id, PengurusArtikel $artikel): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $a = Artikel::query()->findOrFail($id);
        try {
            $a = $artikel->hantar($a, $user);
        } catch (InvalidArgumentException $e) {
            $msg = $a->status === 'draf' ? __('kba::kba.artikel.aliran_tiada') : $e->getMessage();

            return response()->json(['message' => $msg, 'code' => 'tidak_boleh_dihantar'], 422);
        }

        return response()->json(['data' => $this->present($a, true, true), 'message' => __('kba::kba.artikel.dihantar')]);
    }

    public function lapuk(int $id, PengurusArtikel $artikel): JsonResponse
    {
        $a = $artikel->lapukkan(Artikel::query()->findOrFail($id));

        return response()->json(['data' => $this->present($a, true, true), 'message' => __('kba::kba.artikel.dilapukkan')]);
    }

    public function versi(int $id): JsonResponse
    {
        Artikel::query()->findOrFail($id);

        return response()->json(['data' => VersiArtikel::query()->where('id_artikel', $id)->orderByDesc('nombor_versi')->get()]);
    }

    public function keluaran(): JsonResponse
    {
        return response()->json(['data' => Keluaran::query()->orderByDesc('id')->limit(50)->get()]);
    }

    public function simpanKeluaran(Request $request, PengurusArtikel $artikel): JsonResponse
    {
        $data = $request->validate([
            'versi' => ['required', 'string', 'max:40', 'regex:/^\d+(\.\d+){1,3}$/', 'unique:kba_keluaran,versi'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);
        $k = $artikel->keluaran($data['versi'], $data['catatan'] ?? null, $request->user());

        return response()->json(['data' => $k, 'message' => __('kba::kba.keluaran.direkod', ['bil' => $k->bilangan_artikel_ditanda])], 201);
    }

    /**
     * @param  Builder<Artikel>  $q
     * @return Builder<Artikel>
     */
    private function readable(Builder $q, User $user): Builder
    {
        $roles = $this->access->roles($user);

        return $q->whereNotNull('versi_terbit')->where('status', '<>', 'lapuk')
            ->whereIn('klasifikasi', $this->capaian->levels($user))
            ->where(function (Builder $w) use ($roles) {
                $w->whereRaw("senarai_peranan_dibenarkan = '[]'::jsonb");
                if ($roles !== []) {
                    $w->orWhereRaw('jsonb_exists_any(senarai_peranan_dibenarkan, ?::text[])', [Capaian::pgArray($roles)]);
                }
            });
    }

    /** @return array<string, mixed> */
    private function present(Artikel $a, bool $editor, bool $full): array
    {
        $tajuk = $a->tajuk;
        $kandungan = $a->kandungan;
        $versi = $a->versi;
        if (! $editor && $a->versi_terbit !== null && $a->versi_terbit !== $a->versi) {
            $row = VersiArtikel::query()->where('id_artikel', $a->id)->where('nombor_versi', $a->versi_terbit)->first();
            if ($row !== null) {
                [$tajuk, $kandungan, $versi] = [$row->tajuk, $row->kandungan, $row->nombor_versi];
            }
        }
        $out = [
            'id' => $a->id, 'tajuk' => $tajuk, 'kategori' => $a->kategori, 'modul' => $a->modul, 'status' => $editor ? $a->status : 'diterbitkan',
            'versi' => $versi, 'versi_terbit' => $a->versi_terbit, 'klasifikasi' => $a->klasifikasi, 'senarai_peranan_dibenarkan' => $a->senarai_peranan_dibenarkan,
            'id_pemilik' => $a->id_pemilik, 'id_penyemak' => $a->id_penyemak, 'tarikh_terbit' => $a->tarikh_terbit?->toIso8601String(),
            'tarikh_semakan_akhir' => $a->tarikh_semakan_akhir?->toDateString(), 'tarikh_semakan_seterusnya' => $a->tarikh_semakan_seterusnya?->toDateString(),
            'sumber_asal' => $a->sumber_asal, 'id_tiket_asal' => $a->id_tiket_asal,
            'pautan_tiket' => $a->id_tiket_asal !== null ? '/admin/tkt/tiket/'.$a->id_tiket_asal : null,
            'versi_sistem_dirujuk' => $a->versi_sistem_dirujuk, 'perlu_semakan' => $a->perlu_semakan, 'sebab_perlu_semakan' => $a->sebab_perlu_semakan,
            'catatan_semakan' => $editor ? $a->catatan_semakan : null, 'id_instans_aliran' => $editor ? $a->id_instans_aliran : null,
            'updated_at' => $a->updated_at?->toIso8601String(),
        ];
        if ($full) {
            $out['kandungan'] = $kandungan;
            $sumber = SumberDokumen::query()->where('kod', 'artikel:'.$a->id)->where('aktif', true)->first();
            $out['pautan_sumber'] = $sumber !== null ? '/admin/kba/sumber/'.$sumber->id : null;
        }

        return $out;
    }

    /** @return array<string, list<mixed>> */
    private function rules(bool $create): array
    {
        $req = $create ? 'required' : 'sometimes';

        return [
            'tajuk' => [$req, 'string', 'max:250'],
            'kandungan' => [$req, 'string', 'max:200000'],
            'kategori' => ['nullable', 'string', 'max:60'],
            'modul' => ['nullable', 'string', 'regex:/^[a-z]{3}$/i'],
            'klasifikasi' => ['sometimes', Rule::in(SumberDokumen::KLASIFIKASI)],
            'senarai_peranan_dibenarkan' => ['sometimes', 'array'],
            'senarai_peranan_dibenarkan.*' => ['string', 'max:64'],
            'tarikh_semakan_seterusnya' => ['nullable', 'date'],
            'versi_sistem_dirujuk' => ['nullable', 'string', 'max:40', 'regex:/^\d+(\.\d+){1,3}$/'],
            'sebab_perubahan' => ['nullable', 'string', 'max:500'],
            'sumber_asal' => ['sometimes', Rule::in(['manual', 'ai'])],
        ];
    }
}
