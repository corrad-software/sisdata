<?php

namespace App\Modules\Lap\Services;

use App\Models\User;
use App\Modules\Lap\Models\DefinisiLaporan;
use App\Modules\Lap\Models\KatalogLaporan;
use App\Modules\Lap\Support\LaporanStandard;
use App\Modules\Lap\Support\SetData;
use App\Modules\Rbc\Contracts\AccessControl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Central report catalogue and report-level access control (SRS-LAP-02/09/23).
 *
 * A report opens only when the reader holds lap.laporan.lihat, the dataset's
 * own permission (so BI/reporting never bypasses a module's authorisation —
 * SRS-RBC-08), the catalogue entry's extra permission and, if set, one of its
 * allowed roles. Shared definitions use the reader's rights, not the
 * creator's.
 */
class Katalog
{
    public function __construct(private AccessControl $access) {}

    /** Upsert code-owned fields of the standard set; admin-owned fields are kept. */
    public function sync(bool $force = false): void
    {
        $definitions = LaporanStandard::all();
        $hash = sha1((string) json_encode($definitions));
        if (! $force && Cache::get('lap:katalog:versi') === $hash && KatalogLaporan::query()->exists()) {
            return;
        }
        if (! Schema::hasTable('lap_katalog_laporan')) {
            return;
        }
        foreach ($definitions as $kod => $d) {
            $row = KatalogLaporan::query()->firstOrNew(['kod_laporan' => $kod]);
            $row->fill([
                'nama' => $d['nama'], 'kategori' => $d['kategori'], 'jenis' => $d['jenis'], 'set_data' => $d['set_data'],
                'definisi' => $d['definisi'], 'bahagian' => $d['bahagian'], 'pautan' => $d['pautan'], 'sumber_data' => $d['sumber_data'],
                'kebenaran' => $d['kebenaran'], 'susunan' => $d['susunan'],
            ]);
            if (! $row->exists) {
                $row->definisi_metrik = $d['definisi_metrik'];
                $row->pemilik = $this->defaultOwner($d['kategori']);
                $row->aktif = true;
                $row->status_pengesahan = 'draf';
            }
            if ($row->isDirty()) {
                $row->save();
            }
        }
        Cache::forever('lap:katalog:versi', $hash);
    }

    private function defaultOwner(string $kategori): string
    {
        return match ($kategori) {
            'kewangan' => 'Bahagian Kewangan MAIS',
            'pematuhan' => 'Unit Audit Dalam MAIS',
            'lembaga' => 'Pejabat Ketua Pegawai Eksekutif MAIS',
            default => 'Bahagian Pengurusan Hartanah MAIS',
        };
    }

    public function canOpen(User $user, KatalogLaporan $entry, int $depth = 0): bool
    {
        if (! $entry->aktif || ! $this->access->allows($user, 'lap.laporan.lihat')) {
            return false;
        }
        if ($entry->kebenaran && ! $this->access->allows($user, $entry->kebenaran)) {
            return false;
        }
        if (! empty($entry->peranan_dibenarkan) && array_intersect($entry->peranan_dibenarkan, $this->access->roles($user)) === []) {
            return false;
        }

        return match ($entry->jenis) {
            'lap' => $this->canUseSet($user, (string) $entry->set_data),
            'komposit' => $depth === 0 && $this->sections($user, $entry) !== [],
            default => true,
        };
    }

    /** @return list<KatalogLaporan> the composite's sections the user may open */
    public function sections(User $user, KatalogLaporan $entry): array
    {
        $codes = (array) ($entry->bahagian ?? []);
        $rows = KatalogLaporan::query()->whereIn('kod_laporan', $codes)->where('jenis', 'lap')->get()->keyBy('kod_laporan');
        $out = [];
        foreach ($codes as $code) {
            $s = $rows[$code] ?? null;
            if ($s && $this->canOpen($user, $s, 1)) {
                $out[] = $s;
            }
        }

        return $out;
    }

    public function canUseSet(User $user, string $set): bool
    {
        $meta = SetData::get($set);

        return $meta !== null && $this->access->allows($user, $meta['kebenaran']);
    }

    /** @return array<string, array<string, mixed>> datasets $user may query */
    public function sets(User $user): array
    {
        return array_filter(SetData::all(), fn ($s) => $this->access->allows($user, $s['kebenaran']));
    }

    public function canOpenDefinition(User $user, DefinisiLaporan $def): bool
    {
        if (! $this->access->allows($user, 'lap.laporan.lihat') || ! $this->canUseSet($user, $def->set_data)) {
            return false;
        }
        if ($def->id_pencipta === $user->id) {
            return true;
        }

        return match ($def->status_kongsi) {
            'semua' => true,
            'peranan' => array_intersect((array) $def->kongsi_peranan, $this->access->roles($user)) !== [],
            default => false,
        };
    }
}
