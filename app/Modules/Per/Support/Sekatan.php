<?php

namespace App\Modules\Per\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * A case cannot progress (SRS-PER-05/16): 422 with a Malay summary and the
 * exact list of what is missing in errors.kekurangan.
 */
class Sekatan extends ValidationException
{
    /** @param list<string> $items */
    public function __construct(string $message, private array $items = [])
    {
        parent::__construct(Validator::make([], []));
        $this->message = $message;
    }

    /** @return array<string, array<int, string>> */
    public function errors(): array
    {
        return ['kekurangan' => $this->items === [] ? [$this->message] : $this->items];
    }
}
