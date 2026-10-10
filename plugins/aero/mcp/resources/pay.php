<?php

/** Aero.Pay — solo lectura. Sin credenciales, tokens ni webhooks; cobrar se hace con los nodos/servicios de Pay. */
return [
    'pay_qr_codes' => [
        'plugin' => 'Aero.Pay', 'label' => 'cobros QR', 'model' => \Aero\Pay\Models\QrCode::class,
        'fields' => 'id,bank_account_id,product_id,bank_code,flow,origin,internal_reference,external_qr_id,amount,currency,description,branch_code,due_date,single_use,modify_amount,status,customer_nit,tax_percentage,tax_amount,created_at',
        'search' => 'internal_reference,description,customer_nit', 'filters' => 'status,bank_code,flow,origin,currency,bank_account_id',
    ],
    'pay_payments' => [
        'plugin' => 'Aero.Pay', 'label' => 'pagos recibidos', 'model' => \Aero\Pay\Models\Payment::class,
        'fields' => 'id,qr_code_id,bank_account_id,bank_code,source,origin,external_transaction_id,qr_reference,amount,currency,sender_bank_code,sender_name,payment_date,payment_time,created_at',
        'search' => 'sender_name,qr_reference,external_transaction_id', 'filters' => 'qr_code_id,bank_account_id,bank_code,currency,source',
    ],
    'pay_bank_accounts' => [
        'plugin' => 'Aero.Pay', 'label' => 'cuentas bancarias', 'model' => \Aero\Pay\Models\BankAccount::class,
        'fields' => 'id,bank_code,label,environment,status,created_at', 'filters' => 'bank_code,status,environment',
    ],
    'pay_branches' => [
        'plugin' => 'Aero.Pay', 'label' => 'sucursales de cobro', 'model' => \Aero\Pay\Models\Branch::class,
        'fields' => 'id,name,code,whatsapp,telegram,city,address', 'search' => 'name,code,city',
    ],
];
