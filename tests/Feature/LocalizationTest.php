<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * SRS-GEN-09: Bahasa Melayu is the system language.
 */
class LocalizationTest extends TestCase
{
    public function test_default_locale_is_malay_with_english_fallback(): void
    {
        $this->assertSame('ms', app()->getLocale());
        $this->assertSame('en', config('app.fallback_locale'));
    }

    public function test_validation_messages_and_attributes_are_in_malay(): void
    {
        $errors = Validator::make(
            ['email' => 'bukan-emel'],
            ['email' => 'required|email', 'password' => 'required'],
        )->errors();

        $this->assertSame('Medan e-mel mesti merupakan alamat e-mel yang sah.', $errors->first('email'));
        $this->assertSame('Medan kata laluan diperlukan.', $errors->first('password'));
    }

    public function test_malay_translations_cover_every_english_key(): void
    {
        foreach (['auth', 'pagination', 'passwords', 'validation'] as $group) {
            $en = $this->flatten(require lang_path("en/{$group}.php"));
            $ms = $this->flatten(require lang_path("ms/{$group}.php"));

            $this->assertSame([], array_values(array_diff($en, $ms)), "lang/ms/{$group}.php is missing keys");
        }
    }

    public function test_json_strings_are_translated(): void
    {
        $this->assertSame('Tindakan ini tidak dibenarkan.', __('This action is unauthorized.'));
    }

    /**
     * @param  array<string, mixed>  $lines
     * @return list<string>
     */
    private function flatten(array $lines, string $prefix = ''): array
    {
        $keys = [];

        foreach ($lines as $key => $value) {
            if (is_array($value) && ! in_array($key, ['attributes', 'custom'], true)) {
                $keys = [...$keys, ...$this->flatten($value, "{$prefix}{$key}.")];
            } else {
                $keys[] = $prefix.$key;
            }
        }

        return $keys;
    }
}
