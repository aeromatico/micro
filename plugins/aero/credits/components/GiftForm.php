<?php namespace Aero\Credits\Components;

use Aero\Credits\Classes\Gifts;
use Aero\Credits\Models\CreditGift;
use Cms\Classes\ComponentBase;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Página pública /regalar: elegir plan y periodo, datos de quien recibe, pago
 * QR y (por polling) confirmación. Sin sesión — el comprador es anónimo.
 */
class GiftForm extends ComponentBase
{
    public function componentDetails(): array
    {
        return [
            'name'        => 'Regalar suscripción',
            'description' => 'Formulario público para regalar una suscripción pagando un QR.',
        ];
    }

    public function onRun(): void
    {
        $this->page['giftConfigJson'] = json_encode([
            'plans' => Gifts::catalog(),
        ], JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG);
    }

    public function onCreateGift(): array
    {
        $key = 'gift-create:' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return ['success' => false, 'message' => 'Demasiados intentos. Intenta en ' . RateLimiter::availableIn($key) . 's.'];
        }
        RateLimiter::hit($key, 3600);

        try {
            [$gift, $qr] = Gifts::create((string) post('plan'), (string) post('period'), [
                'buyer_name'        => post('buyer_name'),
                'buyer_contact'     => post('buyer_contact'),
                'recipient_channel' => post('recipient_channel'),
                'recipient'         => post('recipient'),
                'recipient_name'    => post('recipient_name'),
                'message'           => post('message'),
            ]);
        }
        catch (\RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return [
            'success'    => true,
            'gift_id'    => $gift->id,
            'reference'  => $gift->payment_reference,
            'amount'     => $gift->amount_bob,
            'expires_at' => $gift->expires_at->toIso8601String(),
            'qr_image'   => $qr->qr_image ? 'data:image/png;base64,' . $qr->qr_image : null,
        ];
    }

    public function onCheckGiftStatus(): array
    {
        $gift = CreditGift::find((int) post('gift_id'));

        if (!$gift || $gift->payment_reference !== post('reference')) {
            return ['status' => 'not_found'];
        }

        if ($gift->status === CreditGift::PAID) {
            return ['status' => 'paid', 'delivered' => (bool) $gift->delivered_at];
        }

        if ($gift->expires_at && $gift->expires_at->isPast()) {
            return ['status' => 'expired'];
        }

        return ['status' => 'pending'];
    }
}
