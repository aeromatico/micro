<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_docs_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->unsignedInteger('nest_left')->nullable();
            $table->unsignedInteger('nest_right')->nullable();
            $table->unsignedInteger('nest_depth')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon', 16)->nullable()->comment('Emoji o símbolo corto');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('aero_docs_articles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable()->comment('Markdown');
            $table->longText('content_html')->nullable();
            $table->text('toc')->nullable();
            $table->unsignedInteger('reading_minutes')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('views')->default(0);
            $table->boolean('is_published')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_docs_articles');
        Schema::dropIfExists('aero_docs_categories');
    }
};
