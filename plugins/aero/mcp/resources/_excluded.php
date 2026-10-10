<?php

/**
 * Modelos con tenant_id que NO se exponen por MCP, con el motivo. Todo modelo
 * de tenant debe estar aquí o en un archivo `resources/<plugin>.php`
 * (`php artisan mcp:audit` y `bin/verify-plugin` lo comprueban).
 *
 * Motivos habituales: credenciales/tokens, configuración, datos internos de
 * la plataforma, o "pendiente" (a revisar cuando se necesite).
 */
return [
    // credenciales y tokens
    \Aero\Hello\Models\ApiKey::class => 'credenciales (key_hash/key_encrypted)',
    \Aero\Hello\Models\Profile::class => 'credenciales de proveedor (zernio/wapi/voice api keys)',
    \Aero\Hello\Models\TenantSettings::class => 'configuración con secreto de webhook',
    \Aero\Hello\Models\WebhookDelivery::class => 'cargas útiles de webhook (datos de terceros, url)',
    \Aero\Pay\Models\ApiToken::class => 'tokens de API',
    \Aero\Pay\Models\EmailPaymentCandidate::class => 'cuerpo de correos bancarios sin depurar',
    \Aero\Pay\Models\WebhookEvent::class => 'cargas útiles de webhook bancario',
    \Aero\Pay\Models\QrboSettings::class => 'configuración',
    \Aero\Oauth\Models\Identity::class => 'tokens OAuth cifrados',
    \Aero\Sites\Models\ApiToken::class => 'tokens de API',
    \Aero\Sites\Models\TenantInvite::class => 'token de invitación',
    \Aero\Pos\Models\Cashier::class => 'pin_hash',
    \Aero\Pos\Models\DeviceToken::class => 'token de dispositivo',
    \Aero\Chat\Models\ChatToken::class => 'token de dispositivo',
    \Aero\Notify\Models\PushSubscription::class => 'endpoints/claves push del navegador',
    \Aero\Notify\Models\Channel::class => 'configuración de canal (config con credenciales)',
    \Aero\Notify\Models\InboxMessage::class => 'bandeja personal del usuario, no del tenant',

    // configuración (se podrá exponer de lectura con campos explícitos)
    \Aero\Crm\Models\CrmSettings::class => 'configuración',
    \Aero\Finance\Models\FinanceSettings::class => 'configuración',
    \Aero\Gym\Models\GymSettings::class => 'configuración',
    \Aero\Pos\Models\PosSettings::class => 'configuración',
    \Aero\Shop\Models\ShopSettings::class => 'configuración',
    \Aero\Shop\Models\PaymentGateway::class => 'configuración de pasarelas (config con credenciales)',
    \Aero\Shop\Models\TenantCurrency::class => 'configuración',
    \Aero\Livechat\Models\ChannelSettings::class => 'configuración',
    \Aero\Sites\Models\ContactConfig::class => 'configuración',
    \Aero\Sites\Models\SeoConfig::class => 'configuración',
    \Aero\Sites\Models\Layout::class => 'HTML a medida del sitio',
    \Aero\Docs\Models\TenantSetting::class => 'configuración',

    // internos / efímeros / pendientes
    \Aero\Credits\Models\CreditHold::class => 'reservas internas del libro',
    \Aero\Credits\Models\CreditInvitation::class => 'códigos canjeables',
    \Aero\Credits\Models\CreditInvitationQuota::class => 'gestión de plataforma',
    \Aero\Credits\Models\CreditPlanGrantLot::class => 'detalle interno del libro',
    \Aero\Shop\Models\ChatSession::class => 'carrito efímero del chat',
    \Aero\Sites\Models\AiGeneration::class => 'prompts y trazas de generación',
    \Aero\Workflows\Models\Revision::class => 'grafo completo (pendiente: lectura resumida)',
    \Aero\Docs\Models\Guide::class => 'HTML de guías (pendiente)',
];
