<?php

namespace App\Modules\Dms\Http\Controllers;

use App\Modules\Dms\Contracts\DocumentRejected;
use App\Modules\Dms\Http\Resources\DokumenPresenter;
use App\Modules\Dms\Models\DokumenVersi;
use App\Modules\Dms\Services\Access\DocumentAccess;
use App\Modules\Dms\Services\Upload\DocumentIngestor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** SRS-DMS-14: version history, new versions (never overwriting) and comparison. */
class VersiController extends DmsController
{
    public function __construct(DocumentAccess $access, private DokumenPresenter $presenter)
    {
        parent::__construct($access);
    }

    public function index(Request $request, string $dokumen): JsonResponse
    {
        $doc = $this->visible($dokumen, $this->user($request));

        return response()->json([
            'data' => $doc->versi()->with('pemuatNaik:id,name')->get()->map(fn (DokumenVersi $v) => $this->presenter->version($v))->values(),
        ]);
    }

    public function store(Request $request, DocumentIngestor $ingestor, string $dokumen): JsonResponse
    {
        $doc = $this->visible($dokumen, $this->user($request));
        if (! in_array($doc->status, ['aktif', 'tamat'], true)) {
            throw new HttpException(409, __('dms::mesej.diarkib_tiada_versi'));
        }

        $data = $request->validate([
            'fail' => ['required', 'file'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'id_transaksi' => ['nullable', 'uuid'],
        ]);

        try {
            $version = $ingestor->addVersion($doc, $request->file('fail'), $this->user($request), $data['catatan'] ?? null, $data['id_transaksi'] ?? null);
        } catch (DocumentRejected $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'DOCUMENT_REJECTED', 'sebab' => $e->reason], 422);
        }

        return response()->json(['data' => $this->presenter->version($version->load('pemuatNaik:id,name'))], 201);
    }

    /** Field-by-field diff of the metadata snapshot plus file facts between two versions. */
    public function compare(Request $request, string $dokumen): JsonResponse
    {
        $doc = $this->visible($dokumen, $this->user($request));
        $data = $request->validate([
            'dari' => ['required', 'integer', 'min:1'],
            'ke' => ['required', 'integer', 'min:1'],
        ]);

        $versions = $doc->versi()->with('pemuatNaik:id,name')->whereIn('no_versi', [$data['dari'], $data['ke']])->get()->keyBy('no_versi');
        $from = $versions->get($data['dari']);
        $to = $versions->get($data['ke']);
        if ($from === null || $to === null) {
            throw new HttpException(404, __('Record not found.'));
        }

        $a = $this->flatten([
            'nama_fail' => $from->nama_fail, 'saiz_bait' => $from->saiz_bait, 'cincangan_fail' => $from->cincangan_fail,
            'jenis_mime' => $from->jenis_mime, ...$from->metadata_snapshot,
        ]);
        $b = $this->flatten([
            'nama_fail' => $to->nama_fail, 'saiz_bait' => $to->saiz_bait, 'cincangan_fail' => $to->cincangan_fail,
            'jenis_mime' => $to->jenis_mime, ...$to->metadata_snapshot,
        ]);

        $fields = [];
        foreach (array_unique([...array_keys($a), ...array_keys($b)]) as $field) {
            $fields[] = [
                'medan' => $field,
                'dari' => $a[$field] ?? null,
                'ke' => $b[$field] ?? null,
                'berubah' => ($a[$field] ?? null) !== ($b[$field] ?? null),
            ];
        }

        return response()->json(['data' => [
            'dari' => $this->presenter->version($from),
            'ke' => $this->presenter->version($to),
            'kandungan_sama' => $from->cincangan_fail === $to->cincangan_fail,
            'medan' => $fields,
        ]]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string|null>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $name = $prefix.$key;
            if (is_array($value) && $value !== [] && ! array_is_list($value)) {
                $out += $this->flatten($value, $name.'.');
            } else {
                $out[$name] = is_array($value) ? implode(', ', array_map('strval', $value)) : ($value === null ? null : (is_bool($value) ? ($value ? 'Ya' : 'Tidak') : (string) $value));
            }
        }

        return $out;
    }
}
