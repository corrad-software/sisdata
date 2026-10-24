<?php

namespace App\Modules\Pjk\Mail;

use App\Modules\Pjk\Support\Wang;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * SRS-PJK-17: official receipt e-mailed to the payer (an external party, not
 * a SISDATA user — outside MOD-NTF's user-recipient model).
 */
class ResitMail extends Mailable
{
    public function __construct(public string $noResit, public string $amaun, public ?string $pdf) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) __('pjk::pjk.resit.subjek', ['no' => $this->noResit]));
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>'.e((string) __('pjk::pjk.resit.badan', ['no' => $this->noResit, 'amaun' => Wang::rm($this->amaun)])).'</p>');
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return $this->pdf === null ? [] : [Attachment::fromData(fn () => (string) $this->pdf, str_replace('/', '-', $this->noResit).'.pdf')->withMime('application/pdf')];
    }
}
