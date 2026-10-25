<?php

use Aero\Shortcuts\Models\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;
use System\Models\Parameter;

/**
 * Fija como plantilla los atajos del tenant del usuario «alvaro» y se los da a
 * los tenants que aún no tienen ninguno. Los tenants con atajos no se tocan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_shortcuts_items') || !class_exists(\Aero\Sites\Models\Tenant::class)) {
            return;
        }

        $user = \Backend\Models\User::where('login', 'alvaro')->first();
        $template = $user ? \Aero\Sites\Models\Tenant::resolveForBackendUser($user)?->getKey() : null;
        if (!$template || !Item::where('tenant_id', $template)->exists()) {
            return;
        }

        Parameter::set(Item::TEMPLATE_PARAM, (int) $template);

        foreach (DB::table('aero_sites_tenants')->pluck('id') as $tenantId) {
            Item::seedFromTemplate((int) $tenantId);
        }
    }

    public function down(): void
    {
    }
};
