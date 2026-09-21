<?php

use October\Rain\Database\Updates\Seeder;

/**
 * Siembra las plantillas globales de los eventos 'credits.purchase.paid' y
 * 'credits.purchase.review' (declarados en
 * Aero\Notify\Classes\EventCatalog::credits()) — mismo patrón que
 * plugins/aero/notify/updates/seed_qr_generated_templates.php.
 *
 * No hace nada si Aero.Notify no está instalado: Aero.Credits sigue
 * funcionando (solo no manda alertas), no truena la migración.
 */
return new class extends Seeder
{
    public function run(): void
    {
        if (!class_exists(\Aero\Notify\Classes\EventSeeder::class)) {
            return;
        }

        (new \Aero\Notify\Classes\EventSeeder)->run();

        $this->seedFor('credits.purchase.paid',
            "✅ Recarga acreditada: Bs {{ amount_bob }} → {{ coins_detail }}. ¡Ya puedes usar tus monedas!",
            '<h2>¡Recarga acreditada!</h2><p>Recibimos tu pago de <strong>Bs {{ amount_bob }}</strong> y ya acreditamos en la cuenta de {{ tenant_name }}:</p><p style="font-size:18px"><strong>{{ coins_detail }}</strong></p><p>Recarga N.º {{ purchase_id }}. Puedes ver tu saldo y movimientos en «Mis monedas».</p>',
            'Recarga acreditada — Bs {{ amount_bob }}'
        );

        $this->seedFor('credits.purchase.review',
            "⚠️ Recarga #{{ purchase_id }} de {{ tenant_name }}: llegó Bs {{ paid_bob }} de Bs {{ amount_bob }}. No se acreditó nada; revisar.",
            '<h2>Recarga con pago incompleto</h2><p>La recarga N.º {{ purchase_id }} de {{ tenant_name }} esperaba <strong>Bs {{ amount_bob }}</strong> pero llegó <strong>Bs {{ paid_bob }}</strong>. No se acreditaron monedas; requiere revisión manual.</p>',
            'Recarga #{{ purchase_id }} con pago incompleto'
        );
    }

    protected function seedFor(string $eventCode, string $whatsappBody, string $emailBody, string $emailSubject): void
    {
        $event = \Aero\Notify\Models\Event::where('code', $eventCode)->first();

        if (!$event) {
            return;
        }

        $this->seedTemplate($event, 'whatsapp', $whatsappBody);
        $this->seedTemplate($event, 'email', $emailBody, $emailSubject);
    }

    protected function seedTemplate($event, string $channel, string $body, ?string $subject = null): void
    {
        $exists = \Aero\Notify\Models\Template::where('event_id', $event->id)
            ->where('channel', $channel)
            ->where('tenant_id', \Aero\Notify\Models\Template::GLOBAL_TENANT)
            ->where('locale', 'es')
            ->exists();

        if ($exists) {
            return;
        }

        $template = new \Aero\Notify\Models\Template();
        $template->event_id  = $event->id;
        $template->tenant_id = \Aero\Notify\Models\Template::GLOBAL_TENANT;
        $template->channel   = $channel;
        $template->locale    = 'es';
        $template->format    = 'twig';
        $template->subject   = $subject;
        $template->body      = $body;
        $template->save();
    }
};
