<?php

namespace App\Modules\Ntf\Mail;

use App\Models\User;
use App\Modules\Ntf\Models\Mesej;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * Malay, MYDS-styled notification e-mail. Sent synchronously from inside
 * DeliverNotification (the job is already the queue). Carries only the
 * summary and a link (SRS-NTF-19).
 */
class NotifikasiMail extends Mailable
{
    use Queueable;

    public function __construct(public Mesej $mesej, public User $penerima) {}

    public function envelope(): Envelope
    {
        $prefix = match ($this->mesej->keutamaan) {
            'kritikal' => '[KRITIKAL] ',
            'tinggi' => '[PENTING] ',
            default => '',
        };

        return new Envelope(subject: $prefix.$this->mesej->subjek.' — SISDATA');
    }

    public function headers(): Headers
    {
        return new Headers(text: ['X-SISDATA-Notifikasi' => (string) $this->mesej->id]);
    }

    public function content(): Content
    {
        return new Content(
            view: 'ntf::mail.notifikasi',
            text: 'ntf::mail.notifikasi-teks',
            with: [
                'mesej' => $this->mesej,
                'penerima' => $this->penerima,
                'pautan' => $this->mesej->pautanPenuh(),
                'keutamaan' => __('ntf::ntf.keutamaan.'.$this->mesej->keutamaan),
            ],
        );
    }
}
