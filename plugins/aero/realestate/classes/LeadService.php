<?php namespace Aero\Realestate\Classes;

use Aero\Realestate\Models\Interaction;
use Aero\Realestate\Models\Lead;
use Aero\Realestate\Models\RealestateSettings;

/** Seguimiento comercial: mueve la etapa, registra contactos y agenda el siguiente seguimiento. */
class LeadService
{
    /** Mueve la oportunidad. El cierre ganado va por DealService (genera la comisión). */
    public function moveTo(Lead $lead, string $stage, ?string $note = null): Lead
    {
        if (!isset(Lead::$stages[$stage])) {
            throw new RealestateException('Etapa desconocida.');
        }
        if ($stage === 'closed_won') {
            throw new RealestateException('Para cerrar la operación usa «Cerrar operación»: elige la oferta y se calcula la comisión.');
        }
        if ($lead->stage === 'closed_won') {
            throw new RealestateException('Una operación cerrada no se reabre.');
        }
        if ($stage === 'closed_lost' && trim((string) $note) === '') {
            throw new RealestateException('Indica por qué se perdió la oportunidad.');
        }
        if ($lead->stage === $stage) {
            return $lead;
        }

        $from = $lead->stage_name;
        $lead->forceFill(['stage' => $stage]);

        if ($stage === 'closed_lost') {
            $lead->forceFill(['lost_reason' => $note, 'closed_at' => now(), 'next_follow_up_at' => null]);
        } else {
            $lead->forceFill(['lost_reason' => null, 'closed_at' => null]);
            if (in_array($stage, ['contacted', 'negotiation'], true)) {
                $lead->last_contact_at = now();
                $lead->next_follow_up_at ??= $this->nextFollowUp($lead);
            }
        }
        $lead->save();

        Interaction::log($lead->client_id, $lead->id, $lead->tenant_id, 'stage', "{$from} → {$lead->stage_name}" . ($note ? ': ' . $note : '.'));
        \Event::fire('aero.realestate.leadStageChanged', [$lead, $stage]);

        return $lead;
    }

    /** Anota un contacto (llamada, WhatsApp, nota…) y reprograma el seguimiento. */
    public function logContact(Lead $lead, string $type, string $body, $nextFollowUp = null): Lead
    {
        if (!isset(Interaction::$types[$type]) || in_array($type, ['stage', 'deal'], true)) {
            throw new RealestateException('Tipo de contacto no válido.');
        }
        if (trim($body) === '') {
            throw new RealestateException('Escribe qué se habló.');
        }
        if (!$lead->is_open) {
            throw new RealestateException('La oportunidad ya está cerrada.');
        }

        Interaction::log($lead->client_id, $lead->id, $lead->tenant_id, $type, $body);

        $lead->last_contact_at = now();
        $lead->next_follow_up_at = $nextFollowUp ? \Carbon\Carbon::parse($nextFollowUp) : $this->nextFollowUp($lead);
        $lead->follow_up_alerted_at = null;
        if ($lead->stage === 'new') {
            $lead->stage = 'contacted';
        }
        $lead->save();

        return $lead;
    }

    public function nextFollowUp(Lead $lead)
    {
        return now()->addDays(RealestateSettings::forTenant($lead->tenant_id)->followup_days)->setTime(9, 0);
    }

    /** Una visita agendada adelanta la etapa (sin retroceder una negociación). */
    public function visitScheduled(?Lead $lead): void
    {
        if ($lead && in_array($lead->stage, ['new', 'contacted'], true)) {
            $this->moveTo($lead, 'visit_scheduled', 'Se programó una visita.');
        }
    }
}
