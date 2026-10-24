<?php

namespace Tests\Modules\Rbc;

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\User;
use App\Modules\Rbc\Exports\TabularExport;
use App\Modules\Rbc\Http\Controllers\MatrixController;
use App\Modules\Rbc\Models\LogLogMasuk;
use App\Modules\Rbc\Models\Peranan;
use App\Modules\Rbc\Models\PerananKebenaran;
use App\Modules\Rbc\Services\AccessMatrix;
use App\Modules\Rbc\Support\BaseRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Modules\Rbc\Concerns\InteractsWithRbc;
use Tests\TestCase;

/** SRS-RBC-10 (access matrix, Excel + PDF) and SRS-RBC-26 (access reports). */
class AccessReportTest extends TestCase
{
    use InteractsWithRbc, RefreshDatabase;

    public function test_SRS_RBC_10_matrix_shows_roles_permissions_and_scopes(): void
    {
        $this->role(BaseRoles::PEGAWAI_OPERASI)->skop()->create(['jenis_skop' => 'daerah', 'nilai_skop' => 'klang']);

        $matrix = $this->actingAs($this->admin())->getJson('/api/v1/rbc/matriks')->assertOk()->json('data');

        $this->assertContains('lihat', $matrix['tindakan']);
        $this->assertCount(Peranan::count(), $matrix['peranan']);
        $operasi = collect($matrix['peranan'])->firstWhere('kod', BaseRoles::PEGAWAI_OPERASI);
        $this->assertSame(['daerah' => ['klang']], $operasi['skop']);
        $this->assertSame($this->role(BaseRoles::JURUAUDIT)->kebenaranKeys(), $matrix['pemberian'][BaseRoles::JURUAUDIT]);
        $this->assertSame('rbc', collect($matrix['modul'])->firstWhere('modul', 'rbc')['modul']);
    }

    /** Acceptance #8: the Excel export matches the live permission configuration. */
    public function test_SRS_RBC_10_matrix_excel_export_matches_configuration(): void
    {
        $custom = Peranan::query()->create(['kod' => 'khas', 'nama' => 'Peranan Khas']);
        $custom->kebenaran()->create(['kebenaran' => 'rbc.matriks.lihat', 'modul' => 'rbc', 'fungsi' => 'matriks', 'tindakan' => 'lihat']);

        $response = $this->actingAs($this->admin())->get('/api/v1/rbc/matriks/eksport?format=xlsx')->assertOk();
        $this->assertSame(hash('sha256', $response->getContent()), $response->headers->get('X-Checksum-SHA256'));
        $this->assertStringContainsString('attachment; filename="matriks-capaian-', $response->headers->get('Content-Disposition'));

        $file = tempnam(sys_get_temp_dir(), 'rbc').'.xlsx';
        file_put_contents($file, $response->getContent());
        $book = IOFactory::load($file);
        @unlink($file);

        $sheet = $book->getSheet(0)->toArray();
        $header = $sheet[3];
        $roles = Peranan::query()->orderByRaw('kategori_rfp is null')->orderBy('id')->get();
        $this->assertSame($roles->pluck('nama')->all(), array_slice($header, 4));

        foreach (array_slice($sheet, 4) as $row) {
            foreach ($roles as $i => $role) {
                $granted = $role->kebenaran()->where('kebenaran', $row[3])->exists();
                $this->assertSame($granted ? '✓' : '', (string) $row[4 + $i], "{$role->kod} × {$row[3]}");
            }
        }

        $scopeSheet = $book->getSheet(1)->toArray();
        $this->assertContains('Peranan Khas', array_column(array_slice($scopeSheet, 4), 0));
    }

    public function test_SRS_RBC_10_matrix_pdf_export_matches_configuration(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get('/api/v1/rbc/matriks/eksport?format=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        // The PDF is rendered from this HTML; check its cells against the DB.
        $matrix = app(AccessMatrix::class)->build();
        $html = TabularExport::html('Matriks', MatrixController::sections(app(AccessMatrix::class), $matrix), 'ujian');
        $granted = count($this->role(BaseRoles::PENTADBIR_SISTEM)->kebenaranKeys()) + count($this->role(BaseRoles::PENGGUNA_PAPARAN)->kebenaranKeys());
        $this->assertGreaterThanOrEqual($granted, substr_count($html, '<td class="c">✓</td>'));
        $this->assertSame(PerananKebenaran::count(), substr_count($html, '<td class="c">✓</td>'));
    }

    public function test_SRS_RBC_10_matrix_export_requires_permission(): void
    {
        $this->actingAs(User::factory()->withPermissions(['rbc.matriks.lihat'])->create())->get('/api/v1/rbc/matriks/eksport')->assertForbidden();
        $this->actingAs(User::factory()->withRole(BaseRoles::JURUAUDIT)->create())->get('/api/v1/rbc/matriks/eksport')->assertOk();
    }

    public function test_SRS_RBC_26_access_report_counts_users_by_status_role_and_division(): void
    {
        $admin = $this->admin();
        User::factory()->withRole(BaseRoles::PELULUS)->create(['bahagian' => 'Bahagian Wakaf']);
        User::factory()->create(['bahagian' => 'Bahagian Wakaf', 'status_akaun' => 'gantung']);
        User::factory()->create(['tarikh_log_masuk_akhir' => now()->subDays(120), 'created_at' => now()->subYear()]);
        LogLogMasuk::query()->create(['pengecam' => 'x@mais.test', 'kaedah' => 'kata_laluan', 'berjaya' => false, 'sebab' => 'kata_laluan_salah', 'alamat_ip' => '192.0.2.1']);
        LogLogMasuk::query()->create(['pengecam' => 'x@mais.test', 'kaedah' => 'kata_laluan', 'berjaya' => false, 'sebab' => 'kata_laluan_salah', 'alamat_ip' => '192.0.2.1']);

        $report = $this->actingAs($admin)->getJson('/api/v1/rbc/laporan')->assertOk()->json('data');

        $this->assertSame(1, $report['mengikut_status']['gantung']);
        $this->assertSame(3, $report['mengikut_status']['aktif']);
        $this->assertSame(0, $report['mengikut_status']['tamat_tempoh']);
        $this->assertSame(1, collect($report['mengikut_peranan'])->firstWhere('kod', BaseRoles::PELULUS)['aktif']);
        $this->assertSame(['bahagian' => 'Bahagian Wakaf', 'jumlah' => 2, 'aktif' => 1], collect($report['mengikut_bahagian'])->firstWhere('bahagian', 'Bahagian Wakaf'));
        $this->assertCount(2, $report['pengguna_tidak_aktif']);
        $this->assertSame(['pengecam' => 'x@mais.test', 'bilangan' => 2], array_intersect_key($report['gagal_mengikut_pengguna'][0], ['pengecam' => 1, 'bilangan' => 1]));
        $this->assertSame('192.0.2.1', $report['gagal_mengikut_ip'][0]['alamat_ip']);
        $this->assertContains('rbc.peranan.lihat', collect($report['kebenaran_peranan'])->firstWhere('kod', BaseRoles::JURUAUDIT)['kebenaran']);
    }

    public function test_SRS_RBC_26_access_report_exports_to_excel_and_pdf(): void
    {
        $admin = $this->admin();

        $xlsx = $this->actingAs($admin)->get('/api/v1/rbc/laporan/eksport')->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'rbc').'.xlsx';
        file_put_contents($file, $xlsx->getContent());
        $this->assertSame(7, IOFactory::load($file)->getSheetCount());
        @unlink($file);

        $this->actingAs($admin)->get('/api/v1/rbc/laporan/eksport?format=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs(User::factory()->withRole(BaseRoles::PENGGUNA_PAPARAN)->create())->getJson('/api/v1/rbc/laporan')->assertForbidden();
    }

    public function test_SRS_RBC_26_policy_settings_are_listed_and_validated(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->getJson('/api/v1/rbc/polisi')->assertOk()->assertJsonFragment(['kunci' => 'sesi_tamat_minit', 'nilai' => 15, 'lalai' => 15]);
        $this->actingAs($admin)->putJson('/api/v1/rbc/polisi', ['nilai' => ['sesi_tamat_minit' => 0]])->assertUnprocessable()->assertJsonValidationErrors('sesi_tamat_minit');
        $this->actingAs($admin)->putJson('/api/v1/rbc/polisi', ['nilai' => ['sesi_ikat_ip' => 'kadang-kadang']])->assertUnprocessable();
        $this->actingAs($admin)->putJson('/api/v1/rbc/polisi', ['nilai' => ['entah' => 1]])->assertUnprocessable();
        $this->actingAs(User::factory()->withRole(BaseRoles::PENTADBIR_APLIKASI)->create())->putJson('/api/v1/rbc/polisi', ['nilai' => ['sesi_tamat_minit' => 60]])->assertForbidden();

        $this->assertSame('rbc.pentadbiran.akses', EnsureUserIsAdmin::DEFAULT_PERMISSION);
    }
}
