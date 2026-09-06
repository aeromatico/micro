<?php namespace Aero\Notify\Classes;

use Aero\Notify\Classes\Drivers\DriverManager;
use Aero\Notify\Classes\Support\Channels;
use Aero\Notify\Models\Delivery;
use Aero\Notify\Models\Event;
use Aero\Notify\Models\Rule;
use Aero\Notify\Models\Template;
use October\Rain\Parse\Twig;

/**
 * Punto de entrada del gateway: `Notify::fire('crm.collection.reminder', $context, $options)`.
 *
 * Por cada regla efectiva del evento (Rule::effectiveFor, con herencia global
 * -> tenant) resuelve la audiencia a destinatarios concretos, renderiza la
 * plantilla del canal y despacha. Cada intento queda logueado en Delivery,
 * incluso cuando se salta por falta de dirección/plantilla/driver — así el
 * listado de entregas explica qué pasó en vez de solo mostrar lo que sí salió.
 *
 * Pendiente de fases posteriores (documentado, no implementado todavía):
 * dedup_window_min, digest_window_min, delay_seconds y max_per_hour de Rule
 * se guardan pero no se aplican aún; toda entrega es inmediata y sin
 * deduplicar.
 *
 * $options:
 *   - tenant_id: int, tenant que dispara el evento (0/omitido = global)
 *   - actor: ['name'=>?, 'email'=>?, 'phone'=>?] — destinatario de audience=actor
 *   - adhoc: array de esos mismos arrays — destinatarios de audience=adhoc
 *   - locale: string, default 'es'
 */
class Notify
{
    public static function fire(string $eventCode, array $context = [], array $options = []): array
    {
        $event = Event::active()->where('code', $eventCode)->first();

        if (!$event) {
            throw new \RuntimeException("Aero.Notify: el evento '{$eventCode}' no existe en el catálogo o está inactivo.");
        }

        $missing = array_diff($event->requiredVariables(), array_keys($context));
        if ($missing) {
            throw new \InvalidArgumentException(
                "Aero.Notify: faltan variables requeridas para '{$eventCode}': " . implode(', ', $missing)
            );
        }

        $tenantId = (int) ($options['tenant_id'] ?? 0);
        $locale   = $options['locale'] ?? 'es';

        $deliveries = [];

        foreach (Rule::effectiveFor($event, $tenantId) as $rule) {
            $recipients = AudienceResolver::resolve($rule->audience, $tenantId, $options);

            foreach ($recipients as $recipient) {
                $deliveries[] = static::deliverOne($event, $rule, $recipient, $tenantId, $locale, $context);
            }
        }

        return $deliveries;
    }

    protected static function deliverOne(Event $event, Rule $rule, array $recipient, int $tenantId, string $locale, array $context): Delivery
    {
        $delivery = new Delivery([
            'event_id'  => $event->id,
            'rule_id'   => $rule->id,
            'tenant_id' => $tenantId,
            'audience'  => $rule->audience,
            'channel'   => $rule->channel,
            'context'   => $context,
            'status'    => 'pending',
        ]);

        $address = static::addressFor($rule->channel, $recipient);
        $delivery->address = $address;

        if (!$address) {
            $delivery->save();
            $delivery->markSkipped('no_address');
            return $delivery;
        }

        $template = Template::resolveFor($event, $rule->channel, $tenantId, $locale);
        $delivery->template_id = $template?->id;

        if (!$template) {
            $delivery->save();
            $delivery->markSkipped('no_template');
            return $delivery;
        }

        $vars = $context + ['to_name' => $recipient['name']];
        $twig = new Twig();

        $subject = $template->hasSubject() && $template->subject ? $twig->parse($template->subject, $vars) : null;
        $body    = $twig->parse($template->body, $vars);

        $delivery->subject = $subject;
        $delivery->body     = $body;
        $delivery->save();

        $drivers = new DriverManager();

        if (!$drivers->has($rule->channel)) {
            $delivery->markSkipped('no_driver');
            return $delivery;
        }

        try {
            $externalId = $drivers->make($rule->channel)->send($address, $subject, $body, $context + ['tenant_id' => $tenantId]);
            $delivery->markSent($externalId);
        } catch (\Throwable $e) {
            $delivery->markFailed($e->getMessage());
            \Log::error("Aero.Notify: fallo entregando '{$event->code}' por {$rule->channel} a {$address}: " . $e->getMessage());
        }

        return $delivery;
    }

    protected static function addressFor(string $channel, array $recipient): ?string
    {
        return match ($channel) {
            Channels::EMAIL => $recipient['email'] ?? null,
            Channels::WHATSAPP, Channels::SMS, Channels::TELEGRAM => $recipient['phone'] ?? null,
            default => null,
        };
    }
}
