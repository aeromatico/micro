<?php namespace Aero\Docs\Updates;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

/** Interruptor de documentación por tenant. Sin fila = desactivado. */
return new class extends Migration
{
    public function up()
    {
        Schema::create('aero_docs_tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tenant_id')->unique();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('aero_docs_tenant_settings');
    }
};
