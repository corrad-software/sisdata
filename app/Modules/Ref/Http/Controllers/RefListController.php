<?php

namespace App\Modules\Ref\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ref\Models\Daerah;
use App\Modules\Ref\Models\JenisDokumen;
use App\Modules\Ref\Models\JenisHakMilik;
use App\Modules\Ref\Models\KategoriAduan;
use App\Modules\Ref\Models\KategoriGunaTanah;
use App\Modules\Ref\Models\KlasifikasiData;
use App\Modules\Ref\Models\Mukim;
use App\Modules\Ref\Models\Negeri;
use App\Modules\Ref\Models\Pbt;
use App\Modules\Ref\Models\RefModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * GET /api/v1/ref/{senarai}               current codes (?tarikh=YYYY-MM-DD for as-of, ?semua=1 incl. inactive)
 * GET /api/v1/ref/{senarai}/{kod}/versi   every version of one code (SRS-DAT-05 before/after)
 */
class RefListController extends Controller
{
    /** @var array<string, class-string<RefModel>> */
    public const LISTS = [
        'negeri' => Negeri::class,
        'daerah' => Daerah::class,
        'mukim' => Mukim::class,
        'pbt' => Pbt::class,
        'kategori-guna-tanah' => KategoriGunaTanah::class,
        'jenis-hak-milik' => JenisHakMilik::class,
        'jenis-dokumen' => JenisDokumen::class,
        'kategori-aduan' => KategoriAduan::class,
        'klasifikasi-data' => KlasifikasiData::class,
    ];

    public function index(Request $request, string $senarai): JsonResponse
    {
        $model = self::LISTS[$senarai];
        $request->validate([
            'tarikh' => ['nullable', 'date_format:Y-m-d'],
            'semua' => ['nullable', 'boolean'],
            'negeri' => ['nullable', 'regex:/^\d{2}$/'],
            'daerah' => ['nullable', 'regex:/^\d{4}$/'],
        ]);

        $date = $request->filled('tarikh') ? Carbon::createFromFormat('Y-m-d', (string) $request->query('tarikh')) : null;
        $query = $model::query()->sahPada($date ?: null);

        if (! $request->boolean('semua')) {
            $query->where('aktif', true);
        }

        $instance = new $model;
        foreach ($model::FILTERS as $param => $column) {
            if ($request->filled($param)) {
                $value = (string) $request->query($param);
                $instance->hasCast($column, ['array', 'json'])
                    ? $query->whereJsonContains($column, $value)
                    : $query->where($column, $value);
            }
        }

        $rows = $query->orderBy('susunan')->orderBy('kod')->get();

        return response()->json([
            'data' => $rows->map(fn (RefModel $r) => $r->toApi())->values(),
            'meta' => [
                'senarai' => $senarai,
                'bilangan' => $rows->count(),
                'tarikh' => ($date ?: now())->toDateString(),
            ],
        ]);
    }

    public function versions(string $senarai, string $kod): JsonResponse
    {
        $model = self::LISTS[$senarai];
        $rows = $model::query()->where('kod', $kod)->orderBy('versi')->get();

        abort_if($rows->isEmpty(), 404, __('ref::ref.kod_tiada', ['kod' => $kod]));

        return response()->json([
            'data' => $rows->map(fn (RefModel $r) => $r->toApi())->values(),
            'meta' => ['senarai' => $senarai, 'kod' => $kod, 'bilangan_versi' => $rows->count()],
        ]);
    }
}
