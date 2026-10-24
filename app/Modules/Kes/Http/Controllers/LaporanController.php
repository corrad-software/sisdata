<?php

namespace App\Modules\Kes\Http\Controllers;

use App\Models\User;
use App\Modules\Kes\Http\Present;
use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Kes\Models\LogCapaian;
use App\Modules\Kes\Services\Eskalasi;
use App\Modules\Kes\Services\Laporan;
use App\Modules\Kes\Services\LogCapaianKes;
use App\Modules\Kes\Services\Wang;
use App\Modules\Kes\Support\Akses;
use App\Modules\Kes\Support\Eksport;
use App\Modules\Kes\Support\Kod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * SRS-KES-09, 21..24: legal calendar, dashboard, filterable / groupable
 * reports, portfolio exposure, access log — every generation and export is
 * written to the case access log and the audit trail.
 */
class LaporanController extends KesBaseController
{
    public function __construct(Akses $akses, private Laporan $laporan, private LogCapaianKes $log, private Present $present)
    {
        parent::__construct($akses);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $f = $this->penapis($request);
        $this->log->rekod('lihat_senarai', null, null, 'Dashboard kes', ['laporan' => 'dashboard', 'penapis' => $f]);

        return response()->json(['data' => $this->laporan->dashboard($user, $f)]);
    }

    public function kalendar(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $f = $this->penapis($request, ['jenis_prosiding' => ['nullable', Rule::in(array_keys(Kod::JENIS_PROSIDING))]]);
        $rows = $this->laporan->kalendar($user, $f);
        $this->log->rekod('lihat_senarai', null, null, 'Kalendar perundangan: '.count($rows).' entri', ['laporan' => 'kalendar', 'penapis' => $f]);

        return response()->json(['data' => $rows]);
    }

    public function laporan(Request $request, string $jenis): JsonResponse
    {
        $user = $this->user($request);
        abort_unless(in_array($jenis, Laporan::JENIS, true) && $jenis !== 'log_capaian', 404);
        $f = $this->penapis($request);
        $data = $this->jana($user, $jenis, $f, $request);
        $this->log->rekod('lihat_senarai', null, null, 'Laporan '.$jenis, ['laporan' => $jenis, 'penapis' => $f]);

        return response()->json(['data' => $data]);
    }

    public function eksport(Request $request, string $jenis): Response
    {
        $user = $this->user($request);
        abort_unless($user->can('kes.laporan.eksport'), 403);
        abort_unless(in_array($jenis, Laporan::JENIS, true) && $jenis !== 'log_capaian', 404);
        $format = (string) $request->validate(['format' => ['required', Rule::in(['pdf', 'xlsx', 'csv'])]])['format'];
        $f = $this->penapis($request);
        $sections = $this->bahagian($user, $jenis, $f, $request);
        $this->log->rekod('eksport', null, null, 'Eksport laporan '.$jenis.' ('.$format.')', ['laporan' => $jenis, 'format' => $format, 'penapis' => $f, 'bil_baris' => array_sum(array_map(fn ($s) => count($s['baris']), $sections))]);

        return Eksport::download($format, 'laporan-kes-'.$jenis.'-'.now()->format('Ymd-His'), 'Laporan Kes Mahkamah — '.(string) __('kes::kes.laporan.'.$jenis), $sections, $this->footer($user, $request));
    }

    public function logCapaian(Request $request): JsonResponse
    {
        $f = $request->validate(['dari' => ['nullable', 'date'], 'hingga' => ['nullable', 'date'], 'id_pengguna' => ['nullable', 'integer'], 'jenis_tindakan' => ['nullable', Rule::in(array_keys(Kod::JENIS_LOG))], 'kes_id' => ['nullable', 'uuid']]);
        $page = $this->laporan->logCapaian($f)->paginate(50);
        $kes = KesMahkamah::query()->withoutGlobalScope('access')->whereIn('id', collect($page->items())->pluck('kes_mahkamah_id')->filter()->unique())->pluck('no_rujukan', 'id');

        return response()->json([
            'data' => collect($page->items())->map(fn (LogCapaian $l) => [
                'id' => $l->id, 'cap_masa' => $l->cap_masa->toIso8601String(), 'pengguna' => $l->pengguna?->name, 'id_pengguna' => $l->id_pengguna,
                'jenis_tindakan' => $l->jenis_tindakan, 'jenis_nama' => Kod::JENIS_LOG[$l->jenis_tindakan] ?? $l->jenis_tindakan,
                'kes_id' => $l->kes_mahkamah_id, 'no_rujukan' => $l->kes_mahkamah_id ? ($kes[$l->kes_mahkamah_id] ?? null) : null,
                'id_dokumen' => $l->id_dokumen, 'alamat_ip' => $l->alamat_ip, 'keterangan' => $l->keterangan,
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function eksportLog(Request $request): Response
    {
        $user = $this->user($request);
        $f = $request->validate(['format' => ['required', Rule::in(['pdf', 'xlsx', 'csv'])], 'dari' => ['nullable', 'date'], 'hingga' => ['nullable', 'date'], 'id_pengguna' => ['nullable', 'integer'], 'jenis_tindakan' => ['nullable', 'string'], 'kes_id' => ['nullable', 'uuid']]);
        $rows = $this->laporan->logCapaian($f)->limit(20000)->get();
        $this->log->rekod('eksport', null, null, 'Eksport log capaian ('.$f['format'].')', ['laporan' => 'log_capaian', 'bil_baris' => $rows->count()]);

        return Eksport::download((string) $f['format'], 'log-capaian-kes-'.now()->format('Ymd-His'), 'Log Capaian Rekod & Dokumen Kes', [[
            'tajuk' => 'Log capaian', 'lajur' => ['Cap masa', 'Pengguna', 'Tindakan', 'Kes', 'Dokumen', 'Alamat IP', 'Keterangan'],
            'baris' => $rows->map(fn (LogCapaian $l) => [$l->cap_masa->format('d/m/Y H:i:s'), $l->pengguna?->name, Kod::JENIS_LOG[$l->jenis_tindakan] ?? $l->jenis_tindakan, $l->kes_mahkamah_id, $l->id_dokumen, $l->alamat_ip, $l->keterangan])->values()->all(),
        ]], $this->footer($user, $request));
    }

    /**
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>|list<mixed>
     */
    private function jana(User $user, string $jenis, array $f, Request $request): array
    {
        return match ($jenis) {
            'ringkasan' => $this->laporan->ringkasan($user, $f, $this->kumpul($request)),
            'senarai' => $this->laporan->kes($user, $f)->with('pegawai')->orderByDesc('kes_mahkamah.created_at')->limit(1000)->get()->map(fn ($k) => $this->present->kes($k))->values()->all(),
            'kalendar' => $this->laporan->kalendar($user, $f),
            'tanpa_tarikh' => Eskalasi::tanpaTarikh($this->laporan->kes($user, $f))->with('pegawai')->orderBy('kes_mahkamah.created_at')->limit(1000)->get()->map(fn ($k) => $this->present->kes($k))->values()->all(),
            'kos' => $this->laporan->kos($user, $f),
            'keputusan' => $this->laporan->keputusan($user, $f),
            'pendedahan' => $this->laporan->pendedahan($user, $f),
            'beban' => $this->laporan->beban($user, $f),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $f
     * @return list<array{tajuk: string, lajur: list<string>, baris: list<list<scalar|null>>}>
     */
    private function bahagian(User $user, string $jenis, array $f, Request $request): array
    {
        $senaraiKes = fn (array $rows, string $tajuk) => [[
            'tajuk' => $tajuk, 'lajur' => ['No. rujukan', 'No. kes', 'Jenis', 'Mahkamah', 'Status', 'Daerah', 'Pegawai', 'Tarikh pemfailan', 'Prosiding berikutnya', 'Nilai tuntutan (RM)', 'Kos guaman (RM)'],
            'baris' => array_map(fn ($k) => [$k['no_rujukan'], $k['no_kes'], $k['jenis_nama'], $k['mahkamah'], $k['status_nama'], $k['daerah'], $k['pegawai']['nama'] ?? null, $k['tarikh_pemfailan'], $k['tarikh_prosiding_berikutnya'], $k['nilai_tuntutan'], $k['kos_guaman']], $rows),
        ]];
        $data = $this->jana($user, $jenis, $f, $request);

        return match ($jenis) {
            'ringkasan' => [['tajuk' => 'Mengikut '.$this->kumpul($request), 'lajur' => ['Kumpulan', 'Bil. kes', 'Kes aktif', 'Nilai tuntutan (RM)', 'Kos guaman (RM)'], 'baris' => array_map(fn ($r) => [$r['label'], $r['bil'], $r['aktif'], $r['nilai_tuntutan'], $r['kos']], $data)]],
            'senarai' => $senaraiKes($data, 'Senarai kes'),
            'tanpa_tarikh' => $senaraiKes($data, 'Kes tanpa tarikh prosiding berikutnya'),
            'kalendar' => [['tajuk' => 'Kalendar prosiding', 'lajur' => ['Tarikh', 'Jenis', 'Status', 'No. rujukan', 'No. kes', 'Mahkamah', 'Pegawai'], 'baris' => array_map(fn ($r) => [substr((string) $r['tarikh'], 0, 16), $r['jenis_nama'], $r['status_nama'], $r['no_rujukan'], $r['no_kes'], $r['mahkamah'], $r['pegawai']], $data)]],
            'kos' => [
                ['tajuk' => 'Kos mengikut tahun', 'lajur' => ['Tahun', 'Fi peguam', 'Kos mahkamah', 'Kos berkaitan', 'Jumlah'], 'baris' => array_map(fn ($r) => [$r['tahun'], $r['fi_peguam'], $r['kos_mahkamah'], $r['kos_berkaitan'], $r['jumlah']], $data['mengikut_tahun'])],
                ['tajuk' => 'Kos mengikut kes', 'lajur' => ['No. rujukan', 'No. kes', 'Jenis', 'Kos terkumpul (RM)'], 'baris' => array_map(fn ($r) => [$r['no_rujukan'], $r['no_kes'], $r['jenis'], $r['kos']], $data['mengikut_kes'])],
            ],
            'keputusan' => [['tajuk' => 'Keputusan mengikut jenis kes', 'lajur' => ['Tahun', 'Jenis kes', 'Keputusan', 'Bil.'], 'baris' => array_map(fn ($r) => [$r['tahun'], $r['jenis_kes_nama'], $r['jenis_keputusan_nama'], $r['bil']], $data)]],
            'pendedahan' => [
                ['tajuk' => 'Ringkasan pendedahan', 'lajur' => ['Ukuran', 'Nilai'], 'baris' => [
                    ['Kes aktif', $data['bil_kes_aktif']], ['Lot terlibat', $data['bil_lot_terlibat']],
                    ['Nilai lot terlibat', $data['nilai_lot']['boleh_lihat'] ? Wang::papar($data['nilai_lot']['nilai']) : 'Memerlukan kebenaran Rahsia penilaian'],
                    ['Nilai tuntutan kes aktif', Wang::papar($data['nilai_tuntutan_aktif'])],
                ]],
                ['tajuk' => 'Kos guaman mengikut tahun', 'lajur' => ['Tahun', 'Jumlah (RM)'], 'baris' => array_map(fn ($r) => [$r['tahun'], $r['jumlah']], $data['kos_mengikut_tahun'])],
                ['tajuk' => 'Trend keputusan mengikut jenis kes', 'lajur' => ['Tahun', 'Jenis kes', 'Keputusan', 'Bil.'], 'baris' => array_map(fn ($r) => [$r['tahun'], $r['jenis_kes_nama'], $r['jenis_keputusan_nama'], $r['bil']], $data['trend_keputusan'])],
            ],
            'beban' => [['tajuk' => 'Beban kes pegawai', 'lajur' => ['Pegawai', 'Kes aktif', 'Jumlah kes', 'Prosiding 30 hari'], 'baris' => array_map(fn ($r) => [$r['nama'], $r['aktif'], $r['jumlah'], $r['prosiding_30_hari']], $data)]],
            default => [],
        };
    }

    private function kumpul(Request $request): string
    {
        $k = (string) $request->query('kumpul', 'status');

        return in_array($k, Laporan::KUMPUL, true) ? $k : 'status';
    }

    /**
     * @param  array<string, list<mixed>>  $extra
     * @return array<string, mixed>
     */
    private function penapis(Request $request, array $extra = []): array
    {
        return array_filter($request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'string', 'max:40'], 'jenis' => ['nullable', 'string', 'max:40'],
            'mahkamah' => ['nullable', 'string', 'max:150'], 'peringkat_mahkamah' => ['nullable', 'string', 'max:30'], 'kod_daerah' => ['nullable', 'string', 'max:4'],
            'kod_mukim' => ['nullable', 'string', 'max:6'], 'kategori_tanah' => ['nullable', 'string', 'max:50'], 'id_pegawai' => ['nullable', 'integer'],
            'peguam' => ['nullable', 'string', 'max:150'], 'dari' => ['nullable', 'date'], 'hingga' => ['nullable', 'date'], 'aktif' => ['nullable', 'boolean'],
            'arkib' => ['nullable', 'boolean'], 'kumpul' => ['nullable', 'string'], ...$extra,
        ]), fn ($v) => $v !== null && $v !== '');
    }

    private function footer(User $user, Request $request): string
    {
        return 'Dijana oleh '.$user->name.' ('.$user->email.') pada '.now()->format('d/m/Y H:i:s').' · IP '.$request->ip().' · SISDATA MOD-KES';
    }
}
