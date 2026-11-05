<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_tickets_categories')) {
            Schema::create('aero_tickets_categories', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->increments('id');
                $t->string('name');
                $t->string('slug')->unique();
                $t->string('icon', 60)->nullable();
                $t->integer('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('aero_tickets_events')) {
            Schema::create('aero_tickets_events', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->increments('id');
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedInteger('category_id')->nullable();
                $t->string('name');
                $t->string('slug')->unique();
                $t->text('description')->nullable();
                $t->string('visibility', 10)->default('public');   // public | private | mixed
                $t->string('status', 12)->default('draft');        // draft | published | cancelled | finished
                $t->string('access_token', 40)->nullable()->unique(); // enlace privado
                $t->dateTime('starts_at')->nullable();
                $t->dateTime('ends_at')->nullable();
                $t->string('department', 30)->nullable();
                $t->string('city', 100)->nullable();
                $t->string('zone', 100)->nullable();
                $t->string('venue_name')->nullable();
                $t->string('address')->nullable();
                $t->text('address_reference')->nullable();
                $t->decimal('lat', 10, 7)->nullable();
                $t->decimal('lng', 10, 7)->nullable();
                $t->unsignedInteger('capacity')->nullable();
                $t->unsignedTinyInteger('min_age')->nullable();
                $t->text('requirements')->nullable();
                $t->string('organizer_name')->nullable();
                $t->text('organizer_description')->nullable();
                $t->string('contact_name')->nullable();
                $t->string('contact_phone', 30)->nullable();
                $t->string('contact_whatsapp', 30)->nullable();
                $t->string('contact_email')->nullable();
                $t->timestamp('published_at')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['tenant_id', 'created_at']);
                $t->index(['status', 'visibility', 'starts_at']);
                $t->index('category_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_tickets_events');
        Schema::dropIfExists('aero_tickets_categories');
    }
};
