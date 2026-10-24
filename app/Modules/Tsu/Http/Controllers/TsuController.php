<?php

namespace App\Modules\Tsu\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tsu\Models\UrusanTanah;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Shared helpers: an urusan (and every child record) is reachable only inside the user's scope — otherwise 404. */
abstract class TsuController extends Controller
{
    protected function user(Request $request): User
    {
        $u = $request->user();
        if (! $u instanceof User) {
            throw new HttpException(401);
        }

        return $u;
    }

    protected function urusan(string $id): UrusanTanah
    {
        return UrusanTanah::query()->findOrFail($id); // ScopedByAccess (daerah / mukim / klasifikasi)
    }
}
