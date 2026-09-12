<?php

use Aero\Notify\Classes\EventSeeder;
use Aero\Notify\Models\Event;
use Aero\Notify\Models\Template;
use October\Rain\Database\Updates\Seeder;

/**
 * Siembra `qrbo.qr.generated` (evento + reglas globales, ver EventCatalog) y
 * sus plantillas globales de whatsapp/email, para que Aero.Qrbo pueda avisar
 * al destinatario suelto que carga el form de creación sin que un admin
 * tenga que escribirlas a mano primero.
 */
return new class extends Seeder
{
    public function run(): void
    {
        (new EventSeeder)->run();

        $event = Event::where('code', 'qrbo.qr.generated')->first();

        if (!$event) {
            return;
        }

        $this->seedTemplate($event, 'whatsapp', <<<'TWIG'
Tu QR de {{ amount }} {{ currency }} está listo{% if description %} — {{ description }}{% endif %}.
TWIG);

        $this->seedTemplate($event, 'email', <<<'TWIG'
<h2>Tu código QR está listo</h2>
<p>Monto: {{ amount }} {{ currency }}</p>
{% if description %}<p>Descripción: {{ description }}</p>{% endif %}
{% if due_date %}<p>Vence: {{ due_date }}</p>{% endif %}
<p>La imagen del QR va adjunta a este correo.</p>
TWIG, 'Tu QR de {{ amount }} {{ currency }} está listo');
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
