<?php

namespace App\Modules\Ast\Support;

use App\Modules\Adm\Contracts\Parameters;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Support\Facades\DB;

/**
 * SRS-AST-06/08: QR references and images. A reference is 12 random
 * Crockford-base32 characters — it does not expose any internal database id
 * (security control) and is unique for ever (aset_qr PK, never deleted).
 * The QR encodes a plain https URL to the public scan page, so any phone
 * camera opens it without an app. Images are drawn from the QR matrix as SVG
 * (screen) or PNG via GD (PDF labels) — no Imagick needed.
 */
class KodQr
{
    private const ABJAD = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function __construct(private Parameters $parameters) {}

    public function rujukanBaharu(): string
    {
        do {
            $ref = '';
            for ($i = 0; $i < 12; $i++) {
                $ref .= self::ABJAD[random_int(0, 31)];
            }
        } while (DB::table('aset_qr')->where('qr_ref', $ref)->exists() || DB::table('aset_fizikal')->where('qr_ref', $ref)->exists());

        return $ref;
    }

    public function pautan(string $ref): string
    {
        $base = trim((string) $this->parameters->get('ast.pautan_qr_asas', ''));
        if ($base === '') {
            // FRONTEND_URL (the only allowed CORS origin) — the scan page lives on the Next.js origin.
            $base = (string) (config('cors.allowed_origins')[0] ?? config('app.url'));
        }

        return rtrim($base, '/').'/aset/q/'.$ref;
    }

    /** @return list<list<bool>> dark modules, row-major */
    public function matriks(string $data): array
    {
        $m = Encoder::encode($data, ErrorCorrectionLevel::Q(), Encoder::DEFAULT_BYTE_MODE_ENCODING, null, false)->getMatrix();
        $w = $m->getWidth();
        $out = [];
        for ($y = 0; $y < $w; $y++) {
            $row = [];
            for ($x = 0; $x < $w; $x++) {
                $row[] = $m->get($x, $y) === 1;
            }
            $out[] = $row;
        }

        return $out;
    }

    public function svg(string $data, int $size = 240): string
    {
        $m = $this->matriks($data);
        $n = count($m) + 8;
        $path = '';
        foreach ($m as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $path .= 'M'.($x + 4).' '.($y + 4).'h1v1h-1z';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$n.' '.$n.'" width="'.$size.'" height="'.$size.'" shape-rendering="crispEdges" role="img" aria-label="Kod QR">'
            .'<rect width="100%" height="100%" fill="#fff"/><path fill="#000" d="'.$path.'"/></svg>';
    }

    /** PNG bytes (quiet zone of 4 modules), $scale pixels per module. */
    public function png(string $data, int $scale = 8): string
    {
        $m = $this->matriks($data);
        $n = (count($m) + 8) * $scale;
        $img = imagecreate($n, $n);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $n, $n, $white);
        foreach ($m as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    imagefilledrectangle($img, ($x + 4) * $scale, ($y + 4) * $scale, ($x + 5) * $scale - 1, ($y + 5) * $scale - 1, $black);
                }
            }
        }
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }
}
