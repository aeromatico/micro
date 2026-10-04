<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_sheets_sources')) {
            Schema::create('aero_sheets_sources', function (Blueprint $table) {
                $table->id();
                $table->string('model_class')->unique();
                $table->string('label');
                $table->text('fields')->nullable();              // json: [{key,label,type,import,export}]
                $table->boolean('tenant_access')->default(false); // ¿lo pueden usar los tenants?
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('aero_sheets_mappings')) {
            Schema::create('aero_sheets_mappings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('backend_user_id')->nullable(); // quien lo creó (informativo)
                $table->unsignedBigInteger('source_id');
                $table->string('name');
                $table->string('spreadsheet_id')->nullable();
                $table->string('spreadsheet_title')->nullable();
                $table->string('sheet_title')->nullable();
                $table->string('direction', 10)->default('import');     // import | export
                $table->unsignedInteger('header_row')->default(1);
                $table->unsignedInteger('start_row')->default(2);
                $table->unsignedInteger('max_rows')->nullable();        // null = todas (con tope)
                $table->string('key_field')->nullable();
                $table->string('import_mode', 10)->default('upsert');   // create | update | upsert
                $table->boolean('write_headers')->default(true);
                $table->boolean('clear_before_export')->default(false);
                $table->text('columns')->nullable();                    // json: [{field,column}]
                $table->timestamp('last_run_at')->nullable();
                $table->string('last_status', 12)->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'created_at']);
                $table->index('source_id');
            });
        }

        if (!Schema::hasTable('aero_sheets_runs')) {
            Schema::create('aero_sheets_runs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mapping_id');
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('backend_user_id')->nullable();
                $table->string('direction', 10);
                $table->boolean('dry_run')->default(false);
                $table->string('status', 12)->default('running');       // running | ok | partial | failed
                $table->unsignedInteger('rows_read')->default(0);
                $table->unsignedInteger('created')->default(0);
                $table->unsignedInteger('updated')->default(0);
                $table->unsignedInteger('skipped')->default(0);
                $table->unsignedInteger('failed')->default(0);
                $table->text('errors')->nullable();                     // json
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'created_at']);
                $table->index('mapping_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_sheets_runs');
        Schema::dropIfExists('aero_sheets_mappings');
        Schema::dropIfExists('aero_sheets_sources');
    }
};
