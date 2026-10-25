<?php

namespace Tests\Modules\Kba;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Kba\Ai\HashEmbeddingProvider;
use App\Modules\Kba\Contracts\EmbeddingProvider;
use App\Modules\Kba\Contracts\LlmGateway;
use App\Modules\Kba\Models\SumberDokumen;
use App\Modules\Kba\Services\Capaian;
use App\Modules\Kba\Services\Pengindeks;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Modules\Aud\Concerns\AssertsAuditTrail;
use Tests\Modules\Kba\Support\ArrayParameters;
use Tests\Modules\Kba\Support\FakeLlm;
use Tests\Modules\Kba\Support\FakeWorkflowEngine;
use Tests\Modules\Kba\Support\RecordingNotifier;
use Tests\TestCase;

/**
 * MOD-KBA test set-up: real RBC (factory permissions / roles / scopes), real
 * AUD, array parameters, the deterministic hashing embedder and a scripted
 * FakeLlm — no Ollama, no network (stray HTTP is refused). A small corpus:
 * a Dalaman user manual, a Sulit admin SOP and an Awam FAQ.
 */
abstract class KbaTestCase extends TestCase
{
    use AssertsAuditTrail, RefreshDatabase;

    protected ArrayParameters $params;

    protected FakeLlm $llm;

    protected RecordingNotifier $notifier;

    protected FakeWorkflowEngine $workflow;

    public const PENGGUNA = ['kba.soalan.lihat'];

    public const PENTADBIR = ['kba.soalan.lihat', 'kba.sumber.lihat', 'kba.sumber.kemas_kini', 'kba.pemetaan.kemas_kini', 'kba.analitik.lihat',
        'kba.maklum_balas.lihat', 'kba.maklum_balas.kemas_kini', 'kba.artikel.cipta', 'kba.artikel.kemas_kini', 'kba.keluaran.kemas_kini',
        'kba.kandungan.eksport', 'kba.sokongan.lihat'];

    public const MANUAL = <<<'MD'
# Manual Pengguna Ujian SISDATA

## 1. Log Masuk

Pengguna log masuk melalui SSO MAIS menggunakan akaun rasmi. Kata laluan tidak disimpan dalam SISDATA.

## 2. Muat Naik Dokumen

Had saiz fail dokumen pejabat ialah 25 MB dan fail spatial 250 MB. Setiap fail diimbas antivirus sebelum disimpan.

## 3. Kelulusan Pajakan

Permohonan pajakan diluluskan oleh Pelulus melalui aliran kerja dua peringkat (MOD-PJK).
MD;

    public const SOP_SULIT = <<<'MD'
# SOP Pentadbir Keselamatan

## 1. Kunci Penyulitan

Kunci penyulitan pangkalan data diputar setiap 90 hari oleh pentadbir keselamatan.
MD;

    public const FAQ_AWAM = <<<'MD'
# Soalan Lazim Portal Wakaf

## Waktu Operasi Kaunter

Kaunter MAIS dibuka dari 8.00 pagi hingga 5.00 petang pada hari bekerja.
MD;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Capaian::flush();
        $this->withHeader('Referer', 'http://localhost:3000');
        $this->app->instance(Parameters::class, $this->params = new ArrayParameters);
        $this->app->instance(EmbeddingProvider::class, new HashEmbeddingProvider(768));
        $this->app->instance(LlmGateway::class, $this->llm = new FakeLlm);
        $this->app->instance(Notifier::class, $this->notifier = new RecordingNotifier);
        $this->app->instance(WorkflowEngine::class, $this->workflow = new FakeWorkflowEngine);
        $this->params->values['kba.mod_jawapan'] = 'ekstraktif';
        $this->params->values['kba.had_prompt_seminit'] = 1000;
    }

    /**
     * @param  list<string>  $permissions
     * @param  array<string, list<string>>  $scopes
     */
    protected function user(array $permissions = self::PENGGUNA, ?string $role = null, array $scopes = []): User
    {
        $factory = User::factory()->withPermissions($permissions);
        if ($role !== null) {
            $factory = $factory->withRole($role);
        }
        foreach ($scopes as $type => $values) {
            $factory = $factory->withScope($type, $values);
        }

        return $factory->create(['role' => 'user', 'is_active' => true]);
    }

    /** @param array<string, mixed> $attributes */
    protected function sumber(string $markdown, array $attributes = []): SumberDokumen
    {
        $s = SumberDokumen::query()->create($attributes + [
            'kod' => 'muat_naik:'.Str::uuid(),
            'asal' => 'muat_naik',
            'jenis_dokumen' => 'manual_pengguna',
            'tajuk' => 'Manual Pengguna Ujian',
            'klasifikasi' => 'dalaman',
            'format' => 'markdown',
            'teks_sumber' => $markdown,
        ]);
        app(Pengindeks::class)->indeks($s, $markdown);

        return $s->refresh();
    }

    protected function korpus(): void
    {
        $this->sumber(self::MANUAL, ['tajuk' => 'Manual Pengguna Ujian', 'modul' => null]);
        $this->sumber(self::SOP_SULIT, ['tajuk' => 'SOP Pentadbir Keselamatan', 'jenis_dokumen' => 'SOP', 'klasifikasi' => 'sulit']);
        $this->sumber(self::FAQ_AWAM, ['tajuk' => 'Soalan Lazim Portal', 'jenis_dokumen' => 'manual_pengguna', 'klasifikasi' => 'awam']);
    }

    /** @return array<string, mixed> */
    protected function tanya(User $user, string $soalan, int $status = 200): array
    {
        return $this->actingAs($user)->postJson('/api/v1/kba/tanya', ['soalan' => $soalan])->assertStatus($status)->json('data');
    }
}
