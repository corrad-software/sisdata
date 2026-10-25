<?php

namespace App\Modules\Kba\Listeners;

use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Dms\Events\DocumentDisposed;
use App\Modules\Dms\Events\DocumentStored;
use App\Modules\Kba\Contracts\DocumentTextSource;
use App\Modules\Kba\Jobs\IndeksDokumenDms;
use App\Modules\Kba\Models\SumberDokumen;
use App\Modules\Kba\Services\Pengindeks;
use Illuminate\Support\Facades\Log;

/**
 * SRS-KBA-02: incremental indexing driven by MOD-DMS events. A document is
 * (re)indexed when it is already a registered source (any new version) or
 * its DMS category is one admins chose for automatic indexing
 * (kba.kategori_dms_automatik). Files KBA itself uploaded are already indexed
 * from the upload. A disposed document leaves the index.
 */
class PendengarDms
{
    public function __construct(private Parameters $parameters, private DocumentTextSource $dms, private Pengindeks $pengindeks) {}

    public function stored(DocumentStored $event): void
    {
        if ($event->ownerType === 'kba_sumber_dokumen') {
            return;
        }
        $registered = SumberDokumen::query()->where('kod', 'dms:'.$event->documentId)->exists();
        if (! $registered) {
            $categories = $this->parameters->get('kba.kategori_dms_automatik', ['laporan']);
            if (is_string($categories)) {
                $categories = json_decode($categories, true) ?? preg_split('/[\s,]+/', $categories);
            }
            $doc = $this->dms->find($event->documentId);
            if ($doc === null || ! in_array($doc['kategori'], (array) $categories, true)) {
                return;
            }
        }
        Log::info('KBA: indeks DMS dijadualkan', ['dokumen' => $event->documentId, 'versi' => $event->version]);
        IndeksDokumenDms::dispatch($event->documentId, $event->version)->afterCommit();
    }

    public function disposed(DocumentDisposed $event): void
    {
        foreach (SumberDokumen::query()->where('id_dokumen_dms', $event->documentId)->get() as $sumber) {
            $this->pengindeks->lapukkan($sumber, __('kba::kba.indeks.dms_tiada'));
        }
    }
}
