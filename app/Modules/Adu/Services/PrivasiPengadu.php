<?php

namespace App\Modules\Adu\Services;

use App\Models\User;
use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Models\AuditCapaianPengadu;
use App\Modules\Adu\Models\Pengadu;
use App\Modules\Adu\Support\Akses;
use App\Modules\Aud\Contracts\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * SRS-ADU-20/21 / acceptance #4: reporter identity is masked everywhere; it
 * is revealed only on an explicit request with a stated purpose, only to the
 * assigned officer, their supervisor or Unit Aduan, and every reveal is
 * written to audit_capaian_pengadu AND the central audit trail.
 */
class PrivasiPengadu
{
    public function __construct(private Akses $akses, private AuditLogger $audit) {}

    /** @return array<string, mixed> masked view (safe for anyone who may see the complaint) */
    public function ringkasan(Aduan $aduan, User $user): array
    {
        if ($aduan->status_tanpa_nama) {
            return ['tanpa_nama' => true, 'ada' => false, 'boleh_dedah' => false];
        }
        $pengadu = Pengadu::query()->where('aduan_id', $aduan->id)->first();
        if ($pengadu === null) {
            return ['tanpa_nama' => false, 'ada' => false, 'boleh_dedah' => false];
        }

        return [
            'tanpa_nama' => false,
            'ada' => true,
            'boleh_dedah' => $this->akses->asasIdentiti($user, $aduan) !== null,
            'bertopeng' => $pengadu->bertopeng(),
            'saluran_makluman' => $pengadu->saluran_makluman,
            'sintetik' => $pengadu->sintetik,
        ];
    }

    /**
     * @param  list<string>  $fields  subset of Pengadu::MEDAN_IDENTITI
     * @return array<string, string|null>
     *
     * @throws AuthorizationException
     */
    public function dedah(Aduan $aduan, User $user, string $tujuan, array $fields, ?string $ip): array
    {
        $asas = $this->akses->asasIdentiti($user, $aduan);
        $pengadu = Pengadu::query()->where('aduan_id', $aduan->id)->first();
        if ($asas === null || $pengadu === null) {
            $this->audit->record('lihat', 'adu', 'aduan', $aduan->id, 'gagal', 'tiada_hak_identiti_pengadu', meta: ['klasifikasi' => 'sulit', 'no_rujukan' => $aduan->no_rujukan_aduan]);
            throw new AuthorizationException(__('adu::adu.ralat.tiada_hak_identiti'));
        }
        $fields = array_values(array_intersect($fields === [] ? Pengadu::MEDAN_IDENTITI : $fields, Pengadu::MEDAN_IDENTITI));

        AuditCapaianPengadu::query()->create([
            'aduan_id' => $aduan->id,
            'no_rujukan_aduan' => $aduan->no_rujukan_aduan,
            'id_pegawai' => $user->id,
            'tarikh_masa' => now(),
            'medan_dicapai' => $fields,
            'tujuan_capaian' => $tujuan,
            'alamat_ip' => $ip,
            'asas_capaian' => $asas,
        ]);
        $this->audit->record('lihat', 'adu', 'pengadu', $pengadu->id, reason: $tujuan, meta: [
            'klasifikasi' => 'sulit', 'medan' => $fields, 'no_rujukan' => $aduan->no_rujukan_aduan, 'asas' => $asas,
        ]);

        $out = [];
        foreach ($fields as $field) {
            $out[$field] = $pengadu->getAttribute($field);
        }

        return $out;
    }
}
