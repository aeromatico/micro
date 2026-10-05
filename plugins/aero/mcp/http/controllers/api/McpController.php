<?php namespace Aero\Mcp\Http\Controllers\Api;

use Aero\Mcp\Classes\McpServer;
use Aero\Api\Models\ApiKey;
use Aero\Sites\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Endpoint MCP global: POST /api/v1/mcp. Autenticado con una API key que
 * tenga `mcp.use`; las tools visibles dependen de sus scopes `mcp.tool.*`.
 */
class McpController extends Controller
{
    public function post(Request $request): JsonResponse|Response
    {
        /** @var ApiKey $key */
        $key = $request->attributes->get('api_key');
        $owner = $request->attributes->get('api_owner');

        $tenantId = $owner instanceof Tenant ? (int) $owner->id : null;

        $server = new McpServer($key, $tenantId, $request->ip());
        $response = $server->handle((array) $request->json()->all());

        // Notificaciones: 202 sin cuerpo, según el transporte de MCP.
        if ($response === null) {
            return response('', 202);
        }

        return response()->json($response);
    }
}
