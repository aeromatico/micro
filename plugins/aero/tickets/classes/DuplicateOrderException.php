<?php namespace Aero\Tickets\Classes;

use Aero\Tickets\Models\Order;

/** El comprador ya tiene una orden en curso para este evento. */
class DuplicateOrderException extends \ApplicationException
{
    public function __construct(public readonly Order $existing, string $message)
    {
        parent::__construct($message);
    }
}
