<?php namespace Aero\Community\Classes;

use Aero\Community\Models\Post;
use BackendAuth;

/**
 * Chat con el equipo desde la pantalla de agradecimiento. Usa el Livechat de
 * la plataforma (la bandeja «Soporte plataforma»). Los usuarios del panel ya
 * están registrados: el widget no pide datos, la identidad va firmada.
 * Dependencia blanda: sin Livechat o con el chat apagado devuelve null y la
 * interfaz simplemente no muestra el botón.
 */
class ChatBridge
{
    public static function forPost(Post $post): ?array
    {
        $user = BackendAuth::getUser();
        if (!$user || !class_exists(\Aero\Livechat\Models\Inbox::class) || !class_exists(\Aero\Livechat\Classes\IdentityToken::class)) {
            return null;
        }

        try {
            // La mesa de soporte es la bandeja del tenant master (o la global sin tenant).
            $master = class_exists(\Aero\Sites\Classes\PlatformServicesBlock::class)
                ? \Aero\Sites\Classes\PlatformServicesBlock::masterTenantId()
                : null;

            $inbox = \Aero\Livechat\Models\Inbox::where('is_active', true)
                ->where(fn ($q) => $master ? $q->where('tenant_id', $master)->orWhereNull('tenant_id') : $q->whereNull('tenant_id'))
                ->orderByRaw('tenant_id IS NULL DESC')->orderBy('id')->first();
            if (!$inbox || !$inbox->isServing()) {
                return null;
            }

            $config = \Aero\Livechat\Models\ChannelSettings::widgetConfig($inbox->tenant_id ? (int) $inbox->tenant_id : null);
            if (empty($config['enabled']) || !in_array($config['mode'] ?? 'livechat', ['livechat', 'both'], true)) {
                return null;
            }

            $tenant = CurrentTenant::name($post->tenant_id);
            $name = trim($user->first_name . ' ' . $user->last_name) ?: $user->login;

            return [
                'script'   => url('/plugins/aero/livechat/assets/js/widget.js') . '?v=' . (@filemtime(plugins_path('aero/livechat/assets/js/widget.js')) ?: 1),
                'key'      => $inbox->widget_key,
                'base'     => rtrim(url('/'), '/'),
                'identity' => \Aero\Livechat\Classes\IdentityToken::make([
                    'name'    => $tenant ? "{$name} ({$tenant})" : $name,
                    'email'   => $user->email,
                    'user_id' => $user->id,
                    'ticket'  => "Ticket #{$post->id} — {$post->title}\n" . \Backend::url('aero/community/posts/update/' . $post->id),
                ]),
            ];
        } catch (\Throwable $e) {
            \Log::warning('Aero.Community chat: ' . $e->getMessage());

            return null;
        }
    }
}
