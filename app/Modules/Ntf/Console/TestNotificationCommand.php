<?php

namespace App\Modules\Ntf\Console;

use App\Models\User;
use App\Modules\Ntf\Models\Penghantaran;
use App\Modules\Ntf\Services\NotificationEngine;
use App\Modules\Ntf\Services\TypeRegistry;
use Illuminate\Console\Command;
use Throwable;

/** Manual check: `php artisan ntf:test admin@example.com ntf.ujian` */
class TestNotificationCommand extends Command
{
    protected $signature = 'ntf:test {user : User id or e-mail} {type=ntf.ujian : Notification type code}
        {--data=* : Template field as medan=nilai (sample values are used for missing fields)}
        {--link=/admin/notifikasi : Frontend path}';

    protected $description = 'Send a test notification of one type to one user and show the delivery status';

    public function handle(NotificationEngine $engine, TypeRegistry $types): int
    {
        $ref = (string) $this->argument('user');
        $user = ctype_digit($ref) ? User::query()->find((int) $ref) : User::query()->where('email', $ref)->first();
        if (! $user) {
            $this->error("User {$ref} not found.");

            return self::FAILURE;
        }

        $code = (string) $this->argument('type');
        $jenis = $types->find($code);
        if (! $jenis) {
            $this->error("Unknown notification type {$code}. Registered: ".implode(', ', array_keys($types->definitions())));

            return self::FAILURE;
        }

        $data = [];
        foreach ($jenis->medan as $field) {
            $data[$field] = 'contoh_'.$field;
        }
        foreach ((array) $this->option('data') as $pair) {
            [$field, $value] = array_pad(explode('=', (string) $pair, 2), 2, '');
            $data[$field] = $value;
        }

        try {
            $result = $engine->dispatch($code, $user, $data, (string) $this->option('link'), 'ujian:'.now()->format('YmdHisu'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $result['mesej']) {
            $this->warn('Nothing sent (type inactive, user opted out, or deduplicated).');

            return self::SUCCESS;
        }

        $this->info("Message #{$result['mesej']->id}: {$result['mesej']->subjek}");
        $this->table(
            ['Penghantaran', 'Saluran', 'Status', 'Cubaan', 'Kod ralat'],
            Penghantaran::query()->where('mesej_id', $result['mesej']->id)->get()
                ->map(fn (Penghantaran $p) => [$p->id, $p->saluran, $p->status, $p->bilangan_cubaan, $p->kod_ralat ?? '—'])
                ->all(),
        );
        $this->line('Queued deliveries are processed by Horizon / queue:work; re-run with QUEUE_CONNECTION=sync to deliver inline.');

        return self::SUCCESS;
    }
}
