<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de staff (agentes IA hoy; `kind` deja lugar a personas después),
 * tarifas, skills, asignación skill↔staff y contrataciones por tenant.
 *
 * Sin FK a propósito: el plugin es independiente de Aero.Sites (igual que aero/workflows).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_workspaces_staff', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->string('role', 120);
            $table->string('kind', 16)->default('ai');          // ai | human (híbridos, después)
            $table->string('rarity', 8)->default('r');          // r | sr | ssr
            $table->string('category', 32)->nullable();          // video, imagen, contenido, ppt, info
            $table->text('bio')->nullable();
            $table->text('system_prompt')->nullable();           // solo superadmin lo edita
            $table->json('tags')->nullable();
            $table->json('capabilities')->nullable();            // 6 ejes, 0–100
            $table->json('guide')->nullable();                   // guía de uso
            $table->unsignedInteger('connector_id')->nullable(); // modelo del Hub (aero/connector)
            $table->string('avatar', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'category']);
        });

        Schema::create('aero_workspaces_staff_rates', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('staff_id')->unique();
            $table->decimal('hire_fee', 10, 2)->default(0);      // 0 = no cobra al contratar
            $table->timestamps();
        });

        Schema::create('aero_workspaces_task_rates', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('staff_id');
            $table->string('task_type', 64);
            $table->decimal('fee', 10, 2)->default(0);           // 0 = gratis por tarea
            $table->timestamps();
            $table->unique(['staff_id', 'task_type']);
        });

        Schema::create('aero_workspaces_skills', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id')->nullable();    // null = oficial o hub; con id = personal
            $table->string('kind', 16)->default('official');     // official | hub | personal
            $table->string('name', 120);
            $table->string('slug', 120);
            $table->text('description');                         // "úsala cuando…"
            $table->longText('body')->nullable();                // SKILL.md: frontmatter + instrucciones
            $table->json('tools')->nullable();                   // nombres de AiToolRegistry / workflows
            $table->string('color', 9)->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('aero_workspaces_staff_skill', function (Blueprint $table) {
            $table->unsignedInteger('staff_id');
            $table->unsignedInteger('skill_id');
            $table->primary(['staff_id', 'skill_id']);
        });

        Schema::create('aero_workspaces_hires', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('staff_id');
            $table->decimal('fee_charged', 10, 2)->default(0);
            $table->timestamp('hired_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'staff_id']);
        });
    }

    public function down(): void
    {
        foreach (['aero_workspaces_hires', 'aero_workspaces_staff_skill', 'aero_workspaces_skills', 'aero_workspaces_task_rates', 'aero_workspaces_staff_rates', 'aero_workspaces_staff'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
