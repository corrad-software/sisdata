<?php

namespace Tests\Modules\Ast;

use App\Models\User;
use App\Modules\Ast\Support\AliranAst;
use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Rbc\Contracts\PermissionRegistry;
use Database\Seeders\AstAliranSeeder;
use Illuminate\Support\Facades\DB;

class PendaftaranModulTest extends AstTestCase
{
    public function test_SRS_AST_16_module_registers_permissions_notification_types_reminders_and_wfe_flows(): void
    {
        $this->assertArrayHasKey('ast.sulit.kemas_kini', app(PermissionRegistry::class)->all()['ast']['permissions']);
        foreach (['ast.penyelenggaraan_berjadual', 'ast.penyelenggaraan_tertunggak', 'ast.penilaian_semula', 'ast.jaminan_tamat', 'ast.insurans_tamat'] as $code) {
            $this->assertTrue(app(NotificationTypes::class)->isRegistered($code), $code);
        }
        User::factory()->create(['role' => 'admin']);
        $this->seed(AstAliranSeeder::class);
        $this->seed(AstAliranSeeder::class); // idempotent
        foreach ([AliranAst::PEROLEHAN, AliranAst::PELUPUSAN, AliranAst::PENYELENGGARAAN] as $flow) {
            $this->assertSame(1, DB::table('wf_definisi')->where('kod_aliran', $flow)->where('status', 'aktif')->count(), $flow);
        }
    }
}
