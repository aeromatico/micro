<?php namespace Aero\MasterAds\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Create the `aero_masterads_workspace_user` pivot table — the many-to-many
 * relation between Workspaces and BackendUsers, carrying the `role` attribute
 * that drives in-workspace authorisation (owner / admin / viewer).
 *
 * The composite UNIQUE(workspace_id, user_id) guarantees a single membership
 * row per (workspace, user) pair, which is a prerequisite for Property 2
 * (tenant isolation) and Requirement 1.5.
 *
 * Validates: Requirements 1.1, 1.2, 1.5, 17.3, 17.4, 17.5
 */
class CreateWorkspaceUserTable extends \October\Rain\Database\Updates\Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('aero_masterads_workspace_user', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('role', ['owner', 'admin', 'viewer'])->default('viewer');
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);

            $table->foreign('workspace_id')
                ->references('id')
                ->on('aero_masterads_workspaces')
                ->onDelete('cascade');

            $table->foreign('user_id')
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
        Schema::dropIfExists('aero_masterads_workspace_user');
    }
}
