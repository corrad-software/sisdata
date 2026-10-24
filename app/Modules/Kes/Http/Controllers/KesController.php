<?php

namespace App\Modules\Kes\Http\Controllers;

use App\Models\User;
use App\Modules\Kes\Contracts\AttorneyGeneralChambers;
use App\Modules\Kes\Contracts\CourtSystemUnavailable;
use App\Modules\Kes\Http\Present;
use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Kes\Models\Kronologi;
use App\Modules\Kes\Models\PanelPeguam;
use App\Modules\Kes\Models\Peguam;
use App\Modules\Kes\Models\Pihak;
use App\Modules\Kes\Services\Eskalasi;
use App\Modules\Kes\Services\KesService;
use App\Modules\Kes\Services\Laporan;
use App\Modules\Kes\Services\LogCapaianKes;
use App\Modules\Kes\Services\PanelService;
use App\Modules\Kes\Services\ProsidingService;
use App\Modules\Kes\Services\Rujukan;
use App\Modules\Kes\Services\StatusLot;
use App\Modules\Kes\Services\Wang;
use App\Modules\Kes\Support\Akses;
use App\Modules\Kes\Support\Eksport;
use App\Modules\Kes\Support\Kod;
use App\Modules\Pro\Contracts\LotRegistry;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/** SRS-KES-02..07, 13, 14, 20: case register, record view (logged), parties & counsel, lots, officer, status, archive. */
class KesController extends KesBaseController
{
    public function __construct(
        Akses $akses,
        private KesService $service,
        private Present $present,
        private Rujukan $ref,
        private LogCapaianKes $log,
        private Laporan $laporan,
    ) {
        parent::__construct($akses);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'string', 'max:40'], 'jenis' => ['nullable', 'string', 'max:40'],
            'mahkamah' => ['nullable', 'string', 'max:150'], 'peringkat_mahkamah' => ['nullable', 'string', 'max:30'], 'kod_daerah' => ['nullable', 'string', 'max:4'],
            'kod_mukim' => ['nullable', 'string', 'max:6'], 'kategori_tanah' => ['nullable', 'string', 'max:50'], 'id_pegawai' => ['nullable', 'integer'],
            'peguam' => ['nullable', 'string', 'max:150'], 'dari' => ['nullable', 'date'], 'hingga' => ['nullable', 'date'], 'aktif' => ['nullable', 'boolean'],
            'arkib' => ['nullable', 'boolean'], 'lot_id' => ['nullable', 'uuid'], 'tanpa_tarikh' => ['nullable', 'boolean'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);
        $q = $this->laporan->kes($user, $f)->with('pegawai');
        if (! empty($f['tanpa_tarikh'])) {
            $q = Eskalasi::tanpaTarikh($q);
        }
        $page = $q->orderByDesc('kes_mahkamah.created_at')->paginate((int) ($f['per_halaman'] ?? 20));
        $this->log->rekod('lihat_senarai', null, null, 'Daftar kes: '.$page->total().' rekod', ['penapis' => array_filter($f, fn ($v) => $v !== null), 'kes' => collect($page->items())->pluck('id')->all()]);

        return response()->json([
            'data' => collect($page->items())->map(fn (KesMahkamah $k) => $this->present->kes($k))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $d = $request->validate([
            ...$this->rulesKes(true),
            'lot_ids' => ['array', 'max:50'], 'lot_ids.*' => ['uuid'],
            'pihak' => ['array', 'max:50'], ...$this->rulesPihak('pihak.*.'),
            'peguam' => ['array', 'max:50'], ...$this->rulesPeguam('peguam.*.'),
            'id_pegawai_bertanggungjawab' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        if (isset($d['id_pegawai_bertanggungjawab']) && ! $user->can('kes.kes.lulus') && (int) $d['id_pegawai_bertanggungjawab'] !== $user->id) {
            throw ValidationException::withMessages(['id_pegawai_bertanggungjawab' => __('kes::kes.ralat.tugaskan_ketua')]);
        }
        $kes = $this->service->daftar($d, $user);

        return response()->json(['data' => $this->present->kes($kes->refresh()->load('pegawai')), 'message' => __('kes::kes.mesej.didaftar', ['no' => $kes->no_rujukan])], 201);
    }

    public function show(Request $request, string $kes, LotRegistry $lots, WorkflowEngine $workflow): JsonResponse
    {
        $user = $this->user($request);
        $k = $this->cari($request, $kes);
        $this->log->rekod('lihat', $k->id, null, $k->no_rujukan);

        $k->load(['pihak', 'peguam', 'prosiding.pegawaiRekod', 'tarikhPenting', 'kos', 'keputusan', 'tindakan.pegawai', 'rayuan', 'penyelesaian', 'dokumen', 'sejarahStatus.pengguna', 'sejarahPegawai.lama', 'sejarahPegawai.baharu', 'kronologi']);
        $pivot = DB::table('kes_mahkamah_lot')->where('kes_mahkamah_id', $k->id)->get();
        $visible = $lots->summaries($pivot->pluck('lot_id')->map(fn ($v) => (string) $v)->all(), $user);
        $statusLot = app(StatusLot::class)->untuk($pivot->pluck('lot_id')->map(fn ($v) => (string) $v)->all());

        return response()->json(['data' => [
            ...$this->present->kes($k),
            'ringkasan_tuntutan' => $k->ringkasan_tuntutan,
            'sebab_arkib' => $k->sebab_arkib,
            'tarikh_arkib' => $k->tarikh_arkib?->toIso8601String(),
            'aliran_pemfailan' => $k->id_instans_aliran ? ['id' => $k->id_instans_aliran, 'status' => $workflow->status($k->id_instans_aliran)] : null,
            'lot' => $pivot->map(fn ($p) => [
                'lot_id' => $p->lot_id, 'no_lot' => $p->no_lot, 'kod_daerah' => $p->kod_daerah, 'daerah' => $this->ref->namaDaerah($p->kod_daerah),
                'kod_mukim' => $p->kod_mukim, 'mukim' => $this->ref->namaMukim($p->kod_mukim), 'kategori_guna_tanah' => $p->kategori_guna_tanah,
                'peranan_lot' => $p->peranan_lot, 'peranan_nama' => Kod::papar(Kod::PERANAN_LOT, $p->peranan_lot), 'tarikh_paut' => $p->tarikh_paut,
                'boleh_buka' => isset($visible[$p->lot_id]), 'pautan' => isset($visible[$p->lot_id]) ? $lots->profileLink($p->lot_id) : null,
                'dalam_tindakan' => $statusLot[$p->lot_id] ?? false,
            ])->values(),
            'pihak' => $k->pihak->map(fn (Pihak $p) => $this->present->pihak($p))->values(),
            'peguam' => $k->peguam->map(fn (Peguam $p) => $this->present->peguam($p))->values(),
            'prosiding' => $k->prosiding->sortBy('tarikh_prosiding')->map(fn ($p) => $this->present->prosiding($p))->values(),
            'tarikh_penting' => $k->tarikhPenting->sortBy('tarikh')->map(fn ($t) => $this->present->tarikh($t))->values(),
            'kos' => $k->kos->sortByDesc('tarikh')->map(fn ($c) => $this->present->kos($c))->values(),
            'kos_mengikut_kategori' => collect(Kod::KATEGORI_KOS)->map(fn ($nama, $kod) => ['kod' => $kod, 'nama' => $nama, 'jumlah' => Wang::jumlah($k->kos->where('kategori_kos', $kod)->where('status_bayaran', '!=', 'ditolak')->pluck('amaun')->all())])->values(),
            'keputusan' => $k->keputusan->sortByDesc('tarikh_keputusan')->map(fn ($d) => $this->present->keputusan($d))->values(),
            'tindakan' => $k->tindakan->sortByDesc('id')->map(fn ($t) => $this->present->tindakan($t))->values(),
            'rayuan' => $k->rayuan->map(fn ($r) => $this->present->rayuan($r))->values(),
            'penyelesaian' => $k->penyelesaian->map(fn ($p) => $this->present->penyelesaian($p))->values(),
            'dokumen' => $k->dokumen->sortByDesc('tarikh_muat_naik')->map(fn ($d) => $this->present->dokumen($d))->values(),
            'sejarah_status' => $k->sejarahStatus->sortByDesc('tarikh_perubahan')->map(fn ($s) => [
                'status_lama' => $this->ref->namaStatus($s->status_lama), 'status_baharu' => $this->ref->namaStatus($s->status_baharu),
                'tarikh' => $s->tarikh_perubahan->toIso8601String(), 'pengguna' => $s->pengguna?->name, 'catatan' => $s->catatan,
            ])->values(),
            'sejarah_pegawai' => $k->sejarahPegawai->sortByDesc('tarikh_tukar')->map(fn ($s) => [
                'lama' => $s->lama?->name, 'baharu' => $s->baharu?->name, 'tarikh' => $s->tarikh_tukar->toIso8601String(), 'justifikasi' => $s->justifikasi,
            ])->values(),
            'kronologi' => $this->kronologi($k),
            'boleh' => [
                'kemas_kini' => $this->akses->bolehUrus($user, $k),
                'lulus' => $user->can('kes.kes.lulus'),
                'dedah_pihak' => $user->can('kes.pihak.lihat'),
            ],
        ]]);
    }

    public function update(Request $request, string $kes): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $this->urus($request, $k);
        $d = $request->validate($this->rulesKes(false));
        $this->service->kemasKini($k, $d);

        return response()->json(['data' => $this->present->kes($k->refresh()->load('pegawai')), 'message' => __('kes::kes.mesej.dikemas_kini')]);
    }

    public function status(Request $request, string $kes): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $user = $this->urus($request, $k);
        $d = $request->validate(['status' => ['required', 'string', Rule::in(array_keys(array_filter($this->ref->status(), fn ($s) => $s['aktif'])))], 'catatan' => ['nullable', 'string', 'max:2000']]);
        $this->service->tukarStatus($k, $d['status'], $user, $d['catatan'] ?? null);

        return response()->json(['data' => $this->present->kes($k->refresh()->load('pegawai')), 'message' => __('kes::kes.mesej.status', ['status' => $this->ref->namaStatus($d['status'])])]);
    }

    /** SRS-KES-20: only Ketua Bahagian (kes.kes.lulus) assigns / reassigns. */
    public function tugaskan(Request $request, string $kes): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $user = $this->user($request);
        abort_unless($user->can('kes.kes.lulus') && ! $k->status_arkib, 403, (string) __('kes::kes.ralat.tugaskan_ketua'));
        $d = $request->validate(['id_pegawai' => ['required', 'integer', 'exists:users,id'], 'justifikasi' => ['required', 'string', 'min:5', 'max:2000']]);
        $pegawai = User::query()->findOrFail($d['id_pegawai']);
        if (! $pegawai->is_active || ! $pegawai->can('kes.kes.kemas_kini') || ! $pegawai->can('kes.rahsia.lihat')) {
            throw ValidationException::withMessages(['id_pegawai' => __('kes::kes.ralat.pegawai_tidak_layak')]);
        }
        $this->service->tugaskan($k, $pegawai, $d['justifikasi'], $user);

        return response()->json(['data' => $this->present->kes($k->refresh()->load('pegawai')), 'message' => __('kes::kes.mesej.ditugaskan', ['nama' => $pegawai->name])]);
    }

    public function arkib(Request $request, string $kes): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $user = $this->user($request);
        abort_unless($user->can('kes.kes.lulus'), 403);
        $d = $request->validate(['sebab' => ['required', 'string', 'min:5', 'max:2000']]);
        $this->service->arkib($k, $d['sebab'], $user);

        return response()->json(['data' => $this->present->kes($k->refresh()->load('pegawai')), 'message' => __('kes::kes.mesej.diarkib')]);
    }

    public function pautLot(Request $request, string $kes): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $user = $this->urus($request, $k);
        $d = $request->validate(['lot_ids' => ['required', 'array', 'min:1', 'max:50'], 'lot_ids.*' => ['uuid'], 'peranan_lot' => ['nullable', Rule::in(array_keys(Kod::PERANAN_LOT))]]);
        $this->service->pautLot($k, $d['lot_ids'], $d['peranan_lot'] ?? 'subjek', $user);

        return response()->json(['message' => __('kes::kes.mesej.lot_dipaut', ['n' => count($d['lot_ids'])])]);
    }

    public function nyahpautLot(Request $request, string $kes, string $lot): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $user = $this->urus($request, $k);
        $this->service->nyahpautLot($k, $lot, $user);

        return response()->json(['message' => __('kes::kes.mesej.lot_dibuang')]);
    }

    public function tambahPihak(Request $request, string $kes): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $this->urus($request, $k);
        $d = $request->validate($this->rulesPihak(''));

        return response()->json(['data' => $this->present->pihak($this->service->tambahPihak($k, $d)), 'message' => __('kes::kes.mesej.disimpan')], 201);
    }

    public function kemasKiniPihak(Request $request, string $kes, int $pihak): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $this->urus($request, $k);
        $p = Pihak::query()->where('kes_mahkamah_id', $k->id)->findOrFail($pihak);
        $d = $request->validate(array_map(fn ($r) => array_map(fn ($x) => $x === 'required' ? 'sometimes' : $x, $r), $this->rulesPihak('')));

        return response()->json(['data' => $this->present->pihak($this->service->kemasKiniPihak($p, $d)), 'message' => __('kes::kes.mesej.disimpan')]);
    }

    /** Full personal data of a party — explicit permission, every reveal logged (kes_log_capaian "lihat_pihak" + audit). */
    public function dedahPihak(Request $request, string $kes, int $pihak): JsonResponse
    {
        $k = $this->cari($request, $kes);
        abort_unless($this->user($request)->can('kes.pihak.lihat'), 403, (string) __('kes::kes.ralat.tiada_akses'));
        $p = Pihak::query()->where('kes_mahkamah_id', $k->id)->findOrFail($pihak);
        $this->log->rekod('lihat_pihak', $k->id, null, 'Butiran pihak #'.$p->id, ['pihak_id' => $p->id]);

        return response()->json(['data' => $this->present->pihak($p, true)]);
    }

    public function tambahPeguam(Request $request, string $kes): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $user = $this->urus($request, $k);
        $d = $request->validate($this->rulesPeguam(''));

        return response()->json(['data' => $this->present->peguam($this->service->tambahPeguam($k, $d, $user)), 'message' => __('kes::kes.mesej.disimpan')], 201);
    }

    /** Appoint a panel firm for MAIS — subject to MAIS approval in MOD-WFE. */
    public function lantikPanel(Request $request, string $kes, PanelService $panels): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $user = $this->urus($request, $k);
        $d = $request->validate(['kes_panel_peguam_id' => ['required', 'integer'], 'kes_pihak_id' => ['nullable', 'integer'], 'nama_peguam' => ['nullable', 'string', 'max:150']]);
        $panel = PanelPeguam::query()->findOrFail($d['kes_panel_peguam_id']);
        $p = $panels->lantik($k, $panel, $d, $user);

        return response()->json(['data' => $this->present->peguam($p), 'message' => __('kes::kes.mesej.panel_dihantar')], 201);
    }

    public function tamatPeguam(Request $request, string $kes, int $peguam, PanelService $panels): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $this->urus($request, $k);
        $panels->tamat(Peguam::query()->where('kes_mahkamah_id', $k->id)->findOrFail($peguam));

        return response()->json(['message' => __('kes::kes.mesej.disimpan')]);
    }

    public function pemfailan(Request $request, string $kes): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $user = $this->urus($request, $k);
        $id = $this->service->mohonPemfailan($k, $user);

        return response()->json(['data' => ['id_instans_aliran' => $id], 'message' => __('kes::kes.mesej.aliran_dimulakan')]);
    }

    /** Court e-Filing adapter: import hearing dates. */
    public function segerakMahkamah(Request $request, string $kes, ProsidingService $prosiding): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $user = $this->urus($request, $k);
        $r = $prosiding->segerakMahkamah($k, $user);

        return response()->json(['data' => $r, 'message' => __('kes::kes.mesej.segerak', ['n' => $r['baharu']])]);
    }

    /** AG Chambers adapter: refer for advice / representation. */
    public function rujukAgc(Request $request, string $kes, AttorneyGeneralChambers $agc): JsonResponse
    {
        $k = $this->cari($request, $kes);
        $user = $this->urus($request, $k);
        $d = $request->validate(['jenis_rujukan' => ['required', Rule::in(['nasihat', 'perwakilan'])], 'ringkasan' => ['required', 'string', 'max:5000']]);
        try {
            $ref = $agc->refer(['no_rujukan' => (string) $k->no_rujukan, 'no_kes' => $k->no_kes, 'jenis' => (string) $k->jenis, 'ringkasan' => $d['ringkasan'], 'jenis_rujukan' => $d['jenis_rujukan']]);
        } catch (CourtSystemUnavailable $e) {
            throw ValidationException::withMessages(['agc' => __('kes::kes.ralat.sistem_mahkamah', ['sebab' => $e->getMessage()])]);
        }
        Kronologi::query()->create(['kes_mahkamah_id' => $k->id, 'tarikh' => now(), 'jenis' => 'sistem', 'keterangan' => 'Dirujuk kepada Jabatan Peguam Negara ('.$d['jenis_rujukan'].'): '.$ref, 'sumber' => 'agc', 'id_pengguna' => $user->id]);

        return response()->json(['data' => ['rujukan' => $ref], 'message' => __('kes::kes.mesej.agc', ['ruj' => $ref])]);
    }

    /** Printable case summary (PDF, watermarked with user + time) — logged as "cetak". */
    public function cetak(Request $request, string $kes): Response
    {
        $user = $this->user($request);
        $k = $this->cari($request, $kes);
        $k->load(['pihak', 'prosiding', 'keputusan']);
        $this->log->rekod('cetak', $k->id, null, $k->no_rujukan);
        $footer = 'Dicetak oleh '.$user->name.' ('.$user->email.') pada '.now()->format('d/m/Y H:i:s').' · IP '.$request->ip();
        $p = $this->present->kes($k);

        return Eksport::download('pdf', 'kes-'.str_replace('/', '-', (string) $k->no_rujukan), 'Ringkasan Kes '.$k->no_rujukan, [
            ['tajuk' => 'Maklumat kes', 'lajur' => ['Medan', 'Nilai'], 'baris' => [
                ['No. kes mahkamah', $k->no_kes], ['Tajuk', $k->tajuk], ['Jenis kes', $p['jenis_nama']], ['Mahkamah', $k->mahkamah], ['Peringkat', $p['peringkat_nama']],
                ['Tarikh pemfailan', $p['tarikh_pemfailan']], ['Peranan MAIS', $p['peranan_mais_nama']], ['Status', $p['status_nama']],
                ['Nilai tuntutan', Wang::papar($k->nilai_tuntutan)], ['Kos guaman terkumpul', Wang::papar($k->kos_guaman)], ['Pegawai', $p['pegawai']['nama'] ?? '—'],
            ]],
            ['tajuk' => 'Pihak', 'lajur' => ['Peranan', 'Nama (bertopeng)', 'Jenis'], 'baris' => $k->pihak->map(fn (Pihak $x) => [Kod::papar(Kod::PERANAN, $x->peranan), $x->bertopeng()['nama_pihak'], Kod::papar(Kod::JENIS_PIHAK, $x->jenis_pihak)])->values()->all()],
            ['tajuk' => 'Kronologi prosiding', 'lajur' => ['Tarikh', 'Jenis', 'Status', 'Hasil', 'Tarikh berikutnya'], 'baris' => $k->prosiding->sortBy('tarikh_prosiding')->map(fn ($x) => [$x->tarikh_prosiding->format('d/m/Y'), Kod::papar(Kod::JENIS_PROSIDING, $x->jenis_prosiding), Kod::papar(Kod::STATUS_PROSIDING, $x->status), $x->hasil, $x->tarikh_berikutnya?->format('d/m/Y')])->values()->all()],
            ['tajuk' => 'Keputusan', 'lajur' => ['Tarikh', 'Keputusan', 'Ringkasan perintah'], 'baris' => $k->keputusan->map(fn ($x) => [$x->tarikh_keputusan->format('d/m/Y'), Kod::papar(Kod::JENIS_KEPUTUSAN, $x->jenis_keputusan), $x->ringkasan_perintah])->values()->all()],
        ], $footer, false);
    }

    /**
     * SRS-KES-08: chronology in time order — proceedings, status changes, decisions, imported entries.
     *
     * @return list<array{tarikh: string, jenis: string, tajuk: string, butiran: string|null}>
     */
    private function kronologi(KesMahkamah $k): array
    {
        $out = [];
        foreach ($k->prosiding as $p) {
            $out[] = ['tarikh' => $p->tarikh_prosiding->toIso8601String(), 'jenis' => 'prosiding', 'tajuk' => Kod::papar(Kod::JENIS_PROSIDING, $p->jenis_prosiding).' — '.Kod::papar(Kod::STATUS_PROSIDING, $p->status), 'butiran' => trim(($p->hasil ?? '').($p->arahan_mahkamah ? ' Arahan: '.$p->arahan_mahkamah : '')) ?: null];
        }
        foreach ($k->sejarahStatus as $s) {
            $out[] = ['tarikh' => $s->tarikh_perubahan->toIso8601String(), 'jenis' => 'status', 'tajuk' => 'Status: '.$this->ref->namaStatus($s->status_baharu), 'butiran' => $s->catatan];
        }
        foreach ($k->keputusan as $d) {
            $out[] = ['tarikh' => $d->tarikh_keputusan->toDateString().'T12:00:00+08:00', 'jenis' => 'keputusan', 'tajuk' => 'Keputusan: '.Kod::papar(Kod::JENIS_KEPUTUSAN, $d->jenis_keputusan), 'butiran' => $d->ringkasan_perintah];
        }
        foreach ($k->kronologi as $c) {
            $out[] = ['tarikh' => $c->tarikh->toIso8601String(), 'jenis' => $c->jenis, 'tajuk' => $c->keterangan, 'butiran' => $c->sumber];
        }
        usort($out, fn ($a, $b) => strcmp($a['tarikh'], $b['tarikh']));

        return $out;
    }

    /** @return array<string, list<mixed>> */
    private function rulesKes(bool $create): array
    {
        $req = $create ? 'required' : 'sometimes';

        return [
            'no_kes' => [$create ? 'nullable' : 'sometimes', 'string', 'max:80'],
            'tajuk' => [$req, 'string', 'max:300'],
            'jenis' => [$req, 'string', Rule::in(array_keys(array_filter($this->ref->jenis(), fn ($j) => $j['aktif'])))],
            'mahkamah' => [$req, 'string', 'max:150'],
            'peringkat_mahkamah' => [$req, Rule::in(array_keys(Kod::PERINGKAT_MAHKAMAH))],
            'tarikh_pemfailan' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'ringkasan_tuntutan' => [$req, 'string', 'max:10000'],
            'nilai_tuntutan' => ['nullable', 'regex:/^\d{1,14}(\.\d{1,2})?$/'],
            'peranan_mais' => [$req, Rule::in(array_keys(Kod::PERANAN))],
        ];
    }

    /** @return array<string, list<mixed>> */
    private function rulesPihak(string $p): array
    {
        return [
            $p.'nama_pihak' => ['required', 'string', 'max:255'],
            $p.'jenis_pihak' => ['required', Rule::in(array_keys(Kod::JENIS_PIHAK))],
            $p.'peranan' => ['required', Rule::in(array_keys(Kod::PERANAN))],
            $p.'no_pengenalan' => ['nullable', 'string', 'max:30', 'regex:/^[0-9A-Za-z\-\/ ]+$/'],
            $p.'maklumat_perhubungan' => ['nullable', 'string', 'max:1000'],
            $p.'status_wakilan' => ['nullable', Rule::in(array_keys(Kod::STATUS_WAKILAN))],
            $p.'adalah_mais' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, list<mixed>> */
    private function rulesPeguam(string $p): array
    {
        return [
            $p.'nama_peguam' => ['required', 'string', 'max:150'],
            $p.'nama_firma' => ['nullable', 'string', 'max:200'],
            $p.'pegawai_penghubung' => ['nullable', 'string', 'max:150'],
            $p.'no_telefon' => ['nullable', 'string', 'max:30'],
            $p.'emel' => ['nullable', 'email', 'max:150'],
            $p.'kes_pihak_id' => ['nullable', 'integer'],
            $p.'pihak_indeks' => ['nullable', 'integer', 'min:0', 'max:49'],
        ];
    }
}
