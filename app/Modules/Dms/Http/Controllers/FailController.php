<?php

namespace App\Modules\Dms\Http\Controllers;

use App\Modules\Dms\Models\DokumenVersi;
use App\Modules\Dms\Services\Access\DocumentAccess;
use App\Modules\Dms\Services\DmsSettings;
use App\Modules\Dms\Services\DocumentFiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Signed, short-lived, user-bound links (SRS Modul 11 security control:
 * "URL muat turun bertempoh"). The link is useless to anyone else: it needs
 * the same logged-in user, and access is re-checked when it is opened.
 */
class FailController extends DmsController
{
    public function __construct(DocumentAccess $access, private DocumentFiles $files, private DmsSettings $settings)
    {
        parent::__construct($access);
    }

    public function link(Request $request, string $dokumen): JsonResponse
    {
        $user = $this->user($request);
        $doc = $this->visible($dokumen, $user);
        $data = $request->validate([
            'jenis' => ['required', Rule::in(DocumentFiles::KINDS)],
            'versi' => ['nullable', 'integer', 'min:1'],
        ]);
        if ($doc->status === 'dilupus') {
            throw new HttpException(410, __('dms::mesej.dilupus'));
        }

        $minutes = $this->settings->linkMinutes();

        return response()->json(['data' => [
            'url' => $this->files->signedUrl($doc, $user, $data['jenis'], $data['versi'] ?? null, $minutes),
            'tamat_pada' => now()->addMinutes($minutes)->toIso8601String(),
        ]]);
    }

    public function serve(Request $request, string $dokumen, string $jenis): Response
    {
        $user = $this->user($request);
        if ((int) $request->query('u') !== $user->id || ! in_array($jenis, DocumentFiles::KINDS, true)) {
            throw new HttpException(403, __('dms::mesej.pautan_tamat'));
        }

        $doc = $this->visible($dokumen, $user);
        $number = $request->query('versi') !== null ? (int) $request->query('versi') : $doc->versi_semasa;
        $version = DokumenVersi::query()->where('dokumen_id', $doc->id)->where('no_versi', $number)->first();
        if ($version === null) {
            throw new HttpException(404, __('Record not found.'));
        }

        return $this->files->serve($doc, $version, $user, $jenis);
    }
}
