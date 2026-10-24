<?php

namespace App\Modules\Tsu\Services;

use App\Models\User;
use App\Modules\Hkm\Contracts\TitleChangeProposals;
use App\Modules\Hkm\Contracts\TitleRegistry;
use App\Modules\Pro\Contracts\LotRegistry;
use App\Modules\Tsu\Contracts\LotChanges;
use App\Modules\Tsu\Events\LotBaharuDicipta;
use App\Modules\Tsu\Models\HasilUkur;
use App\Modules\Tsu\Models\LotSejarahPecahan;
use App\Modules\Tsu\Models\UrusanKeputusan;
use App\Modules\Tsu\Models\UrusanLot;
use App\Modules\Tsu\Models\UrusanPeringkat;
use App\Modules\Tsu\Models\UrusanTanah;
use App\Modules\Tsu\Support\Lalai;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * System effects of a completed stage. Master data owned by other modules
 * is never written here directly: lot changes go through Tsu\Contracts\
 * LotChanges (MOD-PRO), title changes are PROPOSED to MOD-HKM
 * (TitleChangeProposals → officer confirmation + HKM's own approval).
 */
class KesanUrusan
{
    public function __construct(
        private LotChanges $lotChanges,
        private LotRegistry $lots,
        private TitleRegistry $titles,
        private TitleChangeProposals $proposals,
    ) {}

    public function apply(UrusanTanah $urusan, UrusanPeringkat $stage, User $user): void
    {
        match ($stage->kesan) {
            'kemas_kini_syarat' => $this->conditions($urusan, $user),
            'cipta_lot_baharu' => $this->successors($urusan, $user),
            'arkib_lot_dilupus' => $this->dispose($urusan, $user),
            'cadang_geometri' => $this->proposeSurvey($urusan, $user),
            default => null,
        };
    }

    /** SRS-TSU-07: new category / conditions after the new title is registered. */
    private function conditions(UrusanTanah $urusan, User $user): void
    {
        $b = $urusan->butiran;
        $changes = array_filter([
            'kategori_guna_tanah' => $b['kategori_guna_tanah_baharu'] ?? null,
            'syarat_nyata' => $b['syarat_nyata_baharu'] ?? null,
            'zoning' => $b['zoning_baharu'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        $reason = __('tsu::tsu.kesan.sebab_syarat', ['no' => $urusan->no_rujukan_mais]);
        foreach ($this->lotIds($urusan) as $lotId) {
            if ($changes !== []) {
                $this->lotChanges->applyConditions($lotId, $changes, $user, $reason);
            }
            $this->proposeTitle($urusan, $lotId, 'butiran', array_filter([
                'kategori_tanah' => $changes['kategori_guna_tanah'] ?? null,
                'syarat_nyata' => $changes['syarat_nyata'] ?? null,
                'no_hak_milik' => $b['no_hak_milik_baharu'] ?? null,
            ]), $user);
        }
    }

    /** SRS-TSU-06: disposed lots are archived with their history; the titles are proposed as "dilupuskan". */
    private function dispose(UrusanTanah $urusan, User $user): void
    {
        $reason = __('tsu::tsu.kesan.sebab_lupus', ['no' => $urusan->no_rujukan_mais]);
        foreach ($this->lotIds($urusan) as $lotId) {
            $this->lotChanges->archive($lotId, $user, $reason);
            $this->proposeTitle($urusan, $lotId, 'status', ['status_selepas' => 'dilupuskan', 'tarikh_berkuat_kuasa' => today()->toDateString()], $user);
        }
    }

    public function siapUntukLotBaharu(UrusanTanah $urusan): bool
    {
        return HasilUkur::query()->where('urusan_id', $urusan->id)->where('status_pengesahan', 'diterima')->whereNotNull('no_lot_cadangan')->whereNull('id_lot_dicipta')->exists()
            || UrusanLot::query()->where('urusan_id', $urusan->id)->where('peranan_lot', 'baharu')->exists();
    }

    /**
     * SRS-TSU-19/21: one successor lot per accepted survey result, two-way
     * lineage asal ↔ baharu, original lots archived (not deleted).
     */
    private function successors(UrusanTanah $urusan, User $user): void
    {
        $results = HasilUkur::query()->where('urusan_id', $urusan->id)->where('status_pengesahan', 'diterima')->whereNotNull('no_lot_cadangan')->whereNull('id_lot_dicipta')->get();
        if ($results->isEmpty()) {
            return; // already created (idempotent)
        }
        $asalIds = $this->lotIds($urusan, 'asal');
        $asal = $this->lots->summaries($asalIds);
        $first = $asal[$asalIds[0]] ?? null;
        if ($first === null) {
            throw ValidationException::withMessages(['peringkat' => [__('tsu::tsu.urusan.lot_tiada')]]);
        }
        $sintetik = (bool) DB::table('lot_tanah')->whereIn('id', $asalIds)->where('sintetik', true)->exists();
        $jenis = $urusan->jenis_urusan;
        $reason = __('tsu::tsu.kesan.sebab_lot_baharu', ['jenis' => Lalai::JENIS_URUSAN[$jenis] ?? $jenis, 'no' => $urusan->no_rujukan_mais]);
        $new = [];
        foreach ($results as $h) {
            $hex = (string) DB::table('hasil_ukur')->where('id', $h->id)->selectRaw('ST_AsHEXEWKB(geometri) AS h')->value('h');
            $lotId = $this->lotChanges->createSuccessor([
                'no_lot' => (string) $h->no_lot_cadangan, 'kod_mukim' => $first['kod_mukim'], 'kod_daerah' => $first['kod_daerah'],
                'keluasan' => (string) $h->keluasan_dikira, 'kategori_guna_tanah' => $first['kategori_guna_tanah'],
                'bahagian_pemilik' => $first['bahagian_pemilik'], 'klasifikasi' => $first['klasifikasi'], 'sintetik' => $sintetik,
            ], $hex, $user, $reason, 'urusan_tanah:'.$urusan->id.':'.$h->id);
            $h->forceFill(['id_lot_dicipta' => $lotId])->save();
            UrusanLot::query()->create(['urusan_id' => $urusan->id, 'lot_id' => $lotId, 'peranan_lot' => 'baharu', 'tarikh_paut' => now()]);
            foreach ($asalIds as $a) {
                LotSejarahPecahan::query()->create(['id_lot_asal' => $a, 'id_lot_baharu' => $lotId, 'urusan_id' => $urusan->id, 'jenis_perubahan' => $jenis, 'tarikh_berkuat_kuasa' => today()]);
            }
            $new[] = $lotId;
        }
        foreach ($asalIds as $a) {
            $this->lotChanges->archive($a, $user, $reason);
        }
        UrusanKeputusan::query()->create(['urusan_id' => $urusan->id, 'konteks' => 'urusan', 'jenis_keputusan' => 'lot_baharu_dicipta', 'justifikasi' => count($new).' lot baharu; '.count($asalIds).' lot asal diarkibkan.', 'id_pelulus' => $user->id]);
        DB::afterCommit(fn () => event(new LotBaharuDicipta($urusan->id, $jenis, $asalIds, $new)));
    }

    /** Re-survey (e.g. permanent title): accepted geometry → PRO data_spatial proposal + area critical-change proposal. */
    private function proposeSurvey(UrusanTanah $urusan, User $user): void
    {
        foreach (HasilUkur::query()->where('urusan_id', $urusan->id)->where('status_pengesahan', 'diterima')->whereNotNull('lot_id')->whereNull('id_spatial_dicadang')->get() as $h) {
            $hex = (string) DB::table('hasil_ukur')->where('id', $h->id)->selectRaw('ST_AsHEXEWKB(geometri) AS h')->value('h');
            $spatial = $this->lotChanges->proposeGeometry((string) $h->lot_id, $hex, ['sumber_data' => 'juruukur', 'srid_asal' => $h->sistem_koordinat, 'format_asal' => $h->format_fail === 'shp' ? 'shp' : 'geojson', 'id_dokumen_sumber' => $h->id_dokumen_fail, 'rujukan_fail_sumber' => 'hasil_ukur:'.$h->id], $user);
            $this->lotChanges->proposeArea((string) $h->lot_id, (string) $h->keluasan_dikira, $user, __('tsu::tsu.kesan.sebab_keluasan', ['no' => $urusan->no_rujukan_mais]));
            $h->forceFill(['id_spatial_dicadang' => $spatial])->save();
        }
    }

    /** @param array<string, mixed> $data */
    private function proposeTitle(UrusanTanah $urusan, string $lotId, string $kind, array $data, User $user): void
    {
        if ($data === []) {
            return;
        }
        try {
            foreach ($this->titles->forLot($lotId, $user) as $t) {
                if (in_array($t['status_semasa'], ['diganti', 'dilupuskan', 'batal'], true)) {
                    continue;
                }
                $this->proposals->propose([
                    'sumber' => 'ptd', 'rujukan_luaran' => 'tsu:'.$urusan->no_rujukan_mais.':'.$t['id'].':'.$kind,
                    'no_hak_milik' => $t['no_hak_milik'], 'kod_mukim' => $urusan->kod_mukim, 'jenis_perubahan' => $kind, 'data' => $data,
                    'keterangan' => __('tsu::tsu.kesan.cadangan_hkm', ['jenis' => $urusan->jenisLabel(), 'no' => $urusan->no_rujukan_mais]),
                ], $user);
            }
        } catch (Throwable $e) {
            report($e); // a title proposal never blocks the land matter; HKM officers can still act manually
        }
    }

    /** @return list<string> */
    private function lotIds(UrusanTanah $urusan, ?string $peranan = null): array
    {
        return UrusanLot::query()->where('urusan_id', $urusan->id)->when($peranan, fn ($q, $p) => $q->where('peranan_lot', $p), fn ($q) => $q->where('peranan_lot', '!=', 'baharu'))
            ->orderBy('id')->pluck('lot_id')->map(fn ($v) => (string) $v)->all();
    }
}
