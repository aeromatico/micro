<?php namespace Aero\Realestate\Classes;

use Aero\Realestate\Models\Agreement;
use Aero\Realestate\Models\Property;
use Aero\Realestate\Models\RealestateSettings;

/** Variables de un contrato a partir de sus datos reales; lo que el sistema no tiene queda vacío (línea en blanco). */
class DocumentContext
{
    public static function money($n): string
    {
        return $n === null || $n === '' ? '' : number_format((float) $n, 2, ',', '.');
    }

    public static function longDate($d): string
    {
        return $d ? \Carbon\Carbon::parse($d)->locale('es')->translatedFormat('j \d\e F \d\e Y') : '';
    }

    public static function build(Agreement $a): array
    {
        $a->loadMissing(['property', 'owner', 'client', 'agent']);
        $p = $a->property;
        $o = $a->owner;
        $c = $a->client;
        $v = (array) $a->variables;
        $tenant = null;
        try {
            $tenant = class_exists(\Aero\Sites\Models\Tenant::class) ? \Aero\Sites\Models\Tenant::find($a->tenant_id) : null;
        } catch (\Throwable $e) {
            // sin nombre de tenant
        }
        $settings = RealestateSettings::forTenant($a->tenant_id);

        $amount = (float) $a->amount;
        $currency = $a->currency;

        return [
            'owner.name' => $o?->name, 'owner.document' => $o?->document, 'owner.address' => $o?->address,
            'owner.civil_status' => $o?->civil_status, 'owner.nationality' => $o?->nationality,
            'client.name' => $c?->name, 'client.document' => $c?->document, 'client.address' => $c?->address,
            'client.civil_status' => $c?->civil_status, 'client.nationality' => $c?->nationality,
            'agent.name' => $a->agent?->name, 'business_name' => $settings->business_name ?: $tenant?->name,
            'agent_line' => $a->agent ? ' · Agente: ' . $a->agent->name : '',
            'property.title' => $p?->title, 'property.code' => $p?->code, 'property.type' => $p ? mb_strtolower($p->type_name) : null,
            'property.address' => $p?->address, 'property.zone' => $p?->zone, 'property.city' => $p?->city, 'property.department' => $p?->department,
            'property.land_area' => $p?->land_area ? rtrim(rtrim(number_format((float) $p->land_area, 2, ',', '.'), '0'), ',') : null,
            'property.built_area' => $p?->built_area ? rtrim(rtrim(number_format((float) $p->built_area, 2, ',', '.'), '0'), ',') : null,
            'folio_real' => $v['folio_real'] ?? null,
            'amount' => $amount > 0 ? self::money($amount) : null, 'amount_words' => $amount > 0 ? AmountInWords::money($amount, $currency) : null,
            'total_price' => $a->total_price ? self::money($a->total_price) : null, 'total_price_words' => $a->total_price ? AmountInWords::money((float) $a->total_price, $currency) : null,
            'deposit' => $a->deposit ? self::money($a->deposit) : null, 'deposit_words' => $a->deposit ? AmountInWords::money((float) $a->deposit, $currency) : null,
            'currency' => $currency, 'term_months' => $a->term_months ? (string) $a->term_months : null,
            'start_date' => self::longDate($a->start_date), 'end_date' => self::longDate($a->end_date),
            'code' => $a->code, 'place' => $v['place'] ?? $p?->city, 'today' => self::longDate(now()), 'extra_clauses' => $v['extra_clauses'] ?? null,
        ];
    }
}
