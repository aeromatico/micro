<?php namespace Aero\Sites\Classes\Ai;

use Aero\Sites\Models\Page;
use Aero\Sites\Models\Tenant;

/**
 * Handlers de las "AI tools" (categoría `site`) que Aero.Sites registra en
 * `Aero\Chatbots\Classes\AiToolRegistry` vía el evento
 * `aero.chatbots.registerAiTools` (ver Plugin::bootChatbotsIntegration()).
 *
 * Cada método recibe `$arguments` (lo que decidió mandar el LLM) y
 * `$tenantId` por separado — `$tenantId` SIEMPRE es el del bot que está
 * respondiendo, resuelto por ChatbotEngine, nunca algo que venga dentro de
 * `$arguments` aunque el LLM lo incluya.
 */
class ChatbotTools
{
    public static function getContactInfo(array $arguments, int $tenantId): array
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return ['error' => 'Tenant no encontrado.'];
        }

        $contact = $tenant->contactConfig;

        return [
            'business_name' => $tenant->name,
            'phone'         => $contact?->phone,
            'whatsapp'      => $contact?->whatsapp,
            'email'         => $contact?->contact_email,
            'address'       => $contact?->address,
        ];
    }

    public static function getLandingContent(array $arguments, int $tenantId): array
    {
        $slug = is_string($arguments['slug'] ?? null) ? trim($arguments['slug'], '/') : '';

        $page = Page::forTenant($tenantId)
            ->published()
            ->where('slug', $slug)
            ->first();

        if (!$page) {
            return ['error' => 'No se encontró esa página publicada.'];
        }

        $html = $page->puck_data ? (new HeadlessRenderer())->render($page->puck_data) : null;
        $text = $html ? static::htmlToPlainText($html) : $page->content;

        return [
            'title'   => $page->title,
            'content' => $text ?: 'La página no tiene contenido.',
        ];
    }

    /**
     * Convierte el HTML renderizado a texto plano simple: evita mandarle al
     * LLM markup completo (gasta tokens de más y no aporta nada útil para
     * responder por WhatsApp).
     */
    protected static function htmlToPlainText(string $html): string
    {
        $text = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $html);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n+/', "\n", $text);

        return trim($text);
    }
}
