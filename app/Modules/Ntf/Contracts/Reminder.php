<?php

namespace App\Modules\Ntf\Contracts;

use App\Models\User;

/**
 * One due reminder returned by a ReminderSource, e.g. one lease expiring in
 * exactly N days. $key identifies the underlying record so the same reminder
 * is never sent twice for the same day offset.
 */
final class Reminder
{
    /**
     * @param  string  $key  e.g. "kontrak_pajakan:123"
     * @param  User|iterable<User>  $recipients  may be empty when the type's recipient rules decide
     * @param  array<string, scalar|null>  $data  template fields (never Sulit/Rahsia values)
     */
    public function __construct(
        public readonly string $key,
        public readonly User|iterable $recipients,
        public readonly array $data = [],
        public readonly ?string $link = null,
    ) {}
}
