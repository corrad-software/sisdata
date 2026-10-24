<?php

namespace Tests\Modules\Adm;

use App\Modules\Adm\Contracts\ReportTemplates;

class TemplatTest extends AdmTestCase
{
    private function payload(array $override = []): array
    {
        return [
            'kod' => 'laporan.cukai_tertunggak',
            'nama' => 'Laporan Cukai Tertunggak',
            'format_output' => 'pdf',
            'kandungan' => '<h1>Cukai tertunggak {{daerah}}</h1><p>Jumlah: {{jumlah}}</p>',
            'medan' => ['daerah', 'jumlah'],
            ...$override,
        ];
    }

    public function test_SRS_ADM_03_admin_manages_report_template_content_fields_format_and_versions(): void
    {
        $admin = $this->admin();
        $id = $this->actingAs($admin)->postJson('/api/v1/adm/templat', $this->payload())->assertCreated()->assertJsonPath('data.versi', 1)->json('data.id');

        $this->actingAs($admin)->putJson("/api/v1/adm/templat/{$id}", [
            'kandungan' => '<h1>Tunggakan {{daerah}}</h1><p>{{jumlah}} ({{tahun}})</p>',
            'medan' => ['daerah', 'jumlah', 'tahun'],
            'format_output' => 'xlsx',
        ])->assertOk()->assertJsonPath('data.versi', 2)->assertJsonPath('data.format_output', 'xlsx');

        $show = $this->actingAs($admin)->getJson("/api/v1/adm/templat/{$id}")->assertOk();
        $this->assertSame([2, 1], array_column($show->json('data.versi_list'), 'versi'));

        $this->actingAs($admin)->postJson("/api/v1/adm/templat/{$id}/versi/1/pulih")->assertOk()->assertJsonPath('data.versi', 3)->assertJsonPath('data.format_output', 'pdf');
    }

    public function test_SRS_ADM_03_placeholders_must_be_declared_fields(): void
    {
        $this->actingAs($this->admin())->postJson('/api/v1/adm/templat', $this->payload(['medan' => ['daerah']]))
            ->assertStatus(422)->assertJsonValidationErrors('kandungan');
    }

    public function test_SRS_ADM_03_preview_and_render_escape_dynamic_values(): void
    {
        $admin = $this->admin();
        $id = $this->actingAs($admin)->postJson('/api/v1/adm/templat', $this->payload())->json('data.id');

        $this->actingAs($admin)->postJson("/api/v1/adm/templat/{$id}/pratonton", ['data' => ['daerah' => '<b>Klang</b>', 'jumlah' => 5]])
            ->assertOk()
            ->assertJsonPath('data.kandungan', '<h1>Cukai tertunggak &lt;b&gt;Klang&lt;/b&gt;</h1><p>Jumlah: 5</p>');

        $rendered = $this->app->make(ReportTemplates::class)->render('laporan.cukai_tertunggak', ['daerah' => 'Petaling', 'jumlah' => 12]);
        $this->assertSame('pdf', $rendered['format']);
        $this->assertStringContainsString('Cukai tertunggak Petaling', $rendered['kandungan']);
        $this->assertNull($this->app->make(ReportTemplates::class)->render('tiada', []));
    }

    public function test_SRS_ADM_03_template_management_requires_permission(): void
    {
        $viewer = $this->userWith(['adm.templat.lihat']);
        $this->actingAs($viewer)->getJson('/api/v1/adm/templat')->assertOk();
        $this->actingAs($viewer)->postJson('/api/v1/adm/templat', $this->payload())->assertForbidden();
        $this->actingAs($this->userWith([]))->getJson('/api/v1/adm/templat')->assertForbidden();
    }
}
