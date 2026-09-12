<?php

use October\Rain\Database\Updates\Seeder;

/**
 * Siembra las plantillas globales de los eventos 'credits.balance.low' y
 * 'credits.balance.depleted' (declarados en
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

        $this->seedFor('credits.balance.low',
            "⚠️ Saldo de créditos {{ credit_type }} bajo: quedan {{ balance }} (umbral {{ threshold }}).",
            '<h2>Saldo de créditos bajo</h2><p>El color <strong>{{ credit_type }}</strong> de {{ tenant_name }} quedó en {{ balance }} créditos (umbral: {{ threshold }}).</p>',
            'Saldo de créditos bajo — {{ credit_type }}'
        );

        $this->seedFor('credits.balance.depleted',
            "🚫 Saldo de créditos {{ credit_type }} agotado. Las acciones que lo usan están bloqueadas hasta recargar.",
            '<h2>Saldo de créditos agotado</h2><p>El color <strong>{{ credit_type }}</strong> de {{ tenant_name }} llegó a 0. Las acciones que lo usan están bloqueadas hasta recargar.</p>',
            'Saldo de créditos agotado — {{ credit_type }}'
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
