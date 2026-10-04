<?php namespace Aero\Sheets\Classes;

/**
 * Resuelve la "columna" de un mapeo a un índice 0-based. La columna puede ser
 * una letra (A, B, AA) o el texto de la cabecera (sin distinguir mayúsculas).
 */
class ColumnResolver
{
    public static function isLetters(string $ref): bool
    {
        return preg_match('/^[A-Za-z]{1,3}$/', trim($ref)) === 1;
    }

    /** @param string[] $headers fila de cabeceras */
    public static function index(string $ref, array $headers): ?int
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }

        // Una cabecera que se llama igual que una letra gana sobre la letra.
        foreach ($headers as $i => $h) {
            if (mb_strtolower(trim((string) $h)) === mb_strtolower($ref)) {
                return $i;
            }
        }

        return self::isLetters($ref) ? SheetsClient::colIndex($ref) : null;
    }
}
