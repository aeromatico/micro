<?php namespace Aero\Sms\Classes;

/**
 * Convierte texto pegado o un CSV en destinatarios con variables.
 *
 * Si la primera fila no empieza por un teléfono se toma como cabecera y sus
 * columnas (menos la primera) pasan a ser las variables de la plantilla:
 *
 *     telefono,nombre,monto
 *     71234567,Ana,50
 */
class RecipientParser
{
    /** @return array<int, array{to:string, vars:array}> */
    public static function parse(string $text): array
    {
        $rows = [];

        foreach (preg_split('/\R/u', trim($text)) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $delimiter = substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
            $rows[] = array_map('trim', str_getcsv($line, $delimiter, '"', ''));
        }

        if (!$rows) {
            return [];
        }

        $header = null;

        if (!PhoneNumber::normalize($rows[0][0] ?? '')) {
            $header = array_map(fn ($h) => mb_strtolower(trim($h)), array_shift($rows));
        }

        $seen = [];
        $recipients = [];

        foreach ($rows as $row) {
            $to = $row[0] ?? '';
            $normalized = PhoneNumber::normalize($to);
            $key = $normalized ?? $to;

            if (isset($seen[$key])) {
                continue; // el mismo número dos veces no debe cobrarse dos veces
            }

            $seen[$key] = true;
            $vars = [];

            foreach (array_slice($row, 1, null, true) as $i => $value) {
                $vars[$header[$i] ?? (string) $i] = $value;
            }

            $recipients[] = ['to' => $to, 'vars' => $vars];
        }

        return $recipients;
    }
}
