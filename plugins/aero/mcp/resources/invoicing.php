<?php

/** Aero.Invoicing — solo lectura (emitir y anular tienen reglas fiscales y pasan por el servicio). Sin xml: es voluminoso. */
return [
    'invoicing_invoices' => [
        'plugin' => 'Aero.Invoicing', 'label' => 'facturas electrónicas', 'model' => \Aero\Invoicing\Models\Invoice::class,
        'fields' => 'id,uuid,issuer_id,reference,status,buyer_nit,buyer_doc_type,buyer_name,total,iva,number,cuf,reception_code,qr_url,issued_at,voided_at,created_at',
        'search' => 'buyer_name,buyer_nit,number,reference', 'filters' => 'issuer_id,status',
    ],
];
