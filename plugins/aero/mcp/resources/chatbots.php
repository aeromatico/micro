<?php

/** Aero.Chatbots — solo lectura de los bots del tenant. */
return [
    'chatbots_bots' => [
        'plugin' => 'Aero.Chatbots', 'label' => 'chatbots', 'model' => \Aero\Chatbots\Models\Bot::class,
        'fields' => 'id,account_id,reply_mode,ai_model,ai_system_prompt,ai_tool_categories,name,is_active,fallback_message,handoff_minutes,created_at',
        'search' => 'name', 'filters' => 'is_active,reply_mode,account_id',
    ],
];
