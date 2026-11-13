<?php

use Aero\Notify\Classes\EventSeeder;
use Aero\Notify\Models\Event;
use Aero\Notify\Models\Template;
use October\Rain\Database\Updates\Seeder;

/**
 * Arregla que el aviso de renovación del plan nunca llegara: `renewal_due`
 * tenía regla pero ninguna plantilla (todas las entregas quedaban «no_template»).
 * Siembra el evento nuevo `renewal_reminder` y las plantillas globales de
 * correo de los tres eventos de renovación.
 */
return new class extends Seeder
{
    public function run(): void
    {
        (new EventSeeder)->run();

        $this->seed('sites.tenant.renewal_due', 'Tu plan se renueva el {{ due_date }} — paga Bs {{ amount }}', <<<'TWIG'
<h2>Es hora de renovar tu plan</h2>
<p>Hola, el plan de <strong>{{ tenant_name }}</strong> vence el <strong>{{ due_date }}</strong>.</p>
<p>Para seguir usando Market sin interrupciones, paga <strong>Bs {{ amount }}</strong> con el QR que ya te dejamos listo:</p>
<ol>
  <li>Entra a <strong>Mi plan</strong>{% if pay_url %}: <a href="{{ pay_url }}">{{ pay_url }}</a>{% endif %}.</li>
  <li>Escanea el QR desde la app de tu banco.</li>
  <li>Tu plan se extiende al instante.</li>
</ol>
TWIG);

        $this->seed('sites.tenant.renewal_reminder', 'Tu plan {{ when }} — paga Bs {{ amount }}', <<<'TWIG'
<h2>Tu plan {{ when }}</h2>
<p>El plan de <strong>{{ tenant_name }}</strong> {{ when }} ({{ due_date }}) y aún no vemos tu pago de <strong>Bs {{ amount }}</strong>.</p>
{% if overdue %}<p><strong>Tu cuenta se suspenderá{% if grace_days %} en {{ grace_days }} día(s){% endif %} si no renuevas.</strong> No perderás tus datos, pero no podrás entrar hasta pagar.</p>{% endif %}
<p>Paga en un minuto: entra a <strong>Mi plan</strong>{% if pay_url %} (<a href="{{ pay_url }}">{{ pay_url }}</a>){% endif %} y escanea el QR desde la app de tu banco.</p>
<p>Si ya pagaste, ignora este mensaje: se actualiza solo en unos minutos.</p>
TWIG);

        $this->seed('sites.tenant.plan_renewed', 'Recibimos tu pago: plan renovado hasta el {{ plan_expires_at }}', <<<'TWIG'
<h2>¡Gracias! Tu plan está renovado</h2>
<p>El plan de <strong>{{ tenant_name }}</strong> queda activo hasta el <strong>{{ plan_expires_at }}</strong>.</p>
TWIG);
    }

    protected function seed(string $code, string $subject, string $body): void
    {
        $event = Event::where('code', $code)->first();

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
