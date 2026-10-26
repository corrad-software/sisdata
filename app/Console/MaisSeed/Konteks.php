<?php

namespace App\Console\MaisSeed;

use App\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Shared state of one sisdata:mais-seed run: the acting users (the demo
 * accounts created by sisdata:demo-seed when present, else the admin), the
 * record counters for the summary and the warnings to report.
 */
class Konteks
{
    public const SUMBER = 'mais_awam';

    public const FAIL = 'database/data/mais/hartanah_mais.json';

    public User $admin;

    /** Officer who registers / submits (never the approver of the same item). */
    public User $pegawai;

    /** @var list<User> approvers to try, in order */
    public array $pelulus = [];

    /** @var list<User> */
    public array $lapangan = [];

    /** @var array<string, int> */
    public array $kiraan = [];

    /** @var list<string> */
    public array $amaran = [];

    public function __construct()
    {
        $admin = User::query()->where('email', 'admin@example.com')->first()
            ?? User::query()->where('role', 'admin')->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();
        if ($admin === null) {
            throw new RuntimeException('Tiada pengguna dalam pangkalan data — jalankan `php artisan sisdata:demo-seed` (atau `db:seed`) dahulu.');
        }
        $this->admin = $admin;
        $demo = User::query()->whereIn('email', ['siti.aminah@mais.test', 'mohd.faizal@mais.test', 'nurul.huda@mais.test'])->get()->keyBy('email');
        // The registering / submitting officer is the state-wide admin (no district scope, member of no
        // approver group), so the demo approvers can decide every stage without breaking segregation of duties.
        $this->pegawai = $admin;
        $this->pelulus = array_values(array_filter([$demo['mohd.faizal@mais.test'] ?? null, $demo['nurul.huda@mais.test'] ?? null, $demo['siti.aminah@mais.test'] ?? null]));
        if ($this->pelulus === []) {
            $this->pelulus = [$admin];
        }
        /** @var Collection<int, User> $field */
        $field = User::query()->where('is_active', true)->where('email', 'not like', '%@sistem.sisdata.local')->orderBy('id')->limit(8)->get();
        $this->lapangan = $field->isEmpty() ? [$admin] : $field->all();
    }

    public function tambah(string $key, int $n = 1): void
    {
        $this->kiraan[$key] = ($this->kiraan[$key] ?? 0) + $n;
    }

    public function amaran(string $message): void
    {
        if (count($this->amaran) < 200) {
            $this->amaran[] = $message;
        }
    }
}
