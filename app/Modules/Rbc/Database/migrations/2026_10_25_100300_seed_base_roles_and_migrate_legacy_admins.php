<?php

use App\Modules\Rbc\Services\PermissionSync;
use App\Modules\Rbc\Support\BaseRoles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * SRS-RBC-05: the seven base roles exist from day one, and every legacy
     * `role = 'admin'` account becomes Pentadbir Sistem — authorisation no
     * longer reads the `role` string.
     */
    public function up(): void
    {
        app(PermissionSync::class)->sync();

        $roleId = DB::table('peranan')->where('kod', BaseRoles::PENTADBIR_SISTEM)->value('id');
        if (! $roleId) {
            return;
        }

        foreach (DB::table('users')->where('role', 'admin')->pluck('id') as $userId) {
            DB::table('pengguna_peranan')->insertOrIgnore([
                'user_id' => $userId,
                'peranan_id' => $roleId,
                'sumber' => 'manual',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Role data is removed with its tables by the earlier migration's down().
    }
};
