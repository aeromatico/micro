<?php namespace Aero\Chat\Http\Controllers;

use Aero\Notify\Models\InboxMessage;
use Aero\Notify\Models\PushSettings;
use Aero\Notify\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Web Push y bandeja de notificaciones del agente (Aero.Notify). */
class PushController extends Controller
{
    /** GET push/key — clave pública VAPID para pushManager.subscribe(). */
    public function key()
    {
        $keys = PushSettings::keys();

        return response()->json(['data' => ['public_key' => $keys['public'] ?? null]]);
    }

    /** POST push/subscribe — body: la PushSubscription serializada del navegador. */
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint'    => 'required|url|max:2000',
            'keys.p256dh' => 'required|string|max:255',
            'keys.auth'   => 'required|string|max:255',
        ]);

        PushSubscription::register(
            $request->attributes->get('chat_user')->id,
            (int) $request->attributes->get('tenant_id'),
            $data,
            $request->userAgent()
        );

        return response()->json(['data' => ['subscribed' => true]]);
    }

    /** POST push/unsubscribe — body: { endpoint } */
    public function unsubscribe(Request $request)
    {
        $endpoint = (string) $request->input('endpoint');

        if ($endpoint !== '') {
            PushSubscription::where('endpoint_hash', PushSubscription::hashEndpoint($endpoint))
                ->where('user_id', $request->attributes->get('chat_user')->id)
                ->delete();
        }

        return response()->json(['data' => ['subscribed' => false]]);
    }

    /** POST push/test — envía una notificación de prueba a los dispositivos del agente. */
    public function test(Request $request)
    {
        $user = $request->attributes->get('chat_user');
        $devices = PushSubscription::where('user_id', $user->id)->count();

        try {
            (new \Aero\Notify\Classes\Drivers\PushDriver())->send(
                'user:' . $user->id,
                'Prueba de notificaciones',
                'Si ves esto, las notificaciones funcionan en este dispositivo.',
                ['event_code' => 'chat.push.test']
            );
        } catch (\Aero\Notify\Classes\Drivers\SkipDelivery $e) {
            return response()->json(['data' => ['sent' => false, 'devices' => $devices, 'reason' => 'Este usuario no tiene dispositivos suscritos.']]);
        } catch (\Throwable $e) {
            return response()->json(['data' => ['sent' => false, 'devices' => $devices, 'reason' => $e->getMessage()]]);
        }

        return response()->json(['data' => ['sent' => true, 'devices' => $devices]]);
    }

    /** GET notifications — bandeja del agente en este tenant (más recientes primero). */
    public function index(Request $request)
    {
        $query = InboxMessage::forUser($request->attributes->get('chat_user')->id)
            ->whereIn('tenant_id', [0, (int) $request->attributes->get('tenant_id')]);

        return response()->json([
            'data' => $query->clone()->orderByDesc('id')->limit(50)
                ->get(['id', 'event_code', 'title', 'body', 'url', 'read_at', 'created_at']),
            'meta' => ['unread' => $query->clone()->unread()->count()],
        ]);
    }

    /** POST notifications/{id}/read — id = 'all' marca todas. */
    public function read(Request $request, string $id)
    {
        $query = InboxMessage::forUser($request->attributes->get('chat_user')->id);

        if ($id !== 'all') {
            $query->where('id', (int) $id);
        }

        $query->unread()->update(['read_at' => now()]);

        return response()->json(['data' => ['ok' => true]]);
    }
}
