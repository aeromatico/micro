<?php namespace Aero\Chat;

use System\Classes\PluginBase;

/**
 * Capa de API para la PWA de mensajería (tema `whatsapp`). Los agentes son los
 * usuarios de backend del tenant: inician sesión con su login y reciben un
 * token propio, atado al tenant, que reemplaza a las API keys de servidor.
 *
 * Lee los modelos de Aero.Hello directamente; CRM y Pay se sumarán por eventos.
 */
class Plugin extends PluginBase
{
    public $require = ['Aero.Hello', 'Aero.Sites', 'Aero.Notify'];

    public function pluginDetails(): array
    {
        return [
            'name'        => 'Chat',
            'description' => 'API de la PWA de mensajería multiagente: login por tenant, bandeja, respuestas y delegación.',
            'author'      => 'Aero',
            'icon'        => 'icon-comments',
        ];
    }

    public function boot(): void
    {
        $this->app['router']->group([], function () {
            require __DIR__ . '/routes.php';
        });

        // Aero.Pay avisa cuando un QR se paga (webhook del banco o conciliación).
        \Event::listen('aero.pay.paymentReceived', function ($payment, $qrCode) {
            \Aero\Chat\Classes\ChargeSettler::handle($payment, $qrCode);
        });
    }
}
