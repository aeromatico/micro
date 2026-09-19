<?php namespace Aero\Sms\Classes\Api;

class Scopes
{
    public const SEND = 'sms.send';
    public const READ = 'sms.read';

    public static function all(): array
    {
        return [
            self::SEND => 'Enviar SMS (simples y masivos)',
            self::READ => 'Consultar mensajes, lotes y consumo',
        ];
    }
}
