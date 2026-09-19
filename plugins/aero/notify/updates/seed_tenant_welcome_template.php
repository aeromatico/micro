<?php

use Aero\Notify\Classes\EventSeeder;
use Aero\Notify\Models\Event;
use Aero\Notify\Models\Template;
use October\Rain\Database\Updates\Seeder;

/**
 * Plantilla global de email para sites.tenant.welcome — el correo que de
 * verdad le llega al cliente al terminar de comprar un plan en comprar.htm
 * (ver TenantProvisioner::notifyTenantCreated()). Hasta esta migración, el
 * único evento conectado en ese punto era sites.tenant.created, cuya
 * audience es 'superadmin': el comprador no recibía nada.
 */
return new class extends Seeder
{
    public function run(): void
    {
        (new EventSeeder)->run();

        $event = Event::where('code', 'sites.tenant.welcome')->first();

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
        $template->subject   = '¡{{ tenant_name }} ya está en línea!';
        $template->body      = <<<'TWIG'
<h2>¡Tu sitio ya está listo!</h2>
<p>Hola,</p>
<p><strong>{{ tenant_name }}</strong> ya está publicado y disponible en:</p>
<p><a href="https://{{ primary_domain }}">https://{{ primary_domain }}</a></p>
<p>Podés administrarlo desde el panel con tu correo <strong>{{ admin_email }}</strong>:</p>
<p><a href="{{ backend_url }}">{{ backend_url }}</a></p>
<p>Gracias por confiar en nosotros.</p>
TWIG;
        $template->save();
    }
};
