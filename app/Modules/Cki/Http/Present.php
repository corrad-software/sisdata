<?php

namespace App\Modules\Cki\Http;

use App\Modules\Cki\Models\Cukai;
use App\Modules\Cki\Models\Kelulusan;
use App\Modules\Cki\Models\Konflik;
use App\Modules\Cki\Models\Liputan;
use App\Modules\Cki\Models\Obligasi;
use App\Modules\Cki\Models\Resit;
use App\Modules\Cki\Models\Transaksi;
use App\Modules\Cki\Support\Kod;
use App\Modules\Cki\Support\Wang;

/** JSON shapes for the MOD-CKI API — money as {nilai: DECIMAL string, rm: "RM 1,234.50"}, labels in Malay. */
final class Present
{
    /** @return array{nilai: string|null, rm: string} */
    public static function wang(?string $v): array
    {
        return ['nilai' => $v === null ? null : Wang::norm($v), 'rm' => $v === null ? '—' : Wang::rm($v)];
    }

    /** @return array<string, mixed> */
    public static function obligasi(Obligasi $o, ?string $noLot = null): array
    {
        return [
            'id' => $o->id, 'lot_id' => $o->lot_id, 'no_lot' => $noLot ?? $o->getAttribute('no_lot'), 'id_premis' => $o->id_premis,
            'jenis_cukai' => $o->jenis_cukai, 'jenis_cukai_nama' => Kod::JENIS_CUKAI[$o->jenis_cukai] ?? $o->jenis_cukai,
            'pihak_pengeluar' => $o->pihak_pengeluar, 'pihak_pengeluar_nama' => Kod::PIHAK_PENGELUAR[$o->pihak_pengeluar] ?? $o->pihak_pengeluar,
            'kod_pbt' => $o->kod_pbt, 'kod_ptd' => $o->kod_ptd, 'kod_agensi' => $o->kod_agensi, 'nama_agensi' => $o->agensi?->nama_agensi,
            'mod_agensi' => $o->agensi?->mod_semasa, 'no_akaun_cukai' => $o->no_akaun_cukai, 'status_liputan_api' => $o->status_liputan_api,
            'kod_daerah' => $o->kod_daerah, 'kod_mukim' => $o->kod_mukim, 'aktif' => $o->aktif, 'sintetik' => $o->sintetik, 'catatan' => $o->catatan,
        ];
    }

    /** @return array<string, mixed> */
    public static function bil(Cukai $c, ?string $noLot = null): array
    {
        return [
            'id' => $c->id, 'lot_id' => $c->lot_id, 'no_lot' => $noLot ?? $c->getAttribute('no_lot'), 'obligasi_id' => $c->obligasi_id,
            'jenis_cukai' => $c->jenis, 'jenis_cukai_nama' => Kod::JENIS_CUKAI[$c->jenis] ?? $c->jenis,
            'no_bil' => $c->no_bil, 'tahun_taksiran' => $c->tahun_taksiran, 'pihak_pengeluar' => $c->pihak_pengeluar,
            'pihak_pengeluar_nama' => Kod::PIHAK_PENGELUAR[$c->pihak_pengeluar ?? ''] ?? $c->pihak_pengeluar, 'kod_agensi' => $c->kod_agensi,
            'kod_pbt' => $c->kod_pbt, 'kod_daerah' => $c->kod_daerah, 'kod_mukim' => $c->kod_mukim,
            'amaun_pokok' => self::wang($c->amaun_pokok), 'amaun_denda' => self::wang($c->amaun_denda), 'jumlah' => self::wang($c->jumlahPerlu()),
            'amaun_dibayar' => self::wang($c->amaun_dibayar), 'baki' => self::wang($c->baki()),
            'tarikh_bil' => $c->tarikh_bil?->format('Y-m-d'), 'tarikh_akhir' => $c->tarikh_akhir?->format('Y-m-d'), 'tarikh_bayar' => $c->tarikh_bayar?->format('Y-m-d'),
            'status_bayaran' => $c->status_bayaran, 'status_bayaran_nama' => Kod::STATUS_BAYARAN[$c->status_bayaran] ?? $c->status_bayaran,
            'kaedah_bayaran' => $c->kaedah_bayaran, 'kaedah_bayaran_nama' => $c->kaedah_bayaran ? (Kod::KAEDAH_BAYARAN[$c->kaedah_bayaran] ?? $c->kaedah_bayaran) : null,
            'no_resit' => $c->no_resit, 'sumber_rekod' => $c->sumber_rekod, 'sumber_rekod_nama' => Kod::SUMBER_REKOD[$c->sumber_rekod] ?? $c->sumber_rekod,
            'disegerak_pada' => $c->disegerak_pada?->toIso8601String(), 'denda_dikenakan' => $c->denda_dikenakan, 'sintetik' => $c->sintetik, 'versi' => $c->versi,
        ];
    }

    /** @return array<string, mixed> */
    public static function transaksi(Transaksi $t, ?Kelulusan $k = null): array
    {
        $k ??= $t->kelulusan;

        return [
            'id' => $t->id, 'no_permohonan' => $t->no_permohonan, 'id_bil' => $t->id_bil, 'kaedah_bayaran' => $t->kaedah_bayaran,
            'kaedah_bayaran_nama' => Kod::KAEDAH_BAYARAN[$t->kaedah_bayaran] ?? $t->kaedah_bayaran, 'amaun' => self::wang($t->amaun),
            'tarikh_bayaran' => $t->tarikh_bayaran?->format('Y-m-d'), 'no_rujukan_eft' => $t->no_rujukan_eft, 'no_bank_draf' => $t->no_bank_draf,
            'bank_penerbit' => $t->bank_penerbit, 'rujukan_fpx' => $t->rujukan_fpx, 'nama_kaunter' => $t->nama_kaunter, 'no_resit' => $t->no_resit,
            'id_dokumen_resit' => $t->id_dokumen_resit, 'id_dokumen_permohonan' => $t->id_dokumen_permohonan,
            'status_transaksi' => $t->status_transaksi, 'status_transaksi_nama' => Kod::STATUS_TRANSAKSI[$t->status_transaksi] ?? $t->status_transaksi,
            'melalui_api' => $t->melalui_api, 'rujukan_grp' => $t->rujukan_grp, 'status_grp' => $t->status_grp, 'kod_daerah' => $t->kod_daerah,
            'medan_wajib' => Kod::MEDAN_KAEDAH[$t->kaedah_bayaran] ?? [], 'catatan' => $t->catatan, 'dicipta' => $t->created_at->toIso8601String(),
            'kelulusan' => $k ? [
                'id' => $k->id, 'peringkat' => $k->peringkat_pelulus, 'peringkat_nama' => Kod::PERINGKAT[$k->peringkat_pelulus] ?? $k->peringkat_pelulus,
                'tahap' => $k->tahap, 'status' => $k->status, 'id_aliran' => $k->id_aliran, 'tarikh_kelulusan' => $k->tarikh_kelulusan?->toIso8601String(), 'justifikasi' => $k->justifikasi,
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function resit(Resit $r): array
    {
        return [
            'id' => $r->id, 'id_transaksi' => $r->id_transaksi, 'id_bil' => $r->id_bil, 'no_resit' => $r->no_resit, 'no_bil_rujukan' => $r->no_bil_rujukan,
            'amaun' => self::wang($r->amaun), 'tarikh_resit' => $r->tarikh_resit->format('Y-m-d'), 'sumber' => $r->sumber, 'id_dokumen' => $r->id_dokumen, 'sintetik' => $r->sintetik,
        ];
    }

    /** @return array<string, mixed> */
    public static function liputan(Liputan $l): array
    {
        return [
            'id' => $l->id, 'kod_agensi' => $l->kod_agensi, 'jenis_agensi' => $l->jenis_agensi, 'nama_agensi' => $l->nama_agensi, 'kod_pbt' => $l->kod_pbt, 'kod_ptd' => $l->kod_ptd,
            'perkhidmatan_semakan' => $l->perkhidmatan_semakan, 'perkhidmatan_bayaran' => $l->perkhidmatan_bayaran, 'mod_semasa' => $l->mod_semasa,
            'kegagalan_berturut' => $l->kegagalan_berturut, 'semakan_terakhir' => $l->semakan_terakhir?->toIso8601String(), 'catatan' => $l->catatan,
            'tarikh_kemas_kini' => $l->tarikh_kemas_kini?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function konflik(Konflik $k): array
    {
        $b = $k->bil;

        return [
            'id' => $k->id, 'id_bil' => $k->id_bil, 'no_bil' => $b?->no_bil, 'tahun_taksiran' => $b?->tahun_taksiran, 'kod_daerah' => $k->kod_daerah,
            'nilai_manual' => $k->nilai_manual, 'nilai_api' => $k->nilai_api, 'medan_berkonflik' => $k->medan_berkonflik,
            'status_resolusi' => $k->status_resolusi, 'tarikh_resolusi' => $k->tarikh_resolusi?->toIso8601String(), 'catatan' => $k->catatan,
            'dicipta' => $k->created_at->toIso8601String(),
        ];
    }
}
