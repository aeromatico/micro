<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_crm_contact_list_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_list_id')->constrained('aero_crm_contact_lists')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('aero_crm_contacts')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contact_list_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_crm_contact_list_contact');
    }
};
