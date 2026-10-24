<?php

namespace App\Modules\Ntf\Mail;

use App\Models\User;
use App\Modules\Ntf\Models\Mesej;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

/** Periodic digest of held Biasa/Maklumat notifications (SRS-NTF-20). */
class RingkasanMail extends Mailable
{
    use Queueable;

    /** @param Collection<int, Mesej> $mesej */
    public function __construct(public Collection $mesej, public User $penerima) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Ringkasan notifikasi ('.$this->mesej->count().') — SISDATA');
    }

    public function content(): Content
    {
        $origin = rtrim((string) (config('cors.allowed_origins.0') ?: config('app.url')), '/');

        return new Content(
            view: 'ntf::mail.ringkasan',
            text: 'ntf::mail.ringkasan-teks',
            with: [
                'senarai' => $this->mesej,
                'penerima' => $this->penerima,
                'pautanPeti' => $origin.'/admin/notifikasi',
            ],
        );
    }
}
