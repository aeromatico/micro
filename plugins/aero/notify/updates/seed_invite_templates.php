<?php

use Aero\Notify\Classes\EventSeeder;
use Aero\Notify\Models\Event;
use Aero\Notify\Models\Template;
use October\Rain\Database\Updates\Seeder;

/**
 * `sites.user.invited` pasó a tener 'whatsapp' además de 'email' en
 * default_channels (EventCatalog). Reseedear crea la regla global de whatsapp
 * que faltaba (EventSeeder no borra ni pisa reglas existentes, solo agrega
 * las que faltan) y siembra las plantillas globales de ambos canales, para
 * que Aero.Crm\Controllers\Team pueda invitar sin que un admin tenga que
 * escribirlas a mano primero.
 */
return new class extends Seeder
{
    public function run(): void
    {
        (new EventSeeder)->run();

        $event = Event::where('code', 'sites.user.invited')->first();

        if (!$event) {
            return;
        }

        $this->seedTemplate($event, 'email', <<<'TWIG'
Hola {{ invitee_name }},

Te sumaron como colaborador de {{ tenant_name }}.

Ingresá con este enlace para activar tu acceso:
{{ invite_url }}
TWIG, 'Te invitaron a colaborar en ' . '{{ tenant_name }}');

        $this->seedTemplate($event, 'whatsapp', <<<'TWIG'
Hola {{ invitee_name }}, te sumaron como colaborador de {{ tenant_name }}. Ingresá con este enlace para activar tu acceso: {{ invite_url }}
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
