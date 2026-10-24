<?php

namespace App\Modules\Rbc\Support;

use App\Models\User;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Models\AmaranKeselamatan;
use App\Modules\Rbc\Models\LogLogMasuk;
use App\Modules\Rbc\Models\Peranan;
use App\Modules\Rbc\Models\Sesi;
use Illuminate\Validation\ValidationException;

/** JSON shapes of the RBC API (kept in one place for the frontend types). */
final class Presenters
{
    /** @return array<string, string> scope type => Malay label */
    public static function scopeTypes(): array
    {
        return [
            AccessControl::SCOPE_DAERAH => __('rbc::rbc.skop.daerah'),
            AccessControl::SCOPE_MUKIM => __('rbc::rbc.skop.mukim'),
            AccessControl::SCOPE_BAHAGIAN => __('rbc::rbc.skop.bahagian'),
            'unit' => __('rbc::rbc.skop.unit'),
            AccessControl::SCOPE_KLASIFIKASI => __('rbc::rbc.skop.klasifikasi'),
            'kategori_tanah' => __('rbc::rbc.skop.kategori_tanah'),
        ];
    }

    /**
     * @param  array<int|string, mixed>  $scopes
     *
     * @throws ValidationException
     */
    public static function assertScopeTypes(array $scopes): void
    {
        $unknown = array_diff(array_map('strval', array_keys($scopes)), array_keys(self::scopeTypes()));
        if ($unknown !== []) {
            throw ValidationException::withMessages(['skop' => [__('rbc::rbc.skop.jenis_tidak_sah', ['jenis' => implode(', ', $unknown)])]]);
        }
    }

    /** @return array<string, mixed> */
    public static function role(Peranan $role, bool $detail = false): array
    {
        $data = [
            'id' => $role->id,
            'kod' => $role->kod,
            'nama' => $role->nama,
            'kategori_rfp' => $role->kategori_rfp,
            'keterangan' => $role->keterangan,
            'status' => $role->status,
            'boleh_padam' => $role->boleh_padam,
            'bilangan_pengguna' => (int) ($role->getAttribute('pengguna_count') ?? 0),
            'bilangan_kebenaran' => (int) ($role->getAttribute('kebenaran_count') ?? 0),
        ];

        if ($detail) {
            $data['kebenaran'] = $role->kebenaranKeys();
            $data['skop'] = $role->skop()->get()->groupBy('jenis_skop')
                ->map(fn ($rows) => $rows->pluck('nilai_skop')->values()->all())->all() ?: (object) [];
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public static function session(Sesi $sesi, ?string $currentId = null): array
    {
        $ua = UserAgent::parse($sesi->user_agent);

        return [
            'id' => $sesi->id,
            'pengguna' => $sesi->relationLoaded('user')
                ? ['id' => $sesi->user->id, 'name' => $sesi->user->name, 'email' => $sesi->user->email]
                : null,
            'kaedah_log_masuk' => $sesi->kaedah_log_masuk,
            'alamat_ip' => $sesi->alamat_ip,
            'pelayar' => $ua->pelayar,
            'platform' => $ua->platform,
            'peranti' => $ua->peranti,
            'tarikh_mula' => $sesi->tarikh_mula->toIso8601String(),
            'tarikh_aktiviti_akhir' => $sesi->tarikh_aktiviti_akhir->toIso8601String(),
            'tarikh_tamat' => $sesi->tarikh_tamat?->toIso8601String(),
            'kaedah_tamat' => $sesi->kaedah_tamat,
            'semasa' => $currentId !== null && $sesi->id === $currentId,
        ];
    }

    /** @return array<string, mixed> */
    public static function loginLog(LogLogMasuk $log): array
    {
        return [
            'id' => $log->id,
            'pengguna' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name, 'email' => $log->user->email] : null,
            'pengecam' => $log->pengecam,
            'kaedah' => $log->kaedah,
            'berjaya' => $log->berjaya,
            'sebab' => $log->sebab,
            'sebab_label' => $log->sebab ? __('rbc::rbc.sebab.'.$log->sebab) : null,
            'alamat_ip' => $log->alamat_ip,
            'peranti' => $log->peranti,
            'pelayar' => $log->pelayar,
            'platform' => $log->platform,
            'lokasi' => $log->lokasi,
            'negara' => $log->negara,
            'mencurigakan' => $log->mencurigakan,
            'created_at' => $log->created_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function alert(AmaranKeselamatan $alert): array
    {
        return [
            'id' => $alert->id,
            'jenis' => $alert->jenis,
            'jenis_label' => __('rbc::rbc.amaran.'.$alert->jenis),
            'pengguna' => $alert->user ? ['id' => $alert->user->id, 'name' => $alert->user->name, 'email' => $alert->user->email] : null,
            'alamat_ip' => $alert->alamat_ip,
            'butiran' => $alert->butiran ?? (object) [],
            'disemak_pada' => $alert->disemak_pada?->toIso8601String(),
            'created_at' => $alert->created_at->toIso8601String(),
        ];
    }

    /** @return array{id: int, name: string, email: string} */
    public static function userRef(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];
    }
}
