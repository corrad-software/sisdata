<?php

namespace App\Modules\Adm\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Secrets;
use App\Modules\Adm\Models\Rahsia;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * SRS-ADM-28: secrets encrypted at rest (Laravel encryption, APP_KEY — which
 * itself lives outside the repo, in the server environment / vault). Values
 * are never cached, never logged and never returned by an API.
 */
class SecretStore implements Secrets
{
    public function get(string $key): ?string
    {
        $raw = Rahsia::query()->where('kunci', $key)->toBase()->value('nilai_tersulit');
        if ($raw === null) {
            return null;
        }

        try {
            return Crypt::decryptString((string) $raw);
        } catch (DecryptException) {
            Log::error('Secret cannot be decrypted (APP_KEY rotated?)', ['modul' => 'adm', 'kod_ralat' => 'ADM_RAHSIA_NYAHSULIT', 'kunci_rahsia' => $key]);

            return null;
        }
    }

    public function has(string $key): bool
    {
        return Rahsia::query()->where('kunci', $key)->exists();
    }

    public function put(string $key, string $value, ?string $description, ?User $by): void
    {
        $row = Rahsia::query()->firstOrNew(['kunci' => $key]);
        $row->nilai_tersulit = $value;
        if ($description !== null) {
            $row->keterangan = $description;
        }
        $row->dikemas_kini_oleh = $by?->id;
        $row->save();
    }
}
