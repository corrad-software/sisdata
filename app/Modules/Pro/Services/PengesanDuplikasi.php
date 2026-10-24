<?php

namespace App\Modules\Pro\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Aud\Contracts\AuditScope;
use App\Modules\Pro\Models\CadanganDuplikasi;
use App\Modules\Pro\Models\DataSpatial;
use App\Modules\Pro\Models\LotTanah;
use App\Modules\Pro\Support\NoLot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Duplicate detection (SRS-PRO-20, SRS-DAT-02). Candidates come from the
 * stored procedure sp_kesan_duplikasi_lot (same daerah, similar normalised
 * lot number OR overlapping geometry); each pair is scored with MAIS-tunable
 * weights (parameter pro.kriteria_duplikasi). Nothing is ever merged
 * automatically: a steward decides sah / gabung / tolak and the decision is
 * recorded (and audited).
 */
class PengesanDuplikasi
{
    public const DEFAULT_CRITERIA = [
        'berat_no_lot' => 40,
        'berat_mukim' => 20,
        'berat_keluasan' => 20,
        'berat_geometri' => 20,
        'toleransi_keluasan_peratus' => 5,
        'ambang_pertindihan_peratus' => 30,
        'ambang_nama' => 0.6,
        'ambang_skor' => 60,
    ];

    public const DECISIONS = ['sah', 'gabung', 'tolak'];

    public function __construct(private Parameters $parameters, private AuditLogger $audit) {}

    /** @return array<string, float> */
    public function criteria(): array
    {
        $value = $this->parameters->get('pro.kriteria_duplikasi', self::DEFAULT_CRITERIA);
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return array_map('floatval', [...self::DEFAULT_CRITERIA, ...(is_array($value) ? $value : [])]);
    }

    /**
     * Score one candidate pair.
     *
     * @return array{skor: float, kriteria: array<string, mixed>}
     */
    public function score(string $noLotA, string $noLotB, float $nameSimilarity, bool $sameMukim, ?float $areaDiff, float $overlap): array
    {
        $c = $this->criteria();

        $digitsA = NoLot::digits($noLotA);
        $digitsB = NoLot::digits($noLotB);
        $name = match (true) {
            NoLot::key($noLotA) === NoLot::key($noLotB) => 1.0,
            $digitsA !== '' && $digitsA === $digitsB => 0.9,             // LOT 123 vs PT 123
            $digitsA !== '' && levenshtein($digitsA, $digitsB) === 1 && strlen($digitsA) >= 3 => 0.4, // possible typo — needs area or geometry to reach the threshold
            default => max(0.0, min(1.0, $nameSimilarity)) * 0.5,
        };
        $area = $areaDiff === null ? 0.0 : ($areaDiff <= $c['toleransi_keluasan_peratus'] ? 1.0 : 0.0);
        $geometry = $overlap >= $c['ambang_pertindihan_peratus'] ? 1.0 : ($overlap > 0 ? $overlap / max($c['ambang_pertindihan_peratus'], 1) : 0.0);

        $total = $c['berat_no_lot'] + $c['berat_mukim'] + $c['berat_keluasan'] + $c['berat_geometri'];
        $score = $total > 0
            ? ($name * $c['berat_no_lot'] + ($sameMukim ? 1 : 0) * $c['berat_mukim'] + $area * $c['berat_keluasan'] + $geometry * $c['berat_geometri']) / $total * 100
            : 0;

        return [
            'skor' => round($score, 2),
            'kriteria' => [
                'padanan_no_lot' => round($name, 2),
                'sama_mukim' => $sameMukim,
                'beza_keluasan_peratus' => $areaDiff,
                'pertindihan_geometri_peratus' => $overlap,
                'berat' => array_intersect_key($c, array_flip(['berat_no_lot', 'berat_mukim', 'berat_keluasan', 'berat_geometri'])),
            ],
        ];
    }

    /**
     * Run detection (whole register or one lot) and store new suggestions.
     *
     * @return array{diperiksa: int, cadangan_baharu: int}
     */
    public function detect(?string $lotId = null): array
    {
        $c = $this->criteria();
        $rows = DB::select('SELECT * FROM sp_kesan_duplikasi_lot(?::real, ?::uuid)', [$c['ambang_nama'], $lotId]);
        $new = 0;

        foreach ($rows as $r) {
            $result = $this->score((string) $r->no_lot_a, (string) $r->no_lot_b, (float) $r->skor_nama, (bool) $r->sama_mukim,
                $r->beza_keluasan_peratus !== null ? (float) $r->beza_keluasan_peratus : null, (float) $r->pertindihan_peratus);

            if ($result['skor'] < $c['ambang_skor']) {
                continue;
            }

            $existing = CadanganDuplikasi::query()->where(['entiti' => 'lot_tanah', 'id_rekod_a' => $r->id_a, 'id_rekod_b' => $r->id_b])->first();
            if ($existing === null) {
                CadanganDuplikasi::query()->create([
                    'entiti' => 'lot_tanah',
                    'id_rekod_a' => $r->id_a,
                    'id_rekod_b' => $r->id_b,
                    'kriteria_padanan' => $result['kriteria'],
                    'skor_padanan' => $result['skor'],
                    'keputusan' => 'menunggu',
                ]);
                $new++;
            } elseif ($existing->keputusan === 'menunggu') {
                $existing->update(['kriteria_padanan' => $result['kriteria'], 'skor_padanan' => $result['skor']]);
            }
        }

        return ['diperiksa' => count($rows), 'cadangan_baharu' => $new];
    }

    /**
     * Steward decision. "gabung" keeps $keepId as the master: the other lot
     * becomes status digabung (pointing to the master), its geometry moves to
     * the master if the master has none. Satellite records of other modules
     * are NOT re-pointed here — owning modules listen for the audit/decision.
     */
    public function decide(CadanganDuplikasi $suggestion, string $decision, User $user, ?string $note = null, ?string $keepId = null): CadanganDuplikasi
    {
        if (! in_array($decision, self::DECISIONS, true)) {
            throw ValidationException::withMessages(['keputusan' => [__('pro::pro.duplikasi.keputusan_tidak_sah')]]);
        }
        if ($suggestion->keputusan !== 'menunggu') {
            throw ValidationException::withMessages(['keputusan' => [__('pro::pro.duplikasi.sudah_diputuskan')]]);
        }

        DB::transaction(function () use ($suggestion, $decision, $user, $note, $keepId) {
            if ($decision === 'gabung') {
                $keepId ??= (string) $suggestion->id_rekod_a;
                if (! in_array($keepId, [(string) $suggestion->id_rekod_a, (string) $suggestion->id_rekod_b], true)) {
                    throw ValidationException::withMessages(['id_rekod_kekal' => [__('pro::pro.duplikasi.rekod_kekal_tidak_sah')]]);
                }
                $dropId = $keepId === (string) $suggestion->id_rekod_a ? (string) $suggestion->id_rekod_b : (string) $suggestion->id_rekod_a;
                $this->merge($keepId, $dropId, $note);
            }

            $suggestion->update([
                'keputusan' => $decision,
                'catatan_keputusan' => $note,
                'id_data_steward' => $user->id,
                'tarikh_keputusan' => now(),
            ]);
        });

        $this->audit->record('keputusan_duplikasi', 'pro', 'cadangan_duplikasi', $suggestion->id, reason: $note,
            changes: ['keputusan' => ['menunggu', $decision]],
            meta: ['id_rekod_a' => $suggestion->id_rekod_a, 'id_rekod_b' => $suggestion->id_rekod_b, 'skor_padanan' => $suggestion->skor_padanan, 'klasifikasi' => 'dalaman']);

        return $suggestion->refresh();
    }

    private function merge(string $keepId, string $dropId, ?string $note): void
    {
        $keep = LotTanah::query()->withoutGlobalScope('access')->findOrFail($keepId);
        $drop = LotTanah::query()->withoutGlobalScope('access')->findOrFail($dropId);

        AuditScope::withReason($note ?: __('pro::pro.duplikasi.sebab_gabung', ['no_lot' => $keep->no_lot]), function () use ($keep, $drop) {
            $drop->update(['status_lot' => 'digabung', 'id_lot_gabung' => $keep->id, 'status_sebelum_arkib' => $drop->status_lot]);

            $keepHasGeometry = DataSpatial::query()->where('lot_id', $keep->id)->where('semasa', true)->exists();
            if (! $keepHasGeometry) {
                DataSpatial::query()->where('lot_id', $drop->id)->update(['lot_id' => $keep->id]);
            }

            // Fill gaps in the master from the duplicate (never overwrite).
            $fill = [];
            foreach (['kategori_guna_tanah', 'zoning', 'syarat_nyata', 'id_data_owner', 'id_data_steward'] as $field) {
                if ($keep->{$field} === null && $drop->{$field} !== null) {
                    $fill[$field] = $drop->{$field};
                }
            }
            if ($fill !== []) {
                $keep->update([...$fill, 'versi_rekod' => $keep->versi_rekod + 1]);
            }
        });
    }
}
