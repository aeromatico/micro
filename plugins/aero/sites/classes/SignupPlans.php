<?php namespace Aero\Sites\Classes;

use Aero\Sites\Models\Plan;

/**
 * Catálogo de planes ofrecidos en el alta pública (/alta, tema master) y en
 * la invitación a mejorar el plan. Se gestiona desde Sites → Planes.
 */
class SignupPlans
{
    public static function all(): array
    {
        $out = [];

        foreach (Plan::active()->orderBy('sort_order')->get() as $plan) {
            $out[$plan->code] = static::toArray($plan);
        }

        return $out;
    }

    public static function find(string $id): ?array
    {
        $plan = Plan::active()->where('code', $id)->first();

        return $plan ? static::toArray($plan) : null;
    }

    public static function exists(string $id): bool
    {
        return Plan::active()->where('code', $id)->exists();
    }

    public static function labels(): array
    {
        return array_map(fn ($plan) => $plan['label'], static::all());
    }

    protected static function toArray(Plan $plan): array
    {
        return [
            'id'          => $plan->id,
            'label'       => $plan->name,
            'price'       => $plan->price,
            'trial_days'  => $plan->trial_days,
            'periods'     => $plan->periods(),
            'description' => $plan->description,
            'featured'    => $plan->is_featured,
            'is_pro'      => $plan->is_pro,
            'features'    => $plan->featureList(),
            'credits'     => static::creditBadges($plan),
        ];
    }

    /** [['label' => 'Azul', 'color' => '#..', 'amount' => 500], ...] para mostrar en el front. */
    protected static function creditBadges(Plan $plan): array
    {
        if (!class_exists(\Aero\Credits\Models\CreditType::class)) {
            return [];
        }

        $types = \Aero\Credits\Models\CreditType::active()->get()->keyBy('code');
        $out = [];

        foreach ($plan->creditsByType() as $code => $amount) {
            if ($type = $types->get($code)) {
                $out[] = ['label' => $type->label, 'color' => $type->color, 'amount' => $amount];
            }
        }

        return $out;
    }
}
