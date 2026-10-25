<?php

namespace App\Modules\Kba\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Kba\Contracts\DocumentTextSource;
use App\Modules\Kba\Models\Artikel;
use App\Modules\Kba\Models\SumberDokumen;
use App\Modules\Kba\Services\Ingest\PenukarTeks;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Registers and (re)indexes every kind of source (SRS-KBA-01/02):
 * project baseline documents from the repository, manuals / SOPs uploaded
 * by content admins (the file itself is kept by MOD-DMS), DMS documents
 * (text reused from DMS's own extraction) and published articles.
 */
class PengurusSumber
{
    public function __construct(
        private Pengindeks $pengindeks,
        private PenukarTeks $penukar,
        private DocumentTextSource $dms,
        private DocumentStore $store,
        private Parameters $parameters,
    ) {}

    /**
     * SRS-KBA-01: project baseline documents (config kba.baseline).
     *
     * @return list<array{laluan: string, id: int|null, status: string, segmen: int, baharu: int, guna_semula: int, dilangkau: bool}>
     */
    public function indeksBaseline(bool $paksa = false): array
    {
        $out = [];
        foreach ((array) config('kba.baseline', []) as $entry) {
            $laluan = (string) $entry['laluan'];
            $path = base_path($laluan);
            if (! is_file($path)) {
                $out[] = ['laluan' => $laluan, 'id' => null, 'status' => 'tiada_fail', 'segmen' => 0, 'baharu' => 0, 'guna_semula' => 0, 'dilangkau' => false];

                continue;
            }
            $sumber = SumberDokumen::query()->firstOrNew(['kod' => 'baseline:'.$laluan]);
            $sumber->fill([
                'asal' => 'baseline',
                'laluan_fail' => $laluan,
                'jenis_dokumen' => (string) ($entry['jenis'] ?? 'lain'),
                'tajuk' => (string) $entry['tajuk'],
                'modul' => $entry['modul'] ?? null,
                'klasifikasi' => (string) ($entry['klasifikasi'] ?? 'dalaman'),
                'senarai_peranan_dibenarkan' => array_values((array) ($entry['peranan'] ?? [])),
                'peraturan_seksyen' => array_values((array) ($entry['peraturan_seksyen'] ?? [])),
                'format' => 'markdown',
                'aktif' => true,
            ]);
            $sumber->save();
            $r = $this->pengindeks->indeks($sumber, (string) file_get_contents($path), paksa: $paksa);
            $out[] = ['laluan' => $laluan, 'id' => $sumber->id] + $r;
        }

        return $out;
    }

    /**
     * A manual / SOP / release note uploaded by a content admin. Markdown and
     * text are kept in KBA (DMS does not accept them); PDF / DOCX originals go
     * to MOD-DMS (antivirus, encryption, retention) and KBA keeps the
     * extracted, heading-structured text.
     *
     * @param  array{tajuk: string, jenis_dokumen: string, versi?: string|null, modul?: string|null, klasifikasi: string, senarai_peranan_dibenarkan?: array<array-key, string>|null, kod_daerah?: string|null}  $meta
     *
     * @throws DocumentRejected
     */
    public function daftarMuatNaik(UploadedFile $file, array $meta, User $user): SumberDokumen
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $format = PenukarTeks::FORMAT[$ext] ?? throw new RuntimeException(__('kba::kba.sumber.format_tidak_disokong'));
        $markdown = $this->penukar->fromFile((string) $file->getRealPath(), $ext);

        $sumber = SumberDokumen::query()->create([
            'kod' => 'muat_naik:'.Str::uuid(),
            'asal' => 'muat_naik',
            'jenis_dokumen' => $meta['jenis_dokumen'],
            'tajuk' => $meta['tajuk'],
            'versi' => $meta['versi'] ?? null,
            'modul' => isset($meta['modul']) ? strtolower((string) $meta['modul']) : null,
            'id_pemilik' => $user->id,
            'klasifikasi' => $meta['klasifikasi'],
            'senarai_peranan_dibenarkan' => array_values($meta['senarai_peranan_dibenarkan'] ?? []),
            'kod_daerah' => $meta['kod_daerah'] ?? null,
            'format' => $format,
            'teks_sumber' => $markdown,
            'status_indeks' => 'menunggu',
        ]);

        if (in_array($format, ['pdf', 'docx'], true)) {
            try {
                $docId = $this->store->store($file, 'kba_sumber_dokumen', $sumber->id, (string) $this->parameters->get('kba.kategori_dms', 'laporan'), $user, $meta['klasifikasi'], ['pautan_induk' => '/admin/kba/sumber/'.$sumber->id]);
                $sumber->forceFill(['id_dokumen_dms' => $docId])->saveQuietly();
            } catch (\Throwable $e) {
                $sumber->delete();
                throw $e;
            }
        }

        return $sumber;
    }

    /**
     * Register an existing DMS document as a source (or refresh it).
     *
     * @param  array{jenis_dokumen?: string|null, modul?: string|null, senarai_peranan_dibenarkan?: array<array-key, string>|null}  $meta
     */
    public function daftarDms(string $documentId, array $meta = [], ?User $user = null): ?SumberDokumen
    {
        $doc = $this->dms->find($documentId);
        if ($doc === null) {
            return null;
        }
        $sumber = SumberDokumen::query()->firstOrNew(['kod' => 'dms:'.$documentId]);
        $sumber->fill([
            'asal' => 'dms',
            'id_dokumen_dms' => $documentId,
            'jenis_dokumen' => $meta['jenis_dokumen'] ?? ($sumber->jenis_dokumen ?? 'dokumen_dms'),
            'tajuk' => $doc['tajuk'],
            'modul' => isset($meta['modul']) ? strtolower((string) $meta['modul']) : $sumber->modul,
            'id_pemilik' => $sumber->id_pemilik ?? $user->id ?? $doc['id_pencipta'],
            'klasifikasi' => $doc['klasifikasi'],
            'senarai_peranan_dibenarkan' => array_values($meta['senarai_peranan_dibenarkan'] ?? ($sumber->senarai_peranan_dibenarkan ?? [])),
            'kod_daerah' => $doc['kod_daerah'],
            'format' => 'teks',
            'aktif' => true,
        ]);
        $sumber->save();

        return $sumber;
    }

    /**
     * Index the current text of a DMS document. "menunggu" means DMS has not
     * finished extracting / OCR-ing yet — the caller retries later.
     *
     * @return array{status: string, segmen?: int}
     */
    public function indeksDms(SumberDokumen $sumber, ?int $versi = null, bool $paksa = false): array
    {
        $doc = $this->dms->find((string) $sumber->id_dokumen_dms);
        if ($doc === null || in_array($doc['status'], ['dilupus'], true)) {
            $this->pengindeks->lapukkan($sumber, __('kba::kba.indeks.dms_tiada'));

            return ['status' => 'lapuk'];
        }
        if ($versi !== null && $doc['versi'] > $versi) {
            return ['status' => 'versi_lebih_baharu']; // a newer version's own event will index it
        }
        if ($doc['status_indeks'] === 'menunggu') {
            return ['status' => 'menunggu'];
        }
        $sumber->forceFill(['klasifikasi' => $doc['klasifikasi'], 'kod_daerah' => $doc['kod_daerah'], 'tajuk' => $doc['tajuk'], 'aktif' => true])->saveQuietly();
        if (in_array($doc['status_indeks'], ['gagal', 'tiada'], true) || trim((string) $doc['teks']) === '') {
            $sumber->forceFill(['status_indeks' => 'tiada_teks', 'mesej_indeks' => __('kba::kba.indeks.dms_tiada_teks'), 'versi' => 'v'.$doc['versi']])->saveQuietly();

            return ['status' => 'tiada_teks'];
        }

        return $this->pengindeks->indeks($sumber, $this->penukar->plainToMarkdown((string) $doc['teks']), 'v'.$doc['versi'], $paksa, $sumber->asal === 'dms' ? $sumber->id_dokumen_dms : null);
    }

    /** @return array{status: string, segmen?: int} */
    public function indeksSemula(SumberDokumen $sumber, bool $paksa = true): array
    {
        return match ($sumber->asal) {
            'baseline' => is_file(base_path((string) $sumber->laluan_fail))
                ? $this->pengindeks->indeks($sumber, (string) file_get_contents(base_path((string) $sumber->laluan_fail)), paksa: $paksa)
                : ['status' => 'tiada_fail'],
            'muat_naik' => $this->pengindeks->indeks($sumber, (string) $sumber->teks_sumber, $sumber->versi, $paksa),
            'dms' => $this->indeksDms($sumber, null, $paksa),
            'artikel' => ($a = Artikel::query()->find((int) Str::after($sumber->kod, 'artikel:'))) !== null && $a->status !== 'lapuk' && $a->versi_terbit !== null
                ? $this->indeksArtikel($a, $paksa)
                : ['status' => 'tiada'],
            default => ['status' => 'tiada'],
        };
    }

    /** SRS-KBA-13: only a PUBLISHED article reaches the index — drafts never do. */
    public function indeksArtikel(Artikel $artikel, bool $paksa = false): array
    {
        $versi = $artikel->versi_terbit ?? $artikel->versi;
        $kandungan = $artikel->kandungan;
        $tajuk = $artikel->tajuk;
        if ($artikel->versi_terbit !== null && $artikel->versi_terbit !== $artikel->versi) {
            $row = $artikel->sejarahVersi()->where('nombor_versi', $artikel->versi_terbit)->first();
            if ($row !== null) {
                [$kandungan, $tajuk] = [$row->kandungan, $row->tajuk];
            }
        }

        $sumber = SumberDokumen::query()->firstOrNew(['kod' => 'artikel:'.$artikel->id]);
        $sumber->fill([
            'asal' => 'artikel',
            'jenis_dokumen' => $artikel->sumber_asal === 'tiket' ? 'artikel_tiket' : 'artikel',
            'tajuk' => $tajuk,
            'modul' => $artikel->modul,
            'id_pemilik' => $artikel->id_pemilik,
            'klasifikasi' => $artikel->klasifikasi,
            'senarai_peranan_dibenarkan' => $artikel->senarai_peranan_dibenarkan,
            'format' => 'markdown',
            'aktif' => true,
        ]);
        $sumber->save();

        return $this->pengindeks->indeks($sumber, "# {$tajuk}\n\n".$kandungan, 'v'.$versi, $paksa, versiSistem: $artikel->versi_sistem_dirujuk);
    }

    public function lapukArtikel(Artikel $artikel): void
    {
        $sumber = SumberDokumen::query()->where('kod', 'artikel:'.$artikel->id)->first();
        if ($sumber !== null) {
            $this->pengindeks->lapukkan($sumber, __('kba::kba.artikel.lapuk'));
        }
    }
}
