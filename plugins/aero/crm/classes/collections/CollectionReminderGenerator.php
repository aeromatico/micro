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
     * Comportamiento anterior a las reglas: un único recordatorio repetido
     * cada `reminder_interval_days` mientras el cobro siga pendiente y
     * vencido. Solo se usa si el tenant no configuró ninguna regla.
     */
    protected function generateUsingDefaultInterval(int $tenantId, CrmSettings $settings): Collection
    {
        $intervalDays = max(1, (int) $settings->reminder_interval_days);

        $items = CollectionItem::forTenant($tenantId)
            ->pending()
            ->where('due_date', '<=', now()->toDateString())
            ->where(function ($q) use ($intervalDays) {
                $q->whereNull('last_reminder_at')
                    ->orWhere('last_reminder_at', '<=', now()->subDays($intervalDays));
            })
            ->with(['contact', 'recipients'])
            ->get();

        return $items->map(fn (CollectionItem $item) => [
            'item' => $item,
            'sent' => $this->sendReminder($item, $settings),
        ]);
    }

    /**
     * Envía el recordatorio de un CollectionItem puntual vía Aero\Hello
     * (mismo circuito que Contacts::onSendMessage) al contacto principal y a
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
        if (!class_exists(\Aero\Hello\Models\Contact::class)) {
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

        $sendOptions = [
            'platform'  => 'whatsapp',
            'tenant_id' => $item->tenant_id,
        ];

        // Si hay una cuenta de cobranzas configurada en QRBO, cada
        // recordatorio lleva el QR de pago adjunto — reutiliza el mismo QR
        // mientras siga vigente (ver CollectionQrIssuer) en vez de generar
        // uno nuevo en cada envío.
        if (class_exists(\Aero\Crm\Classes\Collections\CollectionQrIssuer::class)) {
            $qrCode = (new \Aero\Crm\Classes\Collections\CollectionQrIssuer())->issueFor($item);
            if ($qrCode && $qrCode->qr_image) {
                $sendOptions['media_url'] = url('/api/v1/pay/public/qr/' . $qrCode->internal_reference . '/image');
                $sendOptions['media_type'] = 'image';
            }
        }

        $sent = 0;
        foreach ($recipients as $contact) {
            if (!$contact->hello_contact_id) {
                continue;
            }

            $helloContact = \Aero\Hello\Models\Contact::with('identities')->find($contact->hello_contact_id);
            if (!$helloContact) {
                continue;
            }

            $body = $this->renderTemplate($template, $contact, $item);

            try {
                \Aero\Hello\Classes\Hello::sendToContact($helloContact, $body, $sendOptions);
            }
            catch (\Throwable $ex) {
                // Un contacto sin cuenta o sin identidad de WhatsApp no debe
                // cortar el resto de los destinatarios.
                continue;
            }

            Activity::create([
                'tenant_id'    => $item->tenant_id,
                'related_type' => Contact::class,
                'related_id'   => $contact->id,
                'type'         => 'whatsapp',
                'subject'      => $rule ? 'Recordatorio de cobro enviado: ' . $rule->name : 'Recordatorio de cobro enviado',
                'description'  => $body,
                'completed_at' => now(),
            ]);

            $sent++;
        }

        if ($sent === 0) {
            if ($rule) {
                CollectionReminderLog::where('collection_item_id', $item->id)
                    ->where('collection_reminder_rule_id', $rule->id)
                    ->where('scheduled_date', $scheduledDate ?: now()->toDateString())
                    ->delete();
            }
            return 0;
        }

        $item->last_reminder_at = now();
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
