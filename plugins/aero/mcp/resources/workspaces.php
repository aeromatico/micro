<?php

/** Aero.Workspaces — tareas, contratos y conversaciones con el staff IA. Solo lectura (cobran puntos). */
return [
    'workspaces_tasks' => [
        'plugin' => 'Aero.Workspaces', 'label' => 'tareas del workspace', 'model' => \Aero\Workspaces\Models\Task::class,
        'fields' => 'id,user_id,orchestrator_id,brief,status,estimated_points,charged_points,source,started_at,finished_at,refunded_at,created_at',
        'search' => 'brief', 'filters' => 'status,orchestrator_id,source',
    ],
    'workspaces_hires' => [
        'plugin' => 'Aero.Workspaces', 'label' => 'contrataciones de staff', 'model' => \Aero\Workspaces\Models\Hire::class,
        'fields' => 'id,staff_id,fee_charged,hired_at,created_at', 'filters' => 'staff_id',
    ],
    'workspaces_skills' => [
        'plugin' => 'Aero.Workspaces', 'label' => 'skills del workspace', 'model' => \Aero\Workspaces\Models\Skill::class,
        'fields' => 'id,kind,name,slug,description,color,created_at', 'search' => 'name,description', 'filters' => 'kind',
    ],
    'workspaces_messages' => [
        'plugin' => 'Aero.Workspaces', 'label' => 'mensajes del workspace', 'model' => \Aero\Workspaces\Models\Message::class,
        'fields' => 'id,staff_id,user_id,role,content,status,charged_points,created_at', 'search' => 'content', 'filters' => 'staff_id,role,status',
    ],
];
