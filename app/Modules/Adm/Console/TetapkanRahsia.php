<?php

namespace App\Modules\Adm\Console;

use App\Modules\Adm\Models\Rahsia;
use App\Modules\Adm\Services\SecretStore;
use App\Modules\Adm\Services\Siem\LogKeselamatan;
use Illuminate\Console\Command;

/**
 * SRS-ADM-28: put a secret into the encrypted store from the server console
 * (deploy scripts, break-glass). The value is read from a hidden prompt or
 * STDIN — never from a command-line argument, which would land in shell
 * history and the process list.
 *
 *   php artisan adm:secret integrasi.jupem.token
 *   printf '%s' "$TOKEN" | php artisan adm:secret integrasi.jupem.token --stdin
 */
class TetapkanRahsia extends Command
{
    protected $signature = 'adm:secret {kunci : Secret key, e.g. integrasi.jupem.token}
        {--keterangan= : Description shown in the admin UI}
        {--stdin : Read the value from STDIN instead of prompting}
        {--padam : Remove the secret}';

    protected $description = 'Set (or remove) a secret in the MOD-ADM encrypted secret store';

    public function handle(SecretStore $store, LogKeselamatan $security): int
    {
        $key = (string) $this->argument('kunci');
        if (! preg_match('/^[a-z0-9_.-]{2,120}$/', $key)) {
            $this->error('Invalid key: use lower-case letters, digits, "_", "." or "-".');

            return self::INVALID;
        }

        if ($this->option('padam')) {
            Rahsia::query()->where('kunci', $key)->delete();
            $security->rekod('rahsia_dipadam', 'success', null, 'Rahsia dipadam melalui konsol', ['kunci' => $key]);
            $this->info("Secret [{$key}] removed.");

            return self::SUCCESS;
        }

        $value = $this->option('stdin')
            ? rtrim((string) stream_get_contents(STDIN), "\r\n")
            : (string) $this->secret('Value (hidden)');

        if ($value === '') {
            $this->error('Empty value — nothing stored.');

            return self::FAILURE;
        }

        $store->put($key, $value, $this->option('keterangan'), null);
        $security->rekod('rahsia_ditetapkan', 'success', null, 'Rahsia ditetapkan melalui konsol', ['kunci' => $key]);
        $this->info("Secret [{$key}] stored (encrypted).");

        return self::SUCCESS;
    }
}
