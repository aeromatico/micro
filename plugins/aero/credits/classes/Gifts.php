<?php namespace Aero\Credits\Classes;

use Aero\Credits\Models\CreditCoupon;
use Aero\Credits\Models\CreditGift;
use DB;
use Str;

/**
 * Regalo de suscripción: pago del comprador (QR de aero/pay) → cupón de un
 * solo uso (max_redemptions = 1) → envío al destinatario con el enlace de
 * canje /comprar?promo=CÓDIGO. El canje lo hace Coupons/SignupWizard como
 * cualquier cupón.
 */
class Gifts
{
    /** Planes de pago y periodos regalables: [slug => [id,label,periods:[monthly=>Bs,annual=>Bs]]]. */
    public static function catalog(): array
    {
        if (!class_exists(\Aero\Sites\Classes\SignupPlans::class)) {
            return [];
        }

        $out = [];
        foreach (\Aero\Sites\Classes\SignupPlans::all() as $slug => $plan) {
            $periods = array_intersect_key($plan['periods'] ?? [], ['monthly' => 1, 'annual' => 1]);
            if ($periods) {
                $out[$slug] = [
                    'id' => $plan['id'], 'label' => $plan['label'], 'is_pro' => (bool) $plan['is_pro'],
                    'features' => $plan['features'] ?? [], 'periods' => $periods,
                ];
            }
        }

        return $out;
    }

    /**
     * Crea el regalo y su QR de cobro. Devuelve [gift, qrCode].
     *
     * @throws \RuntimeException con mensaje apto para mostrar al visitante
     */
    public static function create(string $planSlug, string $period, array $data): array
    {
        $catalog = static::catalog();
        if (!isset($catalog[$planSlug]['periods'][$period])) {
            throw new \RuntimeException('Elige un plan y periodo válidos.');
        }

        $channel = ($data['recipient_channel'] ?? '') === 'email' ? 'email' : 'whatsapp';
        $recipient = trim((string) ($data['recipient'] ?? ''));
        if ($channel === 'email' && !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Indica un correo válido para quien recibe el regalo.');
        }
        if ($channel === 'whatsapp') {
            $recipient = preg_replace('/\D+/', '', $recipient);
            if (strlen($recipient) < 8) {
                throw new \RuntimeException('Indica un número de WhatsApp válido para quien recibe el regalo (con código de país).');
            }
        }

        $bank = class_exists(\Aero\Sites\Models\Settings::class) ? \Aero\Sites\Models\Settings::getSignupBankAccount() : null;
        if (!$bank || !class_exists(\Aero\Pay\Classes\QrIssuer::class)) {
            throw new \RuntimeException('El cobro no está disponible en este momento. Intenta más tarde.');
        }

        $plan = $catalog[$planSlug];
        $amount = (float) $plan['periods'][$period];

        $gift = CreditGift::create([
            'plan_id'           => $plan['id'],
            'period_unit'       => $period,
            'period_count'      => 1,
            'amount_bob'        => $amount,
            'buyer_name'        => static::clip($data['buyer_name'] ?? null, 120),
            'buyer_contact'     => static::clip($data['buyer_contact'] ?? null, 120),
            'recipient_channel' => $channel,
            'recipient'         => $recipient,
            'recipient_name'    => static::clip($data['recipient_name'] ?? null, 120),
            'message'           => static::clip($data['message'] ?? null, 400),
            'status'            => CreditGift::PENDING,
            'expires_at'        => now()->addMinutes(30),
        ]);

        $qr = app(\Aero\Pay\Classes\QrIssuer::class)->issue(
            bankAccount: $bank,
            amount: $amount,
            currency: 'BOB',
            description: "Regalo Market — plan {$plan['label']} (" . Grants::periodLabel($period, 1) . ')',
            origin: 'gifts',
        );

        $gift->qr_code_id = $qr->id;
        $gift->payment_reference = $qr->internal_reference;
        $gift->save();

        return [$gift, $qr];
    }

    /** Pago confirmado: emite el cupón (una sola vez) y avisa al destinatario. */
    public static function settle(CreditGift $gift): void
    {
        $coupon = null;

        DB::transaction(function () use ($gift, &$coupon) {
            $locked = CreditGift::whereKey($gift->id)->lockForUpdate()->first();
            if (!$locked || $locked->coupon_id) {
                return;
            }

            $coupon = CreditCoupon::create([
                'code'            => static::generateCode(),
                'plan_id'         => $locked->plan_id,
                'period_unit'     => $locked->period_unit,
                'period_count'    => $locked->period_count,
                'max_redemptions' => 1,
                'is_active'       => true,
                'note'            => "Regalo #{$locked->id}",
            ]);

            $locked->coupon_id = $coupon->id;
            $locked->status = CreditGift::PAID;
            $locked->paid_at = now();
            $locked->save();
        });

        if ($coupon) {
            static::deliver($gift->fresh());
        }
    }

    public static function deliver(CreditGift $gift): void
    {
        $coupon = $gift->coupon;
        if (!$coupon || $gift->delivered_at) {
            return;
        }

        $plan = class_exists(\Aero\Sites\Models\Plan::class) ? \Aero\Sites\Models\Plan::find($gift->plan_id) : null;
        $planName = $plan->name ?? 'Market';
        $from = $gift->buyer_name ?: 'Alguien';
        $to = $gift->recipient_name ? " {$gift->recipient_name}" : '';
        $duration = Grants::periodLabel($gift->period_unit, (int) $gift->period_count);
        $base = class_exists(\Aero\Sites\Models\Settings::class)
            ? (\Aero\Sites\Models\Settings::get('public_signup_url') ?: url('/comprar'))
            : url('/comprar');
        $link = rtrim($base, '/') . '?promo=' . urlencode($coupon->code);

        $text = "¡Hola{$to}! {$from} te regaló una suscripción a Market: plan {$planName} por {$duration}.";
        if ($gift->message) {
            $text .= "\n\"{$gift->message}\"";
        }
        $text .= "\nCanjéala aquí: {$link}\nTu código: {$coupon->code}";

        try {
            if ($gift->recipient_channel === 'whatsapp' && class_exists(\Aero\Hello\Classes\Hello::class)) {
                \Aero\Hello\Classes\Hello::send($gift->recipient, $text, ['platform' => 'whatsapp']);
            }
            elseif ($gift->recipient_channel === 'email') {
                \Mail::raw($text, fn ($m) => $m->to($gift->recipient)->subject("{$from} te regaló una suscripción a Market"));
            }

            $gift->delivered_at = now();
            $gift->save();
        }
        catch (\Throwable $e) {
            \Log::error("Aero.Credits: fallo al entregar el regalo #{$gift->id}: " . $e->getMessage());
        }
    }

    protected static function generateCode(): string
    {
        do {
            $code = 'GIFT' . strtoupper(Str::random(6));
        } while (CreditCoupon::where('code', $code)->exists());

        return $code;
    }

    protected static function clip($v, int $max): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : mb_substr($v, 0, $max);
    }
}
