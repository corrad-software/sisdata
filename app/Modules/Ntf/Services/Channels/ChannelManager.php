<?php

namespace App\Modules\Ntf\Services\Channels;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class ChannelManager
{
    /** @var array<string, class-string<Channel>> */
    private const CHANNELS = [
        'dalam_sistem' => InAppChannel::class,
        'emel' => EmailChannel::class,
        'sms' => SmsChannel::class,
        'whatsapp' => WhatsAppChannel::class,
    ];

    public function __construct(private Container $container) {}

    public function channel(string $name): Channel
    {
        $class = self::CHANNELS[$name] ?? throw new InvalidArgumentException("Unknown channel {$name}");

        return $this->container->make($class);
    }
}
