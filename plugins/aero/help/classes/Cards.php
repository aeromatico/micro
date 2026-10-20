<?php namespace Aero\Help\Classes;

/**
 * Convierte una referencia (doc, guía, video, enlace) en el bloque que dibuja
 * el panel. Devuelve null si el contenido ya no existe o no está publicado:
 * la ayuda nunca muestra un enlace roto.
 */
class Cards
{
    /**
     * Las páginas públicas (documentación, guías, servicios) viven en el dominio
     * público de Market, no en el del panel, que sirve el sitio de los tenants.
     * El iframe de la guía sí se queda en el dominio actual: su CSP solo permite
     * incrustarlo desde el mismo origen.
     */
    public static function publicUrl(string $path): string
    {
        return rtrim((string) config('aero.services.public_url', 'https://market.com.bo'), '/') . '/' . ltrim($path, '/');
    }

    public static function doc(int $id): ?array
    {
        if (!Integrations::docs()) {
            return null;
        }

        $a = \Aero\Docs\Models\Article::platform()->published()->find($id);

        return $a ? ['type' => 'doc', 'title' => $a->title, 'summary' => $a->excerpt, 'url' => self::publicUrl('documentacion/' . $a->slug), 'minutes' => $a->reading_minutes] : null;
    }

    public static function docBySlug(string $slug): ?array
    {
        if (!Integrations::docs()) {
            return null;
        }
        $id = \Aero\Docs\Models\Article::platform()->published()->where('slug', $slug)->value('id');

        return $id ? self::doc((int) $id) : null;
    }

    public static function guide(int $id): ?array
    {
        if (!Integrations::docs()) {
            return null;
        }

        $g = \Aero\Docs\Models\Guide::platform()->published()->find($id);

        return $g ? ['type' => 'guide', 'title' => $g->title, 'summary' => 'Guía interactiva: practica el formulario paso a paso.', 'url' => self::publicUrl('guias/' . $g->slug), 'embed' => $g->embed_url] : null;
    }

    public static function guideBySlug(string $slug): ?array
    {
        if (!Integrations::docs()) {
            return null;
        }
        $id = \Aero\Docs\Models\Guide::platform()->published()->where('slug', $slug)->value('id');

        return $id ? self::guide((int) $id) : null;
    }

    /** Primer video del servicio construido sobre ese plugin (aero/services → plugin_links, relación built_with). */
    public static function video(?string $plugin): ?array
    {
        if (!$plugin || !Integrations::services()) {
            return null;
        }

        $code = 'aero.' . strtolower(preg_replace('/^aero\./i', '', $plugin));
        $service = \Aero\Services\Models\Service::active()->get()->first(
            fn ($s) => collect((array) $s->plugin_links)->contains(
                fn ($l) => strtolower((string) ($l['plugin'] ?? '')) === $code && ($l['relation'] ?? null) === 'built_with'
            )
        );
        $video = $service ? collect((array) $service->videos)->first(fn ($v) => !empty($v['youtube']) || !empty($v['media'])) : null;
        if (!$video) {
            return null;
        }

        $title = $video['title'] ?? $service->name;
        if (!empty($video['use_media']) && !empty($video['media'])) {
            $poster = !empty($video['poster']) ? \Media\Classes\MediaLibrary::url($video['poster']) : null;

            return ['type' => 'video', 'title' => $title, 'provider' => 'file', 'src' => \Media\Classes\MediaLibrary::url($video['media']), 'poster' => $poster, 'more' => self::publicUrl('plugin/' . $service->slug)];
        }

        if (!empty($video['youtube']) && preg_match('~(?:v=|youtu\.be/|embed/|shorts/)([A-Za-z0-9_-]{11})~', (string) $video['youtube'], $m)) {
            return ['type' => 'video', 'title' => $title, 'provider' => 'youtube', 'src' => 'https://www.youtube-nocookie.com/embed/' . $m[1], 'poster' => null, 'more' => self::publicUrl('plugin/' . $service->slug)];
        }

        return null;
    }

    /** Solo `backend:ruta`, rutas del sitio (/x) o https; todo lo demás se descarta. */
    public static function link(string $label, string $target): ?array
    {
        $target = trim($target);
        if (str_starts_with($target, 'backend:')) {
            $url = \Backend::url(ltrim(substr($target, 8), '/'));
        } elseif (preg_match('~^/(?!/)~', $target)) {
            $url = url($target);
        } elseif (preg_match('~^https://~i', $target)) {
            $url = $target;
        } else {
            return null;
        }

        return ['type' => 'link', 'label' => $label !== '' ? $label : 'Abrir', 'url' => $url];
    }
}
