<?php

namespace App\Modules\Ast\Integrasi;

use App\Models\User;
use App\Modules\Ast\Models\AsetFizikal;
use App\Modules\Ast\Models\RekodPenyelenggaraan;
use App\Modules\Ast\Support\Lalai;
use App\Modules\Ast\Support\SusutNilai;
use App\Modules\Pro\Contracts\FinancialSeries;
use App\Modules\Pro\Contracts\ProfileSection;

/**
 * Profil Hartanah 360° section `aset_fizikal` on the `ruang` tab (the key
 * MOD-PRO reserves for MOD-AST — replaces its placeholder): the lot's assets
 * with condition flags, and every completed maintenance cost as
 * FinancialSeries `kos_penyelenggaraan` for the lot's cumulative cost chart
 * (SRS-AST-17, acceptance #6). Registrations, condition changes and
 * completed work appear on the lot timeline.
 */
class AsetFizikalSection implements FinancialSeries, ProfileSection
{
    public function key(): string
    {
        return 'aset_fizikal';
    }

    public function tab(): string
    {
        return 'ruang';
    }

    public function label(): string
    {
        return 'Aset Fizikal';
    }

    public function forLot(string $lotId, User $user): ?array
    {
        if (! $user->can('ast.aset.lihat')) {
            return null;
        }
        $assets = AsetFizikal::query()->untuk($user)->with('kategori')->where('lot_id', $lotId)->orderBy('status_arkib')->orderBy('nama_aset')->limit(100)->get();
        if ($assets->isEmpty()) {
            return ['items' => [], 'link' => '/admin/ast/aset?lot_id='.$lotId];
        }
        $aktif = $assets->where('status_arkib', false);
        $flags = [];
        if (($n = $aktif->where('status_kondisi', 'kritikal')->count()) > 0) {
            $flags[] = ['level' => 'danger', 'label' => $n.' aset berstatus kritikal'];
        }
        if (($n = $aktif->where('status_kondisi', 'perlu_perhatian')->count()) > 0) {
            $flags[] = ['level' => 'warning', 'label' => $n.' aset perlu perhatian'];
        }
        $items = $assets->map(fn (AsetFizikal $a) => [
            'no_rujukan' => $a->no_rujukan_aset, 'nama_aset' => $a->nama_aset, 'kategori' => $a->kategori->nama_kategori,
            'kondisi' => Lalai::KONDISI[$a->status_kondisi] ?? $a->status_kondisi, 'nilai_semasa' => $a->nilai_semasa,
            'pemeriksaan_terakhir' => $a->tarikh_pemeriksaan_terakhir?->toDateString(), 'status' => Lalai::STATUS_ASET[$a->status_aset] ?? $a->status_aset,
            'link' => '/admin/ast/aset/'.$a->id,
        ])->values()->all();
        array_unshift($items, ['jumlah_aset' => $aktif->count(), 'jumlah_nilai_semasa' => SusutNilai::jumlah($aktif->pluck('nilai_semasa')), 'diarkib' => $assets->count() - $aktif->count()]);

        return ['items' => $items, 'flags' => $flags, 'link' => '/admin/ast/aset?lot_id='.$lotId];
    }

    public function timeline(string $lotId, User $user): array
    {
        if (! $user->can('ast.aset.lihat')) {
            return [];
        }
        $out = [];
        $assets = AsetFizikal::query()->untuk($user)->where('lot_id', $lotId)->limit(100)->get();
        foreach ($assets as $a) {
            $out[] = ['date' => $a->created_at->toDateString(), 'type' => 'aset_fizikal', 'title' => 'Aset didaftarkan: '.$a->nama_aset.' ('.$a->no_rujukan_aset.')', 'link' => '/admin/ast/aset/'.$a->id];
            if ($a->tarikh_arkib) {
                $out[] = ['date' => $a->tarikh_arkib->toDateString(), 'type' => 'aset_fizikal', 'title' => 'Aset diarkibkan: '.$a->nama_aset.' — '.(Lalai::STATUS_ASET[$a->status_aset] ?? $a->status_aset), 'link' => '/admin/ast/aset/'.$a->id];
            }
        }
        if ($user->can('ast.penyelenggaraan.lihat')) {
            foreach (RekodPenyelenggaraan::query()->untuk($user)->whereIn('id_aset', $assets->pluck('id')->all())->where('status', 'selesai')->orderByDesc('tarikh_siap')->limit(50)->get() as $k) {
                $out[] = ['date' => ($k->tarikh_siap ?? $k->created_at)->toDateString(), 'type' => 'aset_fizikal', 'title' => 'Penyelenggaraan siap '.$k->no_rujukan.': '.(Lalai::JENIS_PENYELENGGARAAN[$k->jenis_penyelenggaraan] ?? $k->jenis_penyelenggaraan), 'link' => '/admin/ast/kerja/'.$k->id];
            }
        }

        return $out;
    }

    /** Completed maintenance costs of the lot's assets (incl. archived ones — the cost was incurred). */
    public function financialSeries(string $lotId, User $user): array
    {
        if (! $user->can('ast.aset.lihat')) {
            return [];
        }

        return RekodPenyelenggaraan::query()->untuk($user)->with(['aset' => fn ($q) => $q->withoutGlobalScope('access')])
            ->whereIn('id_aset', AsetFizikal::query()->untuk($user)->where('lot_id', $lotId)->select('id'))
            ->where('status', 'selesai')->whereNotNull('kos')->orderBy('tarikh_siap')->get()
            ->map(fn (RekodPenyelenggaraan $k) => [
                'tarikh' => ($k->tarikh_siap ?? $k->created_at)->toDateString(), 'jenis' => 'kos_penyelenggaraan', 'amaun' => (float) $k->kos,
                'keterangan' => $k->no_rujukan.' — '.($k->aset->nama_aset ?? '').' ('.(Lalai::JENIS_PENYELENGGARAAN[$k->jenis_penyelenggaraan] ?? $k->jenis_penyelenggaraan).')',
                'link' => '/admin/ast/kerja/'.$k->id,
            ])->values()->all();
    }
}
