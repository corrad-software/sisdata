<?php

namespace App\Modules\Tkt\Http;

use App\Models\User;
use App\Modules\Kba\Contracts\ArticleDrafts;
use App\Modules\Tkt\Models\Eskalasi;
use App\Modules\Tkt\Models\Kronologi;
use App\Modules\Tkt\Models\Lampiran;
use App\Modules\Tkt\Models\Tiket;
use App\Modules\Tkt\Services\SlaTiket;
use App\Modules\Tkt\Support\Akses;
use App\Modules\Tkt\Support\Lalai;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * JSON shapes for MOD-TKT. What the reporter sees is a subset: no internal
 * notes, internal attachments, escalations, vendor references, duplicate
 * hints or error-log correlation.
 */
class Present
{
    public function __construct(private SlaTiket $sla, private Akses $akses, private Application $app) {}

    /** @return array<string, mixed> */
    public function tiket(Tiket $t, User $user): array
    {
        $support = $this->akses->sokongan($user, $t);
        $reporter = $this->akses->pelapor($user, $t);

        return [
            'id' => $t->id,
            'nombor_rujukan' => $t->nombor_rujukan,
            'tajuk' => $t->tajuk,
            'kategori' => $t->kategori,
            'kategori_nama' => Lalai::KATEGORI[$t->kategori] ?? $t->kategori,
            'keterukan' => $t->keterukan,
            'keterukan_nama' => Lalai::keterukan($t->keterukan),
            'keterukan_dicadang' => $t->keterukan_dicadang,
            'kesan' => $t->kesan,
            'kesegeraan' => $t->kesegeraan,
            'modul' => $t->modul,
            'modul_nama' => Lalai::MODUL[$t->modul ?? 'umum'] ?? $t->modul,
            'status' => $t->status,
            'status_nama' => Lalai::STATUS[$t->status] ?? $t->status,
            'saluran' => $t->saluran,
            'saluran_nama' => Lalai::SALURAN[$t->saluran] ?? $t->saluran,
            'klasifikasi' => $t->klasifikasi,
            'isu_keselamatan' => $t->isu_keselamatan,
            'pelapor' => $t->pelapor !== null ? ['id' => $t->pelapor->id, 'nama' => $t->pelapor->name] : null,
            'emel_pelapor' => $support ? $t->emel_pelapor : null,
            'pasukan' => $t->pasukan !== null ? ['id' => $t->pasukan->id, 'nama' => $t->pasukan->nama, 'aras' => $t->pasukan->aras] : null,
            'aras_sokongan' => $t->aras_sokongan,
            'juruteknik' => $t->juruteknik !== null ? ['id' => $t->juruteknik->id, 'nama' => $t->juruteknik->name] : null,
            'nama_vendor' => $t->nama_vendor,
            'rujukan_vendor' => $support ? $t->rujukan_vendor : null,
            'tarikh_serah_vendor' => $support ? $t->tarikh_serah_vendor?->toIso8601String() : null,
            'tarikh_buka' => $t->tarikh_buka->toIso8601String(),
            'tarikh_respons_pertama' => $t->tarikh_respons_pertama?->toIso8601String(),
            'tarikh_selesai' => $t->tarikh_selesai?->toIso8601String(),
            'tarikh_disahkan' => $t->tarikh_disahkan?->toIso8601String(),
            'tarikh_tutup' => $t->tarikh_tutup?->toIso8601String(),
            'sla' => $this->sla->gambaran($t),
            'peringkat_eskalasi' => $support ? $t->peringkat_eskalasi : null,
            'bilangan_buka_semula' => $t->bilangan_buka_semula,
            'kepuasan_skor' => $t->kepuasan_skor,
            'pendua_dicadang' => $support && $t->id_tiket_pendua_dicadang !== null ? ['id' => $t->id_tiket_pendua_dicadang, 'skor' => $t->skor_pendua, 'nombor_rujukan' => Tiket::query()->whereKey($t->id_tiket_pendua_dicadang)->value('nombor_rujukan')] : null,
            'id_tiket_induk' => $t->id_tiket_induk,
            'status_kelulusan' => $t->status_kelulusan,
            'id_aduan_adu' => $support ? $t->id_aduan_adu : null,
            'sintetik' => $t->sintetik,
            'peranan_saya' => ['sokongan' => $support, 'pelapor' => $reporter, 'urus' => $this->akses->bolehUrus($user, $t)],
        ];
    }

    /** @return array<string, mixed> */
    public function butiran(Tiket $t, User $user): array
    {
        $t->loadMissing(['pelapor', 'juruteknik', 'pasukan', 'konteks', 'pautanAduan']);
        $support = $this->akses->sokongan($user, $t);
        $reporter = $this->akses->pelapor($user, $t);
        $k = $t->konteks;

        $kronologi = Kronologi::query()->with('pengguna')->where('id_tiket', $t->id)
            ->when(! $support, fn ($q) => $q->whereIn('jenis_catatan', Lalai::KRONOLOGI_PELAPOR))
            ->orderBy('id')->get()
            ->map(fn (Kronologi $c) => [
                'id' => $c->id,
                'jenis' => $c->jenis_catatan,
                'jenis_nama' => Lalai::JENIS_CATATAN[$c->jenis_catatan] ?? $c->jenis_catatan,
                'pengguna' => $c->pengguna->name ?? ($c->jenis_catatan === 'pelapor' ? __('tkt::tkt.pelapor') : __('tkt::tkt.sistem')),
                'kandungan' => $c->kandungan,
                'status_dari' => $c->status_dari !== null ? (Lalai::STATUS[$c->status_dari] ?? $c->status_dari) : null,
                'status_ke' => $c->status_ke !== null ? (Lalai::STATUS[$c->status_ke] ?? $c->status_ke) : null,
                'meta' => $support ? $c->meta : null,
                'cap_masa' => $c->cap_masa->toIso8601String(),
            ])->values()->all();

        $lampiran = Lampiran::query()->where('id_tiket', $t->id)->when(! $support, fn ($q) => $q->where('kelihatan_pelapor', true))->orderBy('id')->get()
            ->map(fn (Lampiran $l) => [
                'id' => $l->id, 'nama_fail' => $l->nama_fail, 'jenis_fail' => $l->jenis_fail, 'saiz' => $l->saiz, 'status_imbasan' => $l->status_imbasan,
                'tangkapan_skrin' => $l->tangkapan_skrin, 'persetujuan' => $l->persetujuan_tangkapan_skrin, 'kelihatan_pelapor' => $l->kelihatan_pelapor,
                'dimuat_naik' => $l->created_at->toIso8601String(),
            ])->values()->all();

        $out = $this->tiket($t, $user) + [
            'penerangan' => $t->penerangan,
            'punca_akar' => $t->punca_akar,
            'penyelesaian' => $t->penyelesaian,
            'tindakan_pembetulan' => $support ? $t->tindakan_pembetulan : null,
            'masa_henti_minit' => $support ? $t->masa_henti_minit : null,
            'sebab_lepas_sla' => $support ? $t->sebab_lepas_sla : null,
            'versi_sistem' => $t->versi_sistem,
            'kepuasan_ulasan' => $reporter || $support ? $t->kepuasan_ulasan : null,
            'konteks' => $k === null ? null : [
                'skrin' => $k->skrin, 'modul' => $k->modul, 'kod_fungsi' => $k->kod_fungsi, 'url' => $k->url,
                'peranan_pengguna' => $k->peranan_pengguna ?? [], 'id_pengguna' => $k->id_pengguna, 'pelayar' => $k->pelayar,
                'versi_sistem' => $k->versi_sistem, 'versi_ui' => $k->versi_ui, 'rujukan_rekod' => $k->rujukan_rekod,
                'id_permintaan' => $support ? ($k->id_permintaan ?? []) : [], 'id_permintaan_cipta' => $support ? $k->id_permintaan_cipta : null,
                'saiz_skrin' => $k->saiz_skrin, 'zon_masa' => $k->zon_masa, 'cap_masa_tangkapan' => $k->cap_masa_tangkapan->toIso8601String(),
            ],
            'kronologi' => $kronologi,
            'lampiran' => $lampiran,
            'peralihan' => $support ? array_values(array_filter(Lalai::PERALIHAN[$t->status] ?? [], fn ($s) => ! ($s === 'dalam_tindakan' && $t->kategori === 'permintaan_perubahan' && $t->status_kelulusan !== 'lulus'))) : [],
            'boleh' => [
                'urus' => $this->akses->bolehUrus($user, $t),
                'balas' => $this->akses->bolehBalasPelapor($user, $t) && ! in_array($t->status, Lalai::AKHIR, true),
                'sahkan' => $this->akses->bolehBalasPelapor($user, $t) && $t->status === 'selesai',
                'buka_semula' => ($this->akses->bolehBalasPelapor($user, $t) || $this->akses->bolehUrus($user, $t)) && in_array($t->status, ['selesai', 'disahkan', 'tutup'], true),
                'kepuasan' => $this->akses->bolehBalasPelapor($user, $t) && in_array($t->status, ['selesai', 'disahkan', 'tutup'], true) && $t->kepuasan_skor === null,
                'lampiran' => ($this->akses->bolehBalasPelapor($user, $t) || $this->akses->bolehUrus($user, $t)) && ! in_array($t->status, Lalai::AKHIR, true),
            ],
        ];

        if ($support) {
            $out['eskalasi'] = Eskalasi::query()->with('penerima')->where('id_tiket', $t->id)->orderBy('id')->get()
                ->map(fn (Eskalasi $e) => [
                    'peringkat' => $e->peringkat, 'sasaran' => $e->sasaran, 'sasaran_nama' => __('tkt::tkt.sasaran.'.$e->sasaran),
                    'sebab' => $e->sebab, 'sebab_nama' => __('tkt::tkt.sebab_eskalasi.'.$e->sebab), 'penerima' => $e->penerima->name ?? $e->jenis_penerima,
                    'jenis_penerima' => $e->jenis_penerima, 'catatan' => $e->catatan, 'cap_masa' => $e->cap_masa->toIso8601String(),
                ])->values()->all();
            $out['artikel_kb'] = $this->artikel($t);
            $out['log_ralat'] = $this->logRalat($k->id_permintaan ?? [], $k?->id_permintaan_cipta, $user);
            $out['pautan_aduan'] = $t->pautanAduan === null ? null : [
                'nombor_aduan_berpusat' => $t->pautanAduan->nombor_aduan_berpusat, 'arah_asal' => $t->pautanAduan->arah_asal,
                'status_penyegerakan' => $t->pautanAduan->status_penyegerakan, 'akhir' => $t->pautanAduan->cap_masa_penyegerakan_akhir?->toIso8601String(),
            ];
        }

        return $out;
    }

    /** @return list<array{id: int, tajuk: string, status: string, pautan: string}> */
    private function artikel(Tiket $t): array
    {
        try {
            return $this->app->make(ArticleDrafts::class)->forTicket($t->id);
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * SRS-ADM-12 correlation: MOD-ADM error-log entries carrying the request
     * ids captured with the ticket. adm_log_ralat is read directly and
     * read-only (MOD-ADM has no log-query contract); details only for
     * holders of adm.log.lihat, everyone else gets the ids + link.
     *
     * @param  list<string>  $ids
     * @return array{id_korelasi: list<string>, entri: list<array<string, mixed>>, boleh_lihat: bool}
     */
    private function logRalat(array $ids, ?string $created, User $user): array
    {
        $ids = array_values(array_unique(array_filter([...$ids, $created])));
        $allowed = $user->can('adm.log.lihat');
        $entries = [];
        if ($ids !== [] && $allowed && Schema::hasTable('adm_log_ralat')) {
            $entries = DB::table('adm_log_ralat')->whereIn('id_korelasi', $ids)->orderByDesc('tarikh_masa')->limit(50)
                ->get(['id', 'id_korelasi', 'tarikh_masa', 'aras_keterukan', 'modul', 'kod_ralat', 'mesej'])
                ->map(fn ($r) => [
                    'id' => (int) $r->id, 'id_korelasi' => $r->id_korelasi, 'tarikh_masa' => (string) $r->tarikh_masa, 'aras' => $r->aras_keterukan,
                    'modul' => $r->modul, 'kod_ralat' => $r->kod_ralat, 'mesej' => mb_strimwidth((string) $r->mesej, 0, 240, '…'),
                    'pautan' => '/admin/pentadbiran/log-ralat?id_korelasi='.rawurlencode((string) $r->id_korelasi),
                ])->values()->all();
        }

        return ['id_korelasi' => $ids, 'entri' => $entries, 'boleh_lihat' => $allowed];
    }
}
