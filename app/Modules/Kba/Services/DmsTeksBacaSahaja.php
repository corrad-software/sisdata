<?php

namespace App\Modules\Kba\Services;

use App\Modules\Kba\Contracts\DocumentTextSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Default DocumentTextSource: a READ-ONLY query over MOD-DMS's `dokumen`
 * table (the text DMS already extracted / OCR'd, SRS-DMS-12). Never writes,
 * never imports DMS classes. MOD-DMS may bind its own implementation.
 */
class DmsTeksBacaSahaja implements DocumentTextSource
{
    public function find(string $documentId): ?array
    {
        if (! Schema::hasTable('dokumen') || ! preg_match('/^[0-9a-f-]{36}$/i', $documentId)) {
            return null;
        }
        $row = DB::table('dokumen as d')
            ->leftJoin('dokumen_kategori as k', 'k.id', '=', 'd.kategori_id')
            ->where('d.id', $documentId)
            ->whereNull('d.deleted_at')
            ->first(['d.id', 'd.tajuk', 'd.versi_semasa', 'k.kod as kategori', 'd.klasifikasi_keselamatan', 'd.kod_daerah', 'd.status', 'd.status_indeks', 'd.teks_kandungan', 'd.id_pencipta', 'd.tarikh_dokumen']);
        if ($row === null) {
            return null;
        }

        return [
            'id' => (string) $row->id,
            'tajuk' => (string) $row->tajuk,
            'versi' => (int) $row->versi_semasa,
            'kategori' => $row->kategori !== null ? (string) $row->kategori : null,
            'klasifikasi' => (string) $row->klasifikasi_keselamatan,
            'kod_daerah' => $row->kod_daerah !== null ? (string) $row->kod_daerah : null,
            'status' => (string) $row->status,
            'status_indeks' => (string) $row->status_indeks,
            'teks' => $row->teks_kandungan !== null ? (string) $row->teks_kandungan : null,
            'id_pencipta' => $row->id_pencipta !== null ? (int) $row->id_pencipta : null,
            'tarikh_dokumen' => $row->tarikh_dokumen !== null ? (string) $row->tarikh_dokumen : null,
        ];
    }
}
