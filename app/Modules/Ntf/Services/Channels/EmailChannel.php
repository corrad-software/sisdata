<?php

namespace App\Modules\Ntf\Services\Channels;

use App\Models\User;
use App\Modules\Ntf\Mail\NotifikasiMail;
use App\Modules\Ntf\Models\Mesej;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/** E-mail through Laravel Mail (MAIS gateway; Mailpit locally). SMTP acceptance = receipt. */
class EmailChannel implements Channel
{
    public function deliver(Mesej $mesej, User $to): bool
    {
        if (! filter_var($to->email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('EMEL_TIADA: recipient has no valid e-mail address');
        }

        Mail::to($to->email, $to->name)->send(new NotifikasiMail($mesej, $to));

        return true;
    }
}
