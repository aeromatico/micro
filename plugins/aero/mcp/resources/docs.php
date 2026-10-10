<?php

/** Aero.Docs — metadatos de los artículos propios del tenant (el cuerpo se omite por tamaño). */
return [
    'docs_articles' => [
        'plugin' => 'Aero.Docs', 'label' => 'artículos de documentación', 'model' => \Aero\Docs\Models\Article::class,
        'fields' => 'id,category_id,title,slug,excerpt,version,is_published,is_featured,published_at,views,helpful_yes,helpful_no,created_at,updated_at',
        'search' => 'title,slug,excerpt', 'filters' => 'category_id,is_published,is_featured',
    ],
    'docs_categories' => [
        'plugin' => 'Aero.Docs', 'label' => 'categorías de documentación', 'model' => \Aero\Docs\Models\Category::class,
        'fields' => 'id,parent_id,name,slug,icon,description,is_active', 'filters' => 'parent_id,is_active',
    ],
];
