<?php

namespace App\Modules\Ntf\Contracts;

use App\Models\User;

/**
 * The one notification engine (SRS-NTF-02). Modules never send mail or write
 * notifications themselves — they call this with a registered type code and
 * the data for its template. MOD-NTF picks channels (user preferences vs
 * mandatory), renders the Malay template, queues delivery, retries, dedupes
 * and audits. Content must never carry Sulit/Rahsia data — pass a summary and
 * a link; the link target enforces its own access (SRS-NTF-19).
 */
interface Notifier
{
    /**
     * @param  User|iterable<User>  $recipients
     * @param  array<string, scalar|null>  $data  template fields, e.g. ['no_lot' => 'PT 1234', 'baki_hari' => 7]
     * @param  string|null  $link  frontend path, e.g. "/admin/wfe/tugasan/123"
     * @param  string|null  $dedupeKey  same key + recipient inside the type's quiet period is sent once (SRS-NTF-22)
     *
     * Recipients may be empty: the type's admin-configured recipient rules
     * (role / permission / bahagian / daerah) are always added. Only fields
     * declared at registration are kept; delivery is queued, so this returns
     * before anything is sent.
     *
     * @throws SensitiveDataRefused when $data carries a field flagged Sulit/Rahsia
     * @throws \InvalidArgumentException unknown type, non-scalar value, or a link that is not an internal path
     */
    public function send(string $type, User|iterable $recipients, array $data = [], ?string $link = null, ?string $dedupeKey = null): void;
}
