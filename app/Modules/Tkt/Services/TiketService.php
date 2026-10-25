<?php

namespace App\Modules\Tkt\Services;

use App\Http\Middleware\AssignRequestId;
use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adu\Contracts\ComplaintIntake;
use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Contracts\DocumentStore;
use App\Modules\Kba\Contracts\ArticleDrafts;
use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Tkt\Models\Konteks;
use App\Modules\Tkt\Models\Lampiran;
use App\Modules\Tkt\Models\Tiket;
use App\Modules\Tkt\Support\Lalai;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use App\Modules\Wfe\Events\WorkflowCompleted;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * MOD-TKT ticket lifecycle (SRS-TKT-04/05/11/12/13): intake with context,
 * severity matrix, duplicate check, auto-assignment and SLA start; status
 * workflow baharu → disiasat → dalam tindakan → selesai → disahkan → tutup
 * (with "waiting for reporter" pausing the SLA clock); internal notes vs
 * replies visible to the reporter; reporter verification, reopen window,
 * auto-close; satisfaction survey; knowledge-article draft to MOD-KBA;
 * change-request approval through MOD-WFE; routing land complaints to
 * MOD-ADU. Every change is audited by MOD-AUD's model observer.
 */
class TiketService
{
    public const SUBJECT = 'tkt_tiket';

    public const FLOW = 'tkt.permintaan_perubahan';

    public function __construct(
        private Application $app,
        private Parameters $parameters,
        private SlaTiket $sla,
        private Penugasan $penugasan,
        private Pendua $pendua,
        private Jejak $jejak,
        private Cadangan $cadangan,
    ) {}

    /**
     * @param  array<string, mixed>  $data  tajuk, penerangan, kategori, keterukan?, kesan?, kesegeraan?, modul?, isu_keselamatan?, klasifikasi?, emel_pelapor?
     * @param  array<string, mixed>  $konteks  captured by the help button (SRS-TKT-02)
     */
    public function buka(array $data, string $saluran, ?User $pelapor, ?string $rujukanSumber = null, array $konteks = [], ?string $sesi = null, bool $sintetik = false): Tiket
    {
        if ($rujukanSumber !== null) {
            $existing = Tiket::query()->where('saluran', $saluran)->where('rujukan_sumber', $rujukanSumber)->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        $kategori = array_key_exists((string) ($data['kategori'] ?? ''), Lalai::KATEGORI) ? (string) $data['kategori'] : 'ralat';
        $keselamatan = (bool) ($data['isu_keselamatan'] ?? false);
        $dicadang = $this->cadangKeterukan($data['kesan'] ?? null, $data['kesegeraan'] ?? null, $kategori, $keselamatan);
        $keterukan = isset($data['keterukan']) && in_array((int) $data['keterukan'], [1, 2, 3, 4], true) ? (int) $data['keterukan'] : $dicadang;
        if ($keselamatan) {
            $keterukan = 1;
        }
        $modul = $this->modul($data['modul'] ?? ($konteks['modul'] ?? null));
        $versi = (string) $this->parameters->get('tkt.versi_sistem', '1.0.0');

        $tiket = DB::transaction(function () use ($data, $saluran, $pelapor, $rujukanSumber, $konteks, $kategori, $keselamatan, $dicadang, $keterukan, $modul, $versi, $sintetik) {
            $seq = (int) DB::selectOne("SELECT nextval('tkt_tiket_nombor_seq') AS n")->n;
            $tiket = new Tiket([
                'nombor_rujukan' => 'TKT-'.now()->setTimezone(KalendarSokongan::TZ)->format('Ym').'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT),
                'id_pelapor' => $pelapor?->id,
                'emel_pelapor' => isset($data['emel_pelapor']) ? mb_substr((string) $data['emel_pelapor'], 0, 200) : null,
                'saluran' => $saluran,
                'rujukan_sumber' => $rujukanSumber,
                'kategori' => $kategori,
                'keterukan' => $keterukan,
                'keterukan_dicadang' => $dicadang,
                'kesan' => $data['kesan'] ?? null,
                'kesegeraan' => $data['kesegeraan'] ?? null,
                'modul' => $modul,
                'tajuk' => mb_substr(trim((string) Penyamar::teks((string) $data['tajuk'])), 0, 200),
                'penerangan' => (string) Penyamar::teks(trim((string) ($data['penerangan'] ?? $data['keterangan'] ?? ''))),
                'status' => 'baharu',
                'klasifikasi' => $keselamatan || ($data['klasifikasi'] ?? null) === 'sulit' ? 'sulit' : 'dalaman',
                'isu_keselamatan' => $keselamatan,
                'tarikh_buka' => now(),
                'versi_sistem' => $versi,
                'sintetik' => $sintetik,
            ]);
            $this->penugasan->auto($tiket);
            $this->sla->kira($tiket, true);
            $similar = $this->pendua->cari($tiket->tajuk, $tiket->modul, null, null, 1);
            if ($similar !== []) {
                $tiket->id_tiket_pendua_dicadang = $similar[0]['id'];
                $tiket->skor_pendua = $similar[0]['skor'];
            }
            $tiket->save();

            Konteks::query()->create($this->konteks($konteks, $pelapor, $modul, $versi) + ['id_tiket' => $tiket->id]);
            $this->jejak->catat($tiket, 'sistem', $pelapor, null, null, 'baharu', [
                'saluran' => $saluran,
                'pasukan' => $tiket->pasukan?->nama,
                'juruteknik' => $tiket->juruteknik?->name,
                'keterukan_dicadang' => $dicadang,
            ]);

            return $tiket;
        });

        if ($sesi !== null && $pelapor !== null) {
            $this->cadangan->keputusan($sesi, $pelapor, true, $tiket->id);
        }

        $tiket->load(['pasukan', 'juruteknik', 'pelapor']);
        $this->jejak->hantar('tkt.tiket_diterima', $tiket->pelapor, $tiket, ['tajuk' => $tiket->tajuk]);
        if ($tiket->juruteknik !== null) {
            $this->jejak->hantar('tkt.tiket_ditugaskan', $tiket->juruteknik, $tiket);
        } else {
            $this->jejak->hantar('tkt.tiket_baharu', [], $tiket);
        }

        return $tiket;
    }

    /** Severity matrix (impact × urgency), security → S1, question → S4. */
    public function cadangKeterukan(mixed $kesan, mixed $kesegeraan, string $kategori = 'ralat', bool $keselamatan = false): int
    {
        if ($keselamatan) {
            return 1;
        }
        if ($kategori === 'soalan') {
            return 4;
        }
        $value = $this->cadangKeterukanMatriks()[(string) $kesan][(string) $kesegeraan] ?? null;

        return in_array((int) $value, [1, 2, 3, 4], true) ? (int) $value : 3;
    }

    /** @return array<string, array<string, int>> admin-configurable (parameter tkt.matriks_keterukan) */
    public function cadangKeterukanMatriks(): array
    {
        $matrix = $this->parameters->get('tkt.matriks_keterukan', Lalai::MATRIKS);
        if (is_string($matrix)) {
            $matrix = json_decode($matrix, true);
        }

        return is_array($matrix) && $matrix !== [] ? $matrix : Lalai::MATRIKS;
    }

    public function modul(mixed $modul): string
    {
        $modul = is_string($modul) ? strtolower(trim($modul)) : '';

        return array_key_exists($modul, Lalai::MODUL) ? $modul : 'umum';
    }

    /**
     * Server-trusted + client-captured context (SRS-TKT-02), masked.
     *
     * @param  array<string, mixed>  $k
     * @return array<string, mixed>
     */
    private function konteks(array $k, ?User $pelapor, string $modul, string $versi): array
    {
        $str = fn (string $key, int $max) => isset($k[$key]) && is_scalar($k[$key]) ? mb_substr((string) Penyamar::teks((string) $k[$key]), 0, $max) : null;
        $ids = array_values(array_unique(array_filter(array_map(
            fn ($v) => is_string($v) && preg_match('/^[A-Za-z0-9._-]{8,64}$/', $v) ? $v : null,
            is_array($k['id_permintaan'] ?? null) ? array_slice($k['id_permintaan'], 0, 20) : [],
        ))));
        $request = $this->app->bound('request') ? request() : null;

        return [
            'skrin' => $str('skrin', 200),
            'modul' => $modul,
            'kod_fungsi' => $str('kod_fungsi', 120),
            'url' => Penyamar::url(isset($k['url']) && is_string($k['url']) ? $k['url'] : null),
            'peranan_pengguna' => $pelapor !== null ? $this->app->make(AccessControl::class)->roles($pelapor) : null,
            'id_pengguna' => $pelapor?->id,
            'pelayar' => $str('pelayar', 400) ?? ($request !== null ? mb_substr((string) $request->userAgent(), 0, 400) : null),
            'versi_sistem' => $versi,
            'versi_ui' => $str('versi_ui', 40),
            'rujukan_rekod' => $str('rujukan_rekod', 200),
            'id_permintaan' => $ids === [] ? null : $ids,
            'id_permintaan_cipta' => AssignRequestId::current(),
            'saiz_skrin' => $str('saiz_skrin', 30),
            'zon_masa' => $str('zon_masa', 60),
            'cap_masa_tangkapan' => now(),
        ];
    }

    /**
     * Support officer status change (SRS-TKT-05).
     *
     * @param  array<string, mixed>  $data  penyelesaian, punca_akar, tindakan_pembetulan, masa_henti_minit, sebab_lepas_sla (for selesai)
     */
    public function tukarStatus(Tiket $tiket, string $ke, User $oleh, ?string $catatan = null, array $data = []): Tiket
    {
        if (! in_array($ke, Lalai::PERALIHAN[$tiket->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => __('tkt::tkt.ralat.peralihan', ['dari' => Lalai::STATUS[$tiket->status] ?? $tiket->status, 'ke' => Lalai::STATUS[$ke] ?? $ke])]);
        }
        if ($ke === 'dalam_tindakan' && $tiket->kategori === 'permintaan_perubahan' && $tiket->status_kelulusan !== 'lulus') {
            throw ValidationException::withMessages(['status' => __('tkt::tkt.ralat.perlu_kelulusan')]);
        }
        if ($ke === 'selesai') {
            $fill = array_filter([
                'penyelesaian' => $data['penyelesaian'] ?? null,
                'punca_akar' => $data['punca_akar'] ?? null,
                'tindakan_pembetulan' => $data['tindakan_pembetulan'] ?? null,
                'masa_henti_minit' => $data['masa_henti_minit'] ?? null,
                'sebab_lepas_sla' => $data['sebab_lepas_sla'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');
            $tiket->fill($fill);
            if (trim((string) $tiket->penyelesaian) === '' || trim((string) $tiket->punca_akar) === '') {
                throw ValidationException::withMessages(['penyelesaian' => __('tkt::tkt.ralat.perlu_penyelesaian')]);
            }
        }

        return $this->peralihan($tiket, $ke, $oleh, $catatan);
    }

    /** Applies a transition: SLA pause/resume, timestamps, timeline, notifications, sync. */
    private function peralihan(Tiket $tiket, string $ke, ?User $oleh, ?string $catatan, array $meta = []): Tiket
    {
        $dari = $tiket->status;
        DB::transaction(function () use ($tiket, $dari, $ke, $oleh, $catatan, $meta) {
            if (in_array($dari, Lalai::JEDA, true) && ! in_array($ke, Lalai::JEDA, true)) {
                $this->sla->sambung($tiket);
            }
            if (in_array($ke, Lalai::JEDA, true)) {
                if ($ke === 'menunggu_pelapor') {
                    $tiket->status_sebelum_jeda = $dari;
                }
                $this->sla->jeda($tiket);
            }
            if ($tiket->tarikh_respons_pertama === null && $dari === 'baharu' && $oleh !== null && $oleh->id !== $tiket->id_pelapor) {
                $tiket->tarikh_respons_pertama = now();
            }
            match ($ke) {
                'selesai' => $tiket->forceFill(['tarikh_selesai' => now(), 'peringatan_pengesahan_pada' => null]),
                'disahkan' => $tiket->forceFill(['tarikh_disahkan' => now()]),
                'tutup', 'pendua', 'dirujuk_aduan' => $tiket->forceFill(['tarikh_tutup' => now()]),
                default => null,
            };
            $tiket->status = $ke;
            $tiket->save();
            $this->jejak->catat($tiket, 'perubahan_status', $oleh, $catatan, $dari, $ke, $meta);
        });

        $tiket->load(['pelapor', 'juruteknik']);
        if ($ke === 'selesai') {
            $this->drafKb($tiket, $oleh);
            $this->jejak->hantar('tkt.tiket_selesai', $tiket->pelapor, $tiket, ['penyelesaian' => mb_strimwidth((string) $tiket->penyelesaian, 0, 300, '…')]);
        } elseif ($ke === 'tutup') {
            $this->drafKb($tiket, $oleh);
            $this->jejak->hantar('tkt.tiket_ditutup', $tiket->pelapor, $tiket);
            if ($tiket->kepuasan_skor === null) {
                $this->jejak->hantar('tkt.tinjauan_kepuasan', $tiket->pelapor, $tiket, [], "tkt:{$tiket->id}:tinjauan");
            }
        } elseif ($oleh?->id !== $tiket->id_pelapor) {
            $this->jejak->hantar('tkt.status_berubah', $tiket->pelapor, $tiket, ['status' => Lalai::STATUS[$ke] ?? $ke]);
        }
        $this->segerak($tiket);

        return $tiket;
    }

    /**
     * Timeline note by support: `dalaman` (never shown to the reporter) or
     * `kelihatan_pelapor` (a reply — counts as the first response).
     */
    public function catatan(Tiket $tiket, string $jenis, string $kandungan, User $oleh): void
    {
        $this->jejak->catat($tiket, $jenis, $oleh, $kandungan);
        if ($jenis === 'kelihatan_pelapor') {
            if ($tiket->tarikh_respons_pertama === null) {
                $tiket->forceFill(['tarikh_respons_pertama' => now()])->save();
            }
            $this->jejak->hantar('tkt.balasan_sokongan', $tiket->pelapor, $tiket);
        }
    }

    /** Reporter message; a ticket waiting for the reporter resumes (SLA clock restarts). */
    public function balasPelapor(Tiket $tiket, string $kandungan, ?User $pelapor): void
    {
        if (in_array($tiket->status, Lalai::AKHIR, true)) {
            throw ValidationException::withMessages(['kandungan' => __('tkt::tkt.ralat.tiket_ditutup')]);
        }
        $this->jejak->catat($tiket, 'pelapor', $pelapor, $kandungan);
        if ($tiket->status === 'menunggu_pelapor') {
            $this->peralihan($tiket, $tiket->status_sebelum_jeda && $tiket->status_sebelum_jeda !== 'menunggu_pelapor' ? $tiket->status_sebelum_jeda : 'disiasat', $pelapor, (string) __('tkt::tkt.kronologi.pelapor_membalas'));
        }
        $this->jejak->hantar('tkt.balasan_pelapor', $tiket->juruteknik, $tiket);
    }

    /** SRS-TKT-12: the reporter confirms the resolution. */
    public function sahkan(Tiket $tiket, ?User $pelapor, ?string $catatan = null): Tiket
    {
        if ($tiket->status !== 'selesai') {
            throw ValidationException::withMessages(['status' => __('tkt::tkt.ralat.belum_selesai')]);
        }
        $this->peralihan($tiket, 'disahkan', $pelapor, $catatan);
        $this->jejak->hantar('tkt.pengesahan_pelapor', $tiket->juruteknik, $tiket);
        if ($tiket->kepuasan_skor === null) {
            $this->jejak->hantar('tkt.tinjauan_kepuasan', $tiket->pelapor, $tiket, [], "tkt:{$tiket->id}:tinjauan");
        }

        return $tiket;
    }

    /** SRS-TKT-12: support closes only after the reporter verified. */
    public function tutup(Tiket $tiket, User $oleh, ?string $catatan = null): Tiket
    {
        if ($tiket->status !== 'disahkan') {
            throw ValidationException::withMessages(['status' => __('tkt::tkt.ralat.perlu_pengesahan')]);
        }

        return $this->peralihan($tiket, 'tutup', $oleh, $catatan);
    }

    /** SRS-TKT-12: reopen within the configured window after resolution. */
    public function bukaSemula(Tiket $tiket, ?User $oleh, string $sebab): Tiket
    {
        if (! in_array($tiket->status, ['selesai', 'disahkan', 'tutup'], true) || $tiket->tarikh_selesai === null) {
            throw ValidationException::withMessages(['status' => __('tkt::tkt.ralat.tidak_boleh_buka_semula')]);
        }
        $days = max(0, (int) $this->parameters->get('tkt.hari_buka_semula', 10));
        if ($tiket->tarikh_selesai->copy()->addDays($days)->isPast()) {
            throw ValidationException::withMessages(['status' => __('tkt::tkt.ralat.tempoh_buka_semula', ['hari' => $days])]);
        }
        if ($tiket->status === 'disahkan' || $tiket->status === 'tutup') {
            // The time after verification / closure is not support time either: count it as paused.
            $tiket->sla_jeda_mula = $tiket->tarikh_disahkan ?? $tiket->tarikh_tutup ?? now();
            $this->sla->sambung($tiket);
        }
        $tiket->forceFill([
            'bilangan_buka_semula' => $tiket->bilangan_buka_semula + 1,
            'tarikh_selesai' => null, 'tarikh_disahkan' => null, 'tarikh_tutup' => null,
            'ditutup_automatik' => false, 'peringatan_pengesahan_pada' => null,
        ]);
        $this->peralihan($tiket, 'dalam_tindakan', $oleh, $sebab, ['buka_semula' => $tiket->bilangan_buka_semula]);
        $this->jejak->hantar('tkt.tiket_dibuka_semula', $tiket->juruteknik, $tiket, ['sebab' => mb_strimwidth($sebab, 0, 200, '…')]);

        return $tiket;
    }

    /** Severity / category / module / classification change with reason (SRS-TKT-11). */
    public function ubahMedan(Tiket $tiket, array $nilai, string $sebab, User $oleh): Tiket
    {
        $changes = [];
        foreach (['keterukan', 'kategori', 'modul', 'klasifikasi'] as $field) {
            if (array_key_exists($field, $nilai) && $nilai[$field] !== null && (string) $nilai[$field] !== (string) $tiket->{$field}) {
                $changes[$field] = [$tiket->{$field}, $nilai[$field]];
                $tiket->{$field} = $field === 'keterukan' ? (int) $nilai[$field] : $nilai[$field];
            }
        }
        if ($changes === []) {
            return $tiket;
        }
        if ($tiket->isu_keselamatan) {
            $tiket->klasifikasi = 'sulit';
        }
        DB::transaction(function () use ($tiket, $changes, $sebab, $oleh) {
            if (isset($changes['keterukan'])) {
                $this->sla->kira($tiket, true);
                $tiket->respons_dilepasi = $tiket->respons_dilepasi && $tiket->tarikh_sasaran_respons?->isPast();
                $tiket->selesai_dilepasi = $tiket->selesai_dilepasi && $tiket->tarikh_sasaran_selesai?->isPast();
            }
            $tiket->save();
            $this->jejak->catat($tiket, 'perubahan_medan', $oleh, $sebab, null, null, ['perubahan' => $changes]);
        });

        return $tiket;
    }

    /** Mark as a duplicate of $induk and close it. */
    public function tandaPendua(Tiket $tiket, Tiket $induk, User $oleh, ?string $catatan = null): Tiket
    {
        if ($induk->id === $tiket->id || $induk->id_tiket_induk === $tiket->id) {
            throw ValidationException::withMessages(['id_tiket_induk' => __('tkt::tkt.ralat.induk_tidak_sah')]);
        }
        if (in_array($tiket->status, Lalai::AKHIR, true)) {
            throw ValidationException::withMessages(['status' => __('tkt::tkt.ralat.tiket_ditutup')]);
        }
        $tiket->id_tiket_induk = $induk->id;
        $this->peralihan($tiket, 'pendua', $oleh, $catatan ?? (string) __('tkt::tkt.kronologi.pendua', ['no' => $induk->nombor_rujukan]), ['induk' => $induk->nombor_rujukan]);
        $this->jejak->catat($induk, 'sistem', $oleh, (string) __('tkt::tkt.kronologi.ada_pendua', ['no' => $tiket->nombor_rujukan]));

        return $tiket;
    }

    /**
     * A "ticket" that is really a land complaint goes to MOD-ADU through
     * ComplaintIntake (idempotent on the ticket id) and is closed here.
     *
     * @param  array{kategori: string, keterangan?: string|null, kod_daerah?: string|null, lot_id?: string|null}  $data
     */
    public function rujukAduan(Tiket $tiket, array $data, User $oleh): Tiket
    {
        if (in_array($tiket->status, Lalai::AKHIR, true)) {
            throw ValidationException::withMessages(['status' => __('tkt::tkt.ralat.tiket_ditutup')]);
        }
        $id = $this->app->make(ComplaintIntake::class)->open(array_filter([
            'kategori' => $data['kategori'],
            'keterangan' => (string) ($data['keterangan'] ?? $tiket->penerangan),
            'kod_daerah' => $data['kod_daerah'] ?? null,
            'lot_id' => $data['lot_id'] ?? null,
            'keutamaan' => $tiket->keterukan <= 2 ? 'tinggi' : 'sederhana',
        ], fn ($v) => $v !== null), 'tkt', $tiket->pelapor, 'tkt_tiket:'.$tiket->id);
        $tiket->id_aduan_adu = $id;
        $this->peralihan($tiket, 'dirujuk_aduan', $oleh, (string) __('tkt::tkt.kronologi.dirujuk_aduan'), ['id_aduan' => $id]);

        return $tiket;
    }

    /** Change request → MOD-WFE approval (never our own approval logic — SRS-GEN-03). */
    public function mohonKelulusan(Tiket $tiket, string $justifikasi, User $oleh): Tiket
    {
        if ($tiket->kategori !== 'permintaan_perubahan' || ! in_array($tiket->status, ['disiasat', 'dalam_tindakan'], true)) {
            throw ValidationException::withMessages(['status' => __('tkt::tkt.ralat.bukan_permintaan_perubahan')]);
        }
        try {
            $instance = $this->app->make(WorkflowEngine::class)->start(self::FLOW, self::SUBJECT, $tiket->id, $oleh, [
                'modul' => $tiket->modul, 'keterukan' => $tiket->keterukan, 'kategori' => $tiket->kategori, 'pautan' => '/admin/tkt/tiket/'.$tiket->id,
            ], $tiket->nombor_rujukan.' — '.$tiket->tajuk);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['status' => __('tkt::tkt.ralat.tiada_aliran')]);
        }
        $tiket->forceFill(['id_instans_aliran' => $instance, 'status_kelulusan' => 'menunggu']);

        return $this->peralihan($tiket, 'menunggu_kelulusan', $oleh, $justifikasi, ['instans' => $instance]);
    }

    public function handle(WorkflowCompleted $event): void
    {
        if ($event->flowCode !== self::FLOW || $event->subjectType !== self::SUBJECT) {
            return;
        }
        $tiket = Tiket::query()->find($event->subjectId);
        if ($tiket === null || $tiket->status !== 'menunggu_kelulusan' || ($tiket->id_instans_aliran !== null && $tiket->id_instans_aliran !== $event->instanceId)) {
            return;
        }
        $by = $event->decidedBy !== null ? User::query()->find($event->decidedBy) : null;
        $approved = $event->outcome === 'lulus';
        $tiket->forceFill(['status_kelulusan' => $approved ? 'lulus' : 'tolak']);
        $this->peralihan($tiket, $approved ? 'dalam_tindakan' : 'disiasat', $by, $event->remarks, ['kelulusan' => $event->outcome]);
        $this->jejak->hantar('tkt.keputusan_kelulusan', array_filter([$tiket->juruteknik, $tiket->pelapor]), $tiket, ['keputusan' => (string) __('tkt::tkt.kelulusan.'.$event->outcome)]);
    }

    /** Satisfaction survey — once, by the reporter, after resolution. */
    public function kepuasan(Tiket $tiket, int $skor, ?string $ulasan, User $pelapor): Tiket
    {
        if (! in_array($tiket->status, ['selesai', 'disahkan', 'tutup'], true)) {
            throw ValidationException::withMessages(['skor' => __('tkt::tkt.ralat.belum_selesai')]);
        }
        if ($tiket->kepuasan_skor !== null) {
            throw ValidationException::withMessages(['skor' => __('tkt::tkt.ralat.kepuasan_sudah')]);
        }
        $tiket->forceFill(['kepuasan_skor' => $skor, 'kepuasan_ulasan' => $ulasan !== null ? mb_substr((string) Penyamar::teks($ulasan), 0, 1000) : null, 'kepuasan_pada' => now()])->save();
        $this->jejak->catat($tiket, 'sistem', $pelapor, null, null, null, ['kepuasan' => $skor]);

        return $tiket;
    }

    /**
     * SRS-TKT-03: attachment / screenshot into MOD-DMS. A screenshot is
     * refused unless the user explicitly consented; DMS applies the file
     * type, magic-number, size and antivirus controls.
     */
    public function lampiran(Tiket $tiket, UploadedFile $file, User $oleh, bool $tangkapanSkrin, bool $persetujuan, bool $kelihatanPelapor = true): Lampiran
    {
        if ($tangkapanSkrin && ! $persetujuan) {
            throw ValidationException::withMessages(['persetujuan' => __('tkt::tkt.ralat.perlu_persetujuan')]);
        }
        try {
            $docId = $this->app->make(DocumentStore::class)->store($file, self::SUBJECT, $tiket->id, 'lain', $oleh, $tiket->klasifikasi === 'sulit' ? 'sulit' : 'dalaman', []);
        } catch (DocumentRejected $e) {
            throw ValidationException::withMessages(['fail' => $e->getMessage()]);
        }
        $row = Lampiran::query()->create([
            'id_tiket' => $tiket->id,
            'id_dokumen' => $docId,
            'nama_fail' => mb_substr($file->getClientOriginalName(), 0, 255),
            'jenis_fail' => mb_substr((string) $file->getClientMimeType(), 0, 120),
            'saiz' => (int) $file->getSize(),
            'status_imbasan' => 'bersih',
            'lokasi_storan' => 'dms:'.$docId,
            'tangkapan_skrin' => $tangkapanSkrin,
            'persetujuan_tangkapan_skrin' => $tangkapanSkrin,
            'persetujuan_pada' => $tangkapanSkrin ? now() : null,
            'kelihatan_pelapor' => $kelihatanPelapor,
            'id_pemuat_naik' => $oleh->id,
        ]);
        $this->jejak->catat($tiket, $kelihatanPelapor ? 'lampiran' : 'dalaman', $oleh, $row->nama_fail, null, null, ['tangkapan_skrin' => $tangkapanSkrin, 'id_lampiran' => $row->id]);

        return $row;
    }

    /**
     * SRS-TKT-12 scheduled part: remind the reporter to verify, then
     * auto-close resolved tickets with no response; close verified ones.
     *
     * @return array{peringatan: int, ditutup: int}
     */
    public function automatik(): array
    {
        $out = ['peringatan' => 0, 'ditutup' => 0];
        $remind = max(0, (int) $this->parameters->get('tkt.hari_peringatan_pengesahan', 3));
        $close = max(1, (int) $this->parameters->get('tkt.hari_tutup_automatik', 5));
        $closeVerified = max(0, (int) $this->parameters->get('tkt.hari_tutup_selepas_disahkan', 2));

        Tiket::query()->where('status', 'selesai')->whereNotNull('tarikh_selesai')->orderBy('tarikh_selesai')
            ->each(function (Tiket $tiket) use (&$out, $remind, $close) {
                if ($tiket->tarikh_selesai->copy()->addDays($close)->isPast()) {
                    $tiket->ditutup_automatik = true;
                    $this->peralihan($tiket, 'tutup', null, (string) __('tkt::tkt.kronologi.tutup_automatik', ['hari' => $close]));
                    $out['ditutup']++;
                } elseif ($tiket->peringatan_pengesahan_pada === null && $tiket->tarikh_selesai->copy()->addDays($remind)->isPast()) {
                    $tiket->forceFill(['peringatan_pengesahan_pada' => now()])->save();
                    $this->jejak->hantar('tkt.peringatan_pengesahan', $tiket->pelapor, $tiket, ['hari' => $close], "tkt:{$tiket->id}:peringatan");
                    $out['peringatan']++;
                }
            });
        Tiket::query()->where('status', 'disahkan')->whereNotNull('tarikh_disahkan')->where('tarikh_disahkan', '<=', now()->subDays($closeVerified))
            ->each(function (Tiket $tiket) use (&$out) {
                $this->peralihan($tiket, 'tutup', null, (string) __('tkt::tkt.kronologi.tutup_selepas_disahkan'));
                $out['ditutup']++;
            });

        return $out;
    }

    /** SRS-TKT-13: problem → cause → solution draft into MOD-KBA (never published here). */
    public function drafKb(Tiket $tiket, ?User $oleh): void
    {
        if (in_array($tiket->status, ['pendua', 'dirujuk_aduan'], true) || trim((string) $tiket->penyelesaian) === '') {
            return;
        }
        try {
            $id = $this->app->make(ArticleDrafts::class)->fromTicket($tiket->id, [
                'tajuk' => mb_substr((string) __('tkt::tkt.kb.tajuk', ['tajuk' => $tiket->tajuk]), 0, 250),
                'masalah' => (string) Penyamar::teks($tiket->penerangan),
                'punca' => (string) Penyamar::teks((string) $tiket->punca_akar),
                'penyelesaian' => (string) Penyamar::teks((string) $tiket->penyelesaian),
                'modul' => $tiket->modul,
                'kategori' => 'penyelesaian_tiket',
                'versi_sistem' => $tiket->versi_sistem,
            ], $oleh);
            if ($tiket->id_artikel_kb !== $id) {
                $tiket->forceFill(['id_artikel_kb' => $id])->save();
                $this->jejak->catat($tiket, 'sistem', $oleh, (string) __('tkt::tkt.kronologi.draf_kb'), null, null, ['id_artikel' => $id]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Status sync back to the central complaints system, for tickets that came from it. */
    private function segerak(Tiket $tiket): void
    {
        if ($tiket->saluran !== 'sistem_aduan_pusat') {
            return;
        }
        try {
            $this->app->make(SegerakPusat::class)->catat($tiket);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
