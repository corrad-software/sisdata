<?php

namespace App\Modules\Ntf\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ntf\Contracts\RecipientDirectory;
use App\Modules\Ntf\Services\NotificationEngine;
use App\Modules\Ntf\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Manual announcements to groups by role / bahagian / daerah (SRS-NTF-24). */
class AnnouncementController extends Controller
{
    private const TARGETS = [RecipientDirectory::PERANAN, RecipientDirectory::BAHAGIAN, RecipientDirectory::DAERAH, RecipientDirectory::PENGGUNA];

    public function groups(RecipientDirectory $directory): JsonResponse
    {
        return response()->json(['data' => [
            'peranan' => $directory->options(RecipientDirectory::PERANAN),
            'bahagian' => $directory->options(RecipientDirectory::BAHAGIAN),
            'daerah' => $directory->options(RecipientDirectory::DAERAH),
        ]]);
    }

    public function store(Request $request, RecipientDirectory $directory, NotificationEngine $engine, Settings $settings): JsonResponse
    {
        $data = $request->validate([
            'tajuk' => ['required', 'string', 'max:150'],
            'kandungan' => ['required', 'string', 'max:2000'],
            'pautan' => ['nullable', 'string', 'max:250', 'regex:#^/(?!/)#'],
            'sasaran' => ['required', 'array', 'min:1', 'max:50'],
            'sasaran.*.jenis' => ['required', Rule::in(self::TARGETS)],
            'sasaran.*.nilai' => ['required', 'string', 'max:100'],
        ]);

        // Rate control on the channel (Modul 15 security controls).
        $key = 'ntf-pengumuman:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, max(1, $settings->int('ntf.had_pengumuman_sejam')))) {
            abort(429);
        }

        $users = [];
        foreach ($data['sasaran'] as $target) {
            foreach ($directory->resolve($target['jenis'], $target['nilai']) as $user) {
                $users[$user->id] = $user;
            }
        }

        if ($users === []) {
            throw ValidationException::withMessages(['sasaran' => __('ntf::ntf.tiada_penerima')]);
        }

        RateLimiter::hit($key, 3600);
        $result = $engine->dispatch('ntf.pengumuman', array_values($users), [
            'tajuk' => $data['tajuk'],
            'kandungan' => $data['kandungan'],
        ], $data['pautan'] ?? '/admin/notifikasi', 'pengumuman:'.Str::uuid(), $request->user());

        return response()->json([
            'message' => __('ntf::ntf.pengumuman_dihantar', ['count' => $result['penerima']]),
            'data' => ['penerima' => $result['penerima'], 'dilangkau' => $result['dilangkau'], 'mesej_id' => $result['mesej']?->id],
        ], 201);
    }
}
