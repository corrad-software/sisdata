<?php

namespace App\Modules\Cki\Services;

use App\Models\User;
use App\Modules\Cki\Support\Akses;
use App\Modules\Cki\Support\DryRun;
use App\Modules\Cki\Support\Kod;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Annual bill import from an Excel / CSV file (e.g. the provider's bill
 * listing, or a district office's statement) — row-by-row validation, error
 * list and an accepted / rejected summary (SRS-DAT-03). Rows are matched to
 * the register by tax type + account number; bills are recorded with
 * sumber_rekod = import. `dry_run` validates without saving.
 */
class ImportService
{
    public const KOLUM = ['jenis_cukai', 'no_akaun_cukai', 'tahun_taksiran', 'no_bil', 'amaun_pokok', 'amaun_denda', 'tarikh_bil', 'tarikh_akhir', 'status_bayaran', 'tarikh_bayar', 'no_resit'];

    public function __construct(private BilService $bil, private Akses $akses) {}

    /** @return array{jumlah_baris: int, diterima: int, ditolak: int, ralat: list<array{baris: int, mesej: string}>, simpan: bool} */
    public function import(UploadedFile $file, User $user, bool $dryRun = false): array
    {
        try {
            $rows = IOFactory::load($file->getRealPath())->getActiveSheet()->toArray(null, true, false, false);
        } catch (Throwable) {
            throw ValidationException::withMessages(['fail' => __('cki::cki.import.fail_tidak_sah')]);
        }
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($rows) ?? []);
        $missing = array_diff(['jenis_cukai', 'no_akaun_cukai', 'tahun_taksiran', 'no_bil', 'amaun_pokok', 'tarikh_akhir'], $header);
        if ($missing !== []) {
            throw ValidationException::withMessages(['fail' => __('cki::cki.import.kolum_tiada', ['kolum' => implode(', ', $missing)])]);
        }

        $out = ['jumlah_baris' => 0, 'diterima' => 0, 'ditolak' => 0, 'ralat' => [], 'simpan' => ! $dryRun];
        foreach ($rows as $i => $raw) {
            if (array_filter($raw, fn ($v) => $v !== null && $v !== '') === []) {
                continue;
            }
            $out['jumlah_baris']++;
            $line = $i + 2;
            $r = [];
            foreach ($header as $c => $name) {
                if (in_array($name, self::KOLUM, true)) {
                    $r[$name] = $this->cell($raw[$c] ?? null, $name);
                }
            }
            $v = Validator::make($r, [
                'jenis_cukai' => 'required|in:'.implode(',', array_keys(Kod::JENIS_CUKAI)),
                'no_akaun_cukai' => 'required|string|max:60',
                'tahun_taksiran' => 'required|integer|min:1990|max:2100',
                'no_bil' => 'required|string|max:50',
                'amaun_pokok' => 'required|numeric|min:0',
                'amaun_denda' => 'nullable|numeric|min:0',
                'tarikh_bil' => 'nullable|date',
                'tarikh_akhir' => 'required|date',
                'status_bayaran' => 'nullable|in:belum_bayar,dibayar',
                'tarikh_bayar' => 'nullable|date',
                'no_resit' => 'nullable|string|max:60',
            ]);
            if ($v->fails()) {
                $out['ditolak']++;
                $out['ralat'][] = ['baris' => $line, 'mesej' => implode(' ', $v->errors()->all())];

                continue;
            }
            $o = $this->akses->obligasi($user)->where('jenis_cukai', $r['jenis_cukai'])->where('no_akaun_cukai', $r['no_akaun_cukai'])->first();
            if ($o === null) {
                $out['ditolak']++;
                $out['ralat'][] = ['baris' => $line, 'mesej' => __('cki::cki.import.akaun_tiada', ['akaun' => $r['no_akaun_cukai']])];

                continue;
            }
            try {
                if ($dryRun) {
                    DB::transaction(function () use ($o, $r, $user) {
                        $this->bil->cipta($o, $r, $user, 'import');
                        throw new DryRun;
                    });
                } else {
                    $this->bil->cipta($o, $r, $user, 'import');
                }
                $out['diterima']++;
            } catch (DryRun) {
                $out['diterima']++;
            } catch (ValidationException $e) {
                $out['ditolak']++;
                $out['ralat'][] = ['baris' => $line, 'mesej' => implode(' ', array_merge(...array_values($e->errors())))];
            }
        }

        return $out;
    }

    private function cell(mixed $v, string $name): mixed
    {
        if ($v === null) {
            return null;
        }
        if (in_array($name, ['tarikh_bil', 'tarikh_akhir', 'tarikh_bayar'], true)) {
            if (is_numeric($v)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $v))->format('Y-m-d');
            }
            $s = trim((string) $v);
            if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $s, $m)) {
                return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            }

            return $s;
        }

        return is_string($v) ? trim($v) : (is_float($v) ? number_format($v, 2, '.', '') : (string) $v);
    }
}
