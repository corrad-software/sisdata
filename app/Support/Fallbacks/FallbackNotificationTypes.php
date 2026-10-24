<?php

namespace App\Support\Fallbacks;

use App\Modules\Ntf\Contracts\NotificationTypes;

/** Stand-in for NotificationTypes until its owning module binds the real one. See App\Support\Fallbacks. */
class FallbackNotificationTypes implements NotificationTypes
{
    public function register(string $code, string $name, string $priority, string $subject, string $body, array $fields = [], bool $mandatory = false): void {}
}
