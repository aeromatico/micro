<?php namespace Aero\Connector\Http\Controllers\Api;

use Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Aero\Connector\Classes\WebhookVerifier;
use Aero\Connector\Models\ConnectorLog;
use Aero\Connector\Models\WebhookEndpoint;

class WebhookReceiverController extends Controller
{
    public function handle(Request $request, string $slug, WebhookVerifier $verifier): JsonResponse
    {
        $endpoint = WebhookEndpoint::where('slug', $slug)->where('is_enabled', true)->first();

        if (!$endpoint) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $signature = $endpoint->signature_header
            ? $request->header($endpoint->signature_header)
            : $request->header('X-Signature');

        if (!$verifier->isValid($endpoint, $request->getContent(), $signature)) {
            return response()->json(['error' => 'invalid_signature'], 401);
        }

        $payload = $request->all();

        ConnectorLog::create([
            'direction'           => 'in',
            'webhook_endpoint_id' => $endpoint->id,
            'request_payload'     => $payload,
            'response_payload'    => null,
            'status_code'         => 200,
        ]);

        Event::dispatch($endpoint->dispatch_event, [$endpoint, $payload, $request]);

        return response()->json(['received' => true]);
    }
}
