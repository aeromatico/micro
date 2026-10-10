<?php namespace Aero\Credits\Classes;

use Aero\Credits\Models\CreditCoupon;
use Aero\Credits\Models\Settings;
use DB;
use Illuminate\Database\QueryException;
use Str;

/**
 * Cupones Trial emitidos por API para clientes de un sistema asociado (el
 * caso de uso: clientes con productos activos en otra instalación reciben una
 * cuenta de prueba aquí). No crea ningún tenant ni clave: emite un cupón de
 * UN solo uso que la persona canjea ella misma en /comprar?promo=CÓDIGO, donde
 * elige su nombre y contraseña — misma ruta verificada que invitaciones y
 * regalos (SignupWizard → Coupons::preview/consume → Grants::apply).
 *
 * El plan y el periodo los fija el servidor (Settings de Créditos: plan con
 * prueba y días por defecto de las invitaciones); quien llama no los elige.
 * (source, external_ref) es único: un cliente = un Trial, para siempre.
 */
class PartnerTrials
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_REDEEMED = 'redeemed';
    public const STATUS_EXPIRED  = 'expired';

    /** Días que el cupón sigue canjeable desde que se emite o se re-arma. */
    protected const VALID_DAYS = 30;

    /**
     * Emite el cupón o devuelve el que ya existe para ese cliente.
     *
     * @return array{status:string,code:?string,link:?string,plan:?string,period:string,expires_at:?string,created:bool}
     */
    public static function issue(string $source, string $externalRef, ?string $label = null): array
    {
        $planId = Settings::defaultInvitePlanId();
        if (!$planId) {
            throw new \RuntimeException('No hay un plan Trial configurado.');
        }

        try {
            return DB::transaction(function () use ($source, $externalRef, $label, $planId) {
                $coupon = static::find($source, $externalRef, true);
                $created = false;

                if (!$coupon) {
                    $coupon = CreditCoupon::create([
                        'code'            => static::generateCode(),
                        'plan_id'         => $planId,
                        'period_unit'     => Settings::defaultInvitePeriodUnit(),
                        'period_count'    => Settings::defaultInvitePeriodCount(),
                        'max_redemptions' => 1,
                        'is_active'       => true,
                        'expires_at'      => now()->addDays(static::VALID_DAYS),
                        'note'            => 'Trial vía API — ' . Str::limit($label ?: $externalRef, 80, ''),
                        'source'          => $source,
                        'external_ref'    => $externalRef,
                    ]);
                    $created = true;
                }
                elseif (static::statusOf($coupon) === self::STATUS_EXPIRED) {
                    // Sigue siendo elegible (nunca lo canjeó): se re-arma la vigencia.
                    $coupon->expires_at = now()->addDays(static::VALID_DAYS);
                    $coupon->save();
                }

                return static::present($coupon, $created);
            });
        }
        catch (QueryException $e) {
            // Carrera contra la restricción única: el otro request ya lo creó.
            $coupon = static::find($source, $externalRef);
            if ($coupon) {
                return static::present($coupon, false);
            }

            throw $e;
        }
    }

    /** Estado de un cliente, o null si nunca se le emitió. */
    public static function status(string $source, string $externalRef): ?array
    {
        $coupon = static::find($source, $externalRef);

        return $coupon ? static::present($coupon, false) : null;
    }

    protected static function find(string $source, string $externalRef, bool $lock = false): ?CreditCoupon
    {
        $q = CreditCoupon::where('source', $source)->where('external_ref', $externalRef);

        return ($lock ? $q->lockForUpdate() : $q)->first();
    }

    protected static function statusOf(CreditCoupon $coupon): string
    {
        if ((int) $coupon->times_redeemed >= (int) $coupon->max_redemptions) {
            return self::STATUS_REDEEMED;
        }

        if (!$coupon->is_active || ($coupon->expires_at && $coupon->expires_at->isPast())) {
            return self::STATUS_EXPIRED;
        }

        return self::STATUS_PENDING;
    }

    protected static function present(CreditCoupon $coupon, bool $created): array
    {
        $status = static::statusOf($coupon);
        $open = $status === self::STATUS_PENDING;

        return [
            'status'     => $status,
            // El código solo se entrega mientras se puede canjear.
            'code'       => $open ? $coupon->code : null,
            'link'       => $open ? static::signupUrl($coupon->code) : null,
            'plan'       => $coupon->plan_name,
            'period'     => $coupon->period_count . ' ' . ['daily' => 'días', 'monthly' => 'meses', 'annual' => 'años'][$coupon->period_unit],
            'expires_at' => $coupon->expires_at?->toIso8601String(),
            'created'    => $created,
        ];
    }

    protected static function generateCode(): string
    {
        do {
            $code = 'TRIAL' . strtoupper(Str::random(7));
        } while (CreditCoupon::where('code', $code)->exists());

        return $code;
    }

    protected static function signupUrl(string $code): string
    {
        $base = class_exists(\Aero\Sites\Models\Settings::class)
            ? (\Aero\Sites\Models\Settings::get('public_signup_url') ?: url('/comprar'))
            : url('/comprar');

        return rtrim($base, '/') . '?promo=' . urlencode($code);
    }
}
