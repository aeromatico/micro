<?php namespace Aero\Sms\Http\Controllers\Api;

use Aero\Sms\Classes\Drivers\TwilioDriver;
use Aero\Sms\Classes\Sms;
use Aero\Sms\Models\Message;
use Aero\Sms\Models\OptOut;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WebhookController extends Controller
{
    /** Callback de estado de Twilio. Sin API key: se autentica por firma. */
    public function twilio(Request $request)
    {
        $params = $request->post();

        if (!TwilioDriver::validSignature($request->fullUrl(), $params, $request->header('X-Twilio-Signature'))) {
            return response('Invalid signature', 403);
        }

        $message = Message::where('provider_message_id', $params['MessageSid'] ?? '')->first();

        if ($message) {
            Sms::applyProviderStatus(
                $message,
                (string) ($params['MessageStatus'] ?? ''),
                $params['ErrorCode'] ?? null
            );
        }

        return response('', 204);
    }

    /** Mensaje entrante de Twilio: STOP/BAJA registra la baja global. */
    public function twilioInbound(Request $request)
    {
        $params = $request->post();

        if (!TwilioDriver::validSignature($request->fullUrl(), $params, $request->header('X-Twilio-Signature'))) {
            return response('Invalid signature', 403);
        }

        $body = mb_strtoupper(trim((string) ($params['Body'] ?? '')));

        if (in_array($body, ['STOP', 'BAJA', 'CANCELAR', 'UNSUBSCRIBE', 'STOPALL'], true) && !empty($params['From'])) {
            OptOut::firstOrCreate(['phone' => $params['From']], ['source' => 'inbound', 'reason' => $body]);
        }

        return response('<Response/>', 200, ['Content-Type' => 'text/xml']);
    }
}
