<?php namespace Aero\Sms\Classes\Drivers;

class DriverResult
{
    public function __construct(
        public readonly ?string $providerMessageId,
        public readonly string $status = 'sent',
    ) {
    }
}
