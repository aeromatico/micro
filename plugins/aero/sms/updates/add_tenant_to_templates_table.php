<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Cada tenant tiene sus plantillas; las de tenant_id NULL son globales (las
 * gestiona el superadmin y las puede usar cualquiera). El slug deja de ser
 * único a nivel global: lo es por tenant, y eso lo valida el modelo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sms_templates', function (Blueprint $table) {
            $table->dropUnique('aero_sms_templates_slug_unique');
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->index(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('aero_sms_templates', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'slug']);
            $table->dropColumn('tenant_id');
            $table->unique('slug');
        });
    }
};
