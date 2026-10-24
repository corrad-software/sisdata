<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Services\LoginService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $access = app(AccessControl::class);
        $isSelf = $request->user()?->is($this->resource) ?? false;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            // Deprecated legacy field — not used for authorisation; use `peranan` / `permissions`.
            'role' => $this->role,
            'is_active' => $this->is_active,
            'status_akaun' => $this->status_akaun,
            'jenis_pengesahan' => $this->jenis_pengesahan,
            'bahagian' => $this->bahagian,
            'unit' => $this->unit,
            'jawatan' => $this->jawatan,
            'peranan' => $access->roles($this->resource),
            // Only for hiding UI; the API enforces access itself.
            'permissions' => $access->permissions($this->resource),
            'tarikh_log_masuk_akhir' => $this->tarikh_log_masuk_akhir,
            'tarikh_tamat_capaian' => $this->tarikh_tamat_capaian?->toDateString(),
            'dikunci' => $this->dikunci_hingga?->isFuture() ?? false,
            'perlu_tukar_kata_laluan' => $isSelf && $request->hasSession() && (bool) $request->session()->get(LoginService::MUST_CHANGE_KEY),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
