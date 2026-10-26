<?php

namespace App\Modules\Rbc\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;

/**
 * SRS-GEN-08: a user's interactive guide ("panduan interaktif") preferences —
 * whether tours auto-start, and which tour versions they have finished or
 * skipped. Each user only ever reads/changes their own record.
 */
class PanduanService
{
    public const PARAMETER_DEFAULT = 'rbc.panduan_aktif_lalai';

    /** Bound on stored tour keys so a client can't grow the JSON without limit. */
    public const MAX_TOURS = 100;

    public function __construct(private Parameters $parameters) {}

    public function declareToAdm(): void
    {
        $this->parameters->declare(self::PARAMETER_DEFAULT, 'bool', true, 'Panduan interaktif dihidupkan secara lalai untuk pengguna yang belum memilih');
    }

    /** @return array{aktif: bool, selesai: array<string, string>} */
    public function forUser(User $user): array
    {
        $stored = is_array($user->tetapan_panduan) ? $user->tetapan_panduan : [];

        $selesai = [];
        foreach ((array) ($stored['selesai'] ?? []) as $key => $version) {
            if (is_string($key) && (is_string($version) || is_int($version))) {
                $selesai[$key] = (string) $version;
            }
        }

        return [
            'aktif' => array_key_exists('aktif', $stored) ? (bool) $stored['aktif'] : $this->defaultActive(),
            'selesai' => $selesai,
        ];
    }

    public function defaultActive(): bool
    {
        return (bool) $this->parameters->get(self::PARAMETER_DEFAULT, true);
    }

    /**
     * @param  array{aktif?: bool, selesai?: array{kunci: string, versi: string}, set_semula?: bool}  $changes
     * @return array{aktif: bool, selesai: array<string, string>}
     */
    public function update(User $user, array $changes): array
    {
        $prefs = $this->forUser($user);

        if (! empty($changes['set_semula'])) {
            // "Ulang semua panduan": forget completions, and switch auto-start back on.
            $prefs['selesai'] = [];
            $prefs['aktif'] = true;
        }

        if (array_key_exists('aktif', $changes)) {
            $prefs['aktif'] = (bool) $changes['aktif'];
        }

        if (isset($changes['selesai'])) {
            $prefs['selesai'][$changes['selesai']['kunci']] = $changes['selesai']['versi'];
            $prefs['selesai'] = array_slice($prefs['selesai'], -self::MAX_TOURS, null, true);
        }

        $user->forceFill(['tetapan_panduan' => $prefs])->save();

        return $prefs;
    }
}
