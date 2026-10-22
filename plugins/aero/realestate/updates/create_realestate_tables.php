<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * tenant_id / user_id son enteros sin FK a propósito (como aero/sms y aero/gym):
 * el plugin no exige que Sites exista. Las tablas hijas sí llevan tenant_id propio.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_realestate_locations')) {
            Schema::create('aero_realestate_locations', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable()->index()->comment('null = catálogo global de Bolivia');
                $t->unsignedBigInteger('parent_id')->nullable()->index();
                $t->string('type', 16)->comment('department, city, zone, neighborhood');
                $t->string('name');
                $t->decimal('lat', 10, 7)->nullable();
                $t->decimal('lng', 10, 7)->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('aero_realestate_features')) {
            Schema::create('aero_realestate_features', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable()->index();
                $t->string('name');
                $t->string('category', 32)->nullable();
                $t->string('icon', 64)->nullable();
                $t->timestamps();
            });
        }

        foreach (['agents', 'owners', 'clients'] as $table) {
            if (Schema::hasTable('aero_realestate_' . $table)) {
                continue;
            }
            Schema::create('aero_realestate_' . $table, function (Blueprint $t) use ($table) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('user_id')->nullable()->index()->comment('RainLab.User');
                $t->string('name');
                $t->string('email')->nullable();
                $t->string('phone', 32)->nullable();
                $t->string('document', 32)->nullable();
                $t->text('notes')->nullable();
                if ($table === 'agents') {
                    $t->string('whatsapp', 32)->nullable();
                    $t->string('license', 64)->nullable();
                    $t->text('bio')->nullable();
                    $t->boolean('is_active')->default(true);
                }
                if ($table === 'owners') {
                    $t->string('address')->nullable();
                }
                if ($table === 'clients') {
                    $t->json('interests')->nullable()->comment('sale, rent, anticretico');
                    $t->decimal('budget_min', 14, 2)->nullable();
                    $t->decimal('budget_max', 14, 2)->nullable();
                    $t->string('currency', 3)->default('USD');
                    $t->json('preferences')->nullable();
                    $t->unsignedBigInteger('agent_id')->nullable()->index();
                    $t->string('source', 32)->nullable();
                }
                $t->timestamps();
                $t->softDeletes();
                $t->index(['tenant_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_realestate_properties')) {
            Schema::create('aero_realestate_properties', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->string('code', 32)->nullable();
                $t->string('title');
                $t->string('slug')->nullable();
                $t->string('type', 24)->comment('house, apartment, land, office, shop, other');
                $t->text('description')->nullable();
                $t->unsignedBigInteger('owner_id')->nullable()->index();
                $t->unsignedBigInteger('agent_id')->nullable()->index();
                $t->unsignedBigInteger('location_id')->nullable()->index();
                $t->string('department', 64)->nullable();
                $t->string('city', 96)->nullable();
                $t->string('zone', 96)->nullable();
                $t->string('neighborhood', 96)->nullable();
                $t->string('address')->nullable();
                $t->decimal('lat', 10, 7)->nullable();
                $t->decimal('lng', 10, 7)->nullable();
                $t->decimal('land_area', 12, 2)->nullable()->comment('m²');
                $t->decimal('built_area', 12, 2)->nullable()->comment('m²');
                $t->unsignedSmallInteger('bedrooms')->nullable();
                $t->unsignedSmallInteger('bathrooms')->nullable();
                $t->unsignedSmallInteger('garages')->nullable();
                $t->unsignedSmallInteger('floors')->nullable();
                $t->unsignedSmallInteger('age_years')->nullable();
                $t->string('video_url')->nullable();
                $t->string('status', 16)->default('available')->comment('available, reserved, sold, rented, anticretico, inactive');
                $t->boolean('is_featured')->default(false);
                $t->boolean('is_published')->default(false);
                $t->unsignedInteger('views_count')->default(0);
                $t->timestamp('published_at')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['tenant_id', 'status']);
                $t->index(['tenant_id', 'is_published']);
                $t->index(['tenant_id', 'slug']);
            });
        }

        if (!Schema::hasTable('aero_realestate_feature_property')) {
            Schema::create('aero_realestate_feature_property', function (Blueprint $t) {
                $t->unsignedBigInteger('property_id');
                $t->unsignedBigInteger('feature_id');
                $t->primary(['property_id', 'feature_id']);
            });
        }

        if (!Schema::hasTable('aero_realestate_offers')) {
            Schema::create('aero_realestate_offers', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->unsignedBigInteger('property_id')->index();
                $t->string('modality', 16)->comment('sale, rent, anticretico');
                $t->decimal('amount', 14, 2)->comment('Precio, canon mensual o capital de anticrético');
                $t->string('currency', 3)->default('USD');
                $t->unsignedSmallInteger('term_months')->nullable()->comment('Plazo (anticrético / alquiler)');
                $t->decimal('deposit', 14, 2)->nullable()->comment('Garantía (alquiler)');
                $t->text('conditions')->nullable();
                $t->string('status', 16)->default('available')->comment('available, reserved, closed, inactive');
                $t->timestamp('closed_at')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['tenant_id', 'modality', 'status']);
            });
        }
    }

    public function down(): void
    {
        foreach (['offers', 'feature_property', 'properties', 'clients', 'owners', 'agents', 'features', 'locations'] as $table) {
            Schema::dropIfExists('aero_realestate_' . $table);
        }
    }
};
