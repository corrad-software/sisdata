<?php

namespace App\Modules\Adm\Services;

use App\Models\User;
use App\Modules\Adm\Contracts\ReportTemplates;
use App\Modules\Adm\Models\Templat;
use App\Modules\Adm\Models\TemplatVersi;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SRS-ADM-03: report templates — content with {{field}} placeholders,
 * declared dynamic fields, output format and full version history.
 */
class TemplatService implements ReportTemplates
{
    public function render(string $code, array $data): ?array
    {
        $templat = Templat::query()->where('kod', $code)->where('status', 'aktif')->first();

        return $templat ? [
            'format' => $templat->format_output,
            'versi' => $templat->versi,
            'kandungan' => self::fill($templat->kandungan, $data),
        ] : null;
    }

    /**
     * @param  array<string, scalar|null>  $data
     */
    public static function fill(string $content, array $data): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function (array $m) use ($data) {
            return array_key_exists($m[1], $data) ? e((string) $data[$m[1]]) : $m[0];
        }, $content);
    }

    /** @return list<string> */
    public static function placeholders(string $content): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $content, $m);

        return array_values(array_unique($m[1]));
    }

    /**
     * Every placeholder used must be a declared field.
     *
     * @param  list<string>  $fields
     *
     * @throws ValidationException
     */
    public function assertFields(string $content, array $fields): void
    {
        $unknown = array_diff(self::placeholders($content), $fields);
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'kandungan' => __('adm::adm.templat.unknown_fields', ['medan' => implode(', ', $unknown)]),
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $by): Templat
    {
        $this->assertFields($data['kandungan'], $data['medan'] ?? []);

        return DB::transaction(function () use ($data, $by) {
            $templat = Templat::query()->create([
                ...$data,
                'jenis' => $data['jenis'] ?? 'laporan',
                'versi' => 1,
                'status' => $data['status'] ?? 'aktif',
                'dikemas_kini_oleh' => $by?->id,
            ]);
            $this->snapshot($templat, $by);

            return $templat;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Templat $templat, array $data, ?User $by): Templat
    {
        $this->assertFields($data['kandungan'] ?? $templat->kandungan, $data['medan'] ?? $templat->medan ?? []);

        return DB::transaction(function () use ($templat, $data, $by) {
            $templat->fill($data);
            $contentChanged = $templat->isDirty(['kandungan', 'medan', 'format_output']);
            if ($contentChanged) {
                $templat->versi = $templat->versi + 1;
            }
            $templat->dikemas_kini_oleh = $by?->id;
            $templat->save();

            if ($contentChanged) {
                $this->snapshot($templat, $by);
            }

            return $templat;
        });
    }

    /** Bring back an older version as a NEW version (history stays linear). */
    public function restore(Templat $templat, TemplatVersi $version, ?string $justification, ?User $by): Templat
    {
        return $this->update($templat, [
            'kandungan' => $version->kandungan,
            'medan' => $version->medan,
            'format_output' => $version->format_output,
            'justifikasi' => $justification,
        ], $by);
    }

    private function snapshot(Templat $templat, ?User $by): void
    {
        TemplatVersi::query()->create([
            'id_templat' => $templat->id,
            'versi' => $templat->versi,
            'format_output' => $templat->format_output,
            'kandungan' => $templat->kandungan,
            'medan' => $templat->medan,
            'dicipta_oleh' => $by?->id,
        ]);
    }
}
