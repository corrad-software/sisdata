<?php

namespace App\Modules\Adu\Services;

use App\Modules\Adm\Contracts\Integrations;
use App\Modules\Adu\Contracts\CentralComplaintSystem;
use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Models\PenyegerakanPusat;
use App\Modules\Adu\Support\Lalai;
use Illuminate\Contracts\Foundation\Application;
use Throwable;

/**
 * SRS-ADU-07 / acceptance #6: two-way exchange with the Sistem Aduan
 * berpusat MAIS. Inbound complaints enter the same queue (same intake,
 * triage, SLA; idempotent on the external id); every status change of
 * such a complaint is put in an outbox and pushed back, retried by
 * `adu:segerak-pusat` until delivered (SRS-INT-10 manual fallback: the
 * outbox is visible and retryable from the integration screen).
 */
class SegerakPusat
{
    public const KOD_INTEGRASI = 'adu.sistem_aduan_pusat';

    public const MAKS_CUBAAN = 10;

    public function __construct(private CentralComplaintSystem $remote, private Application $app) {}

    /** @return array{diterima: int, sedia_ada: int, gagal: int} */
    public function import(?string $since = null): array
    {
        $out = ['diterima' => 0, 'sedia_ada' => 0, 'gagal' => 0];
        $started = microtime(true);
        try {
            $items = $this->remote->fetchNew($since);
        } catch (Throwable $e) {
            $this->log(false, 'fetchNew', $e->getMessage(), $started, null, 'masuk');
            throw $e;
        }
        $service = $this->app->make(AduanService::class);
        foreach ($items as $item) {
            $existing = Aduan::query()->withoutGlobalScope('access')
                ->where('saluran_penerimaan', 'sistem_aduan_pusat')->where('rujukan_sumber', (string) $item['id_luaran'])->exists();
            try {
                $this->terimaSatu($service, $item);
                $existing ? $out['sedia_ada']++ : $out['diterima']++;
            } catch (Throwable $e) {
                report($e);
                $out['gagal']++;
            }
        }
        $this->log($out['gagal'] === 0, 'fetchNew', null, $started, count($items), 'masuk');

        return $out;
    }

    /**
     * One inbound complaint into the common queue (idempotent on id_luaran).
     *
     * @param  array<string, mixed>  $item  see CentralComplaintSystem::fetchNew()
     */
    public function terimaSatu(AduanService $service, array $item): Aduan
    {
        $anon = (bool) ($item['tanpa_nama'] ?? false);
        $pengadu = $anon ? null : array_filter(['nama' => $item['nama'] ?? null, 'no_telefon' => $item['no_telefon'] ?? null, 'emel' => $item['emel'] ?? null]);

        return $service->terima([
            'kategori' => $item['kategori'] ?? null,
            'keterangan' => $item['keterangan'] ?? null,
            'kod_daerah' => $item['kod_daerah'] ?? null,
            'lat' => $item['lat'] ?? null,
            'lng' => $item['lng'] ?? null,
            'lokasi_teks' => $item['lokasi_teks'] ?? null,
        ], 'sistem_aduan_pusat', null, (string) $item['id_luaran'], $pengadu ?: null, $anon || ! $pengadu);
    }

    /** Queue (and try at once) a status push for a complaint that came from the central system. */
    public function catat(Aduan $aduan, ?string $kategoriResolusi = null, ?string $keterangan = null): void
    {
        if ($aduan->saluran_penerimaan !== 'sistem_aduan_pusat' || $aduan->rujukan_sumber === null) {
            return;
        }
        $row = PenyegerakanPusat::query()->create([
            'aduan_id' => $aduan->id,
            'id_aduan_sistem_luaran' => $aduan->rujukan_sumber,
            'arah' => 'keluar',
            'status_aduan' => $aduan->status,
            'muatan' => [
                'no_rujukan' => $aduan->no_rujukan_aduan,
                'status' => $aduan->status,
                'status_awam' => Lalai::STATUS_AWAM[$aduan->status] ?? $aduan->status,
                'kategori_resolusi' => $kategoriResolusi,
                'keterangan' => $keterangan,
                'tarikh' => now()->toIso8601String(),
            ],
            'status' => 'menunggu',
        ]);
        $this->cuba($row);
    }

    public function cuba(PenyegerakanPusat $row): bool
    {
        $started = microtime(true);
        try {
            /** @var array{no_rujukan: string, status: string, status_awam: string, kategori_resolusi?: string|null, keterangan?: string|null, tarikh: string} $payload */
            $payload = $row->muatan;
            $this->remote->pushStatus($row->id_aduan_sistem_luaran, $payload);
            $row->update(['status' => 'berjaya', 'cubaan' => $row->cubaan + 1, 'dihantar_pada' => now(), 'ralat_terakhir' => null]);
            $this->log(true, 'pushStatus', null, $started, 1);

            return true;
        } catch (Throwable $e) {
            $row->update(['status' => 'gagal', 'cubaan' => $row->cubaan + 1, 'ralat_terakhir' => mb_substr($e->getMessage(), 0, 500)]);
            $this->log(false, 'pushStatus', $e->getMessage(), $started, 1);

            return false;
        }
    }

    /** @return array{berjaya: int, gagal: int} */
    public function ulangCuba(): array
    {
        $out = ['berjaya' => 0, 'gagal' => 0];
        $rows = PenyegerakanPusat::query()->whereIn('status', ['menunggu', 'gagal'])->where('cubaan', '<', self::MAKS_CUBAAN)->orderBy('id')->get();
        foreach ($rows as $row) {
            $this->cuba($row) ? $out['berjaya']++ : $out['gagal']++;
        }

        return $out;
    }

    private function log(bool $ok, string $operation, ?string $message, float $started, ?int $records, string $direction = 'keluar'): void
    {
        if (! $this->app->bound(Integrations::class)) {
            return;
        }
        try {
            $this->app->make(Integrations::class)->log(self::KOD_INTEGRASI, $ok, null, (int) ((microtime(true) - $started) * 1000), $message, $operation, $records, [], $direction);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
