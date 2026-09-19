<?php namespace Aero\Chat\Http\Controllers;

use Aero\Chat\Classes\ChargeSettler;
use Aero\Chat\Models\ChatCharge;
use Aero\Chat\Models\ChatEvent;
use Aero\Hello\Classes\ApiCredits;
use Aero\Hello\Classes\MessageComposer;
use Aero\Hello\Models\Account;
use Aero\Hello\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Cobro rápido por QR desde una conversación, con las cuentas bancarias de
 * Aero.Pay del propio tenant. Independiente del CRM: no necesita contacto ni
 * cobranza previa.
 */
class PayController extends Controller
{
    use \Aero\Chat\Classes\ValidatesJson;

    /** Vencimientos que ofrece la PWA (días). La API acepta cualquier valor 1–365. */
    public const PRESETS = [1 => '1 día', 7 => '1 semana', 365 => '1 año'];

    /** GET conversations/{id}/pay — cuentas disponibles y cobros de esta conversación. */
    public function show(Request $request, $id)
    {
        [$conv, $err] = $this->conversation($request, $id);
        if ($err) {
            return $err;
        }

        return $this->ok($this->state($request, $conv));
    }

    /** POST conversations/{id}/pay/charge {amount, description, days, bank_account_id?, notify_on_paid?} */
    public function charge(Request $request, $id)
    {
        [$conv, $err] = $this->conversation($request, $id);
        if ($err) {
            return $err;
        }

        if (!class_exists(\Aero\Pay\Classes\QrIssuer::class)) {
            return $this->fail('pay_unavailable', 'Cobros con QR no está disponible.', 422);
        }

        $data = $this->check($request, [
            'amount'          => 'required|numeric|min:0.01|max:9999999',
            'description'     => 'required|string|max:100',
            'days'            => 'required|integer|min:1|max:365',
            'bank_account_id' => 'nullable|integer',
            'notify_on_paid'  => 'nullable|boolean',
        ]);

        $tenantId = $this->tenantId($request);
        $bank = $this->banks($tenantId)->when($data['bank_account_id'] ?? null, fn ($c, $bid) => $c->where('id', $bid))->first();
        if (!$bank) {
            return $this->fail('no_bank_account', 'Este espacio no tiene una cuenta bancaria activa en Pay.', 422);
        }

        // Se emite primero y se cobra el envío después: si el banco rechaza el
        // QR no se gasta un mensaje.
        try {
            $qr = app(\Aero\Pay\Classes\QrIssuer::class)->issue(
                bankAccount: $bank,
                amount: (float) $data['amount'],
                currency: 'BOB',
                description: $data['description'],
                externalReference: 'chat-' . $conv->id . '-' . now()->timestamp,
                origin: 'chat',
                dueDate: now()->addDays((int) $data['days'])->toDateString(),
            );
        } catch (\Throwable $e) {
            return $this->fail('qr_failed', 'El banco no pudo generar el QR: ' . $e->getMessage(), 422);
        }

        $charge = ChatCharge::create([
            'tenant_id' => $tenantId, 'conversation_id' => $conv->id, 'user_id' => $request->attributes->get('chat_user')->id,
            'qr_code_id' => $qr->id, 'amount' => $data['amount'], 'currency' => 'BOB', 'description' => $data['description'],
            'notify_on_paid' => (bool) ($data['notify_on_paid'] ?? true), 'due_at' => $qr->due_date,
        ]);

        if (!$qr->qr_image) {
            return $this->fail('no_image', 'El QR se generó pero el banco no devolvió imagen. Revisa la cuenta en Pay.', 422);
        }

        $amount = ChargeSettler::money($charge->amount, $charge->currency);
        $days = (int) $data['days'];
        $body = $data['description'] . "\nMonto: " . $amount . "\nVálido " . ($days === 1 ? 'por 1 día' : "por $days días") . '. Paga escaneando este QR desde tu app bancaria.';

        try {
            $tx = ApiCredits::charge($tenantId);
            MessageComposer::sendToContact($conv->account, $conv->contact_id, $body, [
                'media_url' => url('/api/v1/pay/public/qr/' . $qr->internal_reference . '/image'), 'media_type' => 'image', 'credit_transaction_id' => $tx,
            ]);
        } catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException $e) {
            return $this->fail('insufficient_credits', 'El QR se generó, pero no hay créditos para enviarlo. ' . $e->getMessage(), 402);
        } catch (\Throwable $e) {
            return $this->fail('send_failed', 'El QR se generó, pero no se pudo enviar: ' . $e->getMessage(), 422);
        }

        ChatEvent::create([
            'tenant_id' => $tenantId, 'conversation_id' => $conv->id, 'user_id' => $request->attributes->get('chat_user')->id,
            'type' => 'charge', 'body' => 'Cobro enviado por QR: ' . $data['description'] . ' · ' . $amount, 'data' => ['charge_id' => $charge->id],
        ]);
        if (!$conv->assigned_to) {
            $conv->update(['assigned_to' => $request->attributes->get('chat_user')->id]);
        }

        return $this->ok($this->state($request, $conv), 201);
    }

    // ------------------------------------------------------------------

    protected function state(Request $request, Conversation $conv): array
    {
        $tenantId = $this->tenantId($request);

        $charges = ChatCharge::where('conversation_id', $conv->id)->latest('id')->take(20)->get();
        $qrs = \Aero\Pay\Models\QrCode::whereIn('id', $charges->pluck('qr_code_id'))->get()->keyBy('id');

        return [
            'available' => class_exists(\Aero\Pay\Classes\QrIssuer::class),
            'media'     => (bool) ($conv->account->capabilities()['media'] ?? false),
            'presets'   => collect(self::PRESETS)->map(fn ($label, $days) => ['days' => $days, 'label' => $label])->values()->all(),
            'banks'     => class_exists(\Aero\Pay\Models\BankAccount::class)
                ? $this->banks($tenantId)->map(fn ($b) => ['id' => $b->id, 'label' => $b->label])->values()->all() : [],
            'charges'   => $charges->map(function (ChatCharge $c) use ($qrs) {
                $status = $c->status;
                // El webhook actualiza el QR; si venció o se canceló, se refleja aquí.
                if ($status === 'pending' && ($qr = $qrs->get($c->qr_code_id)) && in_array($qr->status, ['expired', 'cancelled'], true)) {
                    $status = $qr->status;
                }
                if ($status === 'pending' && $c->due_at && $c->due_at->isPast()) {
                    $status = 'expired';
                }

                return ['id' => $c->id, 'amount' => (float) $c->amount, 'currency' => $c->currency, 'description' => $c->description,
                    'status' => $status, 'due_at' => optional($c->due_at)->toDateString(), 'created_at' => optional($c->created_at)->toIso8601String()];
            })->all(),
        ];
    }

    protected function banks(int $tenantId)
    {
        $preferred = class_exists(\Aero\Crm\Models\CrmSettings::class) ? \Aero\Crm\Models\CrmSettings::where('tenant_id', $tenantId)->value('collections_bank_account_id') : null;

        return \Aero\Pay\Models\BankAccount::active()->where('tenant_id', $tenantId)->orderBy('label')->get()
            ->sortByDesc(fn ($b) => (int) ($b->id === $preferred))->values();
    }

    protected function conversation(Request $request, $id): array
    {
        $ids = Account::forTenant($this->tenantId($request))->pluck('id');
        $conv = Conversation::whereIn('account_id', $ids)->with('account')->find($id);

        return [$conv, $conv ? null : $this->fail('not_found', 'No encontrado.', 404)];
    }

    protected function tenantId(Request $request): int
    {
        return (int) $request->attributes->get('tenant_id');
    }

    protected function ok($data, int $status = 200)
    {
        return response()->json(['data' => $data], $status);
    }

    protected function fail(string $code, string $message, int $status)
    {
        return response()->json(['error' => $code, 'message' => $message], $status);
    }
}
