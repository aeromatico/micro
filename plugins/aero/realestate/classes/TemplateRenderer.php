<?php namespace Aero\Realestate\Classes;

/**
 * Rellena una plantilla con {{ variable }}. Renderizador propio y deliberadamente mínimo (no es Twig): las plantillas
 * las edita cada inmobiliaria, así que NO se ejecuta código; solo se sustituyen variables de una lista cerrada.
 * Vacío → línea para completar a mano; variable desconocida → marca visible en rojo para que no pase inadvertida.
 */
class TemplateRenderer
{
    public const BLANK = '________________';

    public static function render(string $template, array $vars): string
    {
        return preg_replace_callback('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i', function ($m) use ($vars) {
            $key = strtolower($m[1]);
            if (!array_key_exists($key, $vars)) {
                return '<span style="color:#c0392b;background:#fdecea">[variable desconocida: ' . e($key) . ']</span>';
            }
            $value = $vars[$key];
            if ($key === 'extra_clauses') {
                return $value === null || trim((string) $value) === '' ? '' : '<h3>CLÁUSULAS ADICIONALES</h3><p>' . nl2br(e((string) $value)) . '</p>';
            }

            return $value === null || trim((string) $value) === '' ? self::BLANK : e((string) $value);
        }, $template);
    }

    /** Variables que no existen en la lista del contexto (para avisar al editar una plantilla). */
    public static function unknown(string $template, array $known): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i', $template, $m);

        return array_values(array_diff(array_unique(array_map('strtolower', $m[1])), $known));
    }

    /** Lista de variables disponibles con su descripción (se muestra en la pantalla de plantillas). */
    public static function help(): array
    {
        return [
            'Partes' => ['owner.name', 'owner.document', 'owner.address', 'owner.civil_status', 'owner.nationality', 'client.name', 'client.document', 'client.address', 'client.civil_status', 'client.nationality', 'agent.name', 'business_name'],
            'Inmueble' => ['property.title', 'property.code', 'property.type', 'property.address', 'property.zone', 'property.city', 'property.department', 'property.land_area', 'property.built_area', 'folio_real'],
            'Condiciones' => ['amount', 'amount_words', 'total_price', 'total_price_words', 'deposit', 'deposit_words', 'currency', 'term_months', 'start_date', 'end_date'],
            'Datos del documento' => ['code', 'place', 'today', 'extra_clauses', 'agent_line'],
        ];
    }

    public static function known(): array
    {
        return array_merge(...array_values(self::help()));
    }
}
