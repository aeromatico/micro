<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_producer_styles')) {
            Schema::create('aero_producer_styles', function (Blueprint $table) {
                $table->id();
                $table->string('kind', 12);                          // presenter | explainer
                $table->string('slug', 80)->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('preview_url', 255)->nullable();      // video de muestra (ruta de Medios o URL)
                $table->unsignedSmallInteger('max_seconds')->default(30);
                $table->unsignedInteger('credit_cost')->default(0);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('aero_producer_voices')) {
            Schema::create('aero_producer_voices', function (Blueprint $table) {
                $table->id();
                $table->string('provider', 20)->default('elevenlabs'); // elevenlabs | heygen
                $table->string('voice_id', 64);
                $table->string('name');
                $table->string('gender', 10)->nullable();
                $table->string('language', 10)->default('es');
                $table->string('preview_url', 500)->nullable();
                $table->boolean('is_active')->default(false);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['provider', 'voice_id']);
            });
        }

        if (!Schema::hasTable('aero_producer_orders')) {
            Schema::create('aero_producer_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('style_id');
                $table->string('name');
                $table->longText('script')->nullable();
                $table->unsignedBigInteger('voice_id')->nullable();   // aero_producer_voices.id
                $table->string('aspect_ratio', 6)->default('9:16');
                $table->string('resolution', 6)->default('720p');
                $table->text('extras')->nullable();                   // json: tema, fondo musical…
                $table->string('status', 12)->default('draft');       // draft | queued | producing | ready | failed
                $table->unsignedInteger('credits_quote')->default(0);
                $table->unsignedInteger('credits_charged')->default(0);
                $table->string('output_url', 500)->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'created_at']);
                $table->index('status');
            });
        }

        // Estilos de arranque; se pulen y ajustan desde Productor → Estilos.
        $now = now();
        foreach ([
            ['presenter', 'presentador-calle', 'Presentador en la calle', 'Una persona habla a cámara mientras camina por una calle tranquila. Ideal para anuncios cortos de 15 a 30 segundos.', 30, 1],
            ['explainer', 'explicativo-palitos', 'Explicativo animado', 'Video explicativo con figuras minimalistas, locución y música de fondo. Lo producimos con tu guion.', 60, 2],
        ] as [$kind, $slug, $name, $description, $max, $order]) {
            \DB::table('aero_producer_styles')->insertOrIgnore([
                'kind' => $kind, 'slug' => $slug, 'name' => $name, 'description' => $description,
                'max_seconds' => $max, 'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_producer_orders');
        Schema::dropIfExists('aero_producer_voices');
        Schema::dropIfExists('aero_producer_styles');
    }
};
