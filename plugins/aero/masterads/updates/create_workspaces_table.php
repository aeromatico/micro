<?php namespace Aero\MasterAds\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Create the `aero_masterads_workspaces` table — the root tenant entity of the
 * Master Ads plugin. Every business-domain resource (MetaAccount, Campaign,
 * Subscription, AiAnalysis, etc.) descends from a Workspace and is scoped by
 * the tenant isolation rule (Property 2 / Requirement 1.3, 1.4).
 *
 * Validates: Requirements 1.1, 1.2, 1.5, 17.3, 17.4, 17.5
 */
class CreateWorkspacesTable extends \October\Rain\Database\Updates\Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('aero_masterads_workspaces', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->unsignedBigInteger('owner_id');
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->foreign('owner_id')
                ->references('id')
                ->on('backend_users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('aero_masterads_workspaces');
    }
}
