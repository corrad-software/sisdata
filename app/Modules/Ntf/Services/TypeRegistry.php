<?php

namespace App\Modules\Ntf\Services;

use App\Modules\Ntf\Contracts\NotificationTypes;
use App\Modules\Ntf\Models\Jenis;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Code-declared notification types (singleton). Definitions live in memory
 * and are copied into ntf_jenis + a version-1 template the first time they
 * are needed; from then on the database row (edited by admins, SRS-NTF-03)
 * wins and the code values are only the seeded defaults.
 */
class TypeRegistry implements NotificationTypes
{
    /** @var array<string, array{code: string, name: string, priority: string, subject: string, body: string, fields: list<string>, mandatory: bool, channels: list<string>|null, rules: list<array{0: string, 1: string}>}> */
    private array $definitions = [];

    public function __construct(private Settings $settings) {}

    public function register(string $code, string $name, string $priority, string $subject, string $body, array $fields = [], bool $mandatory = false): void
    {
        if (! preg_match('/^[a-z0-9]+\.[a-z0-9_]+$/', $code)) {
            throw new InvalidArgumentException("Notification type code \"{$code}\" must look like \"<modul>.<nama>\".");
        }

        if (! in_array($priority, Jenis::KEUTAMAAN, true)) {
            throw new InvalidArgumentException("Unknown notification priority \"{$priority}\".");
        }

        $this->definitions[$code] = [
            'code' => $code,
            'name' => $name,
            'priority' => $priority,
            'subject' => $subject,
            'body' => $body,
            'fields' => $fields,
            'mandatory' => $mandatory,
            'channels' => $this->definitions[$code]['channels'] ?? null,
            'rules' => $this->definitions[$code]['rules'] ?? [],
        ];
    }

    /**
     * MOD-NTF-internal: seed defaults for channels and recipient rules of a
     * type (used for NTF's own system types).
     *
     * @param  list<string>|null  $channels
     * @param  list<array{0: string, 1: string}>  $rules  [sasaran, nilai]
     */
    public function seedDefaults(string $code, ?array $channels = null, array $rules = []): void
    {
        if (! isset($this->definitions[$code])) {
            throw new InvalidArgumentException("Register \"{$code}\" before seeding its defaults.");
        }

        $this->definitions[$code]['channels'] = $channels;
        $this->definitions[$code]['rules'] = $rules;
    }

    /** @return array<string, array{code: string, name: string, priority: string, subject: string, body: string, fields: list<string>, mandatory: bool, channels: list<string>|null, rules: list<array{0: string, 1: string}>}> */
    public function definitions(): array
    {
        return $this->definitions;
    }

    public function isRegistered(string $code): bool
    {
        return isset($this->definitions[$code]);
    }

    /**
     * The type row for $code (created from the code definition if new), or
     * null if unknown. Not cached: queue workers are long-lived and must see
     * admin edits immediately.
     */
    public function find(string $code): ?Jenis
    {
        return Jenis::query()->where('kod', $code)->first() ?? $this->seed($code);
    }

    /** Make sure every code-registered type has its row (admin list). */
    public function syncAll(): void
    {
        $existing = Jenis::query()->pluck('kod')->all();

        foreach (array_diff(array_keys($this->definitions), $existing) as $code) {
            $this->seed($code);
        }
    }

    private function seed(string $code): ?Jenis
    {
        $def = $this->definitions[$code] ?? null;
        if ($def === null) {
            return null;
        }

        try {
            return DB::transaction(function () use ($def) {
                $jenis = Jenis::query()->create([
                    'kod' => $def['code'],
                    'nama' => $def['name'],
                    'modul' => strstr($def['code'], '.', true),
                    'keutamaan' => $def['priority'],
                    'wajib' => $def['mandatory'],
                    'saluran_lalai' => $def['channels'] ?? ($def['priority'] === 'maklumat' ? ['dalam_sistem'] : ['dalam_sistem', 'emel']),
                    'kekerapan' => 'serta_merta',
                    'tempoh_eskalasi_minit' => $def['priority'] === 'kritikal' ? $this->settings->int('ntf.tempoh_eskalasi_minit') : null,
                    'tempoh_pendiaman_minit' => $this->settings->int('ntf.tempoh_pendiaman_minit'),
                    'medan' => $def['fields'],
                    'medan_sensitif' => [],
                    'aktif' => true,
                ]);

                $jenis->templat()->create([
                    'versi' => 1,
                    'subjek' => $def['subject'],
                    'kandungan' => $def['body'],
                    'aktif' => true,
                    'catatan' => 'Templat lalai daripada modul',
                ]);

                foreach ($def['rules'] as [$sasaran, $nilai]) {
                    $jenis->peraturan()->create(['sasaran' => $sasaran, 'nilai' => $nilai, 'aktif' => true]);
                }

                return $jenis;
            });
        } catch (UniqueConstraintViolationException) {
            // Another request seeded it first.
            return Jenis::query()->where('kod', $code)->first();
        }
    }
}
