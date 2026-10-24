<?php

namespace App\Modules\Ptn\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Adu\Contracts\ComplaintIntake;
use App\Modules\Ntf\Contracts\Notifier;
use App\Modules\Ntf\Contracts\RecipientDirectory;
use App\Modules\Ptn\Contracts\LegalCaseIntake;
use App\Modules\Ptn\Models\CadanganKemaskiniLot;
use App\Modules\Ptn\Models\IsuPemantauan;
use App\Modules\Ptn\Models\KeperluanPenyelenggaraan;
use App\Modules\Ptn\Models\RekodLawatan;
use App\Modules\Ptn\Support\Akses;
use App\Modules\Ptn\Support\AliranPtn;
use App\Modules\Ptn\Support\Lalai;
use App\Modules\Ptn\Support\Rujukan;
use App\Modules\Wfe\Contracts\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Process step 6 / SRS-PTN-13/17/18/21/08: a submitted visit's "isu" and
 * "kritikal" items become monitoring issues; encroachment-type findings (and
 * any critical one MOD-ADU can take) open ONE complaint per visit through
 * Adu\Contracts\ComplaintIntake (channel "ptn", sourceRef
 * "ptn_lawatan:<id>", idempotent); critical findings notify Bahagian
 * Penguatkuasaan and Bahagian Pengurusan Hartanah and force the lot red on
 * the risk map. Maintenance needs and master-data proposals become records
 * (proposals go to MOD-WFE).
 */
class PenemuanService
{
    /** Categories that always open a complaint (lead brief: encroachment, illegal occupation, dumping). */
    public const AUTO_ADUAN = ['pencerobohan', 'pendudukan_haram', 'binaan_haram', 'pembuangan_sampah'];

    private const TAHAP_URUTAN = ['rendah' => 1, 'sederhana' => 2, 'tinggi' => 3, 'kritikal' => 4];

    public function __construct(
        private ComplaintIntake $intake,
        private LegalCaseIntake $kes,
        private Notifier $notifier,
        private RecipientDirectory $directory,
        private Parameters $parameters,
        private WorkflowEngine $workflow,
        private Akses $akses,
        private Rujukan $ref,
    ) {}

    /** @return list<string> warnings for the caller (non-blocking failures) */
    public function proses(RekodLawatan $lawatan, User $user): array
    {
        $warnings = [];
        $issues = [];
        foreach ($lawatan->item()->whereIn('status_item', ['isu', 'kritikal'])->get() as $item) {
            $existing = IsuPemantauan::query()->withoutGlobalScope('access')->where('id_lawatan', $lawatan->id)->where('kod_item', $item->kod_item)->first();
            if ($existing !== null) {
                $issues[] = $existing;

                continue;
            }
            $isu = new IsuPemantauan;
            $isu->forceFill([
                'no_rujukan' => $this->noIsu(),
                'id_lawatan' => $lawatan->id, 'lot_id' => $lawatan->lot_id,
                'kod_daerah' => $lawatan->kod_daerah, 'kod_mukim' => $lawatan->kod_mukim, 'klasifikasi' => $lawatan->klasifikasi,
                'kod_item' => $item->kod_item, 'kategori_isu' => $item->kategori_isu ?? 'lain',
                'tahap_keterukan' => $item->tahap_keterukan ?? ($item->status_item === 'kritikal' ? 'kritikal' : 'sederhana'),
                'keterangan' => trim($item->label.($item->catatan ? ': '.$item->catatan : '')),
                'tarikh_dibuka' => $lawatan->tarikh_lawatan, 'status_isu' => 'terbuka', 'id_pencipta' => $user->id,
                'sintetik' => $lawatan->sintetik,
            ])->save();
            $issues[] = $isu;
        }

        $this->bukaAduan($lawatan, $issues, $user, $warnings);
        foreach ($issues as $isu) {
            if ($isu->tahap_keterukan === 'kritikal' && $isu->wasRecentlyCreated) {
                $this->maklumKritikal($isu, $lawatan, $user);
            }
        }
        $this->penyelenggaraan($lawatan, $user);
        $warnings = [...$warnings, ...$this->cadangan($lawatan, $user)];

        return $warnings;
    }

    /**
     * @param  list<IsuPemantauan>  $issues
     * @param  list<string>  $warnings
     */
    private function bukaAduan(RekodLawatan $lawatan, array $issues, User $user, array &$warnings): void
    {
        $eligible = array_values(array_filter($issues, fn (IsuPemantauan $i) => $i->id_aduan_berkaitan === null && (
            in_array($i->kategori_isu, self::AUTO_ADUAN, true) || ($i->tahap_keterukan === 'kritikal' && isset(Lalai::KE_ADUAN[$i->kategori_isu]))
        )));
        if ($eligible === []) {
            return;
        }
        usort($eligible, fn ($a, $b) => (self::TAHAP_URUTAN[$b->tahap_keterukan] ?? 0) <=> (self::TAHAP_URUTAN[$a->tahap_keterukan] ?? 0));
        $main = $eligible[0];
        try {
            $id = $this->intake->open($this->dataAduan($lawatan, $eligible), 'ptn', $user, 'ptn_lawatan:'.$lawatan->id);
            DB::table('isu_pemantauan')->whereIn('id', array_map(fn ($i) => $i->id, $eligible))->update(['id_aduan_berkaitan' => $id, 'updated_at' => now()]);
            foreach ($eligible as $i) {
                $i->id_aduan_berkaitan = $id;
            }
        } catch (Throwable $e) {
            report($e);
            $warnings[] = (string) __('ptn::ptn.aduan_gagal', ['no' => $main->no_rujukan]);
        }
    }

    /**
     * @param  list<IsuPemantauan>  $issues
     * @return array{kategori: string, keterangan: string, lot_id: string, kod_daerah: string, kod_mukim: string, lat?: float|null, lng?: float|null, keutamaan: string}
     */
    private function dataAduan(RekodLawatan $lawatan, array $issues): array
    {
        $main = $issues[0];
        $pt = DB::selectOne('SELECT ST_Y(koordinat_lawatan) AS lat, ST_X(koordinat_lawatan) AS lng FROM rekod_lawatan WHERE id = ?', [$lawatan->id]);
        $lines = array_map(fn (IsuPemantauan $i) => '• ['.$i->no_rujukan.'] '.(Lalai::KATEGORI_ISU[$i->kategori_isu] ?? $i->kategori_isu).' ('.Lalai::TAHAP_KETERUKAN[$i->tahap_keterukan].'): '.$i->keterangan, $issues);
        $text = 'Penemuan lawatan pemantauan '.$lawatan->no_rujukan.' bertarikh '.$lawatan->tarikh_lawatan->format('d/m/Y').":\n".implode("\n", $lines)
            ."\nGambar tapak dan laporan lawatan: /admin/ptn/lawatan/".$lawatan->id;

        return [
            'kategori' => Lalai::KE_ADUAN[$main->kategori_isu] ?? 'LAIN',
            'keterangan' => mb_substr($text, 0, 4000),
            'lot_id' => $lawatan->lot_id, 'kod_daerah' => $lawatan->kod_daerah, 'kod_mukim' => $lawatan->kod_mukim,
            'lat' => $pt?->lat !== null ? (float) $pt->lat : null, 'lng' => $pt?->lng !== null ? (float) $pt->lng : null,
            'keutamaan' => $main->tahap_keterukan,
        ];
    }

    /** SRS-PTN-18: manual escalation of one issue to MOD-ADU (no re-entry of data). */
    public function eskalasiAduan(IsuPemantauan $isu, User $user): string
    {
        if ($isu->id_aduan_berkaitan !== null) {
            return $isu->id_aduan_berkaitan;
        }
        $lawatan = $isu->lawatan;
        $ref = $lawatan ? 'ptn_lawatan:'.$lawatan->id.':isu:'.$isu->id : 'ptn_isu:'.$isu->id;
        $data = $lawatan ? $this->dataAduan($lawatan, [$isu]) : [
            'kategori' => Lalai::KE_ADUAN[$isu->kategori_isu] ?? 'LAIN', 'keterangan' => '['.$isu->no_rujukan.'] '.$isu->keterangan,
            'lot_id' => $isu->lot_id, 'kod_daerah' => $isu->kod_daerah, 'kod_mukim' => $isu->kod_mukim, 'keutamaan' => $isu->tahap_keterukan,
        ];
        $id = $this->intake->open($data, 'ptn', $user, $ref);
        $isu->forceFill(['id_aduan_berkaitan' => $id])->save();

        return $id;
    }

    /** SRS-PTN-18: escalation to MOD-KES (when that module is available). */
    public function eskalasiKes(IsuPemantauan $isu, User $user): string
    {
        if ($isu->id_kes_berkaitan !== null) {
            return $isu->id_kes_berkaitan;
        }
        if (! $this->kes->available()) {
            throw ValidationException::withMessages(['kes' => __('ptn::ptn.kes_tiada')]);
        }
        $id = $this->kes->open([
            'lot_id' => $isu->lot_id, 'tajuk' => 'Isu pemantauan '.$isu->no_rujukan, 'keterangan' => $isu->keterangan, 'kategori' => $isu->kategori_isu,
        ], $user, 'ptn_isu:'.$isu->id);
        $isu->forceFill(['id_kes_berkaitan' => $id])->save();

        return $id;
    }

    /**
     * Issue recorded outside a visit (e.g. from a complaint or desk review).
     *
     * @param  array{lot: array{id: string, kod_daerah: string, kod_mukim: string, klasifikasi: string}, kategori_isu: string, tahap_keterukan: string, keterangan: string}  $d
     */
    public function cipta(array $d, User $user): IsuPemantauan
    {
        $isu = new IsuPemantauan;
        $isu->forceFill([
            'no_rujukan' => $this->noIsu(), 'lot_id' => $d['lot']['id'], 'kod_daerah' => $d['lot']['kod_daerah'], 'kod_mukim' => $d['lot']['kod_mukim'],
            'klasifikasi' => $d['lot']['klasifikasi'], 'kategori_isu' => $d['kategori_isu'], 'tahap_keterukan' => $d['tahap_keterukan'],
            'keterangan' => $d['keterangan'], 'tarikh_dibuka' => now()->toDateString(), 'status_isu' => 'terbuka', 'id_pencipta' => $user->id, 'sintetik' => false,
        ])->save();
        if ($isu->tahap_keterukan === 'kritikal') {
            $this->maklumKritikal($isu, null, $user);
        }

        return $isu;
    }

    private function maklumKritikal(IsuPemantauan $isu, ?RekodLawatan $lawatan, User $user): void
    {
        $to = [];
        foreach (['ptn.peranan_penguatkuasaan' => 'penguatkuasaan', 'ptn.peranan_pengurusan_hartanah' => 'pengurusan_hartanah'] as $param => $default) {
            foreach (array_filter(array_map('trim', explode(',', (string) $this->parameters->get($param, $default)))) as $role) {
                foreach ($this->directory->resolve(RecipientDirectory::PERANAN, $role) as $u) {
                    $to[$u->id] = $u;
                }
            }
        }
        foreach ($this->akses->penyeliaKepada($lawatan->pegawai ?? $user) as $u) {
            $to[$u->id] = $u;
        }
        $lot = DB::table('lot_tanah')->where('id', $isu->lot_id)->value('no_lot');
        $this->notifier->send('ptn.penemuan_kritikal', array_values($to), [
            'no_rujukan' => $isu->no_rujukan, 'kategori' => Lalai::KATEGORI_ISU[$isu->kategori_isu] ?? $isu->kategori_isu,
            'no_lot' => (string) $lot, 'daerah' => (string) $this->ref->namaDaerah($isu->kod_daerah), 'tarikh' => $isu->tarikh_dibuka->format('d/m/Y'),
        ], '/admin/ptn/isu/'.$isu->id, 'ptn.kritikal:'.$isu->id);
    }

    private function penyelenggaraan(RekodLawatan $lawatan, User $user): void
    {
        if (KeperluanPenyelenggaraan::query()->withoutGlobalScope('access')->where('id_lawatan', $lawatan->id)->exists()) {
            return;
        }
        foreach ($lawatan->data_tambahan['penyelenggaraan'] ?? [] as $p) {
            if (empty($p['keterangan'])) {
                continue;
            }
            $k = new KeperluanPenyelenggaraan;
            $k->forceFill([
                'id_lawatan' => $lawatan->id, 'lot_id' => $lawatan->lot_id, 'kod_daerah' => $lawatan->kod_daerah, 'kod_mukim' => $lawatan->kod_mukim,
                'klasifikasi' => $lawatan->klasifikasi, 'jenis' => array_key_exists($p['jenis'] ?? '', Lalai::JENIS_PENYELENGGARAAN) ? $p['jenis'] : 'lain',
                'keterangan' => mb_substr((string) $p['keterangan'], 0, 2000), 'keutamaan' => in_array($p['keutamaan'] ?? '', ['rendah', 'sederhana', 'tinggi', 'kritikal'], true) ? $p['keutamaan'] : 'sederhana',
                'tarikh_jadual' => $p['tarikh_jadual'] ?? null, 'anggaran_kos' => isset($p['anggaran_kos']) && is_numeric($p['anggaran_kos']) ? $p['anggaran_kos'] : null,
                'status' => ! empty($p['tarikh_jadual']) ? 'dijadual' : 'dikenal_pasti', 'id_pencipta' => $user->id, 'sintetik' => $lawatan->sintetik,
            ])->save();
        }
    }

    /** @return list<string> */
    private function cadangan(RekodLawatan $lawatan, User $user): array
    {
        $warnings = [];
        if (CadanganKemaskiniLot::query()->withoutGlobalScope('access')->where('id_lawatan', $lawatan->id)->exists()) {
            return [];
        }
        $lot = DB::table('lot_tanah')->where('id', $lawatan->lot_id)->first();
        foreach ($lawatan->data_tambahan['cadangan'] ?? [] as $c) {
            $medan = (string) ($c['medan'] ?? '');
            if (! array_key_exists($medan, Lalai::MEDAN_CADANGAN) || empty($c['nilai_dicadang']) || empty($c['justifikasi'])) {
                continue;
            }
            $row = new CadanganKemaskiniLot;
            $row->forceFill([
                'id_lawatan' => $lawatan->id, 'lot_id' => $lawatan->lot_id, 'kod_daerah' => $lawatan->kod_daerah, 'kod_mukim' => $lawatan->kod_mukim,
                'klasifikasi' => $lawatan->klasifikasi, 'medan' => $medan, 'nilai_semasa' => $lot->{$medan} ?? null,
                'nilai_dicadang' => mb_substr((string) $c['nilai_dicadang'], 0, 2000), 'justifikasi' => mb_substr((string) $c['justifikasi'], 0, 2000),
                'status' => 'menunggu', 'id_pencadang' => $user->id,
            ])->save();
            try {
                $row->forceFill(['id_aliran' => $this->workflow->start(AliranPtn::KEMASKINI_LOT, AliranPtn::SUBJEK_CADANGAN, $row->id, $user, [
                    'kod_daerah' => $lawatan->kod_daerah, 'medan' => $medan, 'pautan' => '/admin/ptn/lawatan/'.$lawatan->id,
                ], 'Cadangan kemas kini '.Lalai::MEDAN_CADANGAN[$medan].' — lot '.($lot->no_lot ?? ''))])->save();
            } catch (\InvalidArgumentException) {
                $warnings[] = (string) __('ptn::ptn.aliran_tiada');
            }
        }

        return $warnings;
    }

    private function noIsu(): string
    {
        return 'ISU-'.now()->format('Y').'-'.str_pad((string) DB::selectOne("SELECT nextval('ptn_no_isu_seq') AS n")->n, 6, '0', STR_PAD_LEFT);
    }
}
