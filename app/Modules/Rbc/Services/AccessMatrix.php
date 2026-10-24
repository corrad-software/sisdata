<?php

namespace App\Modules\Rbc\Services;

use App\Modules\Rbc\Contracts\AccessControl;
use App\Modules\Rbc\Models\Peranan;
use App\Modules\Rbc\Models\PerananKebenaran;
use Illuminate\Support\Facades\DB;

/**
 * The access matrix — role × module × function × action × scope
 * (SRS-RBC-10, acceptance #8). Built straight from the live tables, so the
 * screen, the Excel and the PDF all show exactly what is enforced.
 */
class AccessMatrix
{
    public function __construct(private PermissionCatalogue $catalogue) {}

    /**
     * @return array{
     *     dijana_pada: string,
     *     tindakan: list<string>,
     *     peranan: list<array{id: int, kod: string, nama: string, status: string, kategori_rfp: string|null, bilangan_pengguna: int, skop: array<string, list<string>>}>,
     *     modul: list<array{modul: string, label: string, fungsi: list<array{fungsi: string, kebenaran: array<string, array{kunci: string, label: string}>}>}>,
     *     pemberian: array<string, list<string>>
     * }
     */
    public function build(bool $includeInactive = true): array
    {
        $roles = Peranan::query()
            ->when(! $includeInactive, fn ($q) => $q->where('status', Peranan::AKTIF))
            ->with('skop')
            ->withCount('pengguna')
            ->orderByRaw('kategori_rfp is null')
            ->orderBy('id')
            ->get();

        $grants = DB::table('peranan_kebenaran')
            ->whereIn('peranan_id', $roles->pluck('id'))
            ->orderBy('kebenaran')
            ->get(['peranan_id', 'kebenaran'])
            ->groupBy('peranan_id');

        $actions = AccessControl::ACTIONS;
        $modules = [];

        foreach ($this->catalogue->all() as $module => $definition) {
            $functions = [];
            foreach ($definition['permissions'] as $key => $label) {
                ['fungsi' => $fungsi, 'tindakan' => $tindakan] = PerananKebenaran::split($key);
                $functions[$fungsi][$tindakan] = ['kunci' => $key, 'label' => $label];
                if (! in_array($tindakan, $actions, true)) {
                    $actions[] = $tindakan;
                }
            }

            ksort($functions);
            $modules[] = [
                'modul' => $module,
                'label' => $definition['label'],
                'fungsi' => array_map(fn ($f, $perms) => ['fungsi' => (string) $f, 'kebenaran' => $perms], array_keys($functions), $functions),
            ];
        }

        $byCode = [];
        foreach ($roles as $role) {
            $byCode[$role->kod] = $grants->get($role->id, collect())->pluck('kebenaran')->map(fn ($k) => (string) $k)->values()->all();
        }

        return [
            'dijana_pada' => now()->toIso8601String(),
            'tindakan' => $actions,
            'peranan' => $roles->map(fn (Peranan $role) => [
                'id' => $role->id,
                'kod' => $role->kod,
                'nama' => $role->nama,
                'status' => $role->status,
                'kategori_rfp' => $role->kategori_rfp,
                'bilangan_pengguna' => (int) $role->getAttribute('pengguna_count'),
                'skop' => $role->skop->groupBy('jenis_skop')->map(fn ($rows) => $rows->pluck('nilai_skop')->values()->all())->all(),
            ])->values()->all(),
            'modul' => $modules,
            'pemberian' => $byCode,
        ];
    }

    /**
     * Flat rows (one per permission) for the export files.
     *
     * @param  array<string, mixed>  $matrix
     * @return list<array{modul: string, fungsi: string, tindakan: string, kunci: string, label: string, peranan: array<string, bool>}>
     */
    public function rows(array $matrix): array
    {
        $rows = [];
        foreach ($matrix['modul'] as $module) {
            foreach ($module['fungsi'] as $function) {
                foreach ($function['kebenaran'] as $tindakan => $permission) {
                    $marks = [];
                    foreach ($matrix['peranan'] as $role) {
                        $marks[$role['kod']] = in_array($permission['kunci'], $matrix['pemberian'][$role['kod']] ?? [], true);
                    }
                    $rows[] = [
                        'modul' => $module['label'],
                        'fungsi' => $function['fungsi'],
                        'tindakan' => (string) $tindakan,
                        'kunci' => $permission['kunci'],
                        'label' => $permission['label'],
                        'peranan' => $marks,
                    ];
                }
            }
        }

        return $rows;
    }

    public static function scopeSummary(array $scopes): string
    {
        if ($scopes === []) {
            return __('rbc::rbc.matriks.tiada_had');
        }

        return collect($scopes)->map(fn ($values, $type) => $type.': '.implode(', ', $values))->implode('; ');
    }
}
