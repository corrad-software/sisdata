<?php

namespace App\Modules\Pws\Console;

use App\Models\User;
use App\Modules\Pws\Models\KlienApi;
use App\Modules\Pws\Services\KlienService;
use Illuminate\Console\Command;

/**
 * Create (or rotate) the API client for a portal server from the command
 * line — used by infra/bin/pws-setup.sh for local/staging set-up. The secret
 * is printed once. Same rules as the admin screen (/admin/pws/klien).
 */
class CiptaKlienCommand extends Command
{
    protected $signature = 'pws:cipta-klien {nama : Nama klien}
        {--skop=baca,permohonan,bayaran,aduan,sokongan,analitik : Skop dipisahkan koma}
        {--ip=* : IP / CIDR dibenarkan (kosong = mana-mana, dev sahaja)}
        {--had=120 : Had kadar seminit}
        {--json : Keluaran JSON (id + rahsia)}';

    protected $description = 'Cipta klien API Portal Hartanah Wakaf Selangor (atau putar rahsia jika nama sudah wujud)';

    public function handle(KlienService $clients): int
    {
        $admin = User::query()->where('role', 'admin')->orderBy('id')->first() ?? User::query()->orderBy('id')->first();
        if ($admin === null) {
            $this->error('Tiada pengguna dalam pangkalan data — jalankan seeder dahulu.');

            return self::FAILURE;
        }
        $name = (string) $this->argument('nama');
        $existing = KlienApi::query()->where('nama', $name)->where('status', 'aktif')->first();
        if ($existing !== null) {
            $klien = $existing;
            $secret = $clients->rotate($existing);
        } else {
            [$klien, $secret] = $clients->create([
                'nama' => $name,
                'skop' => array_values(array_filter(array_map('trim', explode(',', (string) $this->option('skop'))))),
                'ip_dibenarkan' => array_values((array) $this->option('ip')),
                'had_kadar_minit' => (int) $this->option('had'),
            ], $admin);
        }

        if ($this->option('json')) {
            $this->line((string) json_encode(['client_id' => $klien->id_klien, 'client_secret' => $secret]));
        } else {
            $this->info("ID klien: {$klien->id_klien}");
            $this->info("Rahsia klien (dipaparkan sekali sahaja): {$secret}");
        }

        return self::SUCCESS;
    }
}
