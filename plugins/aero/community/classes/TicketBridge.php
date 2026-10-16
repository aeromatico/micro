<?php namespace Aero\Community\Classes;

use ApplicationException;
use BackendAuth;
use Illuminate\Support\Facades\RateLimiter;

/**
 * «Abrir un ticket» desde el popup: crea el MISMO ticket que Soporte (CRM →
 * Soporte) en la mesa de ayuda de la plataforma, para quien prefiera ese
 * camino al reporte rápido. Dependencia blanda de Aero.Crm.
 */
class TicketBridge
{
    public static function available(): bool
    {
        return class_exists(\Aero\Crm\Models\Ticket::class) && class_exists(\Aero\Crm\Classes\TenantUsers::class);
    }

    public static function departments(): array
    {
        if (!self::available()) {
            return [];
        }

        // La mesa de la plataforma (tenant_id NULL) puede no tener aún ningún departamento:
        // se crea «Soporte», igual que hace Livechat al abrir un ticket (LeadTicket::department).
        if (!\Aero\Crm\Models\Department::inScope(null)->exists()) {
            \Aero\Crm\Models\Department::create(['tenant_id' => null, 'name' => 'Soporte', 'is_active' => true]);
        }

        return \Aero\Crm\Models\Department::inScope(null)->where('is_active', true)->orderBy('sort_order')
            ->get(['id', 'name'])->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->all();
    }

    public static function create(array $data): \Aero\Crm\Models\Ticket
    {
        $user = BackendAuth::getUser();
        if (!$user || !self::available()) {
            throw new ApplicationException('Los tickets no están disponibles por ahora.');
        }

        $tenantId = \Aero\Crm\Classes\TenantUsers::currentTenantId();
        if (!$tenantId) {
            throw new ApplicationException('Los tickets son para negocios; el equipo los gestiona en CRM → Tickets.');
        }

        $key = 'community-quick:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw new ApplicationException('Enviaste muchos tickets seguidos. Espera unos minutos.');
        }
        RateLimiter::hit($key, 3600);

        $subject = trim((string) ($data['subject'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        if (mb_strlen($subject) < 4) {
            throw new ApplicationException('Escribe un asunto (mínimo 4 caracteres).');
        }

        $department = \Aero\Crm\Models\Department::inScope(null)->where('is_active', true)->find((int) ($data['department_id'] ?? 0));
        if (!$department) {
            throw new ApplicationException('Elige el departamento.');
        }

        // Contexto de la pantalla desde donde se abrió, útil para el agente.
        $ctx = is_array($data['context'] ?? null) ? $data['context'] : [];
        $extra = array_filter([
            !empty($data['url']) ? 'Pantalla: ' . mb_substr((string) $data['url'], 0, 500) : null,
            !empty($data['area']) ? 'Área: ' . mb_substr((string) $data['area'], 0, 150) : null,
            !empty($ctx['userAgent']) ? 'Navegador: ' . mb_substr((string) $ctx['userAgent'], 0, 200) : null,
            !empty($ctx['errors']) && is_array($ctx['errors']) ? "Errores recientes:\n" . implode("\n", array_map(fn ($e) => mb_substr((string) $e, 0, 200), array_slice($ctx['errors'], 0, 5))) : null,
        ]);

        return \Aero\Crm\Models\Ticket::create([
            'tenant_id'        => null,
            'origin_tenant_id' => $tenantId,
            'requester_user_id' => $user->id,
            'subject'          => mb_substr($subject, 0, 255),
            'description'      => mb_substr($description, 0, 8000) . ($extra ? "\n\n— Contexto —\n" . implode("\n", $extra) : ''),
            'department_id'    => $department->id,
            'priority'         => in_array($data['priority'] ?? '', ['low', 'normal', 'high', 'urgent'], true) ? $data['priority'] : 'normal',
            'source'           => 'tenant',
            'requester_name'   => trim($user->first_name . ' ' . $user->last_name) ?: $user->login,
            'requester_email'  => $user->email,
            'unread_for_agent' => true,
        ]);
    }
}
