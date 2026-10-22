<?php namespace Aero\Realestate\Classes;

use Aero\Realestate\Models\Contract;
use Aero\Realestate\Models\ContractDocument;
use Aero\Realestate\Models\ContractEvent;
use Aero\Realestate\Models\Offer;
use Aero\Realestate\Models\Payment;
use Aero\Realestate\Models\Property;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de vida del anticrético: borrador → reservado → vigente → finalizado (o cancelado).
 * Es el único lugar que cambia estado, fechas de vigencia y el estado de propiedad/oferta.
 */
class AnticreticoService
{
    public function reserve(Contract $c): Contract
    {
        $this->assertStatus($c, ['draft'], 'Solo un borrador se puede reservar.');
        $this->assertParties($c);

        return $this->apply($c, ['status' => 'reserved'], 'reserved', 'Contrato reservado: la propiedad queda apartada.', function () use ($c) {
            Property::whereKey($c->property_id)->whereIn('status', ['available'])->update(['status' => 'reserved']);
            $c->offer_id && Offer::whereKey($c->offer_id)->where('status', 'available')->update(['status' => 'reserved']);
        });
    }

    /** Pasa a vigente: exige partes, el capital completo entregado y fecha de inicio. */
    public function activate(Contract $c, $startDate = null): Contract
    {
        $this->assertStatus($c, ['draft', 'reserved'], 'Solo un contrato en borrador o reservado se puede activar.');
        $this->assertParties($c);

        $start = Carbon::parse($startDate ?: $c->start_date ?: now())->startOfDay();
        if ((float) $c->delivered_amount + 0.001 < (float) $c->capital) {
            throw new RealestateException('Registra primero la entrega del capital completo (' . number_format((float) $c->capital, 2, ',', '.') . ' ' . $c->currency . '). Entregado: ' . number_format((float) $c->delivered_amount, 2, ',', '.') . '.');
        }

        $end = $start->copy()->addMonthsNoOverflow((int) $c->term_months);

        return $this->apply($c, [
            'status' => 'active', 'start_date' => $start, 'end_date' => $end, 'activated_at' => now(), 'last_alert_days' => null,
        ], 'activated', 'Contrato vigente del ' . $start->format('d/m/Y') . ' al ' . $end->format('d/m/Y') . '.', function () use ($c) {
            Property::whereKey($c->property_id)->update(['status' => 'anticretico']);
            $c->offer_id && Offer::whereKey($c->offer_id)->update(['status' => 'closed', 'closed_at' => now()]);
        }, 'aero.realestate.anticreticoActivated');
    }

    /** Extiende el plazo (y opcionalmente cambia el capital pactado). */
    public function renew(Contract $c, int $months, ?float $newCapital = null, ?string $note = null): Contract
    {
        $this->assertStatus($c, ['active'], 'Solo un contrato vigente se puede renovar.');
        if ($months < 1 || $months > 600) {
            throw new RealestateException('El plazo de renovación debe ser de 1 mes o más.');
        }

        $oldEnd = $c->end_date->copy();
        $changes = [
            'term_months' => (int) $c->term_months + $months,
            'end_date'    => $oldEnd->copy()->addMonthsNoOverflow($months),
            'last_alert_days' => null,
        ];
        $data = ['months' => $months, 'old_end_date' => $oldEnd->toDateString(), 'new_end_date' => $changes['end_date']->toDateString(), 'note' => $note];

        if ($newCapital !== null && abs($newCapital - (float) $c->capital) > 0.001) {
            if ($newCapital + 0.001 < (float) $c->returned_amount) {
                throw new RealestateException('El nuevo capital no puede ser menor a lo ya devuelto.');
            }
            $data['old_capital'] = (float) $c->capital;
            $data['new_capital'] = $newCapital;
            $changes['capital'] = $newCapital;
        }

        $summary = 'Renovado por ' . $months . ' ' . ($months === 1 ? 'mes' : 'meses') . ' hasta el ' . $changes['end_date']->format('d/m/Y')
            . (isset($changes['capital']) ? ' (capital ' . number_format($data['old_capital'], 2, ',', '.') . ' → ' . number_format($newCapital, 2, ',', '.') . ')' : '') . '.';

        return $this->apply($c, $changes, 'renewed', $summary, null, 'aero.realestate.anticreticoRenewed', $data);
    }

    public function markDispute(Contract $c, string $note): Contract
    {
        $this->assertStatus($c, ['active', 'finished'], 'Solo un contrato vigente o finalizado puede entrar en disputa.');
        if (trim($note) === '') {
            throw new RealestateException('Describe el motivo de la disputa.');
        }

        return $this->apply($c, ['in_dispute' => true, 'dispute_note' => $note], 'dispute', 'Devolución en disputa: ' . $note, fn () => $this->recalc($c->refresh()));
    }

    public function resolveDispute(Contract $c, ?string $note = null): Contract
    {
        if (!$c->in_dispute) {
            throw new RealestateException('El contrato no está en disputa.');
        }

        return $this->apply($c, ['in_dispute' => false], 'dispute_resolved', 'Disputa resuelta' . ($note ? ': ' . $note : '.'), fn () => $this->recalc($c->refresh()));
    }

    /** Cierra el contrato: exige la devolución completa del capital. */
    public function finish(Contract $c): Contract
    {
        $this->assertStatus($c, ['active'], 'Solo un contrato vigente se puede finalizar.');
        $this->recalc($c);
        $c->refresh();
        if ($c->return_status !== 'completed') {
            throw new RealestateException('Faltan por devolver ' . number_format($c->pending_amount, 2, ',', '.') . ' ' . $c->currency . ' (' . mb_strtolower($c->return_status_name) . ').');
        }

        return $this->apply($c, ['status' => 'finished', 'finished_at' => now()], 'finished', 'Contrato finalizado con el capital devuelto.', function () use ($c) {
            Property::whereKey($c->property_id)->where('status', 'anticretico')->update(['status' => 'available']);
        }, 'aero.realestate.anticreticoFinished');
    }

    /** Anula un contrato que aún no entró en vigencia y libera la propiedad y la oferta. */
    public function cancel(Contract $c, string $reason): Contract
    {
        $this->assertStatus($c, ['draft', 'reserved'], 'Un contrato vigente no se cancela: se finaliza con la devolución del capital.');
        if ((float) $c->delivered_amount > 0 && (float) $c->returned_amount + 0.001 < (float) $c->delivered_amount) {
            throw new RealestateException('Ya hay capital entregado: devuélvelo antes de cancelar.');
        }

        return $this->apply($c, ['status' => 'cancelled'], 'cancelled', 'Contrato cancelado' . ($reason ? ': ' . $reason : '.'), function () use ($c) {
            Property::whereKey($c->property_id)->where('status', 'reserved')->update(['status' => 'available']);
            $c->offer_id && Offer::whereKey($c->offer_id)->where('status', 'reserved')->update(['status' => 'available']);
        });
    }

    /** Recalcula lo entregado/devuelto y el estado de la devolución a partir del libro de movimientos. */
    public function recalc(Contract $c): Contract
    {
        $delivered = (float) Payment::where('contract_id', $c->id)->where('type', 'delivery')->sum('amount');
        $returned = (float) Payment::where('contract_id', $c->id)->where('type', 'return')->sum('amount');

        $status = $c->in_dispute ? 'dispute'
            : ($returned <= 0 ? 'pending' : ($returned + 0.001 >= $delivered && $delivered > 0 ? 'completed' : 'partial'));

        Contract::whereKey($c->id)->update(['delivered_amount' => $delivered, 'returned_amount' => $returned, 'return_status' => $status]);

        return $c->refresh();
    }

    /** Documentos obligatorios (escritura, Derechos Reales) que aún no están verificados. */
    public function missingDocuments(Contract $c): array
    {
        $verified = ContractDocument::where('contract_id', $c->id)->where('status', 'verified')->pluck('type')->all();

        return array_values(array_diff(ContractDocument::REQUIRED, $verified));
    }

    protected function assertStatus(Contract $c, array $allowed, string $message): void
    {
        if (!in_array($c->status, $allowed, true)) {
            throw new RealestateException($message);
        }
    }

    protected function assertParties(Contract $c): void
    {
        if (!$c->client_id || !$c->owner_id) {
            throw new RealestateException('El contrato necesita propietario y anticresista (el propietario sale de la propiedad si lo tiene asignado).');
        }
    }

    /** Aplica los cambios en una transacción, registra la bitácora y avisa por evento. */
    protected function apply(Contract $c, array $changes, string $type, string $summary, ?callable $after = null, ?string $event = null, array $data = []): Contract
    {
        DB::transaction(function () use ($c, $changes, $type, $summary, $after, $data) {
            $c->internal = true;
            $c->forceFill($changes)->save();
            $after && $after();
            ContractEvent::log($c, $type, $summary, $data);
        });

        $event && \Event::fire($event, [$c->refresh()]);

        return $c;
    }
}
