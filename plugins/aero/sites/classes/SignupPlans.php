<?php namespace Aero\Sites\Classes;

use Aero\Sites\Models\Settings;

/**
 * Catálogo de planes pagos del alta pública en /alta (tema master). Los
 * precios son configurables desde Settings > Sites (sin redeploy); esta
 * clase es solo el catálogo fijo de qué planes existen y qué incluyen.
 */
class SignupPlans
{
    public static function all(): array
    {
        return [
            'negocio' => [
                'label' => 'Negocio',
                'price' => Settings::getSignupPlanPrice('negocio'),
                'features' => [
                    'Sitio web con generador de IA',
                    'Tienda online e inventario',
                    'CRM y cobranzas por WhatsApp',
                    'Pagos QR propios',
                ],
            ],
            'pro' => [
                'label' => 'Pro',
                'price' => Settings::getSignupPlanPrice('pro'),
                'features' => [
                    'Todo lo de Negocio',
                    'WhatsApp Business sin límites',
                    'Chatbots y automatización con IA',
                    'Soporte prioritario',
                ],
            ],
        ];
    }

    public static function find(string $id): ?array
    {
        return static::all()[$id] ?? null;
    }

    public static function exists(string $id): bool
    {
        return array_key_exists($id, static::all());
    }

    public static function labels(): array
    {
        return array_map(fn ($plan) => $plan['label'], static::all());
    }
}
