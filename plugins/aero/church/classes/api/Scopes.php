<?php namespace Aero\Church\Classes\Api;

class Scopes
{
    public const MEMBERS_READ = 'church.members.read';
    public const EVENTS_READ = 'church.events.read';
    public const VISITS_READ = 'church.visits.read';
    public const VISITS_WRITE = 'church.visits.write';

    public static function all(): array
    {
        return [
            self::MEMBERS_READ => 'Consultar miembros (datos básicos; el contacto solo de adultos que aceptaron mensajes)',
            self::EVENTS_READ  => 'Consultar eventos de la iglesia',
            self::VISITS_READ  => 'Consultar visitantes (sin datos de contacto)',
            self::VISITS_WRITE => 'Registrar visitantes desde un formulario o chatbot externo',
        ];
    }
}
