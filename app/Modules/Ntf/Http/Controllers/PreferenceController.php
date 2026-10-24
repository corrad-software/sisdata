<?php

namespace App\Modules\Ntf\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ntf\Models\Jenis;
use App\Modules\Ntf\Models\KeutamaanPengguna;
use App\Modules\Ntf\Services\NotificationEngine;
use App\Modules\Ntf\Services\TypeRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** "Tetapan notifikasi saya" (SRS-NTF-09/10/20). */
class PreferenceController extends Controller
{
    /** Channels a user may choose today (SMS/WhatsApp have no gateway yet). */
    public const USER_CHANNELS = ['dalam_sistem', 'emel'];

    public function index(Request $request, TypeRegistry $types, NotificationEngine $engine): JsonResponse
    {
        $types->syncAll();
        $prefs = KeutamaanPengguna::query()->where('pengguna_id', $request->user()->id)->get()->keyBy('jenis_id');

        $rows = Jenis::query()->where('aktif', true)->orderBy('modul')->orderBy('nama')->get()
            ->map(function (Jenis $jenis) use ($prefs, $engine) {
                $pref = $prefs->get($jenis->id);

                return [
                    'jenis_id' => $jenis->id,
                    'kod' => $jenis->kod,
                    'nama' => $jenis->nama,
                    'modul' => $jenis->modul,
                    'keutamaan' => $jenis->keutamaan,
                    'wajib' => $jenis->wajib,
                    'boleh_ringkasan' => $jenis->bolehRingkasan() && ! $jenis->wajib && $jenis->kekerapan !== 'ringkasan',
                    'aktif' => $jenis->wajib || ($pref->aktif ?? true),
                    'saluran' => $engine->channelsFor($jenis, $pref) ?: ($pref->saluran ?? $jenis->saluran_lalai),
                    'saluran_lalai' => $jenis->saluran_lalai,
                    'ringkasan' => $jenis->kekerapan === 'ringkasan' || (bool) ($pref->ringkasan ?? false),
                ];
            });

        return response()->json(['data' => $rows, 'meta' => ['saluran_tersedia' => self::USER_CHANNELS]]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'max:500'],
            'items.*.jenis_id' => ['required', 'integer', 'exists:ntf_jenis,id'],
            'items.*.aktif' => ['required', 'boolean'],
            'items.*.saluran' => ['present', 'array'],
            'items.*.saluran.*' => [Rule::in(self::USER_CHANNELS)],
            'items.*.ringkasan' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $types = Jenis::query()->whereIn('id', collect($data['items'])->pluck('jenis_id'))->get()->keyBy('id');
        $errors = [];

        foreach ($data['items'] as $i => $item) {
            $jenis = $types[$item['jenis_id']];
            if ($jenis->wajib && ! $item['aktif']) {
                $errors["items.$i.aktif"] = __('ntf::ntf.wajib_dikunci', ['nama' => $jenis->nama]);
            }
            if (! $jenis->wajib && $item['aktif'] && $item['saluran'] === []) {
                $errors["items.$i.saluran"] = __('ntf::ntf.saluran_kosong');
            }
            if (($item['ringkasan'] ?? false) && ! $jenis->bolehRingkasan()) {
                $errors["items.$i.ringkasan"] = __('ntf::ntf.ringkasan_tidak_dibenarkan');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($data, $types, $user) {
            foreach ($data['items'] as $item) {
                // Mandatory types are locked: nothing the user sends is stored (SRS-NTF-10).
                if ($types[$item['jenis_id']]->wajib) {
                    continue;
                }

                KeutamaanPengguna::query()->updateOrCreate(
                    ['pengguna_id' => $user->id, 'jenis_id' => $item['jenis_id']],
                    ['aktif' => $item['aktif'], 'saluran' => array_values(array_unique($item['saluran'])), 'ringkasan' => (bool) ($item['ringkasan'] ?? false)],
                );
            }
        });

        return response()->json(['message' => __('ntf::ntf.keutamaan_disimpan')]);
    }
}
