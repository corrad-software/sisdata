<?php

namespace App\Modules\Ntf\Services;

use App\Modules\Ntf\Contracts\Reminders;
use App\Modules\Ntf\Contracts\ReminderSource;
use InvalidArgumentException;

class ReminderRegistry implements Reminders
{
    /** @var list<class-string<ReminderSource>> */
    private array $sources = [];

    public function register(string $source): void
    {
        if (! is_subclass_of($source, ReminderSource::class)) {
            throw new InvalidArgumentException("{$source} must implement ".ReminderSource::class);
        }

        if (! in_array($source, $this->sources, true)) {
            $this->sources[] = $source;
        }
    }

    /** @return list<class-string<ReminderSource>> */
    public function sources(): array
    {
        return $this->sources;
    }
}
