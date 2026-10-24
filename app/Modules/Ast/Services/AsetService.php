<?php

namespace App\Modules\Ast\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Ast\Models\AsetAudit;
use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Models\AsetKategori;
use App\Modules\Ast\Models\AsetKondisiSejarah;
use App\Modules\Ast\Models\AsetNilaiSejarah;
use App\Modules\Ast\Models\AsetQr;
use App\Modules\Ast\Models\AsetTag;
use App\Modules\Ast\Support\AliranAst;
use App\Modules\Ast\Support\KodQr;
use App\Modules\Ast\Support\Lalai;
use App\Modules\Ast\Support\SusutNilai;
use App\Modules\Aud\Contracts\AuditScope;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Pro\Contracts\LotRegistry;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * The asset register (SRS-AST-01..06, 11, 18, 23). Registration picks the lot
 * through MOD-PRO's LotRegistry under the user's scope, copies the lot's
 * daerah / mukim / klasifikasi for record scoping, generates the reference
 * number and a never-reused QR reference, places the asset point (given GPS
 * or the lot's centre) and creates the category's maintenance schedules.
 * Value, condition and responsible-officer changes are written to
 * aset_audit (append-only) besides MOD-AUD's automatic before/after entry.
 */
class AsetService
{
    /** Fields an ordinary update may change (value / condition / lot have their own paths). */
    public const MEDAN_KEMASKINI = [
        'id_kategori', 'jenis_aset', 'nama_aset', 'keterangan', 'alamat', 'tahun_pembinaan', 'tahun_perolehan', 'tarikh_perolehan',
        'saiz_keluasan', 'unit_ukuran', 'kos_perolehan', 'id_pegawai_bertanggungjawab', 'id_aset_induk', 'status_aset', 'id_premis_pjk',
    ];

    public function __construct(
        private LotRegistry $lots,
        private KodQr $qr,
        private Parameters $parameters,
        private WorkflowEngine $workflow,
        private Notifier $notifier,
        private PenyelenggaraanService $penyelenggaraan,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function daftar(array $data, User $user): AsetFizikal
    {
        $lot = $this->lots->find((string) $data['lot_id'], $user) ?? throw ValidationException::withMessages(['lot_id' => __('ast::ast.lot_tidak_wujud')]);
        $kategori = AsetKategori::query()->where('status_aktif', true)->findOr((int) $data['id_kategori'], fn () => throw ValidationException::withMessages(['id_kategori' => __('ast::ast.kategori_tidak_sah')]));
        if (isset($data['nilai_semasa']) && ! $user->can('ast.sulit.kemas_kini')) {
            throw ValidationException::withMessages(['nilai_semasa' => __('ast::ast.nilai_tiada_kuasa')]);
        }

        return DB::transaction(function () use ($data, $user, $lot, $kategori) {
            $induk = $this->semakInduk($data['id_aset_induk'] ?? null, $user, null);
            $this->semakPremis($data['id_premis_pjk'] ?? null, $lot['id'], null);
            [$lat, $lng] = $this->koordinat($data, $lot['id']);
            $ref = $this->qr->rujukanBaharu();
            $kos = isset($data['kos_perolehan']) ? SusutNilai::bundar((string) $data['kos_perolehan']) : null;
            $nilai = isset($data['nilai_semasa']) ? SusutNilai::bundar((string) $data['nilai_semasa']) : $kos;
            $ambang = (string) $this->parameters->get('ast.ambang_kelulusan_perolehan_rm', 500000);
            $perluLulus = $kos !== null && bccomp($kos, $ambang, 2) >= 0;

            $a = new AsetFizikal;
            $a->forceFill([
                'no_rujukan_aset' => $this->noRujukan($lot['kod_daerah']), 'qr_ref' => $ref, 'lot_id' => $lot['id'], 'id_aset_induk' => $induk?->id,
                'id_kategori' => $kategori->id, 'jenis_aset' => $data['jenis_aset'] ?? $kategori->jenis_lalai, 'nama_aset' => $data['nama_aset'],
                'keterangan' => $data['keterangan'] ?? null, 'alamat' => $data['alamat'] ?? null, 'latitud' => $lat, 'longitud' => $lng,
                'tahun_pembinaan' => $data['tahun_pembinaan'] ?? null, 'tahun_perolehan' => $data['tahun_perolehan'] ?? (isset($data['tarikh_perolehan']) ? Carbon::parse($data['tarikh_perolehan'])->year : null),
                'tarikh_perolehan' => $data['tarikh_perolehan'] ?? null, 'saiz_keluasan' => $data['saiz_keluasan'] ?? null, 'unit_ukuran' => $data['unit_ukuran'] ?? 'm2',
                'kos_perolehan' => $kos, 'nilai_semasa' => $nilai, 'tarikh_nilai_semasa' => $nilai !== null ? ($data['tarikh_nilai_semasa'] ?? now()->toDateString()) : null,
                'status_kondisi' => $data['status_kondisi'] ?? 'baik', 'id_pegawai_bertanggungjawab' => $data['id_pegawai_bertanggungjawab'] ?? $user->id,
                'status_aset' => 'aktif', 'status_kelulusan' => $perluLulus ? 'menunggu' : 'lulus', 'id_premis_pjk' => $data['id_premis_pjk'] ?? null,
                'kod_daerah' => $lot['kod_daerah'], 'kod_mukim' => $lot['kod_mukim'], 'klasifikasi' => $lot['klasifikasi'] ?: 'dalaman',
                'id_pencipta' => $user->id, 'sintetik' => (bool) ($data['sintetik'] ?? false), 'id_sumber_gis' => $data['id_sumber_gis'] ?? null,
            ])->save();
            $this->letakTitik($a);

            AsetQr::query()->create([
                'qr_ref' => $ref, 'id_aset' => $a->id, 'tarikh_jana' => now(), 'pautan_awam' => $this->qr->pautan($ref), 'status_aktif' => true,
            ]);
            AsetKondisiSejarah::query()->create(['id_aset' => $a->id, 'status_lama' => null, 'status_baharu' => $a->status_kondisi, 'tarikh_perubahan' => now(), 'id_pengguna' => $user->id, 'catatan' => 'Pendaftaran aset']);
            if ($nilai !== null) {
                AsetNilaiSejarah::query()->create([
                    'id_aset' => $a->id, 'tahun' => (int) Carbon::parse($a->tarikh_nilai_semasa ?? now())->year, 'nilai' => $nilai, 'nilai_sebelum' => null,
                    'kaedah_penilaian' => isset($data['nilai_semasa']) ? ($data['kaedah_penilaian'] ?? 'penilaian_dalaman') : 'kos_perolehan',
                    'id_laporan_penilaian' => $data['id_laporan_penilaian'] ?? null, 'tarikh_penilaian' => $a->tarikh_nilai_semasa ?? now(), 'tarikh_rekod' => now(), 'id_pengguna' => $user->id,
                ]);
            }
            foreach ((array) ($data['tag'] ?? []) as $t) {
                $this->tambahTag($a, (string) $t, $user);
            }
            $this->penyelenggaraan->janaJadualUntuk($a, $kategori);

            if ($perluLulus) {
                try {
                    $a->forceFill(['id_aliran' => $this->workflow->start(AliranAst::PEROLEHAN, AliranAst::SUBJEK_ASET, $a->id, $user, [
                        'kod_daerah' => $a->kod_daerah, 'kategori' => $kategori->kod, 'kos_perolehan' => (float) $kos, 'pautan' => '/admin/ast/aset/'.$a->id,
                    ], 'Perolehan aset '.$a->no_rujukan_aset.' — '.$a->nama_aset)])->save();
                } catch (InvalidArgumentException) {
                    throw ValidationException::withMessages(['kos_perolehan' => __('ast::ast.aliran_tiada')]);
                }
            }

            return $a->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function kemaskini(AsetFizikal $a, array $data, User $user): AsetFizikal
    {
        if ($a->status_arkib) {
            throw ValidationException::withMessages(['aset' => __('ast::ast.aset_diarkib')]);
        }

        return DB::transaction(function () use ($a, $data, $user) {
            if (array_key_exists('id_aset_induk', $data)) {
                $this->semakInduk($data['id_aset_induk'], $user, $a);
            }
            if (array_key_exists('id_premis_pjk', $data)) {
                $this->semakPremis($data['id_premis_pjk'], $a->lot_id, $a->id);
            }
            if (isset($data['id_kategori'])) {
                AsetKategori::query()->where('status_aktif', true)->findOr((int) $data['id_kategori'], fn () => throw ValidationException::withMessages(['id_kategori' => __('ast::ast.kategori_tidak_sah')]));
            }
            $changes = array_intersect_key($data, array_flip(self::MEDAN_KEMASKINI));
            if (isset($changes['status_aset']) && in_array($changes['status_aset'], array_values(Lalai::STATUS_SELEPAS_PELUPUSAN), true)) {
                throw ValidationException::withMessages(['status_aset' => __('ast::ast.status_melalui_pelupusan')]);
            }
            if (isset($changes['kos_perolehan'])) {
                $changes['kos_perolehan'] = SusutNilai::bundar((string) $changes['kos_perolehan']);
            }
            $lamaPegawai = $a->id_pegawai_bertanggungjawab;
            $a->fill($changes);
            if (isset($data['latitud'], $data['longitud'])) {
                $a->latitud = (string) $data['latitud'];
                $a->longitud = (string) $data['longitud'];
            }
            $koordinatBerubah = $a->isDirty(['latitud', 'longitud']);
            $a->save();
            if ($koordinatBerubah) {
                $this->letakTitik($a);
            }
            if (array_key_exists('id_pegawai_bertanggungjawab', $changes) && (int) $lamaPegawai !== (int) $a->id_pegawai_bertanggungjawab) {
                $this->jejak($a, 'id_pegawai_bertanggungjawab', $this->namaPegawai($lamaPegawai), $this->namaPegawai($a->id_pegawai_bertanggungjawab), $user, $data['sebab'] ?? null);
            }
            if (array_key_exists('tag', $data)) {
                $this->setTag($a, (array) $data['tag'], $user);
            }

            return $a->refresh();
        });
    }

    /** SRS-AST-05: condition change with history and audit; critical → notification. */
    public function ubahKondisi(AsetFizikal $a, string $status, User $user, ?string $catatan = null, ?string $idPemeriksaan = null, ?Carbon $pada = null): void
    {
        if (! array_key_exists($status, Lalai::KONDISI)) {
            throw ValidationException::withMessages(['status_kondisi' => __('ast::ast.kondisi_tidak_sah')]);
        }
        $lama = $a->status_kondisi;
        if ($lama === $status) {
            return;
        }
        AuditScope::withReason($catatan ?: 'Perubahan status kondisi aset', function () use ($a, $status) {
            $a->forceFill(['status_kondisi' => $status])->save();
        });
        AsetKondisiSejarah::query()->create([
            'id_aset' => $a->id, 'status_lama' => $lama, 'status_baharu' => $status, 'tarikh_perubahan' => $pada ?? now(),
            'id_pemeriksaan' => $idPemeriksaan, 'id_pengguna' => $user->id, 'catatan' => $catatan,
        ]);
        $this->jejak($a, 'status_kondisi', $lama, $status, $user, $catatan);
        if ($status === 'kritikal') {
            $to = $a->pegawai ? [$a->pegawai] : [];
            $this->notifier->send('ast.kondisi_kritikal', $to, [
                'nama_aset' => $a->nama_aset, 'no_rujukan' => $a->no_rujukan_aset, 'tarikh' => now()->format('d/m/Y'), 'catatan' => mb_substr((string) $catatan, 0, 200),
            ], '/admin/ast/aset/'.$a->id, 'ast.kritikal:'.$a->id.':'.now()->toDateString());
        }
    }

    /**
     * SRS-AST-18 / security control: value update from a valuation report — only
     * ast.sulit.kemas_kini (checked by the route); history per year + audit.
     *
     * @param  array{nilai: string|float|int, tarikh_penilaian: string, kaedah_penilaian: string, id_laporan_penilaian?: string|null, id_dokumen_dms?: string|null, catatan?: string|null}  $d
     */
    public function kemaskiniNilai(AsetFizikal $a, array $d, User $user): AsetNilaiSejarah
    {
        if ($a->status_arkib) {
            throw ValidationException::withMessages(['aset' => __('ast::ast.aset_diarkib')]);
        }

        return DB::transaction(function () use ($a, $d, $user) {
            $nilai = SusutNilai::bundar((string) $d['nilai']);
            $tarikh = Carbon::parse($d['tarikh_penilaian']);
            $lama = $a->nilai_semasa;
            $row = AsetNilaiSejarah::query()->create([
                'id_aset' => $a->id, 'tahun' => $tarikh->year, 'nilai' => $nilai, 'nilai_sebelum' => $lama, 'kaedah_penilaian' => $d['kaedah_penilaian'],
                'id_laporan_penilaian' => $d['id_laporan_penilaian'] ?? null, 'id_dokumen_dms' => $d['id_dokumen_dms'] ?? null, 'tarikh_penilaian' => $tarikh,
                'tarikh_rekod' => now(), 'id_pengguna' => $user->id, 'catatan' => $d['catatan'] ?? null,
            ]);
            // The latest valuation by date is the current value (a back-dated report does not overwrite a newer one).
            if ($a->tarikh_nilai_semasa === null || $tarikh->greaterThanOrEqualTo($a->tarikh_nilai_semasa)) {
                AuditScope::withReason('Kemas kini nilai semasa: '.($d['id_laporan_penilaian'] ?? Lalai::KAEDAH_PENILAIAN[$d['kaedah_penilaian']] ?? ''), function () use ($a, $nilai, $tarikh) {
                    $a->forceFill(['nilai_semasa' => $nilai, 'tarikh_nilai_semasa' => $tarikh->toDateString()])->save();
                });
                $this->jejak($a, 'nilai_semasa', $lama, $nilai, $user, $d['id_laporan_penilaian'] ?? null);
            }

            return $row;
        });
    }

    public function tambahTag(AsetFizikal $a, string $nama, User $user): ?AsetTag
    {
        $nama = mb_strtolower(trim(preg_replace('/\s+/', '_', $nama) ?? ''));
        $nama = preg_replace('/[^a-z0-9_\-]/u', '', $nama) ?? '';
        if ($nama === '' || mb_strlen($nama) > 60) {
            return null;
        }
        $berstruktur = in_array($nama, (array) $this->parameters->get('ast.tag_berstruktur', []), true);

        return AsetTag::query()->firstOrCreate(['id_aset' => $a->id, 'nama_tag' => $nama], [
            'jenis_tag' => $berstruktur ? 'berstruktur' : 'bebas', 'id_pengguna' => $user->id, 'tarikh_tambah' => now(),
        ]);
    }

    /** @param list<mixed> $tags */
    public function setTag(AsetFizikal $a, array $tags, User $user): void
    {
        $keep = [];
        foreach ($tags as $t) {
            if (($row = $this->tambahTag($a, (string) $t, $user)) !== null) {
                $keep[] = $row->id;
            }
        }
        AsetTag::query()->where('id_aset', $a->id)->whereNotIn('id', $keep ?: [0])->get()->each->delete();
    }

    /** SRS-AST-06: deactivate the current QR (lost / damaged label) and issue a new one; the old reference stays reserved. */
    public function janaSemulaQr(AsetFizikal $a, User $user, string $sebab): AsetQr
    {
        return DB::transaction(function () use ($a, $sebab) {
            AsetQr::query()->where('id_aset', $a->id)->where('status_aktif', true)->get()
                ->each(fn (AsetQr $q) => $q->forceFill(['status_aktif' => false, 'tarikh_nyahaktif' => now(), 'sebab_nyahaktif' => mb_substr($sebab, 0, 255)])->save());
            $ref = $this->qr->rujukanBaharu();
            $q = AsetQr::query()->create(['qr_ref' => $ref, 'id_aset' => $a->id, 'tarikh_jana' => now(), 'pautan_awam' => $this->qr->pautan($ref), 'status_aktif' => true]);
            $a->forceFill(['qr_ref' => $ref])->save();

            return $q;
        });
    }

    public function statusQr(AsetQr $q, bool $aktif, ?string $sebab): void
    {
        $q->forceFill($aktif ? ['status_aktif' => true, 'tarikh_nyahaktif' => null, 'sebab_nyahaktif' => null] : ['status_aktif' => false, 'tarikh_nyahaktif' => now(), 'sebab_nyahaktif' => mb_substr((string) $sebab, 0, 255)])->save();
    }

    /** Archive (never delete) after an approved disposal (SRS security control). */
    public function arkib(AsetFizikal $a, string $statusAset, string $sebab): void
    {
        AuditScope::withReason($sebab, fn () => $a->forceFill([
            'status_aset' => $statusAset, 'status_arkib' => true, 'tarikh_arkib' => now()->toDateString(), 'sebab_arkib' => mb_substr($sebab, 0, 1000),
        ])->save());
        // Archived assets keep their QR reference reserved; the public page shows "tidak aktif".
        AsetQr::query()->where('id_aset', $a->id)->where('status_aktif', true)->get()
            ->each(fn (AsetQr $q) => $q->forceFill(['status_aktif' => false, 'tarikh_nyahaktif' => now(), 'sebab_nyahaktif' => 'Aset diarkibkan: '.Lalai::STATUS_ASET[$statusAset]])->save());
    }

    public function jejak(AsetFizikal $a, string $medan, mixed $lama, mixed $baharu, ?User $user, ?string $sebab = null): void
    {
        AsetAudit::query()->create([
            'id_aset' => $a->id, 'medan_diubah' => $medan, 'nilai_sebelum' => $lama === null ? null : (string) $lama, 'nilai_selepas' => $baharu === null ? null : (string) $baharu,
            'id_pengguna' => $user?->id, 'sebab' => $sebab !== null ? mb_substr($sebab, 0, 255) : AuditScope::currentReason(), 'cap_masa' => now(),
        ]);
    }

    public function letakTitik(AsetFizikal $a): void
    {
        if ($a->latitud !== null && $a->longitud !== null) {
            DB::update('UPDATE aset_fizikal SET koordinat = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?', [(float) $a->longitud, (float) $a->latitud, $a->id]);
        }
    }

    private function noRujukan(string $kodDaerah): string
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['ast_no_rujukan_'.$kodDaerah]);
        $prefix = 'AST-'.$kodDaerah.'-'.now()->year.'-';
        $last = DB::table('aset_fizikal')->where('no_rujukan_aset', 'like', $prefix.'%')->max('no_rujukan_aset');
        $n = $last ? ((int) substr((string) $last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Given GPS, else the lot's centre (lot_tanah.koordinat_pusat or a point on the current polygon).
     *
     * @param  array<string, mixed>  $data
     * @return array{0: string|null, 1: string|null}
     */
    private function koordinat(array $data, string $lotId): array
    {
        if (isset($data['latitud'], $data['longitud'])) {
            return [(string) $data['latitud'], (string) $data['longitud']];
        }
        $p = DB::selectOne('SELECT ST_Y(p) AS lat, ST_X(p) AS lng FROM (
                SELECT COALESCE(l.koordinat_pusat, (SELECT ST_PointOnSurface(ds.geometri) FROM data_spatial ds WHERE ds.lot_id = l.id AND ds.semasa LIMIT 1)) AS p
                FROM lot_tanah l WHERE l.id = ?) s WHERE p IS NOT NULL', [$lotId]);

        return $p ? [number_format((float) $p->lat, 7, '.', ''), number_format((float) $p->lng, 7, '.', '')] : [null, null];
    }

    private function semakInduk(mixed $id, User $user, ?AsetFizikal $self): ?AsetFizikal
    {
        if ($id === null || $id === '') {
            return null;
        }
        $induk = AsetFizikal::query()->untuk($user)->where('status_arkib', false)->find((string) $id) ?? throw ValidationException::withMessages(['id_aset_induk' => __('ast::ast.induk_tidak_sah')]);
        if ($self !== null) {
            // SRS-AST-04 hierarchy must stay a tree: the parent cannot be the asset or one of its descendants.
            $cursor = $induk;
            for ($i = 0; $cursor !== null && $i < 20; $i++) {
                if ($cursor->id === $self->id) {
                    throw ValidationException::withMessages(['id_aset_induk' => __('ast::ast.induk_kitaran')]);
                }
                $cursor = $cursor->id_aset_induk ? AsetFizikal::query()->withoutGlobalScope('access')->find($cursor->id_aset_induk) : null;
            }
        }

        return $induk;
    }

    /** MOD-PJK premise link (read-only): must exist on the same lot and not be linked to another asset. */
    private function semakPremis(mixed $id, string $lotId, ?string $selfId): void
    {
        if ($id === null || $id === '') {
            return;
        }
        $p = DB::table('premis_aset')->whereNull('deleted_at')->where('id', (int) $id)->first(['id', 'lot_id']);
        if ($p === null || (string) $p->lot_id !== $lotId) {
            throw ValidationException::withMessages(['id_premis_pjk' => __('ast::ast.premis_tidak_sah')]);
        }
        $other = DB::table('aset_fizikal')->where('id_premis_pjk', (int) $id)->when($selfId, fn ($q) => $q->where('id', '!=', $selfId))->exists();
        if ($other) {
            throw ValidationException::withMessages(['id_premis_pjk' => __('ast::ast.premis_sudah_dipaut')]);
        }
    }

    private function namaPegawai(?int $id): ?string
    {
        return $id === null ? null : (string) (User::query()->whereKey($id)->value('name') ?? $id);
    }
}
