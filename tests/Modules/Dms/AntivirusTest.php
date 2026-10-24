<?php

namespace Tests\Modules\Dms;

use App\Modules\Dms\Models\Dokumen;
use App\Modules\Dms\Models\DokumenImbasan;
use App\Modules\Dms\Models\DokumenKuarantin;
use App\Modules\Dms\Services\Crypto\EnvelopeEncryptor;
use App\Modules\Dms\Services\Scanning\ClamdScanner;
use App\Modules\Dms\Services\Scanning\FakeVirusScanner;
use App\Modules\Dms\Services\Scanning\VirusScanner;
use Illuminate\Support\Facades\Storage;
use Tests\Modules\Dms\Support\TestFiles;

class AntivirusTest extends DmsTestCase
{
    public function test_SRS_DMS_07_infected_file_is_not_stored_but_quarantined_encrypted_and_audited(): void
    {
        $user = $this->userWith();

        $this->upload($user, TestFiles::eicar(), ['jenis_entiti_induk' => 'lot_tanah', 'id_rekod_induk' => '77'])
            ->assertStatus(422)
            ->assertJsonPath('keputusan.0.sebab', 'virus')
            ->assertJsonPath('keputusan.0.mesej', fn ($m) => str_contains($m, 'dikuarantin'));

        $this->assertSame(0, Dokumen::query()->count());
        $item = DokumenKuarantin::query()->sole();
        $this->assertSame('Eicar-Test-Signature', $item->nama_ancaman);
        $this->assertSame('lot_tanah', $item->jenis_entiti_induk);

        // Only the quarantine object exists, and it is not readable plaintext.
        $files = Storage::disk('dokumen')->allFiles();
        $this->assertCount(1, $files);
        $this->assertStringStartsWith('kuarantin/', $files[0]);
        $this->assertStringNotContainsString('EICAR', Storage::disk('dokumen')->get($files[0]));

        $scan = DokumenImbasan::query()->sole();
        $this->assertSame('dijangkiti', $scan->keputusan);
        $this->assertSame($item->id, $scan->kuarantin_id);

        $entry = $this->audit->of('kuarantin')[0] ?? null;
        $this->assertNotNull($entry);
        $this->assertSame('gagal', $entry['status']);
        $this->assertSame('Eicar-Test-Signature', $entry['reason']);
    }

    public function test_SRS_DMS_07_scan_happens_before_anything_is_persisted_and_clean_scans_are_recorded(): void
    {
        $user = $this->userWith();
        $doc = $this->uploadDoc($user);

        $this->assertCount(1, $this->scanner->scanned);
        $scan = DokumenImbasan::query()->sole();
        $this->assertSame('bersih', $scan->keputusan);
        $this->assertSame($doc->id, $scan->dokumen_id);
        $this->assertSame($doc->cincangan_fail, $scan->cincangan_fail);
    }

    public function test_SRS_DMS_07_scanner_outage_fails_closed(): void
    {
        $user = $this->userWith();
        $this->scanner->failing = true;

        $this->upload($user, TestFiles::pdf())
            ->assertStatus(422)
            ->assertJsonPath('keputusan.0.sebab', 'imbasan_gagal');
        $this->assertSame(0, Dokumen::query()->count());
        $this->assertSame([], Storage::disk('dokumen')->allFiles());
    }

    public function test_SRS_DMS_08_quarantine_notifies_system_administrators_and_the_uploader(): void
    {
        $admin = $this->userWith(['dms.kuarantin.lihat']);
        $uploader = $this->userWith();
        $this->userWith(); // unrelated officer: not notified

        $this->upload($uploader, TestFiles::eicar());

        $sent = $this->notifier->of('dms.kuarantin');
        $this->assertCount(1, $sent);
        $this->assertEqualsCanonicalizing([$admin->id, $uploader->id], $sent[0]['recipients']);
        $this->assertSame('eicar.pdf', $sent[0]['data']['nama_fail']);
    }

    public function test_SRS_DMS_07_admin_lists_and_destroys_quarantined_files(): void
    {
        $admin = $this->userWith(['dms.kuarantin.lihat', 'dms.kuarantin.padam']);
        $this->upload($this->userWith(), TestFiles::eicar());
        $item = DokumenKuarantin::query()->sole();

        $this->actingAs($admin)->getJson('/api/v1/dms/kuarantin')->assertOk()->assertJsonPath('data.0.nama_fail', 'eicar.pdf');
        $this->actingAs($this->userWith())->getJson('/api/v1/dms/kuarantin')->assertForbidden();

        $this->actingAs($admin)->deleteJson("/api/v1/dms/kuarantin/{$item->id}")->assertOk();
        $this->assertSame([], Storage::disk('dokumen')->allFiles());
        $this->assertNotNull($item->refresh()->dimusnahkan_pada);
        $this->assertCount(1, $this->audit->of('musnah_kuarantin'));
    }

    /** Opt-in: runs against the real clamd when its socket is reachable. */
    public function test_SRS_DMS_07_real_clamd_detects_eicar(): void
    {
        $scanner = new ClamdScanner((string) config('dms.clamav.socket'));
        if (! $scanner->available()) {
            $this->markTestSkipped('clamd not reachable at '.config('dms.clamav.socket'));
        }

        $infected = tempnam(sys_get_temp_dir(), 'eicar');
        file_put_contents($infected, FakeVirusScanner::EICAR);
        $clean = tempnam(sys_get_temp_dir(), 'clean');
        file_put_contents($clean, 'Laporan bersih');

        $result = $scanner->scan($infected);
        $this->assertTrue($result->isInfected(), (string) $result->error);
        $this->assertStringContainsStringIgnoringCase('eicar', (string) $result->threat);
        $this->assertStringStartsWith('ClamAV', (string) $result->signatureVersion);
        $this->assertTrue($scanner->scan($clean)->isClean());

        // And through the full pipeline (acceptance 2): the EICAR file hidden inside a
        // valid DOCX passes every type check and is caught by clamd's archive scanning.
        $this->app->instance(VirusScanner::class, $scanner);
        $this->upload($this->userWith(), TestFiles::docx(name: 'laporan.docx', extraParts: ['word/media/nota.txt' => FakeVirusScanner::EICAR]))
            ->assertJsonPath('keputusan.0.sebab', 'virus');
        $this->assertSame(1, DokumenKuarantin::query()->count());

        @unlink($infected);
        @unlink($clean);
    }

    public function test_SRS_DMS_20_documents_are_encrypted_at_rest_with_per_file_keys_and_sha256(): void
    {
        $user = $this->userWith();
        $secret = 'Nilai penilaian RM 1,234,567 rahsia';
        $doc = $this->uploadDoc($user, TestFiles::fromString("%PDF-1.4\n{$secret}\n%%EOF", 'nilai.pdf'));
        $doc2 = $this->uploadDoc($user, TestFiles::fromString("%PDF-1.4\n{$secret}\n%%EOF", 'nilai2.pdf'));
        $version = $doc->versiSemasa;
        $raw = Storage::disk('dokumen')->get($version->kunci_storan);

        $this->assertStringNotContainsString('penilaian', $raw);
        $this->assertStringStartsWith('SDMS', $raw);
        $this->assertSame('AES-256-GCM', $version->algoritma);
        $this->assertSame(hash('sha256', "%PDF-1.4\n{$secret}\n%%EOF"), $version->cincangan_fail);
        $this->assertNotSame($version->kunci_data_bersulit, $doc2->versiSemasa->kunci_data_bersulit);

        $encryptor = app(EnvelopeEncryptor::class);
        $this->assertSame("%PDF-1.4\n{$secret}\n%%EOF", $encryptor->decryptString($raw, (string) $version->kunci_data_bersulit));
    }

    public function test_SRS_DMS_20_tampering_and_wrong_keys_are_detected(): void
    {
        $encryptor = new EnvelopeEncryptor('base64:'.base64_encode(str_repeat('a', 32)), '');
        $plain = random_bytes(3 * 1048576 + 123); // several chunks
        $enc = $encryptor->encryptString($plain);

        $this->assertSame($plain, $encryptor->decryptString($enc['ciphertext'], $enc['wrapped_key']));
        $this->assertSame(hash('sha256', $plain), $enc['sha256']);

        $tampered = $enc['ciphertext'];
        $tampered[100] = $tampered[100] === 'a' ? 'b' : 'a';
        $this->assertThrows(fn () => $encryptor->decryptString($tampered, $enc['wrapped_key']), \RuntimeException::class);

        $truncated = substr($enc['ciphertext'], 0, 1048576 + 200);
        $this->assertThrows(fn () => $encryptor->decryptString($truncated, $enc['wrapped_key']), \RuntimeException::class);

        $other = new EnvelopeEncryptor('base64:'.base64_encode(str_repeat('b', 32)), '');
        $this->assertThrows(fn () => $other->decryptString($enc['ciphertext'], $enc['wrapped_key']), \RuntimeException::class);

        $empty = $encryptor->encryptString('');
        $this->assertSame('', $encryptor->decryptString($empty['ciphertext'], $empty['wrapped_key']));
    }
}
