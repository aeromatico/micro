<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de publicación: draft (borrador, sin ejecuciones automáticas),
 * published (en vivo) y archived. Los workflows que ya existían quedan
 * publicados para no cambiar su comportamiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('aero_workflows_workflows', 'status')) {
            return;
        }

        Schema::table('aero_workflows_workflows', function (Blueprint $table) {
            $table->string('status', 16)->default('published')->after('is_active');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('aero_workflows_workflows', 'status')) {
            return;
        }

        Schema::table('aero_workflows_workflows', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropColumn('status');
        });
    }
};
