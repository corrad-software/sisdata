<?php

namespace Tests\Modules\Aud\Concerns;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;

/** Returned by AssertsAuditTrail::assertAudited() for fluent follow-up checks. */
class AuditedRecord
{
    /**
     * @param  Collection<int, \stdClass>  $changes
     */
    public function __construct(public object $row, public Collection $changes) {}

    public function assertFieldChanged(string $field, ?string $old, ?string $new): static
    {
        $change = $this->changes->firstWhere('nama_medan', $field);
        Assert::assertNotNull($change, "Medan {$field} tiada dalam perubahan audit.");
        Assert::assertSame($old, $change->nilai_asal, "Nilai asal {$field}");
        Assert::assertSame($new, $change->nilai_baharu, "Nilai baharu {$field}");

        return $this;
    }

    public function assertFieldMasked(string $field): static
    {
        $change = $this->changes->firstWhere('nama_medan', $field);
        Assert::assertNotNull($change, "Medan {$field} tiada dalam perubahan audit.");
        Assert::assertTrue((bool) $change->sensitif_ditapis, "Medan {$field} tidak ditanda sensitif.");
        foreach ([$change->nilai_asal, $change->nilai_baharu] as $value) {
            Assert::assertTrue($value === null || $value === config('aud.topeng'), "Nilai {$field} tidak ditapis: {$value}");
        }

        return $this;
    }

    public function assertFieldNotRecorded(string $field): static
    {
        Assert::assertNull($this->changes->firstWhere('nama_medan', $field), "Medan {$field} tidak sepatutnya direkodkan.");

        return $this;
    }

    /**
     * @param  array<string, mixed>  $expected  meta keys => values
     */
    public function assertMeta(array $expected): static
    {
        $meta = (array) json_decode((string) $this->row->meta, true);
        foreach ($expected as $key => $value) {
            Assert::assertSame($value, $meta[$key] ?? null, "meta.{$key}");
        }

        return $this;
    }

    public function get(string $column): mixed
    {
        return $this->row->{$column};
    }
}
