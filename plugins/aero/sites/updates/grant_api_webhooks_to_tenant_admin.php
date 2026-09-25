<?php

use October\Rain\Database\Updates\Migration;

/**
 * Habilita el tab "Webhooks" del gateway Aero.Api para tenant_admin, aislado
 * a las suyas por WebhookSubscriptions::scopeToOwner(). Mismo patrón que
 * grant_api_keys_to_tenant_admin.php — ver ese archivo para el porqué del
 * class_exists (Sites no depende de Api).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!class_exists(\Aero\Api\Models\WebhookSubscription::class)) {
            return;
        }

        $role = \Backend\Models\UserRole::where('code', 'tenant_admin')->first();
        if (!$role) {
            return;
        }

        $role->permissions = array_merge($role->permissions ?? [], [
            'aero.api.manage_webhooks' => 1,
        ]);
        $role->save();
    }

    public function down(): void
    {
        $role = \Backend\Models\UserRole::where('code', 'tenant_admin')->first();
        if (!$role) {
            return;
        }

        $perms = $role->permissions ?? [];
        unset($perms['aero.api.manage_webhooks']);
        $role->permissions = $perms;
        $role->save();
    }
};
