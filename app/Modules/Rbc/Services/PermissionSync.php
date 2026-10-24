<?php

namespace App\Modules\Rbc\Services;

use App\Modules\Rbc\Models\Kebenaran;
use App\Modules\Rbc\Models\Peranan;
use App\Modules\Rbc\Models\PerananKebenaran;
use App\Modules\Rbc\Support\BaseRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Persists the registered permission catalogue and keeps the seven base
 * roles present (SRS-RBC-05). Runs after every `migrate` (also when nothing
 * was pending), from `php artisan rbc:segerak-kebenaran` and the seeder.
 */
class PermissionSync
{
    public function __construct(private PermissionCatalogue $catalogue) {}

    /**
     * @return array{peranan_baharu: int, kebenaran_baharu: int}
     */
    public function sync(): array
    {
        if (! Schema::hasTable('kebenaran') || ! Schema::hasTable('peranan')) {
            return ['peranan_baharu' => 0, 'kebenaran_baharu' => 0];
        }

        return DB::transaction(function () {
            $newRoles = $this->ensureBaseRoles();

            $registered = $this->catalogue->flat();
            $known = Kebenaran::query()->pluck('kunci')->flip()->all();
            $new = array_diff_key($registered, $known);

            foreach ($registered as $key => $label) {
                $parts = PerananKebenaran::split($key);
                Kebenaran::query()->updateOrCreate(['kunci' => $key], [...$parts, 'label' => $label]);
            }

            $baseRoles = Peranan::query()->whereIn('kod', array_keys(BaseRoles::definitions()))->get();

            foreach ($baseRoles as $role) {
                // A role created in this run gets every matching permission;
                // an existing one only gets keys never seen before.
                $candidates = in_array($role->kod, $newRoles, true) ? $registered : $new;

                foreach (array_keys($candidates) as $key) {
                    if (BaseRoles::grantsByDefault($role->kod, $key)) {
                        PerananKebenaran::query()->firstOrCreate(
                            ['peranan_id' => $role->id, 'kebenaran' => $key],
                            PerananKebenaran::split($key),
                        );
                    }
                }
            }

            RbcAccessControl::flushAll();

            return ['peranan_baharu' => count($newRoles), 'kebenaran_baharu' => count($new)];
        });
    }

    /** @return list<string> codes of base roles created now */
    private function ensureBaseRoles(): array
    {
        $created = [];

        foreach (BaseRoles::definitions() as $kod => $definition) {
            $role = Peranan::query()->firstOrCreate(['kod' => $kod], [
                'nama' => $definition['nama'],
                'kategori_rfp' => $kod,
                'keterangan' => $definition['keterangan'],
                'status' => Peranan::AKTIF,
                'boleh_padam' => false,
            ]);

            if ($role->wasRecentlyCreated) {
                $created[] = $kod;
            }
        }

        return $created;
    }
}
