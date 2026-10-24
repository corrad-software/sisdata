<?php

namespace App\Modules\Kes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Kes\Support\Akses;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

abstract class KesBaseController extends Controller
{
    public function __construct(protected Akses $akses) {}

    protected function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthorizationException;
        }

        return $user;
    }

    /** A case the user may see (scope + panel rule); out of scope behaves as not found. */
    protected function cari(Request $request, string $id): KesMahkamah
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id) !== 1) {
            throw (new ModelNotFoundException)->setModel(KesMahkamah::class);
        }

        return KesMahkamah::query()->bolehDilihat($this->user($request))->with('pegawai')->findOrFail($id);
    }

    protected function urus(Request $request, KesMahkamah $kes): User
    {
        $user = $this->user($request);
        if (! $this->akses->bolehUrus($user, $kes)) {
            throw new AuthorizationException((string) ($kes->status_arkib ? __('kes::kes.ralat.diarkib') : __('kes::kes.ralat.tiada_akses')));
        }

        return $user;
    }
}
