<?php

namespace App\Modules\Dms\Services;

use App\Models\User;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Dms\Models\Dokumen;
use App\Modules\Dms\Models\DokumenKategori;
use App\Modules\Dms\Services\Access\DocumentAccess;
use App\Modules\Dms\Services\Upload\DocumentIngestor;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * SRS-GEN-04 / SRS-DMS-18: the DocumentStore contract other modules use.
 * Same pipeline, access rules and audit as the /api/v1/dms endpoints.
 */
class DmsDocumentStore implements DocumentStore
{
    public function __construct(
        private DocumentIngestor $ingestor,
        private DocumentAccess $access,
        private DocumentFiles $files,
    ) {}

    public function store(
        UploadedFile $file,
        string $ownerType,
        string|int $ownerId,
        string $category,
        User $uploader,
        string $classification = 'dalaman',
        array $metadata = [],
    ): string {
        $kategori = DokumenKategori::query()->where('kod', $category)->where('aktif', true)->first();
        if ($kategori === null) {
            throw new DocumentRejected(__('dms::mesej.kategori_tidak_wujud', ['kategori' => $category]), 'kategori');
        }
        if (! in_array($classification, Dokumen::KLASIFIKASI, true)) {
            $classification = $kategori->klasifikasi_lalai;
        }

        try {
            return $this->ingestor->ingest($file, $kategori, $uploader, $ownerType, (string) $ownerId, $classification, $metadata)->id;
        } catch (ValidationException $e) {
            $first = collect($e->errors())->flatten()->first();
            throw new DocumentRejected(__('dms::mesej.ditolak.metadata', ['nama' => $file->getClientOriginalName(), 'ralat' => (string) $first]), 'metadata');
        }
    }

    public function forOwner(string $ownerType, string|int $ownerId, User $user): array
    {
        return $this->access->scopeVisible(Dokumen::query(), $user)
            ->with('kategori')
            ->where('jenis_entiti_induk', $ownerType)
            ->where('id_rekod_induk', (string) $ownerId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Dokumen $d) => [
                'id' => $d->id,
                'name' => $d->nama_fail,
                'category' => $d->kategori->kod,
                'version' => $d->versi_semasa,
                'classification' => $d->klasifikasi_keselamatan,
                'uploaded_at' => $d->created_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    public function temporaryUrl(string $documentId, User $user, int $minutes = 5): ?string
    {
        $doc = Dokumen::query()->find($documentId);
        if ($doc === null || $doc->status === 'dilupus' || ! $this->access->canView($user, $doc)) {
            return null;
        }

        return $this->files->signedUrl($doc, $user, DocumentFiles::MUAT_TURUN, null, $minutes);
    }
}
