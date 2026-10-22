<?php namespace Aero\Realestate\Classes;

use Aero\Realestate\Models\Agreement;
use Aero\Realestate\Models\AgreementEvent;
use Aero\Realestate\Models\AgreementTemplate;
use Aero\Realestate\Models\Offer;
use Aero\Realestate\Models\Property;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de vida del contrato: borrador → en revisión → aprobado → firmado → finalizado (o cancelado).
 * «Aprobado» y «firmado» exigen que la plantilla tenga revisión legal vigente: sin ella solo hay borradores.
 */
class AgreementService
{
    /** Crea el contrato en borrador con el texto de la plantilla ya rellenado. */
    public function generate(array $data): Agreement
    {
        $property = Property::findOrFail($data['property_id']);
        $template = AgreementTemplate::available($property->tenant_id)->find($data['template_id'] ?? 0);
        if (!$template) {
            throw new RealestateException('Elige una plantilla activa para el tipo de contrato.');
        }
        $type = $data['type'] ?? $template->type;
        if ($template->type !== $type) {
            throw new RealestateException('La plantilla no es de ese tipo de contrato.');
        }

        $offer = !empty($data['offer_id']) ? Offer::where('property_id', $property->id)->find($data['offer_id']) : null;

        $a = new Agreement();
        $a->forceFill(array_filter([
            'tenant_id' => $property->tenant_id, 'type' => $type, 'template_id' => $template->id, 'property_id' => $property->id,
            'offer_id' => $offer?->id, 'owner_id' => $data['owner_id'] ?? null, 'client_id' => $data['client_id'] ?? null, 'agent_id' => $data['agent_id'] ?? null,
            'contract_id' => $data['contract_id'] ?? null, 'lead_id' => $data['lead_id'] ?? null,
            'amount' => $data['amount'] ?? $offer?->amount ?? 0, 'total_price' => $data['total_price'] ?? null, 'currency' => $data['currency'] ?? $offer?->currency ?? 'USD',
            'deposit' => $data['deposit'] ?? $offer?->deposit, 'term_months' => $data['term_months'] ?? $offer?->term_months,
            'start_date' => $data['start_date'] ?? null, 'end_date' => $data['end_date'] ?? null,
            'variables' => $data['variables'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''));
        $a->title = $data['title'] ?? (AgreementTemplate::$types[$type] . ' — ' . $property->title);
        $a->template_version = $template->version;
        $a->template_reviewed = $template->is_reviewed;
        $a->status = 'draft';
        $a->save();

        $this->render($a, $template);
        AgreementEvent::log($a, 'generated', 'Borrador generado desde «' . $template->name . '» (v' . $template->version . ($template->is_reviewed ? ', revisada legalmente' : ', SIN revisión legal') . ').');
        \Event::fire('aero.realestate.agreementGenerated', [$a]);

        return $a;
    }

    /** Vuelve a rellenar el texto con los datos actuales (pierde las ediciones manuales del texto). */
    public function regenerate(Agreement $a): Agreement
    {
        $this->assertStatus($a, Agreement::EDITABLE, 'Solo un borrador o contrato en revisión se puede regenerar.');
        $template = AgreementTemplate::find($a->template_id);
        if (!$template) {
            throw new RealestateException('La plantilla ya no existe.');
        }
        $a->internal = true;
        $a->forceFill(['template_version' => $template->version, 'template_reviewed' => $template->is_reviewed])->save();
        $this->render($a, $template);
        AgreementEvent::log($a, 'regenerated', 'Texto regenerado desde la plantilla (v' . $template->version . ').');

        return $a;
    }

    protected function render(Agreement $a, AgreementTemplate $template): void
    {
        $a->internal = true;
        $a->forceFill(['body' => TemplateRenderer::render($template->body, DocumentContext::build($a->refresh()))])->save();
    }

    public function submitForReview(Agreement $a): Agreement
    {
        $this->assertStatus($a, ['draft'], 'Solo un borrador se envía a revisión.');

        return $this->apply($a, ['status' => 'in_review'], 'in_review', 'Enviado a revisión.');
    }

    public function approve(Agreement $a): Agreement
    {
        $this->assertStatus($a, ['in_review', 'draft'], 'Solo un contrato en borrador o revisión se aprueba.');
        $this->assertReviewedTemplate($a);
        $this->assertComplete($a);

        return $this->apply($a, ['status' => 'approved'], 'approved', 'Contrato aprobado.');
    }

    /** Registra la firma. Un alquiler necesita inicio y vencimiento (de ahí salen las alertas). */
    public function sign(Agreement $a, $signedOn = null): Agreement
    {
        $this->assertStatus($a, ['approved'], 'Solo un contrato aprobado se marca como firmado.');
        $this->assertReviewedTemplate($a);
        if ($a->type === 'rent' && (!$a->start_date || !$a->end_date)) {
            throw new RealestateException('Un alquiler necesita fecha de inicio y de vencimiento para firmarse.');
        }

        $on = \Carbon\Carbon::parse($signedOn ?: now())->startOfDay();

        return $this->apply($a, ['status' => 'signed', 'signed_on' => $on, 'last_alert_days' => null], 'signed', 'Firmado el ' . $on->format('d/m/Y') . '.', null, 'aero.realestate.agreementSigned');
    }

    /** Reabre un contrato aprobado para corregirlo: vuelve a borrador y exigirá aprobarse de nuevo. */
    public function reopen(Agreement $a, string $reason): Agreement
    {
        $this->assertStatus($a, ['approved'], 'Solo un contrato aprobado (sin firmar) se reabre.');
        if (trim($reason) === '') {
            throw new RealestateException('Indica por qué se reabre.');
        }

        return $this->apply($a, ['status' => 'draft'], 'reopened', 'Reabierto para corrección: ' . $reason);
    }

    /** Alquiler firmado: extiende el plazo (y opcionalmente cambia el canon). */
    public function renew(Agreement $a, int $months, ?float $newAmount = null, ?string $note = null): Agreement
    {
        $this->assertStatus($a, ['signed'], 'Solo un contrato firmado se renueva.');
        if ($a->type === 'arras') {
            throw new RealestateException('Las arras no se renuevan: se extiende el plazo editando el contrato en reapertura.');
        }
        if ($months < 1 || $months > 600 || !$a->end_date) {
            throw new RealestateException('Indica cuántos meses se extiende (y el contrato necesita fecha de vencimiento).');
        }

        $old = $a->end_date->copy();
        $changes = ['term_months' => (int) $a->term_months + $months, 'end_date' => $old->copy()->addMonthsNoOverflow($months), 'last_alert_days' => null];
        $data = ['months' => $months, 'old_end_date' => $old->toDateString(), 'new_end_date' => $changes['end_date']->toDateString(), 'note' => $note];
        if ($newAmount !== null && abs($newAmount - (float) $a->amount) > 0.001) {
            $data['old_amount'] = (float) $a->amount;
            $data['new_amount'] = $newAmount;
            $changes['amount'] = $newAmount;
        }

        return $this->apply($a, $changes, 'renewed', 'Renovado por ' . $months . ' ' . ($months === 1 ? 'mes' : 'meses') . ' hasta el ' . $changes['end_date']->format('d/m/Y')
            . (isset($changes['amount']) ? ' (monto ' . number_format($data['old_amount'], 2, ',', '.') . ' → ' . number_format($newAmount, 2, ',', '.') . ')' : '') . '.', null, 'aero.realestate.agreementRenewed', $data);
    }

    public function finish(Agreement $a): Agreement
    {
        $this->assertStatus($a, ['signed'], 'Solo un contrato firmado se finaliza.');

        return $this->apply($a, ['status' => 'finished'], 'finished', 'Contrato finalizado.');
    }

    public function cancel(Agreement $a, string $reason): Agreement
    {
        $this->assertStatus($a, ['draft', 'in_review', 'approved'], 'Un contrato firmado no se cancela: se finaliza.');

        return $this->apply($a, ['status' => 'cancelled', 'cancel_reason' => $reason], 'cancelled', 'Contrato cancelado' . ($reason ? ': ' . $reason : '.'));
    }

    /** Aprobar un contrato exige que la plantilla de la que salió tenga revisión legal. */
    protected function assertReviewedTemplate(Agreement $a): void
    {
        $template = AgreementTemplate::find($a->template_id);
        if (!$template || !$template->is_reviewed || (int) $template->version !== (int) $a->template_version) {
            throw new RealestateException('La plantilla no tiene revisión legal vigente (o cambió después de generar el contrato). Haz revisar la plantilla y regenera el texto, o aprueba el borrador solo cuando un abogado lo haya validado.');
        }
    }

    protected function assertComplete(Agreement $a): void
    {
        $missing = [];
        $a->owner_id || $missing[] = 'propietario';
        $a->client_id || $missing[] = 'cliente';
        (float) $a->amount > 0 || $missing[] = 'monto';
        if ($missing) {
            throw new RealestateException('Falta completar: ' . implode(', ', $missing) . '.');
        }
        if (str_contains((string) $a->body, 'variable desconocida')) {
            throw new RealestateException('El texto tiene variables desconocidas marcadas en rojo: corrígelas antes de aprobar.');
        }
    }

    protected function assertStatus(Agreement $a, array $allowed, string $message): void
    {
        if (!in_array($a->status, $allowed, true)) {
            throw new RealestateException($message);
        }
    }

    protected function apply(Agreement $a, array $changes, string $type, string $summary, ?callable $after = null, ?string $event = null, array $data = []): Agreement
    {
        DB::transaction(function () use ($a, $changes, $type, $summary, $after, $data) {
            $a->internal = true;
            $a->forceFill($changes)->save();
            $after && $after();
            AgreementEvent::log($a, $type, $summary, $data);
        });
        $event && \Event::fire($event, [$a->refresh()]);

        return $a;
    }
}
