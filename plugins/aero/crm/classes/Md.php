<?php namespace Aero\Crm\Classes;

use October\Rain\Support\Facades\Markdown;

/**
 * Markdown de tickets → HTML. parseSafe elimina HTML crudo y scripts: lo que
 * escriben tenants y clientes lo leen agentes, así que nunca se confía en él.
 */
class Md
{
    public static function html(?string $text): string
    {
        $text = trim((string) $text);

        return $text === '' ? '' : '<div class="ticket-md">' . Markdown::parseSafe($text) . '</div>' . self::style();
    }

    /**
     * Estilo del globo de un mensaje: translúcido y sin colores fijos de texto, para que se
     * lea igual en tema claro y oscuro. kind: agent | customer | internal.
     */
    public static function bubbleStyle(string $kind): string
    {
        $rgb = ['customer' => '99,102,241', 'internal' => '234,179,8'][$kind] ?? '148,163,184';

        return "margin-bottom:10px;padding:8px 12px;border-radius:6px;background:rgba($rgb,.14);border:1px solid rgba($rgb,.35);border-left:3px solid rgba($rgb,.8)";
    }

    /** Estilos mínimos (una sola vez por request) para listas, código y citas. */
    protected static function style(): string
    {
        static $done = false;
        if ($done) {
            return '';
        }
        $done = true;

        return '<style>.ticket-md p{margin:0 0 .6em}.ticket-md>:last-child{margin-bottom:0}.ticket-md ul,.ticket-md ol{margin:0 0 .6em 1.2em;padding:0}.ticket-md ul{list-style:disc}.ticket-md ol{list-style:decimal}'
            . '.ticket-md code{background:rgba(0,0,0,.07);padding:1px 4px;border-radius:3px;font-size:.92em}.ticket-md pre{background:#0f172a;color:#e2e8f0;padding:8px 10px;border-radius:6px;overflow:auto;margin:0 0 .6em}.ticket-md pre code{background:none;padding:0;color:inherit}'
            . '.ticket-md blockquote{border-left:3px solid rgba(148,163,184,.6);margin:0 0 .6em;padding-left:10px;opacity:.8}.ticket-md a{text-decoration:underline}.ticket-md img{max-width:100%}.ticket-md h1,.ticket-md h2,.ticket-md h3{font-size:1.05em;font-weight:600;margin:.4em 0}</style>';
    }
}
