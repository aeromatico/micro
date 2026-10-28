<?php namespace Aero\Crm\Classes\Collections;

use Aero\Crm\Models\Activity;
use Aero\Crm\Models\CollectionItem;
use Aero\Crm\Models\CollectionReminderLog;
use Aero\Crm\Models\CollectionReminderRule;
use Aero\Crm\Models\Contact;
use Aero\Crm\Models\CrmSettings;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Genera y envía los recordatorios automáticos de cobranza.
 *
 * Modo principal — reglas (CollectionReminderRule): cada tenant puede
 * definir varios pasos de una cascada de dunning ("3 días antes", "el día
 * del vencimiento", "+5 días vencido", etc.), cada uno con su propia
 * plantilla de mensaje y, opcionalmente, atado a una ContactList específica
 * (una regla sin lista aplica a todos los cobros del tenant). Un
 * CollectionReminderLog por (cobro, regla) evita reenviar el mismo paso.
 *
 * Modo de respaldo — si el tenant no tiene reglas activas, se usa el
 * intervalo fijo simple de CrmSettings (reminder_interval_days /
 * reminder_message_template), tal como funcionaba antes de que existieran
 * las reglas, para no dejar de enviar recordatorios a tenants que todavía
 * no configuraron su automatización.
 *
 * Usado tanto por el comando programado `crm:generate-cobranza-reminders`
 * (ver Plugin::registerSchedule) como por el botón manual "Enviar
 * recordatorio" en el controlador Collections.
 */
class CollectionReminderGenerator
{
    protected const DEFAULT_TEMPLATE = 'Hola {{contacto}}, te recordamos que tienes un pago pendiente de {{monto}} {{moneda}} por "{{concepto}}" con vencimiento el {{vencimiento}}. Por favor regulariza a la brevedad.';

    public function generateForAllTenants(): Collection
    {
        $results = collect();

        CrmSettings::where('collections_enabled', true)->chunk(50, function ($settingsChunk) use (&$results) {
            foreach ($settingsChunk as $settings) {
                $results = $results->merge($this->generateForTenant($settings->tenant_id, $settings));
            }
        });

        return $results;
    }

    public function generateForTenant(int $tenantId, ?CrmSettings $settings = null): Collection
    {
        $settings ??= CrmSettings::where('tenant_id', $tenantId)->first();
        if (!$settings || !$settings->collections_enabled) {
            return collect();
        }

        $rules = CollectionReminderRule::forTenant($tenantId)->active()->get();

        if ($rules->isEmpty()) {
            return $this->generateUsingDefaultInterval($tenantId, $settings);
        }

        $results = collect();
        foreach ($rules as $rule) {
            $results = $results->merge($this->generateForRule($rule, $settings));
        }

        return $results;
    }

    /**
     * Calcula el siguiente recordatorio pendiente desde la fecha de inicio.
     * El vencimiento se trata como una fecha especial, aunque no coincida con
     * la frecuencia configurada.
     */
    protected function generateForRule(CollectionReminderRule $rule, CrmSettings $settings): Collection
    {
        $query = CollectionItem::forTenant($rule->tenant_id)
            ->pending()
            ->where('reminders_paused', false)
            ->where('due_date', '>=', now()->toDateString());

        if ($rule->contact_list_id) {
            $query->where(function ($q) use ($rule) {
                $q->where('contact_list_id', $rule->contact_list_id)
                    ->orWhereHas('contactLists', function ($qq) use ($rule) {
                        $qq->where('aero_crm_contact_lists.id', $rule->contact_list_id);
                    });
            });
        }

        $items = $query
            ->with(['contact', 'recipients', 'reminderLogs' => function ($q) use ($rule) {
                $q->where('collection_reminder_rule_id', $rule->id)
                    ->whereNotNull('scheduled_date')
                    ->orderByDesc('scheduled_date');
            }])
            ->get();

        return $items->map(function (CollectionItem $item) use ($rule, $settings) {
            $today = now()->startOfDay();
            $dueDate = Carbon::parse($item->due_date)->startOfDay();
            $startDate = $dueDate->copy()->subDays((int) $rule->start_days_before);
            $lastLog = $item->reminderLogs->first();
            $dueWasSent = $item->reminderLogs->contains(function ($log) use ($dueDate) {
                return Carbon::parse($log->scheduled_date)->isSameDay($dueDate);
            });

            if ($dueWasSent) {
                return null;
            }

            $nextDate = $lastLog
                ? Carbon::parse($lastLog->scheduled_date)->addDays((int) $rule->frequency_days)
                : $startDate;

            if ($today->gte($dueDate) || $nextDate->gt($dueDate)) {
                $nextDate = $dueDate;
            }

            if ($today->lt($nextDate)) {
                return null;
            }

            return [
                'item' => $item,
                'rule' => $rule,
                'sent' => $this->sendReminder($item, $settings, $rule, $nextDate->toDateString()),
            ];
        })->filter();
    }

    /**
     * Cascada por defecto (tenants sin reglas): días respecto al vencimiento de
     * cada recordatorio automático. Máximo 4 envíos por cobro; después se detiene
     * y el tenant gestiona el cobro a mano.
     */
    protected const DEFAULT_CASCADE = [-2, 0, 3, 7];

    /**
     * Un recordatorio por cobro y por corrida, según DEFAULT_CASCADE (ver
     * dueDefaultStepDate). Un cobro cargado ya vencido no dispara una ráfaga.
     */
    protected function generateUsingDefaultInterval(int $tenantId, CrmSettings $settings): Collection
    {
        $max = count(self::DEFAULT_CASCADE);
        $today = now()->startOfDay();

        $items = CollectionItem::forTenant($tenantId)
            ->pending()
            ->where('reminders_paused', false)
            ->where(function ($q) use ($max) {
                $q->whereNull('reminder_count')->orWhere('reminder_count', '<', $max);
            })
            ->where(function ($q) {
                $q->whereNull('last_reminder_at')->orWhere('last_reminder_at', '<', now()->startOfDay());
            })
            ->with(['contact', 'recipients'])
            ->get();

        return $items->map(function (CollectionItem $item) use ($settings, $today) {
            $stepDate = $this->dueDefaultStepDate($item, $today);
            if (!$stepDate) {
                return null;
            }

            $sent = $this->sendReminder($item, $settings);

            // Si nadie lo recibió (límite de Meta, sin WhatsApp, baja) se espera
            // al siguiente paso en vez de insistir a diario.
            if ($sent === 0) {
                $item->last_reminder_at = now();
                $item->save();
            }

            return ['item' => $item, 'sent' => $sent];
        })->filter();
    }

    /**
     * Paso de la cascada que toca hoy, o null. Es el más reciente que ya llegó, no es
     * anterior a la creación del cobro ni tiene más de 3 días (un servidor caído no
     * dispara mensajes viejos) y aún no se envió (último envío anterior a esa fecha).
     */
    protected function dueDefaultStepDate(CollectionItem $item, Carbon $today): ?Carbon
    {
        $due = Carbon::parse($item->due_date)->startOfDay();
        $created = Carbon::parse($item->created_at)->startOfDay();
        $oldest = $today->copy()->subDays(3);

        $step = collect(self::DEFAULT_CASCADE)
            ->map(fn (int $offset) => $due->copy()->addDays($offset))
            ->filter(fn (Carbon $date) => $date->lte($today) && $date->gte($created) && $date->gte($oldest))
            ->last();

        if (!$step) {
            return null;
        }

        return $item->last_reminder_at && $item->last_reminder_at->startOfDay()->gte($step) ? null : $step;
    }

    /**
     * Envía el recordatorio de un CollectionItem puntual vía Aero.Notify
     * (evento crm.collection.reminder, canal WhatsApp sobre Aero.Hello) al contacto principal y a
     * los contactos adicionales cargados a mano (ver la relación
     * `recipients`), registrando una Activity por cada uno. Devuelve la
     * cantidad de contactos notificados; los que no tienen canal de WhatsApp
     * vinculado se saltean sin lanzar excepción, para no cortar un lote
     * completo. Cuando se dispara desde una regla, registra el
     * CollectionReminderLog correspondiente para que no se repita ese paso de
     * la cascada — si nadie pudo recibirlo, el log se revierte y queda para
     * la próxima corrida.
     */
    public function sendReminder(CollectionItem $item, ?CrmSettings $settings = null, ?CollectionReminderRule $rule = null, ?string $scheduledDate = null): int
    {
        if (!class_exists(\Aero\Notify\Classes\Notify::class)) {
            return 0;
        }

        $item->loadMissing(['contact', 'recipients']);
        $recipients = $this->resolveRecipients($item);
        if ($recipients->isEmpty()) {
            return 0;
        }

        $settings ??= CrmSettings::where('tenant_id', $item->tenant_id)->first();
        $template = $rule?->message_template ?: $settings?->reminder_message_template;

        if ($rule) {
            try {
                CollectionReminderLog::create([
                    'collection_item_id'          => $item->id,
                    'collection_reminder_rule_id' => $rule->id,
                    'scheduled_date'              => $scheduledDate ?: now()->toDateString(),
                    'sent_at'                     => now(),
                ]);
            }
            catch (\Throwable $ex) {
                return 0;
            }
        }

        // El QR de pago (si hay cuenta de cobranzas en Aero.Pay) viaja como
        // adjunto; se reutiliza mientras siga vigente (ver CollectionQrIssuer).
        $media = [];
        if (class_exists(\Aero\Crm\Classes\Collections\CollectionQrIssuer::class)) {
            $qrCode = (new \Aero\Crm\Classes\Collections\CollectionQrIssuer())->issueFor($item);
            if ($qrCode && $qrCode->qr_image) {
                $media = [
                    'media_url'  => url('/api/v1/pay/public/qr/' . $qrCode->internal_reference . '/image'),
                    'media_type' => 'image',
                ];
            }
        }

        $sent = 0;
        $lastError = null;
        foreach ($recipients as $contact) {
            $helloContact = $contact->hello_contact_id && class_exists(\Aero\Hello\Models\Contact::class)
                ? \Aero\Hello\Models\Contact::with('identities')->find($contact->hello_contact_id)
                : null;

            $phone = $helloContact?->identities->firstWhere('platform', 'whatsapp')?->external_id ?: $contact->phone;
            if (!$phone) {
                $lastError = $lastError ?: 'El contacto no tiene número de WhatsApp.';
                continue;
            }

            // Respeta la baja del contacto (lista de bajas de Aero.Sms).
            if (class_exists(\Aero\Sms\Models\OptOut::class) && \Aero\Sms\Models\OptOut::blocks('+' . ltrim($phone, '+'))) {
                $lastError = $lastError ?: 'El contacto está en la lista de bajas.';
                continue;
            }

            $body = $this->renderTemplate($template, $contact, $item);

            // El texto (personalizable por regla/tenant) va como `mensaje`; la
            // plantilla global del evento lo usa tal cual. Envío síncrono: el
            // resultado decide si se revierte el log de la cascada.
            $overdue = $item->due_date && $item->due_date->lt(now()->startOfDay());

            $deliveries = \Aero\Notify\Classes\Notify::fire($overdue ? 'crm.collection.overdue' : 'crm.collection.reminder', [
                'contacto'    => $contact->full_name,
                'monto'       => number_format((float) $item->amount, 2),
                'moneda'      => $item->currency,
                'concepto'    => $item->concept,
                'vencimiento' => optional($item->due_date)->format('d/m/Y'),
                'mensaje'     => $body,
                'dias_atraso' => $overdue ? $item->due_date->diffInDays(now()->startOfDay(), true) : 0,
                // Un cobro es un aviso transaccional: va como utilidad, no como marketing (Meta limita el marketing por destinatario).
                'category'    => 'utility',
            ] + $media, [
                'tenant_id' => (int) $item->tenant_id,
                'actor'     => ['name' => $contact->full_name, 'phone' => $phone],
                'sync'      => true,
                'dedup_key' => "collection:{$item->id}:{$contact->id}:" . ($scheduledDate ?: now()->toDateString()),
            ]);

            if (!collect($deliveries)->contains(fn ($d) => $d->channel === 'whatsapp' && $d->status === 'sent')) {
                $failure = collect($deliveries)->firstWhere('channel', 'whatsapp')?->error;
                $lastError = $lastError ?: mb_substr((string) ($failure ?: 'No se pudo enviar por WhatsApp.'), 0, 250);
                continue;
            }

            Activity::create([
                'tenant_id'    => $item->tenant_id,
                'related_type' => Contact::class,
                'related_id'   => $contact->id,
                'type'         => 'whatsapp',
                'subject'      => $rule ? 'Recordatorio de cobro enviado: ' . $rule->name : 'Recordatorio de cobro enviado',
                'description'  => $body,
                'status'       => Activity::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            $sent++;
        }

        if ($sent === 0) {
            $item->last_reminder_error = $lastError;
            $item->save();

            if ($rule) {
                CollectionReminderLog::where('collection_item_id', $item->id)
                    ->where('collection_reminder_rule_id', $rule->id)
                    ->where('scheduled_date', $scheduledDate ?: now()->toDateString())
                    ->delete();
            }
            return 0;
        }

        $item->last_reminder_at = now();
        $item->last_reminder_error = null;
        $item->reminder_count = ($item->reminder_count ?? 0) + 1;
        $item->save();

        return $sent;
    }

    /**
     * Contacto principal + adicionales, sin duplicados (el principal puede
     * estar también cargado como adicional).
     */
    protected function resolveRecipients(CollectionItem $item): Collection
    {
        $recipients = collect();

        if ($item->contact) {
            $recipients->push($item->contact);
        }

        foreach ($item->recipients as $contact) {
            if (!$recipients->contains('id', $contact->id)) {
                $recipients->push($contact);
            }
        }

        return $recipients;
    }

    protected function renderTemplate(?string $template, Contact $contact, CollectionItem $item): string
    {
        $template = $template ?: self::DEFAULT_TEMPLATE;

        return strtr($template, [
            '{{contacto}}'    => $contact->full_name,
            '{{monto}}'       => number_format((float) $item->amount, 2),
            '{{moneda}}'      => $item->currency,
            '{{concepto}}'    => $item->concept,
            '{{vencimiento}}' => optional($item->due_date)->format('d/m/Y'),
        ]);
    }
}
