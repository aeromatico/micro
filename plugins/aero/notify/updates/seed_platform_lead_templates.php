<?php

use Aero\Notify\Classes\EventSeeder;
use Aero\Notify\Models\Event;
use Aero\Notify\Models\Template;
use October\Rain\Database\Updates\Seeder;

/**
 * Siembra 'sites.platform_lead.created' (EventCatalog) y sus plantillas
 * globales de email/inapp para superadmin. Mismo patrón que
 * seed_contact_templates.php: sin esto, Notify::deliverOne() salta como
 * no_template aunque la Rule ya exista.
 */
return new class extends Seeder
{
    public function run(): void
    {
        (new EventSeeder)->run();

        $event = Event::where('code', 'sites.platform_lead.created')->first();

        if (!$event) {
            return;
        }

        $planLabel = "{% if plan == 'enterprise' %}Gran Empresa{% else %}Trial{% endif %}";

        $this->seedTemplate($event, 'email', <<<TWIG
<h2>Nuevo lead de plataforma — {$planLabel}</h2>
<p><strong>Nombre:</strong> {{ name }}</p>
<p><strong>Correo:</strong> {{ email }}</p>
<p><strong>Celular:</strong> {{ phone }}</p>
{% if verification_url %}<p><strong>Verificación:</strong> {{ verification_url }}</p>{% endif %}
<p><strong>Qué pretende resolver:</strong><br>{{ message }}</p>
TWIG, "Nuevo lead ({$planLabel}) de {{ name }}");

        $this->seedTemplate($event, 'inapp', <<<TWIG
Nuevo lead {$planLabel}: {{ name }} ({{ phone }}) — {{ message }}
TWIG);
    }

    protected function seedTemplate(Event $event, string $channel, string $body, ?string $subject = null): void
    {
        $exists = Template::where('event_id', $event->id)
            ->where('channel', $channel)
            ->where('tenant_id', Template::GLOBAL_TENANT)
            ->where('locale', 'es')
            ->exists();

        if ($exists) {
            return;
        }

        $template = new Template();
        $template->event_id  = $event->id;
        $template->tenant_id = Template::GLOBAL_TENANT;
        $template->channel   = $channel;
        $template->locale    = 'es';
        $template->format    = 'twig';
        $template->subject   = $subject;
        $template->body      = $body;
        $template->save();
    }
};
