<?php namespace Aero\Tracking\Classes\Feeds;

use Aero\Tracking\Models\Feed;

/**
 * Un proveedor externo con enlace público de rastreo. El driver es el ÚNICO
 * que habla con el proveedor, y solo contra hosts fijos suyos: la URL del
 * usuario se usa para extraer un id, nunca se consulta tal cual (sin SSRF).
 */
interface FeedDriver
{
    public function key(): string;

    public function label(): string;

    /** Devuelve ['external_id' => ..., 'url' => normalizada] o null si la URL no es de este proveedor. */
    public function match(string $url): ?array;

    /**
     * Instantánea normalizada:
     *  phase: queued|confirmed|at_origin|on_the_way|delivered|cancelled
     *  title, eta_text, delay_label, message, lat, lng, position_at (Carbon|null),
     *  origin [lat,lng]|null, destination [lat,lng]|null, interval (segundos).
     *
     * @throws FeedGoneException si el enlace ya no existe o venció
     * @throws \RuntimeException para fallos transitorios (se reintenta)
     */
    public function poll(Feed $feed): array;
}
