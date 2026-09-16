<?php

use Aero\Notify\Classes\EventSeeder;
use Aero\Notify\Models\Event;
use Aero\Notify\Models\Template;
use October\Rain\Database\Updates\Seeder;

/**
 * Plantillas globales de email para sites.tenant.created y
 * sites.tenant.suspended — primeros eventos de EventCatalog conectados de
 * verdad (ver TenantProvisioner::notifyTenantCreated() y
 * Tenant::notifyTenantSuspended()). Sin plantilla, Rule existía pero
 * Notify::deliverOne() saltaba como no_template. Solo email: ambos eventos
 * ya tienen Rule global de inapp también, pero ese canal todavía no tiene
 * driver (fase pendiente).
 */
return new class extends Seeder
{
    public function run(): void
    {
        (new EventSeeder)->run();

        $this->seed('sites.tenant.created', <<<'TWIG'
<h2>Nuevo tenant aprovisionado</h2>
<p><strong>Nombre:</strong> {{ tenant_name }}</p>
<p><strong>Handle:</strong> {{ handle }}</p>
{% if niche_type %}<p><strong>Rubro:</strong> {{ niche_type }}</p>{% endif %}
{% if admin_email %}<p><strong>Admin:</strong> {{ admin_email }}</p>{% endif %}
TWIG, 'Nuevo tenant: {{ tenant_name }}');

        $this->seed('sites.tenant.suspended', <<<'TWIG'
<h2>Tenant suspendido</h2>
<p><strong>Nombre:</strong> {{ tenant_name }}</p>
{% if reason %}<p><strong>Motivo:</strong> {{ reason }}</p>{% endif %}
TWIG, 'Tenant suspendido: {{ tenant_name }}');
    }

    protected function seed(string $eventCode, string $body, string $subject): void
    {
        $event = Event::where('code', $eventCode)->first();

        if (!$event) {
            return;
        }

        $exists = Template::where('event_id', $event->id)
            ->where('channel', 'email')
            ->where('tenant_id', Template::GLOBAL_TENANT)
            ->where('locale', 'es')
            ->exists();

        if ($exists) {
            return;
        }

        $template = new Template();
        $template->event_id  = $event->id;
        $template->tenant_id = Template::GLOBAL_TENANT;
        $template->channel   = 'email';
        $template->locale    = 'es';
        $template->format    = 'twig';
        $template->subject   = $subject;
        $template->body      = $body;
        $template->save();
    }
};
