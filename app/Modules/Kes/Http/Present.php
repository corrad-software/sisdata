<?php

namespace App\Modules\Kes\Http;

use App\Models\User;
use App\Modules\Kes\Models\DokumenKes;
use App\Modules\Kes\Models\Keputusan;
use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Kes\Models\KosGuaman;
use App\Modules\Kes\Models\Peguam;
use App\Modules\Kes\Models\Penyelesaian;
use App\Modules\Kes\Models\Pihak;
use App\Modules\Kes\Models\Prosiding;
use App\Modules\Kes\Models\Rayuan;
use App\Modules\Kes\Models\TarikhPenting;
use App\Modules\Kes\Models\TindakanSusulan;
use App\Modules\Kes\Services\Rujukan;
use App\Modules\Kes\Support\Kod;

/** JSON shapes of MOD-KES (labels in Malay). */
class Present
{
    public function __construct(private Rujukan $ref) {}

    /** @return array<string, mixed> */
    public function kes(KesMahkamah $k): array
    {
        return [
            'id' => $k->id,
            'no_rujukan' => $k->no_rujukan,
            'no_kes' => $k->no_kes,
            'tajuk' => $k->tajuk,
            'jenis' => $k->jenis,
            'jenis_nama' => $this->ref->namaJenis($k->jenis),
            'mahkamah' => $k->mahkamah,
            'peringkat_mahkamah' => $k->peringkat_mahkamah,
            'peringkat_nama' => Kod::papar(Kod::PERINGKAT_MAHKAMAH, $k->peringkat_mahkamah),
            'tarikh_pemfailan' => $k->tarikh_pemfailan?->toDateString(),
            'peranan_mais' => $k->peranan_mais,
            'peranan_mais_nama' => Kod::papar(Kod::PERANAN, $k->peranan_mais),
            'status' => $k->status,
            'status_nama' => $this->ref->namaStatus($k->status),
            'aktif' => ! $k->terminal(),
            'tarikh_status' => $k->tarikh_status?->toIso8601String(),
            'nilai_tuntutan' => $k->nilai_tuntutan,
            'kos_guaman' => $k->kos_guaman ?? '0.00',
            'kod_daerah' => $k->kod_daerah,
            'daerah' => $this->ref->namaDaerah($k->kod_daerah),
            'kod_mukim' => $k->kod_mukim,
            'mukim' => $this->ref->namaMukim($k->kod_mukim),
            'pegawai' => $k->pegawai ? ['id' => $k->pegawai->id, 'nama' => $k->pegawai->name] : null,
            'tarikh_prosiding_berikutnya' => $k->tarikh_prosiding_berikutnya?->toDateString(),
            'tarikh_keputusan' => $k->tarikh_keputusan?->toDateString(),
            'status_arkib' => $k->status_arkib,
            'sumber' => $k->sumber,
            'klasifikasi' => $k->klasifikasi,
            'sintetik' => $k->sintetik,
            'created_at' => $k->created_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> masked unless $dedah */
    public function pihak(Pihak $p, bool $dedah = false): array
    {
        $nilai = $dedah ? ['nama_pihak' => $p->nama_pihak, 'no_pengenalan' => $p->no_pengenalan, 'maklumat_perhubungan' => $p->maklumat_perhubungan] : $p->bertopeng();

        return [
            'id' => $p->id,
            ...$nilai,
            'bertopeng' => ! $dedah && $p->perluTopeng(),
            'jenis_pihak' => $p->jenis_pihak,
            'jenis_pihak_nama' => Kod::papar(Kod::JENIS_PIHAK, $p->jenis_pihak),
            'peranan' => $p->peranan,
            'peranan_nama' => Kod::papar(Kod::PERANAN, $p->peranan),
            'status_wakilan' => $p->status_wakilan,
            'status_wakilan_nama' => Kod::papar(Kod::STATUS_WAKILAN, $p->status_wakilan),
            'adalah_mais' => $p->adalah_mais,
            'sintetik' => $p->sintetik,
        ];
    }

    /** @return array<string, mixed> */
    public function peguam(Peguam $p): array
    {
        $mask = fn (?string $v) => $v === null || $v === '' ? null : mb_substr($v, 0, 3).str_repeat('•', max(3, mb_strlen($v) - 5)).mb_substr($v, -2);

        return [
            'id' => $p->id, 'kes_pihak_id' => $p->kes_pihak_id, 'kes_panel_peguam_id' => $p->kes_panel_peguam_id,
            'nama_peguam' => $p->nama_peguam, 'nama_firma' => $p->nama_firma, 'pegawai_penghubung' => $p->pegawai_penghubung,
            'no_telefon' => $mask($p->no_telefon), 'emel' => $mask($p->emel), 'indikator_panel_mais' => $p->indikator_panel_mais,
            'status_lantikan' => $p->status_lantikan, 'status_lantikan_nama' => Kod::papar(Kod::STATUS_LANTIKAN, $p->status_lantikan),
        ];
    }

    /** @return array<string, mixed> */
    public function prosiding(Prosiding $p): array
    {
        return [
            'id' => $p->id, 'tarikh_prosiding' => $p->tarikh_prosiding->toIso8601String(), 'jenis_prosiding' => $p->jenis_prosiding,
            'jenis_nama' => Kod::papar(Kod::JENIS_PROSIDING, $p->jenis_prosiding), 'status' => $p->status, 'status_nama' => Kod::papar(Kod::STATUS_PROSIDING, $p->status),
            'hasil' => $p->hasil, 'arahan_mahkamah' => $p->arahan_mahkamah, 'tarikh_berikutnya' => $p->tarikh_berikutnya?->toDateString(),
            'jenis_berikutnya' => $p->jenis_berikutnya, 'catatan' => $p->catatan, 'sumber' => $p->sumber,
            'pegawai_rekod' => $p->pegawaiRekod?->name, 'eskalasi_dihantar_pada' => $p->eskalasi_dihantar_pada?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function tarikh(TarikhPenting $t): array
    {
        return ['id' => $t->id, 'jenis' => $t->jenis, 'jenis_nama' => Kod::papar(Kod::JENIS_TARIKH, $t->jenis), 'tarikh' => $t->tarikh->toDateString(), 'keterangan' => $t->keterangan, 'status' => $t->status];
    }

    /** @return array<string, mixed> */
    public function kos(KosGuaman $k): array
    {
        return [
            'id' => $k->id, 'kategori_kos' => $k->kategori_kos, 'kategori_nama' => Kod::papar(Kod::KATEGORI_KOS, $k->kategori_kos), 'keterangan' => $k->keterangan,
            'amaun' => $k->amaun, 'tarikh' => $k->tarikh->toDateString(), 'no_resit' => $k->no_resit, 'id_dokumen' => $k->id_dokumen,
            'kes_panel_peguam_id' => $k->kes_panel_peguam_id, 'status_bayaran' => $k->status_bayaran, 'status_bayaran_nama' => Kod::papar(Kod::STATUS_BAYARAN, $k->status_bayaran),
            'rujukan_grp' => $k->rujukan_grp, 'tarikh_bayar' => $k->tarikh_bayar?->toDateString(), 'id_instans_aliran' => $k->id_instans_aliran,
        ];
    }

    /** @return array<string, mixed> */
    public function keputusan(Keputusan $k): array
    {
        return [
            'id' => $k->id, 'jenis_keputusan' => $k->jenis_keputusan, 'jenis_nama' => Kod::papar(Kod::JENIS_KEPUTUSAN, $k->jenis_keputusan),
            'tarikh_keputusan' => $k->tarikh_keputusan->toDateString(), 'peringkat_mahkamah' => $k->peringkat_mahkamah,
            'ringkasan_perintah' => $k->ringkasan_perintah, 'implikasi' => $k->implikasi, 'id_dokumen_penghakiman' => $k->id_dokumen_penghakiman,
        ];
    }

    /** @return array<string, mixed> */
    public function tindakan(TindakanSusulan $t): array
    {
        return [
            'id' => $t->id, 'kes_keputusan_id' => $t->kes_keputusan_id, 'jenis_tindakan' => $t->jenis_tindakan, 'jenis_nama' => Kod::papar(Kod::JENIS_TINDAKAN, $t->jenis_tindakan),
            'keterangan' => $t->keterangan, 'id_tugasan_workflow' => $t->id_tugasan_workflow, 'tarikh_sasaran' => $t->tarikh_sasaran?->toDateString(),
            'status' => $t->status, 'status_nama' => Kod::papar(Kod::STATUS_TINDAKAN, $t->status), 'id_lot_terjejas' => $t->id_lot_terjejas,
            'pegawai' => $t->pegawai?->name, 'tarikh_selesai' => $t->tarikh_selesai?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function rayuan(Rayuan $r): array
    {
        return [
            'id' => $r->id, 'kes_keputusan_id' => $r->kes_keputusan_id, 'pihak_merayu' => $r->pihak_merayu, 'mahkamah_rayuan' => $r->mahkamah_rayuan,
            'peringkat_mahkamah' => $r->peringkat_mahkamah, 'no_kes_rayuan' => $r->no_kes_rayuan, 'tarikh_had_failan' => $r->tarikh_had_failan?->toDateString(),
            'tarikh_failan' => $r->tarikh_failan?->toDateString(), 'alasan' => $r->alasan, 'status' => $r->status, 'status_nama' => Kod::papar(Kod::STATUS_RAYUAN, $r->status),
            'keputusan_rayuan' => $r->keputusan_rayuan, 'keputusan_nama' => Kod::papar(Kod::JENIS_KEPUTUSAN, $r->keputusan_rayuan), 'id_instans_aliran' => $r->id_instans_aliran,
        ];
    }

    /** @return array<string, mixed> */
    public function penyelesaian(Penyelesaian $p): array
    {
        return [
            'id' => $p->id, 'terma' => $p->terma, 'nilai_penyelesaian' => $p->nilai_penyelesaian, 'tarikh_cadangan' => $p->tarikh_cadangan->toDateString(),
            'tarikh_persetujuan' => $p->tarikh_persetujuan?->toDateString(), 'status' => $p->status, 'status_nama' => Kod::papar(Kod::STATUS_PENYELESAIAN, $p->status),
            'id_dokumen_perjanjian' => $p->id_dokumen_perjanjian, 'id_instans_aliran' => $p->id_instans_aliran,
        ];
    }

    /** @return array<string, mixed> */
    public function dokumen(DokumenKes $d): array
    {
        return [
            'id' => $d->id, 'id_dokumen_dms' => $d->id_dokumen_dms, 'kategori_dokumen' => $d->kategori_dokumen, 'kategori_nama' => Kod::papar(Kod::KATEGORI_DOKUMEN, $d->kategori_dokumen),
            'nama_fail' => $d->nama_fail, 'versi' => $d->versi, 'tarikh_muat_naik' => $d->tarikh_muat_naik->toIso8601String(), 'sumber' => $d->sumber,
            'pengguna' => $d->id_pengguna === null ? null : User::query()->whereKey($d->id_pengguna)->value('name'),
        ];
    }
}
