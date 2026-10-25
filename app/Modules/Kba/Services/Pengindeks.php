<?php

namespace App\Modules\Kba\Services;

use App\Modules\Kba\Ai\Vektor;
use App\Modules\Kba\Contracts\EmbeddingProvider;
use App\Modules\Kba\Models\SumberDokumen;
use App\Modules\Kba\Services\Ingest\Chunker;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Indexing (SRS-KBA-01/02/06/08). Markdown → segments → local embeddings →
 * kba_segmen, with every segment carrying classification, allowed roles and
 * district. Incremental: unchanged content is skipped; a changed document
 * gets a new version whose segments replace the old ones (old ones become
 * "lapuk", kept for cited history), and unchanged chunks reuse their stored
 * embedding instead of calling the model again.
 */
class Pengindeks
{
    public function __construct(private Chunker $chunker, private EmbeddingProvider $embedder) {}

    /**
     * @return array{status: string, segmen: int, baharu: int, guna_semula: int, dilangkau: bool}
     */
    public function indeks(SumberDokumen $sumber, string $markdown, ?string $versi = null, bool $paksa = false, ?string $idDokumenDms = null, ?string $versiSistem = null): array
    {
        $hash = hash('sha256', $markdown);
        // Unchanged content is skipped — unless some segments still lack an
        // embedding from the current model (model was down, or replaced).
        $stale = DB::table('kba_segmen')->where('id_sumber', $sumber->id)->where('status', 'aktif')
            ->where(fn ($q) => $q->whereNull('embedding')->orWhere('model_embedding', '<>', $this->embedder->model()))->exists();
        if (! $paksa && ! $stale && $sumber->cincangan_kandungan === $hash && $sumber->status_indeks === 'selesai' && ($versi === null || $versi === $sumber->versi)) {
            $this->kemasKiniMetadata($sumber);

            return ['status' => 'selesai', 'segmen' => $sumber->bilangan_segmen, 'baharu' => 0, 'guna_semula' => 0, 'dilangkau' => true];
        }

        $sumber->forceFill(['status_indeks' => 'sedang', 'mesej_indeks' => null])->saveQuietly();

        try {
            $chunks = $this->chunker->chunk($markdown);
            if ($chunks === []) {
                DB::table('kba_segmen')->where('id_sumber', $sumber->id)->where('status', 'aktif')->update(['status' => 'lapuk', 'updated_at' => now()]);
                $sumber->forceFill([
                    'status_indeks' => 'tiada_teks', 'mesej_indeks' => __('kba::kba.indeks.tiada_teks'), 'bilangan_segmen' => 0,
                    'cincangan_kandungan' => $hash, 'tarikh_indeks_akhir' => now(),
                ])->saveQuietly();

                return ['status' => 'tiada_teks', 'segmen' => 0, 'baharu' => 0, 'guna_semula' => 0, 'dilangkau' => false];
            }

            $model = $this->embedder->model();
            $existing = [];
            foreach (DB::table('kba_segmen')->where('id_sumber', $sumber->id)->where('model_embedding', $model)->whereNotNull('embedding')
                ->select('cincangan', DB::raw('embedding::text AS v'))->get() as $row) {
                $existing[$row->cincangan] = $row->v;
            }

            $rows = [];
            $missing = [];
            $modulByPath = [];
            foreach ($chunks as $i => $c) {
                $embedText = $sumber->tajuk.($c['laluan_seksyen'] ? ' — '.$c['laluan_seksyen'] : '')."\n".$c['kandungan_teks'];
                $cincangan = hash('sha256', $embedText);
                [$klasifikasi, $peranan] = $this->peraturan($sumber, $c['laluan_seksyen']);
                $modul = $sumber->modul ?? $this->modul($c['laluan_seksyen'].' '.mb_substr($c['kandungan_teks'], 0, 400));
                $key = (string) $c['laluan_seksyen'];
                if ($modul !== null && $key !== '') {
                    $modulByPath[$key] ??= $modul;
                }
                $rows[$i] = [
                    'id_sumber' => $sumber->id,
                    'urutan' => $i + 1,
                    'nombor_seksyen' => $c['nombor_seksyen'] !== null ? mb_substr($c['nombor_seksyen'], 0, 40) : null,
                    'tajuk_seksyen' => $c['tajuk_seksyen'],
                    'laluan_seksyen' => $c['laluan_seksyen'],
                    'kandungan_teks' => $c['kandungan_teks'],
                    'cincangan' => $cincangan,
                    'klasifikasi' => $klasifikasi,
                    'senarai_peranan_dibenarkan' => json_encode($peranan),
                    'kod_daerah' => $sumber->kod_daerah,
                    'id_dokumen_dms' => $idDokumenDms,
                    'versi_sistem_dirujuk' => $versiSistem ?? $this->versiSistem($c['kandungan_teks']),
                    'modul' => $modul ?? ($modulByPath[$key] ?? null),
                    'mengandungi_arahan' => PengawalPrompt::detect($c['kandungan_teks']),
                    'status' => 'aktif',
                    'model_embedding' => null,
                    'embedding' => null,
                    '_teks' => $embedText,
                ];
                if (isset($existing[$cincangan])) {
                    $rows[$i]['embedding'] = $existing[$cincangan];
                    $rows[$i]['model_embedding'] = $model;
                } else {
                    $missing[] = $i;
                }
            }

            $reused = count($rows) - count($missing);
            $mesej = null;
            try {
                foreach (array_chunk($missing, (int) config('kba.embedding.saiz_kelompok', 16)) as $batch) {
                    $vectors = $this->embedder->embed(array_map(fn (int $i) => $rows[$i]['_teks'], $batch), 'dokumen');
                    foreach ($batch as $j => $i) {
                        $rows[$i]['embedding'] = Vektor::literal($vectors[$j]);
                        $rows[$i]['model_embedding'] = $model;
                    }
                }
            } catch (Throwable $e) {
                report($e);
                $mesej = __('kba::kba.indeks.tanpa_embedding');
            }

            $no = $sumber->no_versi + 1;
            DB::transaction(function () use ($sumber, $rows, $no) {
                DB::table('kba_segmen')->where('id_sumber', $sumber->id)->where('status', 'aktif')->update(['status' => 'lapuk', 'updated_at' => now()]);
                $now = now();
                foreach (array_chunk(array_values($rows), 100) as $chunk) {
                    DB::table('kba_segmen')->insert(array_map(function (array $r) use ($no, $now) {
                        unset($r['_teks']);

                        return $r + ['no_versi_sumber' => $no, 'created_at' => $now, 'updated_at' => $now];
                    }, $chunk));
                }
            });

            $sumber->forceFill([
                'no_versi' => $no,
                'versi' => $versi ?? ($sumber->asal === 'baseline' || $sumber->asal === 'muat_naik' ? 'v'.$no : $sumber->versi),
                'cincangan_kandungan' => $hash,
                'status_indeks' => 'selesai',
                'mesej_indeks' => $mesej,
                'bilangan_segmen' => count($rows),
                'tarikh_indeks_akhir' => now(),
            ])->saveQuietly();

            return ['status' => 'selesai', 'segmen' => count($rows), 'baharu' => count($rows) - $reused, 'guna_semula' => $reused, 'dilangkau' => false];
        } catch (Throwable $e) {
            report($e);
            $sumber->forceFill(['status_indeks' => 'gagal', 'mesej_indeks' => mb_substr($e->getMessage(), 0, 480)])->saveQuietly();

            return ['status' => 'gagal', 'segmen' => 0, 'baharu' => 0, 'guna_semula' => 0, 'dilangkau' => false];
        }
    }

    /** Re-apply classification / roles / district to the active segments after a metadata change (no re-embedding). */
    public function kemasKiniMetadata(SumberDokumen $sumber): int
    {
        $n = 0;
        foreach (DB::table('kba_segmen')->where('id_sumber', $sumber->id)->where('status', 'aktif')->get(['id', 'laluan_seksyen']) as $row) {
            [$klasifikasi, $peranan] = $this->peraturan($sumber, $row->laluan_seksyen);
            $n += DB::table('kba_segmen')->where('id', $row->id)->update([
                'klasifikasi' => $klasifikasi,
                'senarai_peranan_dibenarkan' => json_encode($peranan),
                'kod_daerah' => $sumber->kod_daerah,
                'updated_at' => now(),
            ]);
        }

        return $n;
    }

    /** Take a source out of retrieval (article made obsolete, DMS document disposed, source deactivated). */
    public function lapukkan(SumberDokumen $sumber, ?string $mesej = null): void
    {
        DB::table('kba_segmen')->where('id_sumber', $sumber->id)->where('status', 'aktif')->update(['status' => 'lapuk', 'updated_at' => now()]);
        $sumber->forceFill(['aktif' => false, 'bilangan_segmen' => 0, 'mesej_indeks' => $mesej])->saveQuietly();
    }

    /**
     * Section rules: a rule whose `padanan` occurs in the heading path raises
     * the classification and/or sets the allowed roles (SRS-KBA-08).
     *
     * @return array{0: string, 1: list<string>}
     */
    private function peraturan(SumberDokumen $sumber, ?string $laluan): array
    {
        $klasifikasi = $sumber->klasifikasi;
        $peranan = $sumber->senarai_peranan_dibenarkan;
        foreach ($sumber->peraturan_seksyen as $rule) {
            $padanan = $rule['padanan'];
            if ($padanan !== '' && $laluan !== null && mb_stripos($laluan, $padanan) !== false) {
                if (isset($rule['klasifikasi'])) {
                    $klasifikasi = Capaian::max($klasifikasi, (string) $rule['klasifikasi']);
                }
                if (! empty($rule['peranan'])) {
                    $peranan = array_map('strval', $rule['peranan']);
                }
            }
        }

        return [$klasifikasi, array_values($peranan)];
    }

    private function modul(string $text): ?string
    {
        return preg_match('/\bMOD-([A-Z]{3})\b/', $text, $m) ? strtolower($m[1]) : null;
    }

    private function versiSistem(string $text): ?string
    {
        return preg_match('/\b(?:versi|version|keluaran|release)\s+v?(\d+\.\d+(?:\.\d+)?)\b/iu', $text, $m) ? $m[1] : null;
    }
}
