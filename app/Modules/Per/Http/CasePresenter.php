<?php

namespace App\Modules\Per\Http;

use App\Models\User;
use App\Modules\Per\Models\DokumenPemerolehan;
use App\Modules\Per\Models\Kelulusan;
use App\Modules\Per\Models\Pemerolehan;
use App\Modules\Per\Models\PeringkatPemerolehan;
use App\Modules\Per\Models\PihakBerkaitan;
use App\Modules\Per\Models\Saluran;
use App\Modules\Per\Services\CaseService;
use App\Modules\Per\Services\ChannelFields;
use App\Modules\Per\Services\Checklist;
use App\Modules\Per\Services\Payments5a;
use App\Modules\Per\Support\ChannelCatalog;
use App\Modules\Per\Support\Masker;
use App\Modules\Per\Support\RefNames;
use App\Modules\Pro\Contracts\LotRegistry;
use App\Modules\Rbc\Contracts\AccessControl;

/** JSON shapes for MOD-PER's API (Malay keys, masked personal data). */
class CasePresenter
{
    public function __construct(
        private AccessControl $access,
        private ChannelFields $fields,
        private Checklist $checklist,
        private Payments5a $payments,
        private LotRegistry $lots,
    ) {}

    /** @return array<string, mixed> */
    public function channel(Saluran $s, bool $full = false): array
    {
        $out = [
            'id' => $s->id,
            'kod' => $s->kod_saluran,
            'nama' => $s->nama_saluran,
            'singkatan' => $s->singkatan,
            'rujukan_lampiran_h' => $s->rujukan_lampiran_h,
            'ikon' => $s->ikon,
            'warna' => $s->warna,
            'bahagian' => $s->bahagian_bertanggungjawab,
            'nama_bahagian' => ChannelCatalog::BAHAGIAN[$s->bahagian_bertanggungjawab] ?? $s->bahagian_bertanggungjawab,
            'tempoh_sasaran_hari' => $s->tempoh_sasaran_hari,
            'keterangan' => $s->keterangan,
            'status_aktif' => $s->status_aktif,
        ];

        if ($full) {
            $out['peringkat'] = $s->peringkat->map(fn ($p) => [
                'id' => $p->id, 'kod' => $p->kod_peringkat, 'nama' => $p->nama_peringkat, 'jenis' => $p->jenis,
                'susunan' => $p->susunan, 'tempoh_sasaran_hari' => $p->tempoh_sasaran_hari, 'aktif' => $p->aktif,
            ])->values()->all();
            $out['senarai_semak'] = $s->senaraiSemak->map(fn ($d) => [
                'id' => $d->id, 'jenis_dokumen' => $d->jenis_dokumen, 'nama_dokumen' => $d->nama_dokumen, 'kategori_dms' => $d->kategori_dms,
                'wajib' => $d->status_wajib, 'dijana_sistem' => $d->dijana_sistem, 'peringkat_diperlukan' => $d->peringkat_diperlukan,
            ])->values()->all();
            $out['medan'] = $this->fields->schema($s->kod_saluran);
            $out['peranan_pihak'] = array_map(fn ($r) => ['kod' => $r, 'label' => ChannelCatalog::PERANAN_PIHAK[$r] ?? $r], ChannelCatalog::partyRoles()[$s->kod_saluran] ?? []);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function summary(Pemerolehan $c): array
    {
        $stage = $c->relationLoaded('peringkat') ? $c->peringkatSemasa() : null;

        return [
            'id' => $c->id,
            'no_kes' => $c->no_kes,
            'tajuk' => $c->tajuk,
            'saluran' => $this->channel($c->saluran),
            'lot' => $this->lot($c),
            'kod_daerah' => $c->kod_daerah,
            'nama_daerah' => RefNames::daerah()[$c->kod_daerah ?? ''] ?? $c->kod_daerah,
            'kod_mukim' => $c->kod_mukim,
            'nama_mukim' => RefNames::mukim()[$c->kod_mukim ?? ''] ?? $c->kod_mukim,
            'peringkat_semasa' => $stage ? [
                'kod' => $stage->kod_peringkat, 'nama' => $stage->nama_peringkat, 'jenis' => $stage->jenis,
                'hari' => $stage->hariBerjalan(), 'tempoh_sasaran_hari' => $stage->tempoh_sasaran_hari, 'lewat' => $stage->lewat(),
                'dieskalasi' => $stage->status_eskalasi === 'dieskalasi',
            ] : null,
            'status' => $c->status_pemerolehan,
            'status_kelengkapan_dokumen' => $c->status_kelengkapan_dokumen,
            'kod_bahagian' => $c->kod_bahagian,
            'tarikh_mula_kes' => $c->tarikh_mula_kes->toDateString(),
            'tarikh_selesai' => $c->tarikh_selesai?->toDateString(),
            'nilai_pemerolehan' => $c->nilai_pemerolehan,
            'pegawai' => $c->pegawai ? ['id' => $c->pegawai->id, 'nama' => $c->pegawai->name] : null,
            'sumber_rekod' => $c->sumber_rekod,
            'klasifikasi' => $c->klasifikasi,
        ];
    }

    /** @return array<string, mixed> */
    public function detail(Pemerolehan $c, User $user, CaseService $cases): array
    {
        $c->loadMissing(['saluran.peringkat', 'saluran.senaraiSemak', 'peringkat.pegawai', 'dokumen.semak', 'dokumen.pegawaiTerima', 'dokumen.pergerakan.pegawai', 'pihak', 'kelulusan.pelulus', 'bayaran.pengesah', 'pegawai', 'pendaftar']);
        $sulit = $this->access->allows($user, 'per.sulit.lihat');
        $channel = $c->kodSaluran();

        $personal = $c->butiran_peribadi ?? [];
        if (! $sulit) {
            $personal = array_map(fn ($v) => is_string($v) ? Masker::name($v) : $v, $personal);
        }

        $out = $this->summary($c) + [
            'saluran_penuh' => $this->channel($c->saluran, true),
            'butiran' => array_merge($c->butiran_saluran ?? [], $personal),
            'peribadi_dikaburkan' => ! $sulit,
            'nilai_pasaran' => $c->nilai_pasaran,
            'sumber_dana' => $c->sumber_dana,
            'rujukan_penilaian_jpph' => $c->rujukan_penilaian_jpph,
            'tarikh_pemerolehan' => $c->tarikh_pemerolehan?->toDateString(),
            'catatan' => $c->catatan,
            'rujukan_luaran' => $c->rujukan_luaran,
            'pendaftar' => $c->pendaftar ? ['id' => $c->pendaftar->id, 'nama' => $c->pendaftar->name] : null,
            'peringkat' => $c->peringkat->map(fn (PeringkatPemerolehan $s) => [
                'id' => $s->id, 'kod' => $s->kod_peringkat, 'nama' => $s->nama_peringkat, 'jenis' => $s->jenis, 'susunan' => $s->susunan,
                'status' => $s->status_peringkat, 'tarikh_masuk' => $s->tarikh_masuk?->toIso8601String(), 'tarikh_keluar' => $s->tarikh_keluar?->toIso8601String(),
                'tempoh_hari' => $s->tempoh_hari ?? $s->hariBerjalan(), 'tempoh_sasaran_hari' => $s->tempoh_sasaran_hari, 'lewat' => $s->lewat(),
                'status_eskalasi' => $s->status_eskalasi, 'tarikh_eskalasi' => $s->tarikh_eskalasi?->toIso8601String(),
                'pegawai' => $s->pegawai ? ['id' => $s->pegawai->id, 'nama' => $s->pegawai->name] : null, 'catatan' => $s->catatan,
            ])->values()->all(),
            'senarai_semak' => $this->checklist->items($c),
            'kekurangan_dokumen' => $this->checklist->missingAll($c),
            'dokumen' => $c->dokumen->map(fn (DokumenPemerolehan $d) => $this->document($d))->values()->all(),
            'pihak' => $c->pihak->map(fn (PihakBerkaitan $p) => $this->party($p, $sulit))->values()->all(),
            'kelulusan' => $c->kelulusan->map(fn (Kelulusan $k) => [
                'id' => $k->id, 'aras' => $k->aras_kelulusan, 'tindakan' => $k->tindakan, 'tarikh_masa' => $k->tarikh_masa->toIso8601String(),
                'pelulus' => $k->pelulus ? ['id' => $k->pelulus->id, 'nama' => $k->pelulus->name] : null, 'jawatan' => $k->jawatan_pelulus,
                'justifikasi' => $k->justifikasi,
            ])->values()->all(),
            'id_aliran_kerja' => $c->id_aliran_kerja,
            'sekatan' => $cases->blockers($c),
            'ringkasan_tempoh' => [
                'hari_berjalan' => (int) $c->tarikh_mula_kes->copy()->startOfDay()->diffInDays(($c->tarikh_selesai ?? now())->copy()->startOfDay()),
                'tempoh_keseluruhan_hari' => $c->tempoh_keseluruhan_hari,
                'tempoh_sasaran_hari' => $c->tempoh_sasaran_hari,
            ],
            'hak_milik' => $c->id_hak_milik_dijana ? ['id' => $c->id_hak_milik_dijana] + ($c->butiran_hak_milik ?? []) : null,
            'boleh' => [
                'kemas_kini' => $this->access->allows($user, 'per.kes.kemas_kini'),
                'dokumen' => $this->access->allows($user, 'per.dokumen.cipta'),
                'tutup' => $this->access->allows($user, 'per.hak_milik.cipta'),
                'batal' => $this->access->allows($user, 'per.kes.padam'),
                'bayaran' => $this->access->allows($user, 'per.bayaran.cipta'),
                'sahkan_bayaran' => $this->access->allows($user, 'per.bayaran.lulus'),
                'lihat_sulit' => $sulit,
            ],
        ];

        if ($channel === 'pemberimilikan_5a') {
            $out['bayaran_5a'] = $this->payments->summary($c) + [
                'bayaran' => $c->bayaran->map(fn ($b) => [
                    'id' => $b->id, 'jumlah_dibayar' => $b->jumlah_dibayar, 'tarikh_bayaran' => $b->tarikh_bayaran->toDateString(),
                    'mod_bayaran' => $b->mod_bayaran, 'no_resit' => $b->no_resit, 'status_pengesahan' => $b->status_pengesahan,
                    'pengesah' => $b->pengesah ? ['id' => $b->pengesah->id, 'nama' => $b->pengesah->name] : null,
                    'tarikh_pengesahan' => $b->tarikh_pengesahan?->toIso8601String(), 'catatan' => $b->catatan,
                    'id_pegawai_rekod' => $b->id_pegawai_rekod,
                ])->values()->all(),
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function party(PihakBerkaitan $p, bool $sulit, bool $fullId = false): array
    {
        return [
            'id' => $p->id,
            'peranan_pihak' => $p->peranan_pihak,
            'label_peranan' => ChannelCatalog::PERANAN_PIHAK[$p->peranan_pihak] ?? $p->peranan_pihak,
            'nama' => $sulit ? $p->nama : Masker::name($p->nama),
            'hubungan' => $p->hubungan,
            'jenis_dokumen_pengenalan' => $p->jenis_dokumen_pengenalan,
            'no_dokumen_pengenalan' => $fullId && $sulit ? $p->no_dokumen_pengenalan : Masker::idNumber($p->no_pengenalan_4akhir),
            'bahagian_pusaka' => $p->bahagian_pusaka,
            'status_pengesahan' => $p->status_pengesahan,
            'sumber_pengesahan' => $p->sumber_pengesahan,
            'catatan' => $p->catatan,
        ];
    }

    /** @return array<string, mixed> */
    public function document(DokumenPemerolehan $d): array
    {
        return [
            'id' => $d->id, 'id_semak' => $d->id_semak, 'jenis_dokumen' => $d->jenis_dokumen, 'nama_dokumen' => $d->semak->nama_dokumen ?? $d->jenis_dokumen,
            'rujukan_dms' => $d->rujukan_dms, 'no_versi' => $d->no_versi, 'tarikh_dokumen' => $d->tarikh_dokumen?->toDateString(),
            'tarikh_terima' => $d->tarikh_terima?->toDateString(), 'status_dokumen' => $d->status_dokumen,
            'penerima' => $d->pegawaiTerima ? ['id' => $d->pegawaiTerima->id, 'nama' => $d->pegawaiTerima->name] : null,
            'kod_peti_besi' => $d->kod_peti_besi, 'no_fail' => $d->no_fail, 'lokasi_rak' => $d->lokasi_rak, 'status_dokumen_asal' => $d->status_dokumen_asal,
            'catatan' => $d->catatan,
            'pergerakan' => $d->pergerakan->map(fn ($m) => [
                'jenis' => $m->jenis, 'tarikh' => $m->tarikh->toIso8601String(), 'tujuan' => $m->tujuan,
                'pegawai' => $m->pegawai ? ['id' => $m->pegawai->id, 'nama' => $m->pegawai->name] : null,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function lot(Pemerolehan $c): array
    {
        if ($c->lot_id !== null) {
            return ['id' => $c->lot_id, 'berdaftar' => true, 'no_lot' => $this->lotNumber($c), 'pautan' => $this->lots->profileLink($c->lot_id)];
        }

        return ['id' => null, 'berdaftar' => false, 'no_lot' => $c->no_lot_dijangka, 'pautan' => null];
    }

    /** @var array<string, string|null> */
    private array $lotNumbers = [];

    private function lotNumber(Pemerolehan $c): ?string
    {
        $id = (string) $c->lot_id;

        return $this->lotNumbers[$id] ??= ($this->lots->find($id)['no_lot'] ?? $c->no_lot_dijangka);
    }
}
