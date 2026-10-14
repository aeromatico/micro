<?php namespace Aero\Community\Classes;

use Aero\Community\Models\Post;
use ApplicationException;
use BackendAuth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

/**
 * Soporte rápido: crea un reporte privado (error, ayuda o sugerencia) desde
 * cualquier pantalla, con la URL, el contexto del navegador y, si la persona
 * quiere, una captura. Es otra forma de abrir un ticket, pero apuntando a
 * corregir la pantalla desde donde se pidió.
 */
class Reporter
{
    public const MAX_SHOT_BYTES = 3 * 1024 * 1024;

    public static function create(array $data): Post
    {
        $user = BackendAuth::getUser();
        if (!$user) {
            throw new ApplicationException('Sesión no válida.');
        }

        $tenantId = CurrentTenant::id();
        if (!CurrentTenant::isAdmin() && !$tenantId) {
            throw new ApplicationException('No pudimos identificar tu negocio. Escríbenos desde el foro.');
        }

        $key = 'community-quick:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw new ApplicationException('Enviaste muchos reportes seguidos. Espera unos minutos.');
        }
        RateLimiter::hit($key, 3600);

        $type = in_array($data['type'] ?? '', ['bug', 'help', 'idea'], true) ? $data['type'] : 'help';
        $message = trim((string) ($data['message'] ?? ''));
        if (mb_strlen($message) < 5) {
            throw new ApplicationException('Cuéntanos un poco más (mínimo 5 caracteres).');
        }

        $area = trim(mb_substr((string) ($data['area'] ?? ''), 0, 150)) ?: null;
        $context = is_array($data['context'] ?? null) ? self::cleanContext($data['context']) : [];
        $pageUrl = mb_substr((string) ($data['url'] ?? ''), 0, 1000) ?: null;

        $post = new Post([
            'tenant_id'  => $tenantId,
            'user_id'    => $user->id,
            'type'       => $type,
            // Solo una sugerencia que la persona decide compartir sale a la comunidad.
            'visibility' => ($type === 'idea' && !empty($data['share'])) ? 'public' : 'private',
            'status'     => 'open',
            'title'      => trim(($area ? "[{$area}] " : '') . mb_substr($message, 0, 80)),
            'body'       => mb_substr($message, 0, 8000),
            'area'       => $area,
            'page_url'   => $pageUrl,
            'context'    => $context,
        ]);

        if (!empty($data['shot'])) {
            $post->screenshot = self::storeScreenshot((string) $data['shot']);
        }

        $post->save();

        return $post;
    }

    /** Solo PNG real y de tamaño razonable; se guarda en disco privado. */
    public static function storeScreenshot(string $dataUri): ?string
    {
        if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=\s]+)$#', $dataUri, $m)) {
            return null;
        }

        $binary = base64_decode($m[1], true);
        if ($binary === false || strlen($binary) > self::MAX_SHOT_BYTES || !str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            return null;
        }

        $path = 'community/shots/' . now()->format('Y/m') . '/' . bin2hex(random_bytes(12)) . '.png';
        Storage::disk('local')->put($path, $binary);

        return $path;
    }

    protected static function cleanContext(array $context): array
    {
        $out = [];
        foreach (['title', 'userAgent', 'viewport', 'language', 'element', 'breadcrumb'] as $k) {
            if (isset($context[$k]) && is_scalar($context[$k])) {
                $out[$k] = mb_substr((string) $context[$k], 0, 300);
            }
        }
        if (isset($context['errors']) && is_array($context['errors'])) {
            $out['errors'] = array_slice(array_map(fn ($e) => mb_substr((string) (is_scalar($e) ? $e : ''), 0, 300), $context['errors']), 0, 5);
        }

        return $out;
    }
}
