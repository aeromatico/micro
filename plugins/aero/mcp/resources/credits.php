<?php

/** Aero.Credits — solo lectura del saldo y su libro. Nunca se acredita/debita por esta vía. */
return [
    'credits_accounts' => [
        'plugin' => 'Aero.Credits', 'label' => 'saldos de créditos', 'model' => \Aero\Credits\Models\CreditAccount::class,
        'fields' => 'id,credit_type_id,balance,updated_at', 'filters' => 'credit_type_id',
    ],
    'credits_transactions' => [
        'plugin' => 'Aero.Credits', 'label' => 'movimientos de créditos', 'model' => \Aero\Credits\Models\CreditTransaction::class,
        'fields' => 'id,credit_type_id,delta,kind,balance_after,action_code,source_plugin,reason,created_at',
        'filters' => 'credit_type_id,kind,action_code,source_plugin',
    ],
    'credits_purchases' => [
        'plugin' => 'Aero.Credits', 'label' => 'compras de créditos', 'model' => \Aero\Credits\Models\CreditPurchase::class,
        'fields' => 'id,amount_bob,wallet_units,status,payment_reference,expires_at,paid_at,created_at', 'filters' => 'status',
    ],
];
