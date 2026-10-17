<?php

/**
 * Aero.Medical — SOLO datos administrativos y de lectura: pacientes (sin alergias, medicación, antecedentes ni
 * notas) y cobros (sin el concepto, que puede nombrar un servicio). Todo el contenido clínico (notas, signos
 * vitales, diagnósticos, receta, órdenes, laboratorio, archivos, vacunas, documentos) está excluido a propósito.
 */
return [
    'medical_patients' => [
        'plugin' => 'Aero.Medical', 'label' => 'pacientes (datos administrativos, sin contenido clínico)', 'model' => \Aero\Medical\Models\Patient::class,
        'fields' => 'id,kind,name,document,birth_date,sex,species,breed,microchip,is_active,customer_id,created_at',
        'search' => 'name,document,microchip', 'filters' => 'kind,is_active,customer_id',
    ],
    'medical_charges' => [
        'plugin' => 'Aero.Medical', 'label' => 'cobros de la atención (sin concepto)', 'model' => \Aero\Medical\Models\Charge::class,
        'fields' => 'id,patient_id,consultation_id,booking_id,amount,currency,status,method,paid_at,created_at',
        'filters' => 'patient_id,status,method', 'order' => ['id', 'desc'],
    ],
];
