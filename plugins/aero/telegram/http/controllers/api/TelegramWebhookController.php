<?php namespace Aero\Telegram\Http\Controllers\Api;

use Aero\Hello\Jobs\ProcessWebhookEventJob;
use Aero\Hello\Models\Account;
use Aero\Hello\Models\WebhookEvent;
use Aero\Telegram\Models\TelegramBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Updates de la Bot API. Telegram reenvía el secret_token de setWebhook en el
 * header X-Telegram-Bot-Api-Secret-Token; sin ese valor no se acepta nada.
 */
class TelegramWebhookController extends Controller
{
    public function handle(string $accountId, Request $request): JsonResponse
    {
        $account = Account::ofDriver('telegram')->find($accountId);
        if (!$account) {
            return response()->json(['error' => 'unknown_account'], 404);
        }

        $bot = TelegramBot::where('account_id', $account->id)->first();
        $given = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
        if (!$bot || $bot->webhook_secret === '' || !hash_equals($bot->webhook_secret, $given)) {
            return response()->json(['error' => 'invalid_secret'], 401);
        }

        $update = $request->all();
        if (empty($update['message']) && empty($update['edited_message'])) {
            return response()->json(['ok' => true]);
        }

        $eventId = 'telegram:' . $account->id . ':' . ($update['update_id'] ?? md5(json_encode($update)));
        $event = WebhookEvent::firstOrNew(['event_id' => $eventId]);

        if (!$event->exists) {
            $event->fill([
                'event_type' => 'message.received',
                'account_id' => $account->id,
                'payload'    => $update,
            ])->save();

            ProcessWebhookEventJob::dispatch($event->id);
        }

        return response()->json(['ok' => true]);
    }
}
