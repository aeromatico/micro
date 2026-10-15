<?php

/** Aero.School — colegio: estudiantes, apoderados, cursos, cuotas, pagos, asistencia, notas y comunicados. */
return [
    'school_years' => [
        'plugin' => 'Aero.School', 'label' => 'gestiones escolares', 'model' => \Aero\School\Models\Year::class,
        'fields' => 'id,name,starts_on,ends_on,is_current,periods', 'filters' => 'is_current',
    ],
    'school_courses' => [
        'plugin' => 'Aero.School', 'label' => 'cursos', 'model' => \Aero\School\Models\Course::class,
        'fields' => 'id,year_id,name,level,grade,parallel,shift,tutor,capacity,is_active', 'search' => 'name,tutor', 'filters' => 'year_id,level,is_active',
    ],
    'school_subjects' => [
        'plugin' => 'Aero.School', 'label' => 'áreas y materias', 'model' => \Aero\School\Models\Subject::class,
        'fields' => 'id,course_id,name,field,teacher,sort_order', 'search' => 'name,teacher', 'filters' => 'course_id', 'order' => ['sort_order', 'asc'],
    ],
    'school_students' => [
        'plugin' => 'Aero.School', 'label' => 'estudiantes', 'model' => \Aero\School\Models\Student::class,
        'fields' => 'id,rude,document,first_name,paternal_surname,maternal_surname,birthdate,sex,course_id,status,discount_pct,discount_note,enrolled_on,notes',
        'search' => 'first_name,paternal_surname,maternal_surname,rude,document', 'filters' => 'course_id,status',
    ],
    'school_guardians' => [
        'plugin' => 'Aero.School', 'label' => 'apoderados', 'model' => \Aero\School\Models\Guardian::class,
        'fields' => 'id,name,document,phone,email,address,occupation', 'search' => 'name,phone,email,document',
    ],
    'school_fee_plans' => [
        'plugin' => 'Aero.School', 'label' => 'conceptos de cobro', 'model' => \Aero\School\Models\FeePlan::class,
        'fields' => 'id,year_id,name,kind,amount,course_id,level,installments,first_month,due_day,is_active', 'filters' => 'kind,year_id,course_id,is_active',
    ],
    'school_charges' => [
        'plugin' => 'Aero.School', 'label' => 'cuotas de estudiantes', 'model' => \Aero\School\Models\Charge::class,
        'fields' => 'id,student_id,year_id,fee_plan_id,kind,concept,period,due_on,amount,discount,paid_amount,status,paid_at,notes',
        'search' => 'concept', 'filters' => 'student_id,status,kind,period', 'order' => ['due_on', 'desc'],
    ],
    'school_payments' => [
        'plugin' => 'Aero.School', 'label' => 'pagos y recibos', 'model' => \Aero\School\Models\Payment::class,
        'fields' => 'id,student_id,guardian_id,receipt_no,amount,method,reference,paid_at,notes,voided_at',
        'filters' => 'student_id,method', 'order' => ['paid_at', 'desc'],
    ],
    'school_payment_items' => [
        'plugin' => 'Aero.School', 'label' => 'detalle de pagos', 'model' => \Aero\School\Models\PaymentItem::class,
        'tenant_via' => ['payment_id', \Aero\School\Models\Payment::class],
        'fields' => 'id,payment_id,charge_id,amount', 'filters' => 'payment_id,charge_id',
    ],
    'school_attendances' => [
        'plugin' => 'Aero.School', 'label' => 'asistencia', 'model' => \Aero\School\Models\Attendance::class,
        'fields' => 'id,student_id,course_id,date,status,note,notified_at', 'filters' => 'student_id,course_id,status,date', 'order' => ['date', 'desc'],
    ],
    'school_grades' => [
        'plugin' => 'Aero.School', 'label' => 'calificaciones', 'model' => \Aero\School\Models\Grade::class,
        'fields' => 'id,student_id,subject_id,course_id,year_id,period,ser,saber,hacer,decidir,auto_ser,auto_decidir,total,qualitative',
        'filters' => 'student_id,subject_id,course_id,period',
    ],
    'school_announcements' => [
        'plugin' => 'Aero.School', 'label' => 'comunicados', 'model' => \Aero\School\Models\Announcement::class,
        'fields' => 'id,title,body,audience,level,course_id,status,sent_at,total,delivered,failed', 'search' => 'title', 'filters' => 'status,audience',
    ],
    'school_deliveries' => [
        'plugin' => 'Aero.School', 'label' => 'entregas de comunicados', 'model' => \Aero\School\Models\Delivery::class,
        'tenant_via' => ['announcement_id', \Aero\School\Models\Announcement::class],
        'fields' => 'id,announcement_id,guardian_id,status,error,created_at', 'filters' => 'announcement_id,status',
    ],
];
