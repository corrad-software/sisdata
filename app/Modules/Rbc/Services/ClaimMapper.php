<?php

namespace App\Modules\Rbc\Services;

use App\Models\User;
use App\Modules\Rbc\Models\PemetaanTuntutan;
use Illuminate\Support\Str;

/**
 * SRS-RBC-02: maps SSO MAIS identity claims (roles, bahagian, unit,
 * jawatan) to SISDATA roles and scopes using admin-configured rules
 * (pemetaan_tuntutan). Runs on every SSO login: roles/scopes that came from
 * SSO are replaced; ones an administrator assigned by hand are kept.
 */
class ClaimMapper
{
    public function __construct(private RoleService $roles) {}

    /**
     * @param  array<string, mixed>  $claims
     */
    public function apply(User $user, array $claims): void
    {
        $profile = [];
        foreach (['bahagian', 'unit', 'jawatan', 'no_pekerja'] as $attribute) {
            $value = $this->claimValues($claims, $attribute)[0] ?? null;
            if ($value !== null) {
                $profile[$attribute] = mb_substr($value, 0, $attribute === 'jawatan' ? 150 : 100);
            }
        }
        if ($profile !== []) {
            $user->forceFill($profile)->save();
        }

        ['roles' => $roleIds, 'scopes' => $scopes] = $this->evaluate($claims);

        $this->roles->syncUserRoles($user, $roleIds, 'sso');
        $this->roles->syncUserScopes($user, $scopes, 'sso');
    }

    /**
     * Which roles/scopes the rules give for these claims (also used by the
     * admin "test a mapping" preview).
     *
     * @param  array<string, mixed>  $claims
     * @return array{roles: list<int>, scopes: array<string, list<string>>, rules: list<int>}
     */
    public function evaluate(array $claims): array
    {
        $roleIds = [];
        $scopes = [];
        $matched = [];

        $rules = PemetaanTuntutan::query()->where('aktif', true)->orderBy('keutamaan')->orderBy('id')->get();

        foreach ($rules as $rule) {
            foreach ($this->claimValues($claims, $rule->jenis_tuntutan) as $value) {
                if (! Str::is(Str::lower($rule->nilai), Str::lower($value))) {
                    continue;
                }

                $matched[] = $rule->id;

                if ($rule->peranan_id) {
                    $roleIds[] = $rule->peranan_id;
                }

                if ($rule->skop_jenis && $rule->skop_nilai !== null && $rule->skop_nilai !== '') {
                    $scopes[$rule->skop_jenis][] = $rule->skop_nilai === '{nilai}' ? $value : $rule->skop_nilai;
                }
            }
        }

        return [
            'roles' => array_values(array_unique($roleIds)),
            'scopes' => array_map(fn ($v) => array_values(array_unique($v)), $scopes),
            'rules' => array_values(array_unique($matched)),
        ];
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return list<string>
     */
    private function claimValues(array $claims, string $type): array
    {
        $raw = $claims[$type] ?? null;
        $values = is_array($raw) ? $raw : ($raw === null ? [] : [$raw]);

        return array_values(array_filter(array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : '', $values), fn ($v) => $v !== ''));
    }
}
