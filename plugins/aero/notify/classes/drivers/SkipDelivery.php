<?php namespace Aero\Notify\Classes\Drivers;

/**
 * Lanzada por un driver cuando no hay a dónde entregar (p. ej. usuario sin
 * dispositivos suscritos). No es un fallo: la entrega se marca 'skipped' con
 * el motivo y no se reintenta.
 */
class SkipDelivery extends \RuntimeException
{
}
