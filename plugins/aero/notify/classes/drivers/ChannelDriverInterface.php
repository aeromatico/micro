<?php namespace Aero\Notify\Classes\Drivers;

interface ChannelDriverInterface
{
    /**
     * Envía un mensaje ya renderizado a una dirección concreta. Devuelve el
     * id externo del mensaje (proveedor) cuando existe, o cadena vacía.
     *
     * @throws \RuntimeException si el canal no puede entregar (sin
     *         credenciales, driver no instalado, etc). Notify::fire() lo
     *         captura y marca la entrega como failed sin abortar el resto.
     */
    public function send(string $address, ?string $subject, string $body, array $context = []): string;
}
