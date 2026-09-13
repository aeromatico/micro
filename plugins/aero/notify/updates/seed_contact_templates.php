<?php

use Aero\Notify\Classes\EventSeeder;
use Aero\Notify\Models\Event;
use Aero\Notify\Models\Template;
use October\Rain\Database\Updates\Seeder;

/**
 * Plantillas globales de 'sites.contact.submitted' para los 5 canales que
 * EventCatalog le declara. Sin esto, ni siquiera un tenant con Rule + Channel
 * bien configurados recibía nada: Notify::deliverOne() salta como
 * no_template antes de llegar al driver. Mismo patrón que
 * seed_invite_templates.php / seed_qr_generated_templates.php.
 */
return new class extends Seeder
{
    public function run(): void
    {
        (new EventSeeder)->run();

        $event = Event::where('code', 'sites.contact.submitted')->first();

        if (!$event) {
            return;
        }

        $this->seedTemplate($event, 'email', <<<'TWIG'
<h2>Nuevo mensaje de contacto{% if tenant_name %} — {{ tenant_name }}{% endif %}</h2>
<p><strong>Nombre:</strong> {{ name }}</p>
{% if email %}<p><strong>Email:</strong> {{ email }}</p>{% endif %}
{% if phone %}<p><strong>Teléfono:</strong> {{ phone }}</p>{% endif %}
<p><strong>Mensaje:</strong><br>{{ message }}</p>
{% if page %}<p><small>Enviado desde {{ page }}</small></p>{% endif %}
TWIG, 'Nuevo mensaje de contacto de {{ name }}');

        $this->seedTemplate($event, 'inapp', <<<'TWIG'
Nuevo mensaje de {{ name }}: {{ message }}
TWIG, 'Nuevo mensaje de contacto');

        $body = <<<'TWIG'
📩 Nuevo mensaje de contacto{% if tenant_name %} — {{ tenant_name }}{% endif %}

Nombre: {{ name }}
{% if email %}Email: {{ email }}
{% endif %}{% if phone %}Teléfono: {{ phone }}
{% endif %}
{{ message }}
TWIG;

        $this->seedTemplate($event, 'whatsapp', $body);
        $this->seedTemplate($event, 'telegram', $body);
        $this->seedTemplate($event, 'sms', <<<'TWIG'
Contacto de {{ name }}{% if phone %} ({{ phone }}){% endif %}: {{ message }}
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
