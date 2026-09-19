<?php namespace Aero\Sms\Classes\Drivers;

use RuntimeException;

class SmsDriverException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $providerCode = null, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }
}
