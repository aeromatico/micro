<?php

/**
 * Aero.Clinica — SOLO datos administrativos y de lectura: pacientes (sin alergias, medicación, antecedentes,
 * enfermedades crónicas, hábitos ni notas) y cobros (sin el concepto, que puede nombrar un servicio). Todo el
 * contenido clínico (notas, signos vitales, diagnósticos, receta, órdenes, laboratorio, archivos, vacunas,
 * documentos) está excluido a propósito.
 */
return [
    'clinica_patients' => [
        'plugin' => 'Aero.Clinica', 'label' => 'pacientes de la clínica (datos administrativos, sin contenido clínico)', 'model' => \Aero\Clinica\Models\Patient::class,
        'fields' => 'id,name,document,birth_date,sex,is_active,customer_id,created_at',
        'search' => 'name,document', 'filters' => 'is_active,customer_id',
    ],
    'clinica_charges' => [
        'plugin' => 'Aero.Clinica', 'label' => 'cobros de la atención de la clínica (sin concepto)', 'model' => \Aero\Clinica\Models\Charge::class,
        'fields' => 'id,patient_id,consultation_id,booking_id,amount,currency,status,method,paid_at,created_at',
        'filters' => 'patient_id,status,method', 'order' => ['id', 'desc'],
    ],
];
