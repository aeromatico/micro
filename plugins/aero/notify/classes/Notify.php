<?php namespace Aero\Notify\Classes;

use Aero\Notify\Classes\Drivers\DriverManager;
use Aero\Notify\Classes\Drivers\SkipDelivery;
use Aero\Notify\Jobs\DeliverNotification;
use Aero\Notify\Classes\Support\Channels;
use Aero\Notify\Models\Channel;
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
 * Si el tenant tiene un Channel propio para el canal de la regla (SMTP,
 * bot de Telegram, cuenta Twilio, WhatsApp explícito — ver Models\Channel),
 * su dirección y credenciales reemplazan a las resueltas por audiencia. Es
 * opt-in: sin Channel configurado, todo funciona igual que antes de que
 * existiera esa tabla.
 *
 * La entrega real va en cola (Jobs\DeliverNotification, Redis, 3 intentos con
 * backoff). Rule aplica conditions, delay_seconds, dedup_window_min,
 * digest_window_min (colapsa, no resume) y max_per_hour. Un contexto con
 * `attachment_binary` o $options['sync'] fuerza el envío inmediato.
 *
 * $options:
 *   - tenant_id: int, tenant que dispara el evento (0/omitido = global)
 *   - actor: ['name'=>?, 'email'=>?, 'phone'=>?] — destinatario de audience=actor
 *   - adhoc: array de esos mismos arrays — destinatarios de audience=adhoc
 *   - locale: string, default 'es'
 *   - sync: bool, envía en el acto en vez de encolar
 *   - dedup_key: string, sustituye al hash del contexto para dedup_window_min
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

        // Adjuntos binarios no caben en JSON ni en la cola: viajan solo en
        // memoria y fuerzan el envío síncrono.
        $transient = array_intersect_key($context, array_flip(['attachment_binary']));
        $context   = array_diff_key($context, $transient);
        $sync      = !empty($options['sync']) || $transient !== [];

        $deliveries = [];

        foreach (Rule::effectiveFor($event, $tenantId) as $rule) {
            if (!static::conditionsPass((array) $rule->conditions, $context)) {
                continue;
            }

            $recipients = AudienceResolver::resolve($rule->audience, $tenantId, $options);

            foreach ($recipients as $recipient) {
                $delivery = static::deliverOne($event, $rule, $recipient, $tenantId, $locale, $context, $options);

                if ($delivery->status === 'queued') {
                    if ($sync) {
                        static::transmit($delivery, $transient);
                    } else {
                        $job = DeliverNotification::dispatch($delivery->id);
                        if ($rule->delay_seconds > 0) {
                            $job->delay($rule->delay_seconds);
                            $delivery->scheduled_at = now()->addSeconds($rule->delay_seconds);
                            $delivery->save();
                        }
                    }
                }

                $deliveries[] = $delivery;
            }
        }

        return $deliveries;
    }

    /** Prepara la entrega: dirección, plantilla, guardas y render. Deja 'queued' o 'skipped'. */
    protected static function deliverOne(Event $event, Rule $rule, array $recipient, int $tenantId, string $locale, array $context, array $options = []): Delivery
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

        // Un canal propio del tenant (SMTP, bot de Telegram, cuenta Twilio,
        // WhatsApp explícito — ver Models\Channel) puede reemplazar la
        // dirección resuelta por audiencia. Es opt-in.
        $channel = Channel::activeFor($tenantId, $rule->channel);
        $address = $channel?->destinationAddress() ?: $address;

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

        $delivery->dedup_key = sha1($rule->id . '|' . $address . '|' . ($options['dedup_key'] ?? json_encode($context)));

        $delivery->digest_key = $rule->digest_key_expr ? sha1(trim($twig->parse($rule->digest_key_expr, $vars))) : '';

        if ($reason = static::guardReason($rule, $address, $delivery->dedup_key, $delivery->digest_key)) {
            $delivery->save();
            $delivery->markSkipped($reason);
            return $delivery;
        }

        $delivery->subject = $template->hasSubject() && $template->subject ? $twig->parse($template->subject, $vars) : null;
        $delivery->body    = $twig->parse($template->body, $vars);

        $drivers = new DriverManager();

        if (!$drivers->has($rule->channel)) {
            $delivery->save();
            $delivery->markSkipped('no_driver');
            return $delivery;
        }

        $delivery->status = 'queued';
        $delivery->save();

        return $delivery;
    }

    /**
     * dedup_window_min (misma regla+dirección+contexto), digest_window_min
     * (colapsa: solo pasa el primero de la ventana, por regla+dirección o por
     * digest_key_expr) y max_per_hour (tope por regla y tenant).
     */
    protected static function guardReason(Rule $rule, string $address, string $dedupKey, string $digestKey): ?string
    {
        $live = fn () => Delivery::where('rule_id', $rule->id)->whereIn('status', ['queued', 'sent']);

        if ($rule->dedup_window_min > 0
            && $live()->where('dedup_key', $dedupKey)->where('created_at', '>=', now()->subMinutes($rule->dedup_window_min))->exists()) {
            return 'deduplicated';
        }

        if ($rule->digest_window_min > 0
            && $live()->where('address', $address)->where('digest_key', $digestKey)
                ->where('created_at', '>=', now()->subMinutes($rule->digest_window_min))->exists()) {
            return 'digested';
        }

        if ($rule->max_per_hour
            && $live()->where('tenant_id', $rule->tenant_id)->where('created_at', '>=', now()->subHour())->count() >= $rule->max_per_hour) {
            return 'rate_limited';
        }

        return null;
    }

    /** Condiciones AND: [{"var":"amount","op":">=","value":100}]. */
    protected static function conditionsPass(array $conditions, array $context): bool
    {
        foreach ($conditions as $c) {
            $actual = $context[$c['var'] ?? ''] ?? null;
            $value  = $c['value'] ?? null;

            $ok = match ($c['op'] ?? '=') {
                '=', '==' => $actual == $value,
                '!='      => $actual != $value,
                '>'       => $actual > $value,
                '>='      => $actual >= $value,
                '<'       => $actual < $value,
                '<='      => $actual <= $value,
                'in'      => in_array($actual, (array) $value, false),
                'contains' => is_string($actual) && str_contains($actual, (string) $value),
                default   => true,
            };

            if (!$ok) {
                return false;
            }
        }

        return true;
    }

    /**
     * Envía una entrega 'queued'. Con $throwOnError (cola) relanza para que
     * el Job reintente; sin él (síncrono) marca 'failed' y sigue.
     */
    public static function transmit(Delivery $delivery, array $transient = [], bool $throwOnError = false): void
    {
        $delivery->attempts++;
        $delivery->save();

        try {
            $driverContext = ((array) $delivery->context) + $transient + [
                'tenant_id'   => (int) $delivery->tenant_id,
                'delivery_id' => $delivery->id,
                'event_code'  => $delivery->event?->code,
            ];

            $channel = Channel::activeFor((int) $delivery->tenant_id, $delivery->channel);
            if ($channel) {
                $driverContext['channel_config'] = $channel->config;
            }

            $externalId = (new DriverManager())->make($delivery->channel)
                ->send($delivery->address, $delivery->subject, (string) $delivery->body, $driverContext);
            $delivery->markSent($externalId);
        } catch (SkipDelivery $e) {
            $delivery->markSkipped($e->getMessage());
        } catch (\Throwable $e) {
            if ($throwOnError) {
                $delivery->error = $e->getMessage();
                $delivery->save();
                throw $e;
            }

            $delivery->markFailed($e->getMessage());
            \Log::error("Aero.Notify: fallo entregando '{$delivery->event?->code}' por {$delivery->channel} a {$delivery->address}: " . $e->getMessage());
        }
    }

    protected static function addressFor(string $channel, array $recipient): ?string
    {
        return match ($channel) {
            Channels::EMAIL => $recipient['email'] ?? null,
            Channels::WHATSAPP, Channels::SMS, Channels::TELEGRAM => $recipient['phone'] ?? null,
            Channels::INAPP, Channels::PUSH => !empty($recipient['user_id']) ? 'user:' . $recipient['user_id'] : null,
            default => null,
        };
    }
}
