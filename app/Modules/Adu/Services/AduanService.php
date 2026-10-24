<?php

namespace App\Modules\Adu\Services;

use App\Models\User;
use App\Modules\Adu\Models\Aduan;
use App\Modules\Adu\Models\DokumenAduan;
use App\Modules\Adu\Models\EskalasiAduan;
use App\Modules\Adu\Models\LawatanTapak;
use App\Modules\Adu\Models\Pengadu;
use App\Modules\Adu\Models\ResolusiAduan;
use App\Modules\Adu\Models\RujukanAduan;
use App\Modules\Adu\Support\Akses;
use App\Modules\Adu\Support\Lalai;
use App\Modules\Adu\Support\PenggunaSistem;
use App\Modules\Adu\Support\Rujukan;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Ntf\Contracts\RecipientDirectory;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * MOD-ADU lifecycle (SRS-ADU-01, 03, 06, 09–12, 15, 17–19, 23): intake from
 * every channel into one queue, triage, assignment, investigation actions,
 * site visits, referral, escalation, resolution with supervisor approval
 * through MOD-WFE (flow `adu.penutupan`, segregation of duties enforced by
 * the engine), reopen and duplicate merge. Every step lands in the
 * immutable timeline; data changes are audited by MOD-AUD automatically.
 */
class AduanService
{
    public const FLOW = 'adu.penutupan';

    public const SUBJECT = 'aduan';

    public function __construct(
        private Triaj $triaj,
        private Penugasan $penugasan,
        private SlaAduan $sla,
        private Kronologi $kronologi,
        private MaklumPengadu $maklum,
        private SegerakPusat $pusat,
        private Akses $akses,
        private Rujukan $ref,
        private Notifier $notifier,
        private WorkflowEngine $workflow,
        private Application $app,
    ) {}

    /**
     * Open a complaint (all channels). Idempotent on (channel, sourceRef).
     *
     * @param  array<string, mixed>  $data  kategori, subkategori, keterangan, lokasi_teks, lat, lng, lot_id, kod_daerah, kod_mukim, tahap_keutamaan, tarikh_aduan, id_aduan_sistem_luaran, sintetik
     * @param  array{nama?: string|null, no_telefon?: string|null, emel?: string|null, alamat?: string|null, saluran_makluman?: string|null}|null  $pengadu  null / tanpa_nama → anonymous
     * @param  list<UploadedFile>  $files
     */
    public function terima(array $data, string $saluran, ?User $oleh, ?string $sourceRef = null, ?array $pengadu = null, bool $tanpaNama = false, array $files = [], ?User $pemuatNaik = null): Aduan
    {
        if (! array_key_exists($saluran, Lalai::SALURAN)) {
            throw new InvalidArgumentException("Saluran aduan tidak dikenali: {$saluran}");
        }
        if ($sourceRef !== null && ($existing = Aduan::query()->withoutGlobalScope('access')->where('saluran_penerimaan', $saluran)->where('rujukan_sumber', $sourceRef)->first())) {
            return $existing;
        }

        $lat = isset($data['lat']) && $data['lat'] !== '' ? (float) $data['lat'] : null;
        $lng = isset($data['lng']) && $data['lng'] !== '' ? (float) $data['lng'] : null;
        $hasIdentity = ! $tanpaNama && $pengadu !== null && collect(Pengadu::MEDAN_IDENTITI)->contains(fn ($f) => ($pengadu[$f] ?? null) !== null && $pengadu[$f] !== '');

        $result = DB::transaction(function () use ($data, $saluran, $oleh, $sourceRef, $pengadu, $hasIdentity, $lat, $lng) {
            $seq = (int) DB::selectOne("SELECT nextval('aduan_no_rujukan_seq') AS n")->n;
            $aduan = new Aduan([
                'no_rujukan_aduan' => sprintf('ADU-%s-%06d', now()->format('Y'), $seq),
                'saluran_penerimaan' => $saluran,
                'tarikh_terima' => now(),
                'tarikh_aduan' => $data['tarikh_aduan'] ?? now()->toDateString(),
                'kategori' => ! empty($data['kategori']) && $this->ref->kategoriSah((string) $data['kategori']) ? $data['kategori'] : null,
                'subkategori' => $data['subkategori'] ?? null,
                'keterangan' => $data['keterangan'] ?? null,
                'lokasi_teks' => $data['lokasi_teks'] ?? null,
                'lot_id' => $data['lot_id'] ?? null,
                'kod_daerah' => $data['kod_daerah'] ?? null,
                'kod_mukim' => $data['kod_mukim'] ?? null,
                'tahap_keutamaan' => $data['tahap_keutamaan'] ?? 'sederhana',
                'status' => 'baharu',
                'status_tanpa_nama' => ! $hasIdentity,
                'klasifikasi' => 'dalaman',
                'id_pencipta' => $oleh?->id,
                'rujukan_sumber' => $sourceRef,
                'id_aduan_sistem_luaran' => $data['id_aduan_sistem_luaran'] ?? ($saluran === 'sistem_aduan_pusat' ? $sourceRef : null),
                'sintetik' => (bool) ($data['sintetik'] ?? false),
            ]);
            $aduan->setKoordinat($lat, $lng);
            $triage = $this->triaj->jalankan($aduan, $lat, $lng);
            if ($aduan->kod_mukim !== null && $aduan->kod_daerah === null) {
                $aduan->kod_daerah = substr($aduan->kod_mukim, 0, 4);
            }
            $aduan->save();

            if ($hasIdentity) {
                Pengadu::query()->create([
                    'aduan_id' => $aduan->id,
                    'no_rujukan_aduan' => $aduan->no_rujukan_aduan,
                    'nama' => $pengadu['nama'] ?? null,
                    'no_telefon' => $pengadu['no_telefon'] ?? null,
                    'emel' => $pengadu['emel'] ?? null,
                    'alamat' => $pengadu['alamat'] ?? null,
                    'cincangan_emel' => Pengadu::cincang($pengadu['emel'] ?? null, 'emel'),
                    'cincangan_telefon' => Pengadu::cincang($pengadu['no_telefon'] ?? null, 'telefon'),
                    'saluran_makluman' => $pengadu['saluran_makluman'] ?? (($pengadu['emel'] ?? null) ? 'emel' : (($pengadu['no_telefon'] ?? null) ? 'sms' : 'tiada')),
                    'status_tanpa_nama' => false,
                    'sintetik' => (bool) ($data['sintetik'] ?? false),
                ]);
            }

            $this->kronologi->rekod($aduan, 'terima', (string) __('adu::adu.kronologi.terima', ['saluran' => Lalai::SALURAN[$saluran]]), $oleh, null, 'baharu', ['saluran' => $saluran, 'rujukan_sumber' => $sourceRef]);
            $this->kronologi->rekod($aduan, 'triaj', $this->ringkasTriaj($triage), null, data: $triage);
            $this->sla->mula($aduan);

            $pegawai = $aduan->status_bukan_tanah_mais ? null : $this->penugasan->cadang($aduan);
            if ($pegawai !== null) {
                $this->tetapkanPegawai($aduan, $pegawai, null, (string) __('adu::adu.kronologi.tugas_auto'));
            }

            return $aduan;
        });

        foreach ($files as $file) {
            try {
                $this->lampir($result, $file, $pemuatNaik ?? $oleh, $saluran === 'portal' ? 'portal' : ($saluran === 'lapangan' ? 'lapangan' : 'dalaman'));
            } catch (Throwable $e) {
                $this->kronologi->rekod($result, 'dokumen', (string) __('adu::adu.kronologi.dokumen_ditolak', ['nama' => $file->getClientOriginalName(), 'sebab' => $e->getMessage()]));
            }
        }

        $this->beritahu('adu.aduan_baharu', $this->unitAduan($result), $result);
        if ($result->id_pegawai !== null) {
            $this->beritahu('adu.aduan_ditugaskan', $result->pegawai ? [$result->pegawai] : [], $result);
        }
        $this->maklum->hantar($result, 'diterima');

        return $result->refresh();
    }

    /** @param array<string, mixed> $data kategori, subkategori, tahap_keutamaan, lot_id, lokasi_teks, keterangan_tambahan */
    public function kemasKini(Aduan $aduan, array $data, User $oleh): Aduan
    {
        $this->pastikanTerbuka($aduan);
        $before = $aduan->only(['kategori', 'subkategori', 'tahap_keutamaan', 'lot_id', 'lokasi_teks']);
        $aduan->fill(array_intersect_key($data, array_flip(['kategori', 'subkategori', 'tahap_keutamaan', 'lokasi_teks'])));
        if (array_key_exists('lot_id', $data) && $data['lot_id'] !== $aduan->lot_id) {
            $aduan->lot_id = $data['lot_id'];
            $this->triaj->jalankan($aduan, null, null);
            $aduan->status_bukan_tanah_mais = $aduan->lot_id === null && $aduan->status_bukan_tanah_mais;
        }
        $changes = array_filter($aduan->getDirty(), fn ($k) => array_key_exists($k, $before), ARRAY_FILTER_USE_KEY);
        $aduan->save();
        if ($changes !== []) {
            $this->kronologi->rekod($aduan, 'kemas_kini', (string) __('adu::adu.kronologi.kemas_kini', ['medan' => implode(', ', array_keys($changes))]), $oleh, data: ['sebelum' => array_intersect_key($before, $changes), 'selepas' => $changes]);
        }

        return $aduan;
    }

    /** SRS-ADU-11/17: manual (re)assignment by Unit Aduan / supervisor. */
    public function tugaskan(Aduan $aduan, User $pegawai, User $oleh, ?string $catatan = null): Aduan
    {
        $this->pastikanTerbuka($aduan);
        if (! $this->penugasan->layak($pegawai, $aduan)) {
            abort(422, __('adu::adu.ralat.pegawai_tidak_layak'));
        }
        $reassign = $aduan->id_pegawai !== null;
        DB::transaction(fn () => $this->tetapkanPegawai($aduan, $pegawai, $oleh, $catatan, $reassign));
        $this->beritahu('adu.aduan_ditugaskan', [$pegawai], $aduan);
        if (! $reassign) {
            $this->maklum->hantar($aduan, 'ditugaskan');
        }

        return $aduan;
    }

    public function mulaSiasatan(Aduan $aduan, User $oleh): Aduan
    {
        $this->pastikanStatus($aduan, ['ditugaskan', 'dirujuk']);
        $before = $aduan->status;
        $aduan->update(['status' => 'dalam_siasatan']);
        $this->sla->respons($aduan);
        $this->kronologi->rekod($aduan, 'mula_siasatan', null, $oleh, $before, 'dalam_siasatan');
        $this->maklum->hantar($aduan, 'siasatan');
        $this->pusat->catat($aduan);

        return $aduan;
    }

    /** SRS-ADU-17/18: follow-up action, note, correction or communication with the reporter. */
    public function tindakan(Aduan $aduan, User $oleh, string $jenis, string $keterangan, bool $maklumPengadu = false): void
    {
        if (! in_array($jenis, ['tindakan', 'catatan', 'komunikasi', 'pembetulan'], true)) {
            throw new InvalidArgumentException('jenis');
        }
        if ($jenis !== 'pembetulan') {
            $this->pastikanTerbuka($aduan);
        }
        $this->kronologi->rekod($aduan, $jenis, $keterangan, $oleh, komunikasi: $jenis === 'komunikasi');
        if ($jenis === 'tindakan') {
            $aduan->update(['tindakan_susulan' => $keterangan]);
            $this->sla->respons($aduan);
        }
        if ($jenis === 'komunikasi' && $maklumPengadu) {
            $this->maklum->hantar($aduan, 'komunikasi', $keterangan);
        }
    }

    public function jadualLawatan(Aduan $aduan, User $oleh, string $tarikh, ?int $idPegawai = null): LawatanTapak
    {
        $this->pastikanTerbuka($aduan);
        $visit = LawatanTapak::query()->create([
            'aduan_id' => $aduan->id, 'no_rujukan_aduan' => $aduan->no_rujukan_aduan,
            'tarikh_jadual' => $tarikh, 'id_pegawai' => $idPegawai ?? $aduan->id_pegawai ?? $oleh->id, 'status' => 'dijadualkan',
        ]);
        $this->kronologi->rekod($aduan, 'lawatan_dijadual', (string) __('adu::adu.kronologi.lawatan_dijadual', ['tarikh' => $tarikh]), $oleh, data: ['id_lawatan' => $visit->id]);
        $this->sla->respons($aduan);

        return $visit;
    }

    public function rekodLawatan(LawatanTapak $visit, User $oleh, string $penemuan, ?string $tarikh = null, ?string $idRekodPtn = null): LawatanTapak
    {
        $aduan = $visit->aduan;
        $visit->update(['status' => 'selesai', 'tarikh_lawatan' => $tarikh ?? now(), 'penemuan' => $penemuan, 'id_rekod_lawatan_ptn' => $idRekodPtn]);
        $this->kronologi->rekod($aduan, 'lawatan_selesai', $penemuan, $oleh, data: ['id_lawatan' => $visit->id]);

        return $visit;
    }

    /** Process step 8: refer to enforcement / legal / property division or an outside agency. */
    public function rujuk(Aduan $aduan, User $oleh, string $jenis, string $keterangan, ?string $agensi = null): RujukanAduan
    {
        $this->pastikanStatus($aduan, ['ditugaskan', 'dalam_siasatan', 'dirujuk']);
        $before = $aduan->status;
        $ref = RujukanAduan::query()->create([
            'aduan_id' => $aduan->id, 'no_rujukan_aduan' => $aduan->no_rujukan_aduan, 'jenis_rujukan' => $jenis,
            'nama_agensi' => $agensi, 'keterangan' => $keterangan, 'id_pegawai' => $oleh->id, 'status' => 'dihantar',
        ]);
        $aduan->update(['status' => 'dirujuk', 'bahagian_dirujuk' => $jenis]);
        $this->sla->respons($aduan);
        $this->kronologi->rekod($aduan, 'rujuk', (string) __('adu::adu.kronologi.rujuk', ['kepada' => $agensi ?: Lalai::JENIS_RUJUKAN[$jenis] ?? $jenis]).' — '.$keterangan, $oleh, $before, 'dirujuk', ['id_rujukan' => $ref->id, 'jenis' => $jenis]);
        $this->beritahu('adu.rujukan', [], $aduan, ['bahagian' => Lalai::JENIS_RUJUKAN[$jenis] ?? $jenis]);
        $this->pusat->catat($aduan);

        return $ref;
    }

    public function eskalasiManual(Aduan $aduan, User $oleh, string $sebab, ?User $kepada = null): EskalasiAduan
    {
        $this->pastikanTerbuka($aduan);
        $recipients = $kepada ? [$kepada] : $this->akses->penyelia($aduan);
        $level = (int) EskalasiAduan::query()->where('aduan_id', $aduan->id)->max('peringkat') + 1;
        $row = EskalasiAduan::query()->create([
            'aduan_id' => $aduan->id, 'no_rujukan_aduan' => $aduan->no_rujukan_aduan, 'peringkat' => $level, 'jenis' => 'manual',
            'id_pegawai_asal' => $oleh->id, 'id_pegawai_eskalasi' => $recipients[0]->id ?? null,
            'penerima' => array_map(fn (User $u) => $u->id, $recipients), 'sebab_eskalasi' => $sebab, 'tarikh_eskalasi' => now(),
        ]);
        $this->kronologi->rekod($aduan, 'eskalasi', $sebab, $oleh, data: ['manual' => true, 'penerima' => $row->penerima]);
        $this->beritahu('adu.eskalasi', $recipients, $aduan, ['sebab' => mb_substr($sebab, 0, 200)]);

        return $row;
    }

    /** Merge into an earlier complaint (duplicate). */
    public function tandaPendua(Aduan $aduan, Aduan $induk, User $oleh): Aduan
    {
        $this->pastikanTerbuka($aduan);
        if ($induk->id === $aduan->id || $induk->status === 'pendua') {
            abort(422, __('adu::adu.ralat.induk_tidak_sah'));
        }
        $before = $aduan->status;
        DB::transaction(function () use ($aduan, $induk, $oleh, $before) {
            $aduan->update(['status' => 'pendua', 'id_aduan_induk' => $induk->id, 'tarikh_tutup' => now()]);
            $this->sla->selesai($aduan);
            $this->kronologi->rekod($aduan, 'pendua', (string) __('adu::adu.kronologi.pendua', ['induk' => $induk->no_rujukan_aduan]), $oleh, $before, 'pendua', ['id_induk' => $induk->id]);
            $this->kronologi->rekod($induk, 'catatan', (string) __('adu::adu.kronologi.pendua_digabung', ['no' => $aduan->no_rujukan_aduan]), $oleh);
        });
        $this->pusat->catat($aduan, 'pendua');

        return $aduan;
    }

    /**
     * SRS-ADU-10: officer confirms "bukan tanah MAIS" — the reviewed reply
     * goes to the reporter and the closure is requested like any other.
     *
     * @param  list<array{nama: string, jenis: string}>|null  $agensi
     */
    public function sahkanBukanMais(Aduan $aduan, User $oleh, string $jawapan, ?array $agensi = null): ResolusiAduan
    {
        $this->pastikanTerbuka($aduan);
        $aduan->update(['status_bukan_tanah_mais' => true, 'jawapan_automatik' => $jawapan, 'agensi_dicadang' => $agensi ?? $aduan->agensi_dicadang]);
        $this->kronologi->rekod($aduan, 'bukan_tanah_mais', $jawapan, $oleh);
        $sent = $this->maklum->hantar($aduan, 'bukan_tanah_mais', $jawapan);
        $aduan->update(['jawapan_dihantar' => $sent]);
        if ($aduan->id_pegawai === null) {
            $aduan->update(['id_pegawai' => $oleh->id]);
        }

        return $this->mohonPenutupan($aduan, $oleh, 'bukan_tanah_mais', $jawapan, (string) __('adu::adu.justifikasi_bukan_mais'));
    }

    /** SRS-ADU-19: resolution + justification, then supervisor approval in MOD-WFE. */
    public function mohonPenutupan(Aduan $aduan, User $oleh, string $kategoriResolusi, string $keterangan, string $justifikasi): ResolusiAduan
    {
        $this->pastikanStatus($aduan, ['baharu', 'ditugaskan', 'dalam_siasatan', 'dirujuk']);
        if (! array_key_exists($kategoriResolusi, Lalai::KATEGORI_RESOLUSI)) {
            abort(422, __('adu::adu.ralat.kategori_resolusi'));
        }

        return DB::transaction(function () use ($aduan, $oleh, $kategoriResolusi, $keterangan, $justifikasi) {
            $resolusi = ResolusiAduan::query()->create([
                'aduan_id' => $aduan->id, 'no_rujukan_aduan' => $aduan->no_rujukan_aduan,
                'kategori_resolusi' => $kategoriResolusi, 'keterangan_resolusi' => $keterangan, 'justifikasi' => $justifikasi,
                'id_pegawai_penyelesai' => $oleh->id, 'status' => 'menunggu',
                'status_makluman_pengadu' => $aduan->status_tanpa_nama ? 'tidak_berkenaan' : 'belum',
            ]);
            try {
                $instance = $this->workflow->start(self::FLOW, self::SUBJECT, $aduan->id, $oleh, [
                    'kategori' => $aduan->kategori, 'kod_daerah' => $aduan->kod_daerah, 'tahap_keutamaan' => $aduan->tahap_keutamaan,
                    'saluran' => $aduan->saluran_penerimaan, 'kategori_resolusi' => $kategoriResolusi, 'pautan' => '/admin/adu/aduan/'.$aduan->id,
                ], (string) __('adu::adu.tajuk_aliran', ['no' => $aduan->no_rujukan_aduan]));
            } catch (InvalidArgumentException) {
                abort(422, __('adu::adu.ralat.aliran_tidak_aktif'));
            }
            $before = $aduan->status;
            $resolusi->update(['id_instans_aliran' => $instance]);
            $aduan->update(['status' => 'menunggu_kelulusan', 'id_instans_aliran' => $instance]);
            $this->sla->respons($aduan);
            $this->kronologi->rekod($aduan, 'resolusi', Lalai::KATEGORI_RESOLUSI[$kategoriResolusi].' — '.$keterangan, $oleh, $before, 'menunggu_kelulusan', ['id_resolusi' => $resolusi->id, 'justifikasi' => $justifikasi]);

            return $resolusi;
        });
    }

    /** MOD-WFE outcome for `adu.penutupan` (listener). */
    public function handle(WorkflowCompleted $event): void
    {
        if ($event->flowCode !== self::FLOW || $event->subjectType !== self::SUBJECT) {
            return;
        }
        $aduan = Aduan::query()->withoutGlobalScope('access')->find($event->subjectId);
        $resolusi = $aduan ? ResolusiAduan::query()->where('aduan_id', $aduan->id)->where('status', 'menunggu')->latest('id')->first() : null;
        if ($aduan === null || $resolusi === null || $aduan->status !== 'menunggu_kelulusan') {
            return;
        }
        $pelulus = $event->decidedBy ? User::query()->find($event->decidedBy) : null;

        if ($event->outcome === 'lulus') {
            // SRS-ADU-19: the reporter is told of the resolution before the complaint is closed.
            $sent = $this->maklum->hantar($aduan->setAttribute('status', 'ditutup'), 'selesai', $resolusi->keterangan_resolusi);
            DB::transaction(function () use ($aduan, $resolusi, $event, $pelulus, $sent) {
                $resolusi->update([
                    'status' => 'lulus', 'id_penyelia_pelulus' => $event->decidedBy, 'ulasan_pelulus' => $event->remarks, 'tarikh_tutup' => now(),
                    'status_makluman_pengadu' => $aduan->status_tanpa_nama ? 'tidak_berkenaan' : ($sent ? 'dihantar' : 'belum'),
                ]);
                $aduan->update(['status' => 'ditutup', 'tarikh_tutup' => now(), 'id_instans_aliran' => null]);
                $this->sla->selesai($aduan);
                $this->kronologi->rekod($aduan, 'penutupan_diluluskan', $event->remarks, $pelulus, 'menunggu_kelulusan', 'ditutup', ['id_resolusi' => $resolusi->id]);
            });
            $this->beritahu('adu.aduan_selesai', array_values(array_filter([$aduan->pegawai, $resolusi->penyelesai])), $aduan);
            $this->pusat->catat($aduan, $resolusi->kategori_resolusi, $resolusi->keterangan_resolusi);

            return;
        }

        $resolusi->update(['status' => $event->outcome === 'tolak' ? 'tolak' : 'ditarik_balik', 'id_penyelia_pelulus' => $event->decidedBy, 'ulasan_pelulus' => $event->remarks]);
        $aduan->update(['status' => $aduan->id_pegawai ? 'dalam_siasatan' : 'baharu', 'id_instans_aliran' => null]);
        $this->kronologi->rekod($aduan, 'penutupan_ditolak', $event->remarks, $pelulus, 'menunggu_kelulusan', $aduan->status, ['keputusan' => $event->outcome]);
        $this->beritahu('adu.penutupan_ditolak', $resolusi->penyelesai ? [$resolusi->penyelesai] : [], $aduan, ['ulasan' => mb_substr((string) $event->remarks, 0, 200)]);
    }

    public function bukaSemula(Aduan $aduan, User $oleh, string $sebab): Aduan
    {
        $this->pastikanStatus($aduan, ['ditutup']);
        DB::transaction(function () use ($aduan, $oleh, $sebab) {
            $aduan->update([
                'status' => $aduan->id_pegawai ? 'ditugaskan' : 'baharu',
                'tarikh_tutup' => null,
                'bilangan_buka_semula' => $aduan->bilangan_buka_semula + 1,
            ]);
            $this->sla->mula($aduan);
            $this->kronologi->rekod($aduan, 'buka_semula', $sebab, $oleh, 'ditutup', $aduan->status);
        });
        if ($aduan->pegawai) {
            $this->beritahu('adu.aduan_ditugaskan', [$aduan->pegawai], $aduan);
        }
        $this->maklum->hantar($aduan, 'dibuka_semula');
        $this->pusat->catat($aduan);

        return $aduan;
    }

    /** SRS-ADU-02 / GEN-04: store a photo/document in MOD-DMS, linked to the complaint. */
    public function lampir(Aduan $aduan, UploadedFile $file, ?User $oleh, string $sumber = 'dalaman', ?float $lat = null, ?float $lng = null): DokumenAduan
    {
        $uploader = $oleh ?? PenggunaSistem::portal();
        $image = str_starts_with((string) $file->getMimeType(), 'image/');
        $id = $this->app->make(DocumentStore::class)->store($file, self::SUBJECT, $aduan->id, $image ? 'gambar_tapak' : 'lain', $uploader, 'dalaman', []);
        $row = DokumenAduan::query()->create([
            'aduan_id' => $aduan->id, 'no_rujukan_aduan' => $aduan->no_rujukan_aduan, 'id_dokumen' => $id,
            'jenis_dokumen' => $image ? 'gambar' : 'dokumen', 'laluan_simpanan' => 'dms:'.$id, 'saiz' => $file->getSize(),
            'jenis_fail' => $file->getMimeType(), 'sumber_muat_naik' => $sumber, 'lat' => $lat, 'lng' => $lng,
        ]);
        $this->kronologi->rekod($aduan, 'dokumen', $file->getClientOriginalName(), $oleh, idDokumen: $id);

        return $row;
    }

    private function tetapkanPegawai(Aduan $aduan, User $pegawai, ?User $oleh, ?string $catatan, bool $reassign = false): void
    {
        $before = $aduan->status;
        $penyelia = $this->penugasan->penyeliaUntuk($pegawai);
        $aduan->id_pegawai = $pegawai->id;
        $aduan->id_penyelia = $penyelia?->id;
        if ($aduan->status === 'baharu') {
            $aduan->status = 'ditugaskan';
        }
        $aduan->save();
        $aduan->setRelation('pegawai', $pegawai);
        $this->kronologi->rekod($aduan, $reassign ? 'tugas_semula' : 'tugas', trim(__('adu::adu.kronologi.tugas', ['nama' => $pegawai->name]).' '.($catatan ?? '')), $oleh, $before, $aduan->status, ['id_pegawai' => $pegawai->id, 'id_penyelia' => $penyelia?->id]);
    }

    /** Unit Aduan coordinators in scope (NTF recipient directory), for "aduan baharu". */
    private function unitAduan(Aduan $aduan): array
    {
        if (! $this->app->bound(RecipientDirectory::class)) {
            return [];
        }
        $access = $this->app->make(AccessControl::class);

        return array_values(array_filter(
            $this->app->make(RecipientDirectory::class)->resolve(RecipientDirectory::KEBENARAN, 'adu.penugasan.kemas_kini'),
            function (User $u) use ($access, $aduan) {
                $scope = $access->scope($u, 'daerah');

                return $scope === null || ($aduan->kod_daerah !== null && in_array($aduan->kod_daerah, $scope, true));
            },
        ));
    }

    /**
     * @param  iterable<User>  $to
     * @param  array<string, scalar|null>  $extra
     */
    private function beritahu(string $type, iterable $to, Aduan $aduan, array $extra = []): void
    {
        $unique = [];
        foreach ($to as $u) {
            $unique[$u->id] = $u;
        }
        try {
            $this->notifier->send($type, array_values($unique), [
                'no_rujukan' => $aduan->no_rujukan_aduan,
                'kategori' => $this->ref->namaKategori($aduan->kategori),
                'daerah' => $this->ref->namaDaerah($aduan->kod_daerah),
                'keutamaan' => Lalai::KEUTAMAAN[$aduan->tahap_keutamaan] ?? $aduan->tahap_keutamaan,
                ...$extra,
            ], '/admin/adu/aduan/'.$aduan->id, $type.':'.$aduan->id.':'.$aduan->status);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @param array<string, mixed> $t */
    private function ringkasTriaj(array $t): string
    {
        $parts = [];
        $parts[] = match ($t['kaedah_padanan_lot'] ?? null) {
            'pilihan' => __('adu::adu.triaj.lot_dipilih'),
            'koordinat' => __('adu::adu.triaj.lot_koordinat'),
            'tiada' => __('adu::adu.triaj.bukan_mais'),
            default => __('adu::adu.triaj.tiada_lokasi'),
        };
        if (! empty($t['kategori_dicadang'])) {
            $parts[] = __('adu::adu.triaj.kategori', ['kategori' => $this->ref->namaKategori($t['kategori_dicadang'])]);
        }
        if (! empty($t['pendua'])) {
            $parts[] = __('adu::adu.triaj.pendua', ['no' => $t['pendua']['no_rujukan'], 'skor' => $t['pendua']['skor']]);
        }

        return implode(' ', $parts);
    }

    /** @param list<string> $allowed */
    private function pastikanStatus(Aduan $aduan, array $allowed): void
    {
        if (! in_array($aduan->status, $allowed, true)) {
            abort(422, __('adu::adu.ralat.status_tidak_sah', ['status' => Lalai::STATUS[$aduan->status] ?? $aduan->status]));
        }
    }

    private function pastikanTerbuka(Aduan $aduan): void
    {
        $this->pastikanStatus($aduan, ['baharu', 'ditugaskan', 'dalam_siasatan', 'dirujuk']);
    }
}
