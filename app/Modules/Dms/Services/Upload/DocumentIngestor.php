<?php

namespace App\Modules\Dms\Services\Upload;

use App\Models\User;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Events\DocumentStored;
use App\Modules\Dms\Jobs\IndeksKandunganDokumen;
use App\Modules\Dms\Models\Dokumen;
use App\Modules\Dms\Models\DokumenImbasan;
use App\Modules\Dms\Models\DokumenKategori;
use App\Modules\Dms\Models\DokumenKuarantin;
use App\Modules\Dms\Models\DokumenVersi;
use App\Modules\Dms\Services\DmsSettings;
use App\Modules\Dms\Services\Lifecycle\QuotaService;
use App\Modules\Dms\Services\MetadataNormalizer;
use App\Modules\Dms\Services\Recipients;
use App\Modules\Dms\Services\Scanning\ScanResult;
use App\Modules\Dms\Services\Scanning\VirusScanner;
use App\Modules\Dms\Services\Storage\DocumentVault;
use App\Modules\Ntf\Contracts\Notifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The upload pipeline (SRS Modul 11 process steps 2–6):
 * inspect → transaction & quota limits → antivirus in temp storage →
 * (infected: encrypted quarantine + audit + alert) → encrypt + store →
 * DB rows → audit → queue text/OCR indexing.
 */
class DocumentIngestor
{
    /** Bytes accepted per transaction within this PHP process (requests without an id_transaksi). */
    private array $localTransactions = [];

    public function __construct(
        private FileInspector $inspector,
        private VirusScanner $scanner,
        private DocumentVault $vault,
        private MetadataNormalizer $metadata,
        private DmsSettings $settings,
        private QuotaService $quota,
        private AuditLogger $audit,
        private Notifier $notifier,
        private Recipients $recipients,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata  core fields (tajuk, tarikh_dokumen, kod_daerah…) + extra fields
     *
     * @throws DocumentRejected
     * @throws ValidationException
     */
    public function ingest(
        UploadedFile $file,
        DokumenKategori $kategori,
        User $uploader,
        ?string $ownerType,
        ?string $ownerId,
        ?string $classification = null,
        array $metadata = [],
        ?string $transaction = null,
    ): Dokumen {
        $meta = $this->metadata->normalize($metadata, $kategori);
        $checked = $this->check($file, $kategori, $uploader, $ownerType, $ownerId, $transaction);

        $id = (string) Str::uuid();
        $key = $this->objectKey($id, 1);
        $stored = $this->vault->put($key, $checked['path']);

        try {
            $doc = DB::transaction(function () use ($id, $key, $stored, $checked, $kategori, $uploader, $ownerType, $ownerId, $classification, $meta) {
                $core = $meta['core'];
                $doc = new Dokumen;
                $doc->id = $id;
                $doc->forceFill([
                    'no_rujukan' => $this->nextReference(),
                    'kategori_id' => $kategori->id,
                    'modul_sumber' => $core['modul_sumber'] ?? 'dms',
                    'jenis_entiti_induk' => $ownerType,
                    'id_rekod_induk' => $ownerId,
                    'pautan_induk' => $core['pautan_induk'] ?? null,
                    'tajuk' => ($core['tajuk'] ?? null) ?: pathinfo($checked['name'], PATHINFO_FILENAME),
                    'nama_fail' => $checked['name'],
                    'jenis_mime' => $checked['mime'],
                    'sambungan' => $checked['extension'],
                    'saiz_bait' => $stored['size'],
                    'cincangan_fail' => $stored['sha256'],
                    'klasifikasi_keselamatan' => $classification ?: $kategori->klasifikasi_lalai,
                    'status' => 'aktif',
                    'tarikh_dokumen' => $core['tarikh_dokumen'] ?? null,
                    'tarikh_luput' => $core['tarikh_luput'] ?? null,
                    'tahun' => $core['tahun'] ?? (int) now()->format('Y'),
                    'kod_daerah' => $core['kod_daerah'] ?? null,
                    'kod_mukim' => $core['kod_mukim'] ?? null,
                    'pemilik_proses_id' => $core['pemilik_proses_id'] ?? $uploader->id,
                    'id_pencipta' => $uploader->id,
                    'versi_semasa' => 1,
                    'kata_kunci' => $core['kata_kunci'] ?? [],
                    'metadata' => $meta['extra'],
                    'status_indeks' => 'menunggu',
                ])->save();

                $this->createVersion($doc, 1, $key, $stored, $checked, $uploader, null);
                $this->recordScan($checked['scan'], $checked, $doc->id, null);

                return $doc;
            });
        } catch (Throwable $e) {
            $this->vault->delete($key);
            throw $e;
        }

        $this->accept($checked, $uploader, $transaction);
        $this->audit->record('muat_naik', 'dms', 'dokumen', $doc->id, meta: [
            'nama_fail' => $doc->nama_fail, 'saiz_bait' => $doc->saiz_bait, 'cincangan' => $doc->cincangan_fail,
            'versi' => 1, 'kategori' => $kategori->kod, 'klasifikasi' => $doc->klasifikasi_keselamatan,
            'induk' => $ownerType ? $ownerType.':'.$ownerId : null,
        ]);
        $this->afterStore($doc, 1);

        return $doc;
    }

    /**
     * SRS-DMS-14: a new version never overwrites the previous one.
     *
     * @throws DocumentRejected
     */
    public function addVersion(Dokumen $doc, UploadedFile $file, User $uploader, ?string $note = null, ?string $transaction = null): DokumenVersi
    {
        $checked = $this->check($file, $doc->kategori, $uploader, $doc->jenis_entiti_induk, $doc->id_rekod_induk, $transaction);

        $number = $doc->versi_semasa + 1;
        $key = $this->objectKey($doc->id, $number);
        $stored = $this->vault->put($key, $checked['path']);

        try {
            $version = DB::transaction(function () use ($doc, $number, $key, $stored, $checked, $uploader, $note) {
                $locked = Dokumen::query()->lockForUpdate()->findOrFail($doc->id);
                if ($locked->versi_semasa + 1 !== $number) {
                    throw new \RuntimeException('Concurrent version upload.');
                }
                $version = $this->createVersion($locked, $number, $key, $stored, $checked, $uploader, $note);
                $locked->forceFill([
                    'versi_semasa' => $number,
                    'nama_fail' => $checked['name'],
                    'jenis_mime' => $checked['mime'],
                    'sambungan' => $checked['extension'],
                    'saiz_bait' => $stored['size'],
                    'cincangan_fail' => $stored['sha256'],
                    'status_indeks' => 'menunggu',
                ])->save();
                $this->recordScan($checked['scan'], $checked, $locked->id, null);

                return $version;
            });
        } catch (Throwable $e) {
            $this->vault->delete($key);
            throw $e;
        }

        $this->accept($checked, $uploader, $transaction);
        $doc->refresh();
        $this->audit->record('versi_baharu', 'dms', 'dokumen', $doc->id, changes: [
            'versi_semasa' => [$number - 1, $number],
        ], meta: ['nama_fail' => $checked['name'], 'saiz_bait' => $stored['size'], 'cincangan' => $stored['sha256'], 'catatan' => $note]);
        $this->afterStore($doc, $number);

        return $version;
    }

    /**
     * Steps 2–3: validation, limits, antivirus. Returns the checked temp file.
     *
     * @return array{path: string, name: string, extension: string, mime: string, size: int, sha256: string, scan: ScanResult}
     *
     * @throws DocumentRejected
     */
    private function check(UploadedFile $file, DokumenKategori $kategori, User $uploader, ?string $ownerType, ?string $ownerId, ?string $transaction): array
    {
        $name = $this->inspector->displayName($file->getClientOriginalName());

        if (! $file->isValid()) {
            throw in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? new DocumentRejected(__('dms::mesej.ditolak.terlalu_besar', ['nama' => $name, 'saiz' => '?', 'had' => (int) ini_get('upload_max_filesize'), 'kategori' => $kategori->nama]), 'terlalu_besar')
                : new DocumentRejected(__('dms::mesej.ditolak.fail_rosak', ['nama' => $name]), 'fail_rosak');
        }

        $path = (string) $file->getRealPath();
        $size = (int) filesize($path);
        $type = $this->inspector->inspect($path, $file->getClientOriginalName(), $size, $kategori);

        $limit = $this->settings->transactionLimitMb() * 1048576;
        if ($this->transactionBytes($uploader, $transaction) + $size > $limit) {
            throw new DocumentRejected(__('dms::mesej.ditolak.had_transaksi', ['nama' => $name, 'had' => $this->settings->transactionLimitMb()]), 'had_transaksi');
        }
        if ($this->quota->wouldExceed($size)) {
            throw new DocumentRejected(__('dms::mesej.ditolak.kuota_penuh', ['nama' => $name]), 'kuota_penuh');
        }

        $sha = (string) hash_file('sha256', $path);
        $checked = [...$type, 'path' => $path, 'name' => $name, 'size' => $size, 'sha256' => $sha];

        // SRS-DMS-07: scan in temp storage, before anything is persisted.
        $scan = $this->scanner->scan($path);
        if ($scan->isInfected()) {
            $this->quarantine($checked, $scan, $uploader, $ownerType, $ownerId);
            throw new DocumentRejected(__('dms::mesej.ditolak.virus', ['nama' => $name]), 'virus');
        }
        if (! $scan->isClean()) {
            $this->recordScan($scan, $checked, null, null);
            if ($this->settings->scanRequired()) {
                $this->audit->record('imbasan', 'dms', 'dokumen', null, 'gagal', $scan->error, meta: ['nama_fail' => $name, 'cincangan' => $sha]);
                throw new DocumentRejected(__('dms::mesej.ditolak.imbasan_gagal', ['nama' => $name]), 'imbasan_gagal');
            }
        }

        return [...$checked, 'scan' => $scan];
    }

    /** @param array{path: string, name: string, size: int, sha256: string} $checked */
    private function quarantine(array $checked, ScanResult $scan, User $uploader, ?string $ownerType, ?string $ownerId): void
    {
        $key = 'kuarantin/'.now()->format('Y/m').'/'.Str::uuid().'.enc';
        $stored = $this->vault->put($key, $checked['path']);

        $item = DokumenKuarantin::query()->create([
            'nama_fail' => $checked['name'],
            'saiz_bait' => $checked['size'],
            'cincangan_fail' => $checked['sha256'],
            'kunci_storan' => $key,
            'kunci_data_bersulit' => $stored['wrapped_key'],
            'id_kunci_induk' => $stored['key_id'],
            'nama_ancaman' => $scan->threat,
            'jenis_entiti_induk' => $ownerType,
            'id_rekod_induk' => $ownerId,
            'dimuat_naik_oleh' => $uploader->id,
        ]);
        $this->recordScan($scan, $checked, null, $item->id);

        $this->audit->record('kuarantin', 'dms', 'dokumen_kuarantin', $item->id, 'gagal', $scan->threat, meta: [
            'nama_fail' => $checked['name'], 'cincangan' => $checked['sha256'], 'enjin' => $scan->engine,
            'versi_tandatangan' => $scan->signatureVersion, 'induk' => $ownerType ? $ownerType.':'.$ownerId : null,
        ]);

        // SRS-DMS-08: system administrators + the uploader.
        $recipients = $this->recipients->withPermission('dms.kuarantin.lihat')
            ->push($uploader)
            ->unique('id')
            ->values();
        $this->notifier->send('dms.kuarantin', $recipients, [
            'nama_fail' => $checked['name'],
            'ancaman' => (string) $scan->threat,
            'pemuat_naik' => $uploader->name,
            'masa' => now()->format('d/m/Y H:i'),
        ], '/admin/dms/tetapan?tab=kuarantin', 'dms.kuarantin:'.$item->id);
    }

    /**
     * @param  array{wrapped_key: string, key_id: string, sha256: string, size: int}  $stored
     * @param  array{name: string, mime: string}  $checked
     */
    private function createVersion(Dokumen $doc, int $number, string $key, array $stored, array $checked, User $uploader, ?string $note): DokumenVersi
    {
        return DokumenVersi::query()->create([
            'dokumen_id' => $doc->id,
            'no_versi' => $number,
            'nama_fail' => $checked['name'],
            'jenis_mime' => $checked['mime'],
            'saiz_bait' => $stored['size'],
            'cincangan_fail' => $stored['sha256'],
            'kunci_storan' => $key,
            'kunci_data_bersulit' => $stored['wrapped_key'],
            'id_kunci_induk' => $stored['key_id'],
            'dimuat_naik_oleh' => $uploader->id,
            'catatan' => $note,
            'metadata_snapshot' => self::snapshot($doc),
        ]);
    }

    /** Metadata as it stood when a version was made — what "compare versions" diffs. */
    public static function snapshot(Dokumen $doc): array
    {
        return [
            'tajuk' => $doc->tajuk,
            'kategori_id' => $doc->kategori_id,
            'klasifikasi_keselamatan' => $doc->klasifikasi_keselamatan,
            'status' => $doc->status,
            'tarikh_dokumen' => $doc->tarikh_dokumen?->toDateString(),
            'tarikh_luput' => $doc->tarikh_luput?->toDateString(),
            'tahun' => $doc->tahun,
            'kod_daerah' => $doc->kod_daerah,
            'kod_mukim' => $doc->kod_mukim,
            'pemilik_proses_id' => $doc->pemilik_proses_id,
            'kata_kunci' => $doc->kata_kunci,
            'metadata' => $doc->metadata,
        ];
    }

    /** @param array{name: string, sha256: string} $checked */
    private function recordScan(ScanResult $scan, array $checked, ?string $docId, ?int $quarantineId): void
    {
        DokumenImbasan::query()->create([
            'dokumen_id' => $docId,
            'kuarantin_id' => $quarantineId,
            'nama_fail' => $checked['name'],
            'cincangan_fail' => $checked['sha256'],
            'enjin' => $scan->engine,
            'versi_tandatangan' => $scan->signatureVersion !== null ? substr($scan->signatureVersion, 0, 250) : null,
            'keputusan' => $scan->outcome,
            'nama_ancaman' => $scan->threat ?? ($scan->error !== null ? substr($scan->error, 0, 250) : null),
            'tarikh_imbasan' => now(),
        ]);
    }

    private function afterStore(Dokumen $doc, int $version): void
    {
        IndeksKandunganDokumen::dispatch($doc->id, $version)->afterCommit();
        DocumentStored::dispatch($doc->id, $version, $doc->jenis_entiti_induk, $doc->id_rekod_induk, $doc->klasifikasi_keselamatan);
        $this->quota->checkAndAlert();
    }

    private function objectKey(string $docId, int $version): string
    {
        return sprintf('dokumen/%s/%s/v%d-%s.enc', now()->format('Y/m'), $docId, $version, Str::random(12));
    }

    private function nextReference(): string
    {
        $seq = (int) DB::selectOne("select nextval('dokumen_no_rujukan_seq') as n")->n;

        return sprintf('DMS-%s-%06d', now()->format('Y'), $seq);
    }

    private function transactionBytes(User $user, ?string $transaction): int
    {
        $local = $this->localTransactions[$transaction ?? ''] ?? 0;

        return $transaction === null ? $local : (int) Cache::get($this->transactionKey($user, $transaction), 0);
    }

    /** @param array{size: int} $checked */
    private function accept(array $checked, User $user, ?string $transaction): void
    {
        if ($transaction === null) {
            $this->localTransactions[''] = ($this->localTransactions[''] ?? 0) + $checked['size'];

            return;
        }
        $key = $this->transactionKey($user, $transaction);
        Cache::put($key, (int) Cache::get($key, 0) + $checked['size'], now()->addHours(2));
    }

    public function resetLocalTransaction(): void
    {
        $this->localTransactions = [];
    }

    private function transactionKey(User $user, string $transaction): string
    {
        return 'dms:transaksi:'.$user->id.':'.sha1($transaction);
    }
}
