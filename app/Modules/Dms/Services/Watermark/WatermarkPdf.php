<?php

namespace App\Modules\Dms\Services\Watermark;

use setasign\Fpdi\Fpdi;

/** FPDI + two small FPDF extensions: rotated text and transparency (ExtGState). */
class WatermarkPdf extends Fpdi
{
    /** @var array<string, array{parms: string, n?: int}> */
    private array $extGStates = [];

    public function rotatedText(float $x, float $y, string $text, float $angle): void
    {
        $rad = $angle * M_PI / 180;
        $c = cos($rad);
        $s = sin($rad);
        $cx = $x * $this->k;
        $cy = ($this->h - $y) * $this->k;
        $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        $this->Text($x, $y, $text);
        $this->_out('Q');
    }

    public function setAlpha(float $alpha): void
    {
        $key = sprintf('%.3F', $alpha);
        if (! isset($this->extGStates[$key])) {
            $this->extGStates[$key] = ['parms' => sprintf('/ca %s /CA %s /BM /Normal', $key, $key)];
        }
        $index = array_search($key, array_keys($this->extGStates), true) + 1;
        $this->_out(sprintf('/GS%d gs', $index));
    }

    protected function _putresources()
    {
        foreach ($this->extGStates as $key => $state) {
            $this->_newobj();
            $this->extGStates[$key]['n'] = $this->n;
            $this->_put('<</Type /ExtGState '.$state['parms'].'>>');
            $this->_put('endobj');
        }
        parent::_putresources();
    }

    protected function _putresourcedict()
    {
        parent::_putresourcedict();
        if ($this->extGStates === []) {
            return;
        }
        $this->_put('/ExtGState <<');
        $i = 1;
        foreach ($this->extGStates as $state) {
            $this->_put('/GS'.$i++.' '.($state['n'] ?? 0).' 0 R');
        }
        $this->_put('>>');
    }
}
