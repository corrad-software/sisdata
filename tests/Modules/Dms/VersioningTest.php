<?php

namespace Tests\Modules\Dms;

use Illuminate\Support\Facades\Storage;
use Tests\Modules\Dms\Support\TestFiles;

class VersioningTest extends DmsTestCase
{
    public function test_SRS_DMS_14_new_version_keeps_the_previous_one_and_both_are_retrievable(): void
    {
        $user = $this->userWith();
        $other = $this->userWith();
        $doc = $this->uploadDoc($user, TestFiles::pdf('Versi pertama'));

        $this->actingAs($other)->post("/api/v1/dms/dokumen/{$doc->id}/versi", [
            'fail' => TestFiles::pdf('Versi kedua dengan pindaan', 'geran-v2.pdf'),
            'catatan' => 'Pindaan selepas ukur semula',
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.no_versi', 2);

        $doc->refresh();
        $this->assertSame(2, $doc->versi_semasa);
        $this->assertSame('geran-v2.pdf', $doc->nama_fail);
        $this->assertCount(2, Storage::disk('dokumen')->allFiles());

        $versions = $this->actingAs($user)->getJson("/api/v1/dms/dokumen/{$doc->id}/versi")->assertOk()->json('data');
        $this->assertSame([2, 1], array_column($versions, 'no_versi'));
        $this->assertSame($other->id, $versions[0]['dimuat_naik_oleh']['id']);
        $this->assertSame($user->id, $versions[1]['dimuat_naik_oleh']['id']);
        $this->assertNotSame($versions[0]['cincangan_fail'], $versions[1]['cincangan_fail']);

        // Acceptance 5: both versions can be opened.
        foreach ([1, 2] as $n) {
            $url = $this->actingAs($user)->postJson("/api/v1/dms/dokumen/{$doc->id}/pautan", ['jenis' => 'muat_turun', 'versi' => $n])->json('data.url');
            $content = $this->actingAs($user)->get($url)->assertOk()->streamedContent();
            $this->assertSame($versions[2 - $n]['cincangan_fail'], hash('sha256', $content));
        }

        $this->assertCount(1, $this->audit->of('versi_baharu', $doc->id));
    }

    public function test_SRS_DMS_14_versions_can_be_compared(): void
    {
        $user = $this->userWith();
        $doc = $this->uploadDoc($user, TestFiles::pdf('A'), ['tajuk' => 'Asal', 'kod_daerah' => '10']);
        $this->actingAs($user)->putJson("/api/v1/dms/dokumen/{$doc->id}", ['tajuk' => 'Dipinda', 'kod_daerah' => '11'])->assertOk();
        $this->actingAs($user)->post("/api/v1/dms/dokumen/{$doc->id}/versi", ['fail' => TestFiles::pdf('B')], ['Accept' => 'application/json'])->assertCreated();

        $diff = $this->actingAs($user)->getJson("/api/v1/dms/dokumen/{$doc->id}/versi/banding?dari=1&ke=2")->assertOk()->json('data');

        $this->assertFalse($diff['kandungan_sama']);
        $byField = collect($diff['medan'])->keyBy('medan');
        $this->assertTrue($byField['tajuk']['berubah']);
        $this->assertSame(['Asal', 'Dipinda'], [$byField['tajuk']['dari'], $byField['tajuk']['ke']]);
        $this->assertSame(['10', '11'], [$byField['kod_daerah']['dari'], $byField['kod_daerah']['ke']]);
        $this->assertTrue($byField['cincangan_fail']['berubah']);
        $this->assertFalse($byField['kategori_id']['berubah']);
    }

    public function test_SRS_DMS_14_new_versions_go_through_the_same_checks(): void
    {
        $user = $this->userWith();
        $doc = $this->uploadDoc($user);

        $this->actingAs($user)->post("/api/v1/dms/dokumen/{$doc->id}/versi", ['fail' => TestFiles::exe()], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('sebab', 'disekat');
        $this->actingAs($user)->post("/api/v1/dms/dokumen/{$doc->id}/versi", ['fail' => TestFiles::eicar()], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('sebab', 'virus');
        $this->assertSame(1, $doc->refresh()->versi_semasa);

        $this->actingAs($this->userWith(['dms.dokumen.lihat']))->post("/api/v1/dms/dokumen/{$doc->id}/versi", ['fail' => TestFiles::pdf()], ['Accept' => 'application/json'])
            ->assertForbidden();
    }
}
