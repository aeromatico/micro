<?php namespace Aero\Sms\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

abstract class ApiController extends Controller
{
    /**
     * Quién consume: la key autenticada y, si su dueño es un tenant, ese tenant.
     * Una key sin dueño es de la plataforma (tenant_id null, no se cobra).
     */
    protected function consumer(Request $request): array
    {
        $key = $request->attributes->get('api_key');
        $tenantId = null;

        if ($key && $key->owner_type === \Aero\Sites\Models\Tenant::class) {
            $tenantId = (int) $key->owner_id;
        }

        return [
            'tenant_id'  => $tenantId,
            'api_key_id' => $key?->id,
            'label'      => $key?->name,
        ];
    }

    /** Todo lo que un consumidor lee queda acotado a lo suyo. */
    protected function scoped($query, Request $request)
    {
        $c = $this->consumer($request);

        return $c['tenant_id']
            ? $query->where('tenant_id', $c['tenant_id'])
            : $query->where('api_key_id', $c['api_key_id']);
    }

    protected function error(string $code, string $message, int $status, array $extra = [])
    {
        return response()->json(['error' => $code, 'message' => $message] + $extra, $status);
    }

    protected function insufficient(\Throwable $e)
    {
        return $this->error('insufficient_credits', $e->getMessage(), 402);
    }
}
