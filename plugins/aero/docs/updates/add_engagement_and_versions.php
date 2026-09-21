<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use October\Rain\Support\Facades\Schema;

/**
 * Añade el contador de visitas con base 1000, el feedback "¿fue útil?" y el
 * historial de versiones por artículo. Es idempotente para que convivan
 * instalaciones nuevas (el esquema ya viene en create_docs_tables) con
 * instalaciones existentes que actualizan el plugin.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('aero_docs_articles', 'version')) {
            Schema::table('aero_docs_articles', function (Blueprint $table) {
                $table->unsignedInteger('version')->default(1)->after('reading_minutes');
            });
        }

        if (!Schema::hasColumn('aero_docs_articles', 'helpful_yes')) {
            Schema::table('aero_docs_articles', function (Blueprint $table) {
                $table->unsignedBigInteger('helpful_yes')->default(0)->after('views');
            });
        }

        if (!Schema::hasColumn('aero_docs_articles', 'helpful_no')) {
            Schema::table('aero_docs_articles', function (Blueprint $table) {
                $table->unsignedBigInteger('helpful_no')->default(0)->after('helpful_yes');
            });
        }

        // El contador arranca en 1000 incluso para los artículos ya existentes.
        \Db::table('aero_docs_articles')->where('views', '<', 1000)->update(['views' => 1000]);

        if (!Schema::hasTable('aero_docs_article_versions')) {
            Schema::create('aero_docs_article_versions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('article_id')->index();
                $table->unsignedInteger('version')->default(1);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('title');
                $table->text('excerpt')->nullable();
                $table->longText('content')->nullable()->comment('Markdown');
                $table->longText('content_html')->nullable();
                $table->text('toc')->nullable();
                $table->unsignedInteger('reading_minutes')->default(1);
                $table->timestamps();
                $table->unique(['article_id', 'version']);
            });
        }

        // Cada artículo existente empieza con su versión 1 registrada.
        $now = now();
        foreach (\Db::table('aero_docs_articles')->get() as $article) {
            $hasVersions = \Db::table('aero_docs_article_versions')
                ->where('article_id', $article->id)
                ->exists();

            if ($hasVersions) {
                continue;
            }

            \Db::table('aero_docs_article_versions')->insert([
                'article_id'      => $article->id,
                'version'         => max(1, (int) ($article->version ?? 1)),
                'user_id'         => null,
                'title'           => $article->title,
                'excerpt'         => $article->excerpt,
                'content'         => $article->content,
                'content_html'    => $article->content_html,
                'toc'             => $article->toc,
                'reading_minutes' => $article->reading_minutes ?? 1,
                'created_at'      => $article->created_at ?? $now,
                'updated_at'      => $article->updated_at ?? $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_docs_article_versions');

        if (Schema::hasColumn('aero_docs_articles', 'version')) {
            Schema::table('aero_docs_articles', function (Blueprint $table) {
                $table->dropColumn(['version', 'helpful_yes', 'helpful_no']);
            });
        }
    }
};
