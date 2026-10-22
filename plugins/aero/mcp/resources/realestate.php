<?php

/** Aero.Realestate — inmobiliaria: propiedades, ofertas, clientes, propietarios, agentes. Solo lectura (las ofertas tienen reglas de negocio). */
return [
    'realestate_properties' => [
        'plugin' => 'Aero.Realestate', 'label' => 'propiedades', 'model' => \Aero\Realestate\Models\Property::class,
        'fields' => 'id,code,title,type,status,department,city,zone,neighborhood,address,lat,lng,land_area,built_area,bedrooms,bathrooms,garages,floors,age_years,owner_id,agent_id,is_featured,is_published,published_at,created_at',
        'search' => 'code,title,city,zone,neighborhood,address', 'filters' => 'type,status,city,is_published,agent_id,owner_id',
        'order' => ['id', 'desc'],
    ],
    'realestate_offers' => [
        'plugin' => 'Aero.Realestate', 'label' => 'ofertas (venta, alquiler, anticrético)', 'model' => \Aero\Realestate\Models\Offer::class,
        'fields' => 'id,property_id,modality,amount,currency,term_months,deposit,conditions,status,closed_at,created_at',
        'filters' => 'property_id,modality,currency,status', 'order' => ['id', 'desc'],
    ],
    'realestate_clients' => [
        'plugin' => 'Aero.Realestate', 'label' => 'clientes interesados', 'model' => \Aero\Realestate\Models\Client::class,
        'fields' => 'id,name,phone,email,document,interests,budget_min,budget_max,currency,agent_id,source,notes,created_at',
        'search' => 'name,phone,email,document', 'filters' => 'agent_id,source',
    ],
    'realestate_owners' => [
        'plugin' => 'Aero.Realestate', 'label' => 'propietarios', 'model' => \Aero\Realestate\Models\Owner::class,
        'fields' => 'id,name,phone,email,document,address,notes,created_at', 'search' => 'name,phone,email,document',
    ],
    'realestate_agents' => [
        'plugin' => 'Aero.Realestate', 'label' => 'agentes inmobiliarios', 'model' => \Aero\Realestate\Models\Agent::class,
        'fields' => 'id,name,phone,whatsapp,email,license,is_active', 'search' => 'name,email,license', 'filters' => 'is_active',
    ],
    'realestate_contracts' => [
        'plugin' => 'Aero.Realestate', 'label' => 'contratos de anticrético', 'model' => \Aero\Realestate\Models\Contract::class,
        'fields' => 'id,code,property_id,offer_id,owner_id,client_id,agent_id,capital,currency,term_months,start_date,end_date,status,delivered_amount,returned_amount,return_status,in_dispute,activated_at,finished_at,created_at',
        'search' => 'code', 'filters' => 'status,return_status,property_id,client_id,owner_id,agent_id,currency,in_dispute', 'order' => ['end_date', 'asc'],
    ],
    'realestate_contract_payments' => [
        'plugin' => 'Aero.Realestate', 'label' => 'entregas y devoluciones de capital', 'model' => \Aero\Realestate\Models\Payment::class,
        'fields' => 'id,contract_id,type,amount,currency,paid_on,method,reference,created_at', 'filters' => 'contract_id,type', 'order' => ['paid_on', 'desc'],
    ],
    'realestate_contract_documents' => [
        'plugin' => 'Aero.Realestate', 'label' => 'documentos de anticrético', 'model' => \Aero\Realestate\Models\ContractDocument::class,
        'fields' => 'id,contract_id,type,number,issued_on,status,verified_at,created_at', 'filters' => 'contract_id,type,status',
    ],
    'realestate_contract_events' => [
        'plugin' => 'Aero.Realestate', 'label' => 'historial de contratos de anticrético', 'model' => \Aero\Realestate\Models\ContractEvent::class,
        'fields' => 'id,contract_id,type,summary,created_at', 'filters' => 'contract_id,type', 'order' => ['id', 'desc'],
    ],
    'realestate_leads' => [
        'plugin' => 'Aero.Realestate', 'label' => 'oportunidades comerciales (leads)', 'model' => \Aero\Realestate\Models\Lead::class,
        'fields' => 'id,client_id,property_id,offer_id,agent_id,modality,stage,source,final_amount,lost_reason,last_contact_at,next_follow_up_at,closed_at,created_at',
        'filters' => 'stage,modality,agent_id,client_id,property_id', 'order' => ['id', 'desc'],
    ],
    'realestate_visits' => [
        'plugin' => 'Aero.Realestate', 'label' => 'visitas a propiedades', 'model' => \Aero\Realestate\Models\Visit::class,
        'fields' => 'id,code,property_id,client_id,lead_id,agent_id,starts_at,ends_at,status,source,cancel_reason,created_at',
        'search' => 'code', 'filters' => 'status,property_id,client_id,agent_id', 'order' => ['starts_at', 'desc'],
    ],
];
