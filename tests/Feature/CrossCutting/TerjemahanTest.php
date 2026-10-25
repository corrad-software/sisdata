<?php

namespace Tests\Feature\CrossCutting;

use Illuminate\Support\Arr;
use Tests\TestCase;

/** SRS-GEN-09: every module message has a non-empty Malay translation. */
class TerjemahanTest extends TestCase
{
    public function test_SRS_GEN_09_every_module_message_key_has_a_malay_translation(): void
    {
        $missing = [];
        foreach (glob(base_path('app/Modules/*/lang/en/*.php')) ?: [] as $en) {
            $ms = str_replace('/lang/en/', '/lang/ms/', $en);
            $malay = is_file($ms) ? Arr::dot(require $ms) : [];
            foreach (Arr::dot(require $en) as $key => $value) {
                if (! is_string($value)) {
                    continue;
                }
                if (! isset($malay[$key]) || trim((string) $malay[$key]) === '') {
                    $missing[] = str_replace(base_path().'/', '', $ms).': '.$key;
                }
            }
        }

        $this->assertSame([], $missing);
    }
}
