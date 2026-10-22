<?php namespace Aero\Realestate\Classes;

use Aero\Realestate\Models\Commission;
use Aero\Realestate\Models\CommissionRule;
use Aero\Realestate\Models\Contract;
use Aero\Realestate\Models\Interaction;
use Aero\Realestate\Models\Lead;
use Aero\Realestate\Models\Offer;
use Aero\Realestate\Models\Property;
use Illuminate\Support\Facades\DB;

/**
 * Cierre de una operación: la oportunidad pasa a «operación cerrada», la oferta y la propiedad cambian de estado
 * (venta → vendida, alquiler → alquilada) y se calcula la comisión con la regla vigente. Para el anticrético
 * el estado de la propiedad lo maneja el contrato; aquí solo se vincula y se calcula la comisión.
 */
class DealService
{
    public function close(Lead $lead, Offer $offer, ?float $finalAmount = null): Lead
    {
        if (!$lead->is_open) {
            throw new RealestateException('La oportunidad ya está cerrada.');
        }
        if ($offer->tenant_id !== $lead->tenant_id) {
            throw new RealestateException('La oferta no pertenece a esta inmobiliaria.');
        }
        if ($lead->property_id && $offer->property_id !== $lead->property_id) {
            throw new RealestateException('La oferta es de otra propiedad.');
        }
        if (in_array($offer->status, ['closed', 'inactive'], true)) {
            throw new RealestateException('La oferta ya no está disponible.');
        }

        $amount = $finalAmount !== null ? $finalAmount : (float) $offer->amount;
        if ($amount <= 0) {
            throw new RealestateException('El monto final debe ser mayor a cero.');
        }

        DB::transaction(function () use ($lead, $offer, $amount) {
            $lead->forceFill([
                'stage' => 'closed_won', 'offer_id' => $offer->id, 'property_id' => $offer->property_id, 'modality' => $offer->modality,
                'final_amount' => $amount, 'closed_at' => now(), 'next_follow_up_at' => null,
            ])->save();

            if ($offer->modality !== 'anticretico') {
                $offer->forceFill(['status' => 'closed'])->save();
                Property::whereKey($offer->property_id)->update(['status' => $offer->modality === 'sale' ? 'sold' : 'rented']);
            }

            $commission = $this->commissionFor($lead, $offer, $amount);
            Interaction::log($lead->client_id, $lead->id, $lead->tenant_id, 'deal',
                'Operación cerrada: ' . $offer->modality_name . ' por ' . number_format($amount, 2, ',', '.') . ' ' . $offer->currency
                . ($commission ? '. Comisión ' . number_format((float) $commission->commission_amount, 2, ',', '.') . ' ' . $offer->currency . '.' : '. Sin regla de comisión.'));
        });

        \Event::fire('aero.realestate.dealClosed', [$lead->refresh()]);

        return $lead;
    }

    /** Calcula y guarda la comisión; null si no hay regla vigente para esa modalidad. */
    public function commissionFor(Lead $lead, Offer $offer, float $amount): ?Commission
    {
        $rule = CommissionRule::resolve($lead->tenant_id, $offer->modality, $lead->agent_id);
        if (!$rule) {
            return null;
        }

        $total = round(match ($rule->basis) {
            'percent'       => $amount * (float) $rule->value / 100,
            'monthly_rents' => (float) $offer->amount * (float) $rule->value, // N meses del alquiler mensual de la oferta, no el monto final de cierre
            default         => (float) $rule->value,
        }, 2);
        $share = $lead->agent_id ? (float) $rule->agent_share_percent : 0.0;

        $contractId = $offer->modality === 'anticretico' ? Contract::where('offer_id', $offer->id)->orderByDesc('id')->value('id') : null;

        $commission = new Commission();
        $commission->forceFill([
            'tenant_id' => $lead->tenant_id, 'lead_id' => $lead->id, 'offer_id' => $offer->id, 'contract_id' => $contractId,
            'property_id' => $offer->property_id, 'client_id' => $lead->client_id, 'agent_id' => $lead->agent_id,
            'modality' => $offer->modality, 'base_amount' => $amount, 'currency' => $offer->currency, 'rule_text' => $rule->describe(),
            'commission_amount' => $total, 'agent_share_percent' => $share, 'agent_amount' => round($total * $share / 100, 2), 'status' => 'pending',
        ])->save();

        return $commission;
    }
}
