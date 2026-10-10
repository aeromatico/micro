<?php

/** Aero.Crm — contactos, empresas, leads, negocios, actividades, tickets y cobranzas. */
return [
    'crm_contacts' => [
        'plugin' => 'Aero.Crm', 'label' => 'contactos del CRM', 'model' => \Aero\Crm\Models\Contact::class,
        'fields' => 'id,company_id,first_name,last_name,email,phone,source,owner_id,created_at',
        'search' => 'first_name,last_name,email,phone', 'filters' => 'company_id,source,owner_id',
        'writable' => 'first_name,last_name,email,phone,company_id,source',
    ],
    'crm_companies' => [
        'plugin' => 'Aero.Crm', 'label' => 'empresas del CRM', 'model' => \Aero\Crm\Models\Company::class,
        'fields' => 'id,name,website,industry,phone,address,owner_id,created_at',
        'search' => 'name,website,industry', 'filters' => 'industry,owner_id',
    ],
    'crm_leads' => [
        'plugin' => 'Aero.Crm', 'label' => 'leads del CRM', 'model' => \Aero\Crm\Models\Lead::class,
        'fields' => 'id,name,email,phone,company_name,source,status,owner_id,converted_contact_id,converted_deal_id,created_at',
        'search' => 'name,email,phone,company_name', 'filters' => 'status,source,owner_id',
    ],
    'crm_deals' => [
        'plugin' => 'Aero.Crm', 'label' => 'negocios del CRM', 'model' => \Aero\Crm\Models\Deal::class,
        'fields' => 'id,pipeline_id,stage_id,contact_id,company_id,title,value,currency,owner_id,expected_close_date,status,closed_at,created_at',
        'search' => 'title', 'filters' => 'pipeline_id,stage_id,contact_id,company_id,status,owner_id',
    ],
    'crm_activities' => [
        'plugin' => 'Aero.Crm', 'label' => 'actividades del CRM', 'model' => \Aero\Crm\Models\Activity::class,
        'fields' => 'id,related_type,related_id,type,subject,status,description,due_at,completed_at,owner_id,created_at',
        'search' => 'subject', 'filters' => 'type,status,owner_id,related_type,related_id',
    ],
    'crm_tickets' => [
        'plugin' => 'Aero.Crm', 'label' => 'tickets de soporte', 'model' => \Aero\Crm\Models\Ticket::class,
        'fields' => 'id,seq,subject,description,department_id,contact_id,requester_name,requester_email,requester_phone,assigned_to,status,priority,source,first_response_at,closed_at,created_at',
        'search' => 'subject,requester_name,requester_email', 'filters' => 'status,priority,assigned_to,department_id,contact_id',
    ],
    'crm_ticket_replies' => [
        'plugin' => 'Aero.Crm', 'label' => 'respuestas de tickets', 'model' => \Aero\Crm\Models\TicketReply::class,
        'tenant_via' => ['ticket_id', \Aero\Crm\Models\Ticket::class],
        'fields' => 'id,ticket_id,author_type,author_name,body,is_internal,created_at',
        'filters' => 'ticket_id,author_type',
    ],
    'crm_pipelines' => [
        'plugin' => 'Aero.Crm', 'label' => 'embudos del CRM', 'model' => \Aero\Crm\Models\Pipeline::class,
        'fields' => 'id,name,created_at',
    ],
    'crm_pipeline_stages' => [
        'plugin' => 'Aero.Crm', 'label' => 'etapas de embudo', 'model' => \Aero\Crm\Models\PipelineStage::class,
        'fields' => 'id,pipeline_id,name,sort_order,color,is_won,is_lost', 'filters' => 'pipeline_id', 'order' => ['sort_order', 'asc'],
    ],
    'crm_collection_items' => [
        'plugin' => 'Aero.Crm', 'label' => 'cobranzas del CRM', 'model' => \Aero\Crm\Models\CollectionItem::class,
        'fields' => 'id,contact_id,contact_list_id,owner_id,concept,amount,currency,due_date,status,paid_at,last_reminder_at,reminder_count,notes,payment_reference,created_at',
        'search' => 'concept', 'filters' => 'contact_id,contact_list_id,status,currency',
    ],
    'crm_contact_lists' => [
        'plugin' => 'Aero.Crm', 'label' => 'listas de contactos', 'model' => \Aero\Crm\Models\ContactList::class,
        'fields' => 'id,name,description,color,created_at',
    ],
    'crm_departments' => [
        'plugin' => 'Aero.Crm', 'label' => 'departamentos de soporte', 'model' => \Aero\Crm\Models\Department::class,
        'fields' => 'id,name,description,email,is_active,sort_order',
    ],
    'crm_teams' => [
        'plugin' => 'Aero.Crm', 'label' => 'equipos del CRM', 'model' => \Aero\Crm\Models\Team::class,
        'fields' => 'id,name,created_at',
    ],
];
