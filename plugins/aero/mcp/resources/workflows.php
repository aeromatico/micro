<?php

/** Aero.Workflows — definición resumida (sin grafo) y ejecuciones. */
return [
    'workflows_workflows' => [
        'plugin' => 'Aero.Workflows', 'label' => 'automatizaciones', 'model' => \Aero\Workflows\Models\Workflow::class,
        'fields' => 'id,name,slug,description,is_active,status,trigger_type,expose_as_tool,tool_description,version,created_at,updated_at',
        'search' => 'name,slug,description', 'filters' => 'is_active,status,trigger_type,expose_as_tool',
    ],
    'workflows_runs' => [
        'plugin' => 'Aero.Workflows', 'label' => 'ejecuciones de automatizaciones', 'model' => \Aero\Workflows\Models\Run::class,
        'fields' => 'id,workflow_id,status,source,steps_count,error,started_at,finished_at,created_at',
        'filters' => 'workflow_id,status,source',
    ],
];
