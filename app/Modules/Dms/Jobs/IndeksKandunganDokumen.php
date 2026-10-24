<?php

namespace App\Modules\Dms\Jobs;

use App\Modules\Dms\Models\Dokumen;
use App\Modules\Dms\Services\DmsSettings;
use App\Modules\Dms\Services\Storage\DocumentVault;
use App\Modules\Dms\Services\Text\TextExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SRS-DMS-12: extract text / OCR the current version into dokumen.teks_kandungan,
 * which feeds the generated `carian` tsvector. Queued — OCR of a 30-page scan
 * takes minutes.
 */
class IndeksKandunganDokumen implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 900;

    public function __construct(public string $documentId, public int $version) {}

    public function handle(DocumentVault $vault, TextExtractor $extractor, DmsSettings $settings): void
    {
        $doc = Dokumen::query()->with('versiSemasa')->find($this->documentId);
        $version = $doc?->versiSemasa;
        // A newer version supersedes this job; a disposed one has no content.
        if ($doc === null || $version === null || $version->no_versi !== $this->version || $version->kunci_storan === null || $version->kunci_data_bersulit === null) {
            return;
        }

        $path = null;
        try {
            $path = $vault->toTempFile($version->kunci_storan, $version->kunci_data_bersulit);
            $result = $extractor->extract($path, $doc->sambungan, $settings->ocrLanguages());

            $doc->forceFill($result === null
                ? ['teks_kandungan' => null, 'status_indeks' => 'tiada', 'kaedah_indeks' => null]
                : ['teks_kandungan' => $result['text'], 'status_indeks' => 'selesai', 'kaedah_indeks' => $result['method']]
            )->saveQuietly();
        } catch (Throwable $e) {
            Log::warning('DMS indexing failed', ['dokumen' => $doc->id, 'error' => $e->getMessage()]);
            $doc->forceFill(['status_indeks' => 'gagal'])->saveQuietly();
            if ($this->attempts() < $this->tries) {
                throw $e;
            }
        } finally {
            if ($path !== null) {
                @unlink($path);
            }
        }
    }
}
