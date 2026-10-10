<?php

/** Aero.Sites — solo lectura (el contenido de las páginas lo gestiona el editor / las AI tools de `site`). */
return [
    'sites_pages' => [
        'plugin' => 'Aero.Sites', 'label' => 'páginas del sitio', 'model' => \Aero\Sites\Models\Page::class,
        'fields' => 'id,title,slug,content_mode,is_placeholder,meta_title,meta_description,layout,is_published,sort_order,show_in_menu,created_at,updated_at',
        'search' => 'title,slug', 'filters' => 'is_published,show_in_menu,content_mode',
    ],
    'sites_domains' => [
        'plugin' => 'Aero.Sites', 'label' => 'dominios del sitio', 'model' => \Aero\Sites\Models\Domain::class,
        'fields' => 'id,domain,is_primary,is_subdomain,created_at', 'filters' => 'is_primary',
    ],
    'sites_contact_submissions' => [
        'plugin' => 'Aero.Sites', 'label' => 'mensajes del formulario de contacto', 'model' => \Aero\Sites\Models\ContactSubmission::class,
        'fields' => 'id,name,email,phone,message,status,dispatched_at,created_at',
        'search' => 'name,email,phone,message', 'filters' => 'status',
    ],
    'sites_team' => [
        'plugin' => 'Aero.Sites', 'label' => 'miembros del equipo del tenant', 'model' => \Aero\Sites\Models\TenantUser::class,
        'fields' => 'id,user_id,role,created_at', 'filters' => 'role',
    ],
    'sites_plan_renewals' => [
        'plugin' => 'Aero.Sites', 'label' => 'renovaciones del plan', 'model' => \Aero\Sites\Models\PlanRenewal::class,
        'fields' => 'id,plan_id,period,cycle_due_at,status,payment_reference,amount,currency,paid_at,created_at', 'filters' => 'status,plan_id',
    ],
];
