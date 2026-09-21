<?php

use October\Rain\Database\Updates\Seeder;

/**
 * Bandeja (inapp) y push de los avisos de recarga: los eventos declaran esos
 * canales por defecto pero solo se sembraron WhatsApp y correo, así que se
 * omitían con "no_template". Idempotente.
 */
return new class extends Seeder
{
    public function run(): void
    {
        if (!class_exists(\Aero\Notify\Models\Template::class)) {
            return;
        }

        $this->seed('credits.purchase.paid', 'Recarga acreditada', 'Recibimos tu pago de Bs {{ amount_bob }}: {{ coins_detail }}.');
        $this->seed('credits.purchase.review', 'Recarga con pago incompleto', 'La recarga #{{ purchase_id }} de {{ tenant_name }} recibió Bs {{ paid_bob }} de Bs {{ amount_bob }}. Revisar.', ['inapp']);
    }

    protected function seed(string $code, string $subject, string $body, array $channels = ['inapp', 'push']): void
    {
        $event = \Aero\Notify\Models\Event::where('code', $code)->first();

        if (!$event) {
            return;
        }

        foreach ($channels as $channel) {
            $exists = \Aero\Notify\Models\Template::where('event_id', $event->id)
                ->where('channel', $channel)
                ->where('tenant_id', \Aero\Notify\Models\Template::GLOBAL_TENANT)
                ->where('locale', 'es')
                ->exists();

            if ($exists) {
                continue;
            }

            $t = new \Aero\Notify\Models\Template();
            $t->event_id  = $event->id;
            $t->tenant_id = \Aero\Notify\Models\Template::GLOBAL_TENANT;
            $t->channel   = $channel;
            $t->locale    = 'es';
            $t->format    = 'twig';
            $t->subject   = $subject;
            $t->body      = $body;
            $t->save();
        }
    }
};
