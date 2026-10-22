<?php namespace Aero\Realestate\Classes;

use Aero\Realestate\Models\Client;
use Aero\Realestate\Models\Interaction;
use Aero\Realestate\Models\Lead;
use Aero\Realestate\Models\Property;
use Aero\Realestate\Models\Visit;
use Carbon\Carbon;

/** Agenda de visitas: pedir, confirmar, reprogramar, cancelar y dar por realizada, con choques de agenda y avisos. */
class VisitService
{
    public function __construct(protected LeadService $leads)
    {
    }

    /**
     * Crea una visita. Desde el panel queda confirmada; desde el portal, solicitada (la confirma el equipo).
     *
     * @param array $data property_id, client_id, starts_at, [lead_id, agent_id, notes, ends_at]
     */
    public function schedule(array $data, string $source = 'panel'): Visit
    {
        $start = Carbon::parse($data['starts_at']);
        if ($start->isPast()) {
            throw new RealestateException('La fecha de la visita ya pasó.');
        }

        $property = Property::findOrFail($data['property_id']);
        $client = Client::where('tenant_id', $property->tenant_id)->findOrFail($data['client_id']);
        $agentId = $data['agent_id'] ?? $property->agent_id ?? $client->agent_id;

        $visit = new Visit($data + ['source' => $source]);
        $visit->tenant_id = $property->tenant_id;
        $visit->agent_id = $agentId;
        $visit->starts_at = $start;
        $visit->ends_at = isset($data['ends_at']) ? Carbon::parse($data['ends_at']) : null;
        $visit->status = $source === 'portal' ? 'requested' : 'confirmed';
        $visit->client_id = $client->id;

        if (!empty($data['lead_id'])) {
            $lead = Lead::where('tenant_id', $property->tenant_id)->findOrFail($data['lead_id']);
            $visit->lead_id = $lead->id;
        }
        $visit->save(); // calcula ends_at y código
        $this->assertFree($visit);

        $this->leads->visitScheduled($visit->lead);
        $this->note($visit, 'Visita ' . $visit->code . ' ' . ($source === 'portal' ? 'solicitada' : 'programada') . ' para el ' . $visit->starts_at->format('d/m/Y H:i') . '.');
        \Event::fire('aero.realestate.visitScheduled', [$visit]);
        Notifier::fireVisit($visit->status === 'requested' ? 'requested' : 'confirmed', $visit);

        return $visit;
    }

    public function confirm(Visit $visit): Visit
    {
        $this->assertStatus($visit, ['requested'], 'Solo una visita solicitada se confirma.');
        $this->assertFree($visit);
        $visit->forceFill(['status' => 'confirmed'])->save();
        $this->note($visit, 'Visita ' . $visit->code . ' confirmada.');
        \Event::fire('aero.realestate.visitConfirmed', [$visit]);
        Notifier::fireVisit('confirmed', $visit);

        return $visit;
    }

    public function reschedule(Visit $visit, $newStart, ?string $reason = null): Visit
    {
        $this->assertStatus($visit, Visit::ACTIVE, 'Solo una visita pendiente se reprograma.');
        $start = Carbon::parse($newStart);
        if ($start->isPast()) {
            throw new RealestateException('La nueva fecha ya pasó.');
        }

        $old = $visit->starts_at->copy();
        $visit->forceFill([
            'status' => 'rescheduled', 'starts_at' => $start, 'ends_at' => $start->copy()->addMinutes(max(10, (int) $old->diffInMinutes($visit->ends_at, true))),
            'reminder_sent_at' => null,
        ]);
        $this->assertFree($visit);
        $visit->save();

        $this->note($visit, 'Visita ' . $visit->code . ' reprogramada del ' . $old->format('d/m H:i') . ' al ' . $start->format('d/m/Y H:i') . ($reason ? ': ' . $reason : '.'));
        \Event::fire('aero.realestate.visitRescheduled', [$visit]);
        Notifier::fireVisit('rescheduled', $visit, ['reason' => (string) $reason]);

        return $visit;
    }

    public function cancel(Visit $visit, ?string $reason = null): Visit
    {
        $this->assertStatus($visit, Visit::ACTIVE, 'Solo una visita pendiente se cancela.');
        $visit->forceFill(['status' => 'cancelled', 'cancel_reason' => $reason])->save();
        $this->note($visit, 'Visita ' . $visit->code . ' cancelada' . ($reason ? ': ' . $reason : '.'));
        \Event::fire('aero.realestate.visitCancelled', [$visit]);
        Notifier::fireVisit('cancelled', $visit, ['reason' => (string) $reason]);

        return $visit;
    }

    public function complete(Visit $visit, ?string $notes = null): Visit
    {
        $this->assertStatus($visit, ['confirmed', 'rescheduled'], 'Solo una visita confirmada se da por realizada.');
        $visit->forceFill(['status' => 'done', 'notes' => trim($visit->notes . "\n" . $notes)])->save();
        $this->note($visit, 'Visita ' . $visit->code . ' realizada' . ($notes ? ': ' . $notes : '.'));
        \Event::fire('aero.realestate.visitDone', [$visit]);

        return $visit;
    }

    /** El agente no puede tener dos visitas a la vez. */
    protected function assertFree(Visit $visit): void
    {
        if (!$visit->agent_id) {
            return;
        }
        $clash = Visit::active()->where('agent_id', $visit->agent_id)->where('id', '!=', $visit->id ?? 0)
            ->where('starts_at', '<', $visit->ends_at)->where('ends_at', '>', $visit->starts_at)->first();

        if ($clash) {
            if ($visit->exists && $visit->wasRecentlyCreated) {
                $visit->forceDelete(); // la creación no deja residuos si choca
            }
            throw new RealestateException('El agente ya tiene la visita ' . $clash->code . ' el ' . $clash->starts_at->format('d/m H:i') . '.');
        }
    }

    protected function assertStatus(Visit $visit, array $allowed, string $message): void
    {
        if (!in_array($visit->status, $allowed, true)) {
            throw new RealestateException($message);
        }
    }

    protected function note(Visit $visit, string $text): void
    {
        Interaction::log($visit->client_id, $visit->lead_id, $visit->tenant_id, 'visit', $text . ' «' . ($visit->property?->title ?? '') . '».');
    }
}
