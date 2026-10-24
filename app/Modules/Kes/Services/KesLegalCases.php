<?php

namespace App\Modules\Kes\Services;

use App\Models\User;
use App\Modules\Kes\Contracts\LegalCases;
use App\Modules\Kes\Models\KesMahkamah;
use App\Modules\Kes\Models\Kronologi;
use App\Modules\Kes\Support\Akses;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/** MOD-KES's LegalCases implementation (see the contract). */
class KesLegalCases implements LegalCases
{
    private const SUMBER = ['adu', 'ptn', 'hkm', 'lain'];

    public function __construct(private KesService $service, private DokumenService $dokumen, private Akses $akses, private Rujukan $ref, private StatusLot $status) {}

    public function open(array $data, User $by, string $sourceRef): string
    {
        $sumber = in_array($data['sumber'] ?? 'lain', self::SUMBER, true) ? (string) ($data['sumber'] ?? 'lain') : 'lain';
        $existing = KesMahkamah::query()->withoutGlobalScope('access')->where('sumber', $sumber)->where('rujukan_sumber', $sourceRef)->value('id');
        if ($existing !== null) {
            return (string) $existing;
        }
        $jenis = isset($data['jenis']) && isset($this->ref->jenis()[$data['jenis']]) ? (string) $data['jenis'] : null;

        return DB::transaction(function () use ($data, $by, $sourceRef, $sumber, $jenis) {
            $kes = $this->service->daftar([
                'tajuk' => mb_substr((string) $data['tajuk'], 0, 300),
                'jenis' => $jenis,
                'ringkasan_tuntutan' => $data['keterangan'] ?? null,
                'lot_ids' => $data['lot_ids'] ?? [],
            ], $by, $sumber, $sourceRef);

            foreach ((array) ($data['dokumen'] ?? []) as $d) {
                $this->dokumen->paut($kes, (string) $d['id_dokumen_dms'], 'dokumen_aduan', $d['nama_fail'] ?? null, $by->id, $sumber);
            }
            foreach ((array) ($data['kronologi'] ?? []) as $c) {
                try {
                    $tarikh = Carbon::parse((string) $c['tarikh']);
                } catch (Throwable) {
                    $tarikh = now();
                }
                Kronologi::query()->create(['kes_mahkamah_id' => $kes->id, 'tarikh' => $tarikh, 'jenis' => $sumber === 'adu' ? 'aduan' : ($sumber === 'ptn' ? 'pemantauan' : 'nota'), 'keterangan' => mb_substr((string) $c['keterangan'], 0, 5000), 'sumber' => $sumber, 'id_pengguna' => null]);
            }
            Kronologi::query()->create(['kes_mahkamah_id' => $kes->id, 'tarikh' => now(), 'jenis' => 'sistem', 'keterangan' => "Kes dibuka daripada {$sumber} ({$sourceRef})", 'sumber' => $sumber, 'id_pengguna' => $by->id]);

            return $kes->id;
        });
    }

    public function exists(string $caseId): bool
    {
        return preg_match('/^[0-9a-f-]{36}$/i', $caseId) === 1 && KesMahkamah::query()->withoutGlobalScope('access')->whereKey($caseId)->exists();
    }

    public function summary(string $caseId, User $user): ?array
    {
        if (! $this->akses->bolehCapai($user) || preg_match('/^[0-9a-f-]{36}$/i', $caseId) !== 1) {
            return null;
        }
        $k = KesMahkamah::query()->bolehDilihat($user)->whereKey($caseId)->first();

        return $k === null ? null : $this->ringkas($k);
    }

    public function forLot(string $lotId, User $user): array
    {
        if (! $this->akses->bolehCapai($user)) {
            return [];
        }

        return KesMahkamah::query()->bolehDilihat($user)
            ->whereIn('id', DB::table('kes_mahkamah_lot')->where('lot_id', $lotId)->select('kes_mahkamah_id'))
            ->orderByDesc('created_at')->get()->map(fn (KesMahkamah $k) => $this->ringkas($k))->values()->all();
    }

    public function underLegalAction(array $lotIds): array
    {
        return $this->status->untuk(array_map('strval', $lotIds));
    }

    public function link(string $caseId): string
    {
        return '/admin/kes/kes/'.$caseId;
    }

    /** @return array{id: string, no_rujukan: string, jenis: string|null, status: string, aktif: bool, pautan: string} */
    private function ringkas(KesMahkamah $k): array
    {
        return ['id' => $k->id, 'no_rujukan' => (string) $k->no_rujukan, 'jenis' => $this->ref->namaJenis($k->jenis), 'status' => (string) $this->ref->namaStatus($k->status), 'aktif' => ! $k->terminal(), 'pautan' => $this->link($k->id)];
    }
}
