<?php namespace Aero\Credits\Classes\Api;

class Scopes
{
    public const TRIALS = 'credits.trials.issue';

    public static function all(): array
    {
        return [
            self::TRIALS => 'Emitir y consultar cupones Trial para clientes de un sistema asociado',
        ];
    }
}
