<?php

namespace App\Modules\Rbc\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Aud\Contracts\AuditLogger;
use App\Modules\Rbc\Exports\TabularExport;
use App\Modules\Rbc\Services\AccessMatrix;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** SRS-RBC-10 / acceptance #8: the access matrix on screen, in Excel and PDF. */
class MatrixController extends Controller
{
    public function __construct(private AccessMatrix $matrix) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->matrix->build(! $request->boolean('aktif_sahaja'))]);
    }

    public function export(Request $request, AuditLogger $audit): Response
    {
        $format = $request->query('format') === 'pdf' ? 'pdf' : 'xlsx';
        $sections = self::sections($this->matrix, $this->matrix->build(! $request->boolean('aktif_sahaja')));

        $audit->record('eksport', 'rbc', 'matriks_capaian', null, meta: ['format' => $format]);

        return TabularExport::download(
            $format,
            'matriks-capaian-'.now()->format('Ymd-His'),
            __('rbc::rbc.matriks.tajuk'),
            $sections,
            __('rbc::rbc.dijana_oleh', ['nama' => $request->user()?->name, 'tarikh' => now()->format('d/m/Y H:i')]),
        );
    }

    /**
     * @param  array<string, mixed>  $matrix
     * @return list<array{tajuk: string, lajur: list<string>, baris: list<list<scalar|null>>}>
     */
    public static function sections(AccessMatrix $service, array $matrix): array
    {
        $roleCodes = array_column($matrix['peranan'], 'kod');
        $roleNames = array_column($matrix['peranan'], 'nama');

        $permissionRows = array_map(fn ($row) => [
            $row['modul'],
            $row['fungsi'],
            $row['tindakan'],
            $row['kunci'],
            ...array_map(fn ($code) => $row['peranan'][$code] ? '✓' : '', $roleCodes),
        ], $service->rows($matrix));

        $scopeRows = array_map(fn ($role) => [
            $role['nama'],
            $role['kod'],
            $role['status'] === 'aktif' ? __('rbc::rbc.aktif') : __('rbc::rbc.tidak_aktif'),
            (string) $role['bilangan_pengguna'],
            (string) count($matrix['pemberian'][$role['kod']] ?? []),
            AccessMatrix::scopeSummary($role['skop']),
        ], $matrix['peranan']);

        return [
            [
                'tajuk' => __('rbc::rbc.matriks.helaian_kebenaran'),
                'lajur' => [__('rbc::rbc.matriks.modul'), __('rbc::rbc.matriks.fungsi'), __('rbc::rbc.matriks.tindakan'), __('rbc::rbc.matriks.kunci'), ...$roleNames],
                'baris' => $permissionRows,
            ],
            [
                'tajuk' => __('rbc::rbc.matriks.helaian_skop'),
                'lajur' => [__('rbc::rbc.matriks.peranan'), __('rbc::rbc.matriks.kod'), __('rbc::rbc.matriks.status'), __('rbc::rbc.matriks.pengguna'), __('rbc::rbc.matriks.bilangan_kebenaran'), __('rbc::rbc.matriks.skop')],
                'baris' => $scopeRows,
            ],
        ];
    }
}
