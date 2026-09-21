<?php namespace Aero\Docs\Models;

use Model;

/**
 * Instantánea inmutable de un artículo en una versión concreta. Se crea sola
 * en cada actualización desde {@see Article::afterSave()}.
 */
class ArticleVersion extends Model
{
    public $table = 'aero_docs_article_versions';

    public $fillable = [
        'article_id', 'version', 'user_id', 'title', 'excerpt',
        'content', 'content_html', 'toc', 'reading_minutes',
    ];

    public $jsonable = ['toc'];

    protected $dates = ['created_at', 'updated_at'];

    public $belongsTo = [
        'article' => [Article::class, 'key' => 'article_id'],
        'user'    => [\Backend\Models\User::class, 'key' => 'user_id'],
    ];
}
