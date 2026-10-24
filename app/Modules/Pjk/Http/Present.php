<?php

namespace App\Modules\Pjk\Http;

use App\Modules\Pjk\Models\Bayaran;
use App\Modules\Pjk\Models\DasarAgihanManfaat;
use App\Modules\Pjk\Models\DepositCagaran;
use App\Modules\Pjk\Models\JadualCaj;
use App\Modules\Pjk\Models\Kontrak;
use App\Modules\Pjk\Models\LejarTransaksi;
use App\Modules\Pjk\Models\PelarasanLejar;
use App\Modules\Pjk\Models\Penyewa;
use App\Modules\Pjk\Models\PermohonanSewaan;
use App\Modules\Pjk\Models\PindaanKontrak;
use App\Modules\Pjk\Models\PremisAset;
use App\Modules\Pjk\Models\Resit;
use App\Modules\Pjk\Models\TransaksiGateway;
use App\Modules\Pjk\Support\Kod;
use App\Modules\Pjk\Support\Masker;
use App\Modules\Pjk\Support\Wang;
use Illuminate\Support\Carbon;

/** JSON shapes for the MOD-PJK API (labels in Malay, money as DECIMAL strings + RM text). */
final class Present
{
    /** @return array{nilai: string|null, rm: string} */
    public static function wang(?string $amount): array
    {
        return ['nilai' => $amount === null ? null : Wang::norm($amount), 'rm' => $amount === null ? '—' : Wang::rm($amount)];
    }

    /** @return array<string, mixed> */
    public static function penyewa(Penyewa $p, bool $full = false): array
    {
        return [
            'id' => $p->id,
            'jenis_entiti' => $p->jenis_entiti,
            'jenis_entiti_nama' => Kod::JENIS_ENTITI[$p->jenis_entiti] ?? $p->jenis_entiti,
            'nama' => $p->nama,
            'no_pengenalan' => $full ? $p->no_pengenalan : Masker::ic($p->no_pengenalan),
            'no_pendaftaran_syarikat' => $full ? $p->no_pendaftaran_syarikat : Masker::tail($p->no_pendaftaran_syarikat),
            'alamat' => $full ? $p->alamat : null,
            'poskod' => $p->poskod,
            'telefon' => $full ? $p->telefon : Masker::tail($p->telefon),
            'emel' => $full ? $p->emel : Masker::email($p->emel),
            'ditutup' => ! $full,
            'klasifikasi' => $p->klasifikasi,
            'sintetik' => $p->sintetik,
        ];
    }

    /** @return array<string, mixed> */
    public static function premis(PremisAset $p): array
    {
        return [
            'id' => $p->id, 'lot_id' => $p->lot_id, 'kod_premis' => $p->kod_premis, 'nama_premis' => $p->nama_premis,
            'jenis_aset' => $p->jenis_aset, 'jenis_aset_nama' => Kod::JENIS_ASET[$p->jenis_aset] ?? $p->jenis_aset,
            'kategori_hartanah' => $p->kategori_hartanah, 'kategori_hartanah_nama' => Kod::KATEGORI_HARTANAH[$p->kategori_hartanah] ?? $p->kategori_hartanah,
            'saluran_pemerolehan' => $p->saluran_pemerolehan, 'keluasan' => $p->keluasan, 'alamat' => $p->alamat,
            'status_premis' => $p->status_premis, 'status_premis_nama' => Kod::STATUS_PREMIS[$p->status_premis] ?? $p->status_premis,
            'tarikh_status' => $p->tarikh_status?->format('Y-m-d'),
            'hari_status' => $p->tarikh_status ? (int) $p->tarikh_status->diffInDays(Carbon::today()) : null,
            'kadar_tawaran' => self::wang($p->kadar_tawaran),
            'kod_daerah' => $p->kod_daerah, 'kod_mukim' => $p->kod_mukim, 'bahagian' => $p->bahagian,
            'bahagian_nama' => Kod::BAHAGIAN[$p->bahagian ?? ''] ?? null,
            'papar_portal' => $p->papar_portal, 'keterangan_awam' => $p->keterangan_awam, 'sintetik' => $p->sintetik,
        ];
    }

    /** Public catalogue entry (Awam fields only — SRS-PJK-04). @return array<string, mixed> */
    public static function premisAwam(PremisAset $p): array
    {
        return [
            'kod_premis' => $p->kod_premis, 'nama_premis' => $p->nama_premis,
            'jenis_aset' => Kod::JENIS_ASET[$p->jenis_aset] ?? $p->jenis_aset,
            'kategori_hartanah' => $p->kategori_hartanah, 'kategori_hartanah_nama' => Kod::KATEGORI_HARTANAH[$p->kategori_hartanah] ?? $p->kategori_hartanah,
            'keluasan_m2' => $p->keluasan, 'kod_daerah' => $p->kod_daerah, 'kod_mukim' => $p->kod_mukim,
            'kadar_tawaran_sebulan' => $p->kadar_tawaran, 'keterangan' => $p->keterangan_awam,
        ];
    }

    /** @return array<string, mixed> */
    public static function kontrak(Kontrak $k, ?string $baki = null): array
    {
        return [
            'id' => $k->id, 'no_kontrak' => $k->no_kontrak, 'lot_id' => $k->lot_id,
            'premis_id' => $k->premis_id, 'premis' => $k->relationLoaded('premis') && $k->premis ? ['id' => $k->premis->id, 'kod_premis' => $k->premis->kod_premis, 'nama_premis' => $k->premis->nama_premis] : null,
            'penyewa_id' => $k->penyewa_id, 'penyewa' => $k->penyewa,
            'jenis_perjanjian' => $k->jenis_perjanjian, 'jenis_perjanjian_nama' => Kod::JENIS_PERJANJIAN[$k->jenis_perjanjian] ?? $k->jenis_perjanjian,
            'kategori_hartanah' => $k->kategori_hartanah, 'kategori_hartanah_nama' => Kod::KATEGORI_HARTANAH[$k->kategori_hartanah ?? ''] ?? null,
            'tarikh_mula' => $k->tarikh_mula?->format('Y-m-d'), 'tarikh_tamat' => $k->tarikh_tamat?->format('Y-m-d'),
            'baki_hari' => $k->tarikh_tamat && $k->berkuatKuasa() ? (int) Carbon::today()->diffInDays($k->tarikh_tamat, false) : null,
            'kadar' => self::wang($k->kadar), 'deposit' => self::wang($k->deposit), 'nilai_kontrak' => self::wang($k->nilai_kontrak),
            'terma' => $k->terma, 'kitaran_caj' => $k->kitaran_caj, 'kitaran_caj_nama' => Kod::KITARAN_CAJ[$k->kitaran_caj] ?? $k->kitaran_caj,
            'hari_tempoh_bayar' => $k->hari_tempoh_bayar,
            'status' => $k->status, 'status_nama' => Kod::STATUS_KONTRAK[$k->status] ?? $k->status, 'tarikh_status' => $k->tarikh_status?->format('Y-m-d'),
            'kod_daerah' => $k->kod_daerah, 'kod_mukim' => $k->kod_mukim, 'bahagian' => $k->bahagian,
            'no_versi' => $k->no_versi, 'id_kontrak_asal' => $k->id_kontrak_asal, 'id_dokumen_perjanjian' => $k->id_dokumen_perjanjian,
            'id_instans_aliran' => $k->id_instans_aliran, 'sumber' => $k->sumber, 'permohonan_id' => $k->permohonan_id,
            'tarikh_penamatan' => $k->tarikh_penamatan?->format('Y-m-d'), 'sebab_penamatan' => $k->sebab_penamatan,
            'baki_lejar' => $baki === null ? null : self::wang($baki),
            'klasifikasi' => $k->klasifikasi, 'sintetik' => $k->sintetik,
        ];
    }

    /** @return array<string, mixed> */
    public static function caj(JadualCaj $c): array
    {
        return [
            'id' => $c->id, 'no_caj' => $c->no_caj, 'kod_caj' => $c->kod_caj, 'kod_caj_nama' => Kod::KOD_CAJ[$c->kod_caj] ?? $c->kod_caj,
            'tempoh' => $c->tempoh, 'tarikh_caj' => $c->tarikh_caj->format('Y-m-d'), 'tarikh_akhir' => $c->tarikh_akhir->format('Y-m-d'),
            'amaun' => self::wang($c->amaun), 'amaun_dibayar' => self::wang($c->amaun_dibayar), 'amaun_dikecualikan' => self::wang($c->amaun_dikecualikan),
            'baki' => self::wang(Wang::dec($c->bakiSen())), 'status_caj' => $c->status_caj, 'status_caj_nama' => Kod::STATUS_CAJ[$c->status_caj] ?? $c->status_caj,
            'dikeluarkan' => $c->dikeluarkan, 'umur_tunggakan' => $c->umurTunggakan(),
            'kumpulan_umur' => $c->umurTunggakan() > 0 ? Kod::umur($c->umurTunggakan()) : null, 'keterangan' => $c->keterangan,
        ];
    }

    /** @return array<string, mixed> */
    public static function lejar(LejarTransaksi $l): array
    {
        return [
            'id' => $l->id, 'no_transaksi' => $l->no_transaksi, 'tarikh_transaksi' => $l->tarikh_transaksi->format('Y-m-d'),
            'jenis' => $l->jenis, 'jenis_nama' => Kod::JENIS_LEJAR[$l->jenis] ?? $l->jenis, 'kod_caj' => $l->kod_caj,
            'debit' => self::wang($l->debit), 'kredit' => self::wang($l->kredit), 'baki_berjalan' => self::wang($l->baki_berjalan),
            'kaedah_bayaran' => $l->kaedah_bayaran, 'kaedah_bayaran_nama' => Kod::SALURAN_BAYARAN[$l->kaedah_bayaran ?? ''] ?? null,
            'no_resit' => $l->no_resit, 'id_transaksi_asal' => $l->id_transaksi_asal, 'pelarasan_id' => $l->pelarasan_id,
            'status_pelarasan' => $l->status_pelarasan, 'keterangan' => $l->keterangan, 'masa' => $l->created_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function bayaran(Bayaran $b): array
    {
        return [
            'id' => $b->id, 'no_bayaran' => $b->no_bayaran, 'kontrak_id' => $b->kontrak_id, 'saluran' => $b->saluran,
            'saluran_nama' => Kod::SALURAN_BAYARAN[$b->saluran] ?? $b->saluran, 'amaun' => self::wang($b->amaun),
            'tarikh_bayaran' => $b->tarikh_bayaran->format('Y-m-d'), 'rujukan_luar' => $b->rujukan_luar, 'id_dokumen_bukti' => $b->id_dokumen_bukti,
            'agihan_manual' => $b->agihan_manual, 'status' => $b->status, 'status_nama' => Kod::STATUS_BAYARAN[$b->status] ?? $b->status,
            'nama_pembayar' => $b->nama_pembayar, 'emel_pembayar' => Masker::email($b->emel_pembayar),
            'id_perekod' => $b->id_perekod, 'id_pengesah' => $b->id_pengesah, 'tarikh_pengesahan' => $b->tarikh_pengesahan?->toIso8601String(),
            'catatan' => $b->catatan, 'resit' => $b->relationLoaded('resit') ? $b->resit->map(fn (Resit $r) => self::resit($r))->values()->all() : null,
            'kontrak' => $b->relationLoaded('kontrak') ? ['id' => $b->kontrak->id, 'no_kontrak' => $b->kontrak->no_kontrak, 'penyewa' => $b->kontrak->penyewa] : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function resit(Resit $r): array
    {
        return [
            'id' => $r->id, 'no_resit' => $r->no_resit, 'jenis' => $r->jenis, 'amaun' => self::wang($r->amaun),
            'tarikh' => $r->tarikh->format('Y-m-d'), 'id_resit_asal' => $r->id_resit_asal, 'id_dokumen' => $r->id_dokumen,
        ];
    }

    /** @return array<string, mixed> */
    public static function gateway(TransaksiGateway $t): array
    {
        return [
            'id' => $t->id, 'no_rujukan' => $t->no_rujukan, 'kontrak_id' => $t->kontrak_id, 'saluran' => $t->saluran,
            'saluran_nama' => Kod::SALURAN_BAYARAN[$t->saluran] ?? $t->saluran, 'amaun' => self::wang($t->amaun),
            'status' => $t->status, 'status_nama' => Kod::STATUS_GATEWAY[$t->status] ?? $t->status, 'url_bayaran' => $t->status === 'menunggu' ? $t->url_bayaran : null,
            'masa_mula' => $t->masa_mula->toIso8601String(), 'masa_pengesahan' => $t->masa_pengesahan?->toIso8601String(),
            'kod_respons' => $t->kod_respons, 'rujukan_gateway' => $t->rujukan_gateway, 'disahkan_melalui' => $t->disahkan_melalui,
            'bil_semakan_status' => $t->bil_semakan_status,
        ];
    }

    /** @return array<string, mixed> */
    public static function deposit(DepositCagaran $d): array
    {
        return [
            'id' => $d->id, 'kontrak_id' => $d->kontrak_id, 'jenis' => $d->jenis, 'amaun' => self::wang($d->amaun),
            'tarikh_terima' => $d->tarikh_terima?->format('Y-m-d'), 'status' => $d->status, 'status_nama' => Kod::STATUS_DEPOSIT[$d->status] ?? $d->status,
            'amaun_tolakan' => self::wang($d->amaun_tolakan), 'justifikasi_tolakan' => $d->justifikasi_tolakan,
            'amaun_dipulangkan' => self::wang($d->amaun_dipulangkan), 'tarikh_pulangan' => $d->tarikh_pulangan?->format('Y-m-d'),
            'id_instans_aliran' => $d->id_instans_aliran,
        ];
    }

    /** @return array<string, mixed> */
    public static function pindaan(PindaanKontrak $p): array
    {
        return [
            'id' => $p->id, 'kontrak_id' => $p->kontrak_id, 'jenis_pindaan' => $p->jenis_pindaan, 'jenis_pindaan_nama' => Kod::JENIS_PINDAAN[$p->jenis_pindaan] ?? $p->jenis_pindaan,
            'data_cadangan' => $p->data_cadangan, 'data_semasa' => $p->data_semasa, 'justifikasi' => $p->justifikasi, 'semakan_kadar' => $p->semakan_kadar,
            'id_dokumen_selepas' => $p->id_dokumen_selepas, 'status' => $p->status, 'id_pemohon' => $p->id_pemohon, 'id_pelulus' => $p->id_pelulus,
            'tarikh_keputusan' => $p->tarikh_keputusan?->toIso8601String(), 'ulasan' => $p->ulasan, 'id_instans_aliran' => $p->id_instans_aliran,
            'id_kontrak_baharu' => $p->id_kontrak_baharu, 'dicipta' => $p->created_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function pelarasan(PelarasanLejar $p): array
    {
        return [
            'id' => $p->id, 'kontrak_id' => $p->kontrak_id, 'jenis' => $p->jenis, 'jenis_nama' => Kod::JENIS_PELARASAN[$p->jenis] ?? $p->jenis,
            'lejar_id_sasaran' => $p->lejar_id_sasaran, 'caj_id' => $p->caj_id, 'amaun' => self::wang($p->amaun), 'justifikasi' => $p->justifikasi,
            'status' => $p->status, 'id_pemohon' => $p->id_pemohon, 'id_pelulus' => $p->id_pelulus,
            'tarikh_keputusan' => $p->tarikh_keputusan?->toIso8601String(), 'ulasan' => $p->ulasan, 'id_instans_aliran' => $p->id_instans_aliran,
        ];
    }

    /** @return array<string, mixed> */
    public static function permohonan(PermohonanSewaan $p, bool $full = false): array
    {
        $m = $p->maklumat_pemohon ?? [];

        return [
            'id' => $p->id, 'no_permohonan' => $p->no_permohonan, 'sumber' => $p->sumber, 'rujukan_portal' => $p->rujukan_portal,
            'premis_id' => $p->premis_id, 'premis' => $p->relationLoaded('premis') ? ['id' => $p->premis->id, 'kod_premis' => $p->premis->kod_premis, 'nama_premis' => $p->premis->nama_premis] : null,
            'penyewa_id' => $p->penyewa_id,
            'pemohon' => $m === [] ? null : [
                'jenis_entiti' => $m['jenis_entiti'] ?? null, 'nama' => $m['nama'] ?? null,
                'no_pengenalan' => $full ? ($m['no_pengenalan'] ?? null) : Masker::ic($m['no_pengenalan'] ?? null),
                'no_pendaftaran_syarikat' => $full ? ($m['no_pendaftaran_syarikat'] ?? null) : Masker::tail($m['no_pendaftaran_syarikat'] ?? null),
                'emel' => $full ? ($m['emel'] ?? null) : Masker::email($m['emel'] ?? null),
                'telefon' => $full ? ($m['telefon'] ?? null) : Masker::tail($m['telefon'] ?? null),
            ],
            'tarikh_mula_dicadang' => $p->tarikh_mula_dicadang?->format('Y-m-d'), 'tempoh_bulan' => $p->tempoh_bulan,
            'kadar_dicadang' => self::wang($p->kadar_dicadang), 'tujuan' => $p->tujuan,
            'status' => $p->status, 'status_nama' => Kod::STATUS_PERMOHONAN[$p->status] ?? $p->status, 'kontrak_id' => $p->kontrak_id,
            'catatan' => $p->catatan, 'kod_daerah' => $p->kod_daerah, 'dicipta' => $p->created_at->toIso8601String(), 'sintetik' => $p->sintetik,
        ];
    }

    /** @return array<string, mixed> */
    public static function dasar(DasarAgihanManfaat $d): array
    {
        return [
            'id' => $d->id, 'versi_dasar' => $d->versi_dasar, 'kategori_hartanah' => $d->kategori_hartanah,
            'kategori_hartanah_nama' => Kod::KATEGORI_HARTANAH[$d->kategori_hartanah] ?? $d->kategori_hartanah,
            'status_perolehan' => $d->status_perolehan, 'status_perolehan_nama' => Kod::STATUS_PEROLEHAN[$d->status_perolehan] ?? $d->status_perolehan,
            'kadar_agihan' => $d->kadar_agihan, 'penerima_manfaat' => $d->penerima_manfaat,
            'tarikh_mula_kuasa' => $d->tarikh_mula_kuasa->format('Y-m-d'), 'tarikh_tamat' => $d->tarikh_tamat?->format('Y-m-d'),
            'rujukan_kelulusan' => $d->rujukan_kelulusan, 'status' => $d->status, 'id_instans_aliran' => $d->id_instans_aliran,
        ];
    }
}
