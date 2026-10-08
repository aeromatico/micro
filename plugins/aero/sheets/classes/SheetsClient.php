<?php namespace Aero\Sheets\Classes;

use Aero\Oauth\Classes\Oauth;
use Illuminate\Support\Facades\Http;

/**
 * Cliente mínimo de la API de Google Sheets v4, en nombre de un usuario del
 * backend (su cuenta de Google vinculada en aero/oauth).
 */
class SheetsClient
{
    const BASE = 'https://sheets.googleapis.com/v4/spreadsheets/';
    const SCOPE = 'https://www.googleapis.com/auth/spreadsheets';

    public function __construct(protected $user)
    {
    }

    /** Acepta una URL de Google Sheets o el ID a secas. */
    public static function spreadsheetId(string $input): ?string
    {
        $input = trim($input);

        if (preg_match('~/spreadsheets/d/([a-zA-Z0-9_-]+)~', $input, $m)) {
            return $m[1];
        }

        return preg_match('/^[a-zA-Z0-9_-]{20,}$/', $input) ? $input : null;
    }

    public static function a1(string $tab, string $range): string
    {
        return "'" . str_replace("'", "''", $tab) . "'!" . $range;
    }

    public static function colLetter(int $index): string  // 0 => A
    {
        $s = '';
        for ($i = $index + 1; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26) . $s;
        }

        return $s;
    }

    public static function colIndex(string $letters): int // A => 0
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    /** ¿La persona conectó Google con permiso de Sheets? */
    public static function connection($user): ?\Aero\Oauth\Models\Identity
    {
        $identity = $user ? Oauth::identityFor($user->id, 'google') : null;

        return $identity && $identity->hasScopes([self::SCOPE]) ? $identity : null;
    }

    protected function http()
    {
        $identity = self::connection($this->user);
        if (!$identity) {
            throw new SheetsException('Conecta tu cuenta de Google con permiso de Sheets en «Cuentas conectadas».');
        }

        try {
            $token = Oauth::accessToken($identity);
        } catch (\RuntimeException $e) {
            throw new SheetsException($e->getMessage());
        }

        return Http::withToken($token)->timeout(30)->acceptJson();
    }

    protected function check($response)
    {
        if ($response->successful()) {
            return $response->json();
        }

        $msg = $response->json('error.message') ?? ('HTTP ' . $response->status());
        throw new SheetsException(match ($response->status()) {
            401 => 'Google rechazó la sesión; reconecta tu cuenta.',
            403 => 'Sin permiso sobre esa hoja (' . $msg . ').',
            404 => 'No se encontró la hoja de cálculo.',
            429 => 'Google limitó las peticiones; espera un minuto.',
            default => $msg,
        });
    }

    /** @return array{title: string, sheets: string[]} */
    public function meta(string $id): array
    {
        $data = $this->check($this->http()->get(self::BASE . $id, ['fields' => 'properties.title,sheets.properties.title']));

        return [
            'title'  => $data['properties']['title'] ?? '',
            'sheets' => array_map(fn ($s) => $s['properties']['title'], $data['sheets'] ?? []),
        ];
    }

    public function get(string $id, string $range): array
    {
        $data = $this->check($this->http()->get(self::BASE . $id . '/values/' . rawurlencode($range), [
            'majorDimension'    => 'ROWS',
            'valueRenderOption' => 'FORMATTED_VALUE',
        ]));

        return $data['values'] ?? [];
    }

    /** @param array<string, array> $data rango => matriz de filas. RAW: nada se interpreta como fórmula. */
    public function batchUpdate(string $id, array $data): void
    {
        if (!$data) {
            return;
        }

        $this->check($this->http()->post(self::BASE . $id . '/values:batchUpdate', [
            'valueInputOption' => 'RAW',
            'data'             => collect($data)->map(fn ($values, $range) => ['range' => $range, 'majorDimension' => 'ROWS', 'values' => $values])->values()->all(),
        ]));
    }

    public function batchClear(string $id, array $ranges): void
    {
        if ($ranges) {
            $this->check($this->http()->post(self::BASE . $id . '/values:batchClear', ['ranges' => array_values($ranges)]));
        }
    }
}
