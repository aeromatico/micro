<?php namespace Aero\Credits\Classes\Exceptions;

use Exception;

/**
 * Lanzada por Credits::charge() cuando el saldo del tenant en el color
 * requerido no alcanza y Settings::blocksOnInsufficientBalance() es true.
 * Los controladores que ya envuelven sus llamadas de IA en try/catch
 * (ej. Aero\AiFields\Http\Controllers\AiController) la capturan como
 * cualquier otra excepción y la muestran al usuario tal cual.
 */
class InsufficientCreditsException extends Exception
{
    public function __construct(
        public readonly string $creditTypeCode,
        public readonly int $required,
        public readonly int $available,
    ) {
        parent::__construct(
            "Saldo insuficiente ({$creditTypeCode}): se necesitan {$required} créditos, disponibles {$available}."
        );
    }
}
