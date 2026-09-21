<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

/** Documentos (Aero.Docs) que corresponden a un servicio. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_services_service_article', function (Blueprint $table) {
            $table->foreignId('service_id')->constrained('aero_services_services')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('aero_docs_articles')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['service_id', 'article_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_services_service_article');
    }
};
