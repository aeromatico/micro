<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('aero_producer_projects')) {
            return;
        }

        Schema::create('aero_producer_projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable(); // NULL = proyecto de la plataforma
            $table->string('kind', 12)->default('video');         // video | audio (audio, más adelante)
            $table->string('slug', 80);
            $table->string('name');
            $table->text('topic')->nullable();
            $table->longText('script')->nullable();               // guion: una escena por párrafo
            $table->string('status', 12)->default('draft');       // draft | designed | voiced | rendered | published
            $table->string('voice_id', 40)->nullable();
            $table->string('background')->nullable();
            $table->unsignedBigInteger('service_id')->nullable(); // página de servicio donde se muestra
            $table->string('source_dir', 255)->nullable();        // carpeta del proyecto (relativa a la raíz de la app)
            $table->text('metadata')->nullable();                 // json: título, descripción, etiquetas, categoría…
            $table->text('outputs')->nullable();                  // json: archivos publicados en Medios
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_producer_projects');
    }
};
