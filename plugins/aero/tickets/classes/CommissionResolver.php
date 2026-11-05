<?php namespace Aero\Tickets\Classes;

use Aero\Tickets\Models\CommissionRule;
use Aero\Tickets\Models\Event;
use Aero\Tickets\Models\Settings;
use Aero\Tickets\Models\TicketType;

/**
 * Condiciones comerciales de una entrada. Cada componente (porcentaje, monto fijo, quién paga) se toma del primer nivel
 * que lo defina: tipo de entrada → regla del evento → regla del organizador → valores de la plataforma.
 */
class CommissionResolver
{
    protected static array $rules = [];

    public static function flush(): void
    {
        self::$rules = [];
    }

    protected static function rule(string $scope, int $tenantId, ?int $eventId): ?CommissionRule
    {
        $k = "{$scope}:{$tenantId}:{$eventId}";
        if (!array_key_exists($k, self::$rules)) {
            $q = CommissionRule::where('scope', $scope)->where('tenant_id', $tenantId)->where('is_active', true);
            $scope === 'event' ? $q->where('event_id', $eventId) : $q->whereNull('event_id');
            self::$rules[$k] = $q->orderByDesc('id')->get()->first(fn ($r) => $r->appliesNow());
        }

        return self::$rules[$k];
    }

    /** @return array{percent: float, fixed: float, payer: string, source: array{percent:string,fixed:string,payer:string}, rule_ids: array} */
    public static function terms(TicketType $t): array
    {
        $event = Event::withTrashed()->find($t->event_id);
        $tenantId = (int) ($t->tenant_id ?: $event?->tenant_id);

        $levels = [
            'type' => [$t->fee_percent, $t->fee_fixed, $t->fee_payer],
        ];
        $ids = [];
        if ($event && ($r = self::rule('event', $tenantId, $event->id))) {
            $levels['event'] = [$r->percent, $r->fixed, $r->payer];
            $ids['event'] = $r->id;
        }
        if ($tenantId && ($r = self::rule('tenant', $tenantId, null))) {
            $levels['organizer'] = [$r->percent, $r->fixed, $r->payer];
            $ids['organizer'] = $r->id;
        }
        $levels['platform'] = [Settings::defaultFeePercent(), Settings::defaultFeeFixed(), Settings::defaultFeePayer()];

        $out = ['source' => [], 'rule_ids' => $ids];
        foreach (['percent' => 0, 'fixed' => 1, 'payer' => 2] as $key => $i) {
            foreach ($levels as $name => $vals) {
                if ($vals[$i] !== null && $vals[$i] !== '') {
                    $out[$key] = $vals[$i];
                    $out['source'][$key] = $name;
                    break;
                }
            }
        }
        $out['percent'] = (float) ($out['percent'] ?? 0);
        $out['fixed'] = (float) ($out['fixed'] ?? 0);
        $out['payer'] = ($out['payer'] ?? 'buyer') === 'organizer' ? 'organizer' : 'buyer';

        return $out;
    }
}
