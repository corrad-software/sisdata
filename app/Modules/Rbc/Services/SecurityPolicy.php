<?php

namespace App\Modules\Rbc\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\Parameters;
use App\Modules\Rbc\Models\PolisiKeselamatan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * POLISI_KESELAMATAN — the admin-tunable security parameters of MOD-RBC,
 * stored in the DB so they change without a deploy (acceptance #4).
 *
 * Resolution order: RBC override row → MOD-ADM Parameters ("rbc.<kunci>") →
 * declared default. Every parameter is also declared to MOD-ADM so that when
 * ADM centralises system parameters it can take ownership; this class is the
 * only thing RBC code reads, so moving storage later touches one file.
 */
class SecurityPolicy
{
    /**
     * @var array<string, array{type: string, default: mixed, min?: int, max?: int, options?: list<string>, label: string, critical?: bool}>
     */
    public const DEFINITIONS = [
        'sesi_tamat_minit' => ['type' => 'int', 'default' => 15, 'min' => 1, 'max' => 480, 'label' => 'Tamat sesi selepas tidak aktif (minit)', 'critical' => true],
        'sesi_amaran_saat' => ['type' => 'int', 'default' => 120, 'min' => 15, 'max' => 900, 'label' => 'Amaran sebelum sesi tamat (saat)'],
        'sesi_had_serentak' => ['type' => 'int', 'default' => 3, 'min' => 1, 'max' => 20, 'label' => 'Had sesi serentak setiap pengguna'],
        'sesi_ikat_ip' => ['type' => 'string', 'default' => 'subnet', 'options' => ['tiada', 'subnet', 'tepat'], 'label' => 'Ikat sesi kepada alamat IP (tiada / subnet /24 / tepat)'],
        'sesi_ikat_peranti' => ['type' => 'bool', 'default' => true, 'label' => 'Ikat sesi kepada sidik pelayar'],
        'kunci_ambang_cubaan' => ['type' => 'int', 'default' => 5, 'min' => 3, 'max' => 20, 'label' => 'Kunci akaun selepas cubaan gagal berturutan', 'critical' => true],
        'kunci_tetingkap_minit' => ['type' => 'int', 'default' => 15, 'min' => 1, 'max' => 1440, 'label' => 'Tempoh kiraan cubaan gagal (minit)'],
        'kunci_tempoh_minit' => ['type' => 'int', 'default' => 30, 'min' => 1, 'max' => 10080, 'label' => 'Tempoh akaun dikunci (minit)'],
        'kata_laluan_min_panjang' => ['type' => 'int', 'default' => 12, 'min' => 12, 'max' => 64, 'label' => 'Panjang minimum kata laluan'],
        'kata_laluan_luput_hari' => ['type' => 'int', 'default' => 90, 'min' => 0, 'max' => 365, 'label' => 'Tempoh luput kata laluan (hari, 0 = tiada)'],
        'kata_laluan_sejarah' => ['type' => 'int', 'default' => 5, 'min' => 0, 'max' => 24, 'label' => 'Larangan guna semula kata laluan terakhir'],
        'tidak_aktif_gantung_hari' => ['type' => 'int', 'default' => 90, 'min' => 0, 'max' => 3650, 'label' => 'Gantung akaun selepas tidak log masuk (hari, 0 = tiada)'],
        'amaran_gagal_ambang' => ['type' => 'int', 'default' => 5, 'min' => 2, 'max' => 100, 'label' => 'Amaran: cubaan gagal dari satu IP/pengecam dalam tetingkap'],
        'amaran_tolak_ambang' => ['type' => 'int', 'default' => 3, 'min' => 1, 'max' => 100, 'label' => 'Amaran: capaian tanpa kebenaran oleh satu pengguna dalam 10 minit'],
        'recert_kitaran_hari' => ['type' => 'int', 'default' => 180, 'min' => 0, 'max' => 730, 'label' => 'Kitaran pengesahan semula capaian (hari, 0 = manual)'],
        'recert_tempoh_hari' => ['type' => 'int', 'default' => 14, 'min' => 1, 'max' => 90, 'label' => 'Tempoh semakan setiap kitaran (hari)'],
        'recert_tarik_belum_disemak' => ['type' => 'bool', 'default' => true, 'label' => 'Tarik capaian yang tidak disahkan apabila kitaran ditutup'],
    ];

    /** @var array<string, mixed>|null */
    private ?array $overrides = null;

    public function __construct(private Parameters $parameters) {}

    public function declareToAdm(): void
    {
        foreach (self::DEFINITIONS as $key => $definition) {
            $this->parameters->declare('rbc.'.$key, $definition['type'], $definition['default'], $definition['label'], $definition['critical'] ?? false);
        }
    }

    public function get(string $key): mixed
    {
        $definition = self::DEFINITIONS[$key] ?? throw new \InvalidArgumentException("Unknown RBC policy [{$key}]");
        $overrides = $this->overrides();

        $value = array_key_exists($key, $overrides)
            ? $overrides[$key]
            : $this->parameters->get('rbc.'.$key, $definition['default']);

        return $this->cast($definition['type'], $value ?? $definition['default']);
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $values = [];
        foreach (array_keys(self::DEFINITIONS) as $key) {
            $values[$key] = $this->get($key);
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     *
     * @throws ValidationException
     */
    public function update(array $values, ?User $actor): void
    {
        $errors = [];
        $clean = [];

        foreach ($values as $key => $value) {
            $definition = self::DEFINITIONS[$key] ?? null;
            if ($definition === null) {
                $errors[$key][] = __('rbc::rbc.polisi.tidak_dikenali');

                continue;
            }

            $cast = $this->cast($definition['type'], $value);

            if ($definition['type'] === 'int' && (! is_numeric($value)
                || $cast < $definition['min'] || $cast > $definition['max'])) {
                $errors[$key][] = __('rbc::rbc.polisi.julat', ['min' => $definition['min'], 'max' => $definition['max']]);

                continue;
            }

            if (isset($definition['options']) && ! in_array($cast, $definition['options'], true)) {
                $errors[$key][] = __('rbc::rbc.polisi.pilihan', ['pilihan' => implode(', ', $definition['options'])]);

                continue;
            }

            $clean[$key] = $cast;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        foreach ($clean as $key => $value) {
            PolisiKeselamatan::query()->updateOrCreate(['kunci' => $key], ['nilai' => $value, 'dikemas_kini_oleh' => $actor?->id]);
        }

        $this->overrides = null;
    }

    public function flush(): void
    {
        $this->overrides = null;
    }

    /** @return array<string, mixed> */
    private function overrides(): array
    {
        if ($this->overrides === null) {
            $this->overrides = Schema::hasTable('polisi_keselamatan')
                ? PolisiKeselamatan::query()->pluck('nilai', 'kunci')->all()
                : [];
        }

        return $this->overrides;
    }

    private function cast(string $type, mixed $value): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL),
            default => (string) $value,
        };
    }
}
