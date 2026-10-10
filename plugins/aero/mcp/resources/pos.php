<?php

/** Aero.Pos — solo lectura (ventas y caja pasan por el servicio de POS). Sin cajeros (pin) ni tokens de dispositivo. */
return [
    'pos_sales' => [
        'plugin' => 'Aero.Pos', 'label' => 'ventas del POS', 'model' => \Aero\Pos\Models\Sale::class,
        'fields' => 'id,order_id,shift_id,terminal_id,cashier_user_id,table_id,tab_state,tip_total,discount_reason,nit,tax_name,closed_at,created_at',
        'filters' => 'shift_id,terminal_id,cashier_user_id,table_id,tab_state', 'search' => 'nit,tax_name',
    ],
    'pos_payments' => [
        'plugin' => 'Aero.Pos', 'label' => 'pagos del POS', 'model' => \Aero\Pos\Models\Payment::class,
        'fields' => 'id,sale_id,shift_id,payment_method_id,amount,tendered,change_given,reference,status,user_id,created_at',
        'filters' => 'sale_id,shift_id,payment_method_id,status',
    ],
    'pos_payment_methods' => [
        'plugin' => 'Aero.Pos', 'label' => 'métodos de pago del POS', 'model' => \Aero\Pos\Models\PaymentMethod::class,
        'fields' => 'id,code,label,kind,is_active,sort_order', 'filters' => 'is_active,kind',
    ],
    'pos_shifts' => [
        'plugin' => 'Aero.Pos', 'label' => 'turnos de caja', 'model' => \Aero\Pos\Models\Shift::class,
        'fields' => 'id,terminal_id,opened_by_user_id,closed_by_user_id,opened_at,closed_at,opening_cash,expected_cash,counted_cash,difference,status,notes,summary',
        'filters' => 'terminal_id,status',
    ],
    'pos_cash_movements' => [
        'plugin' => 'Aero.Pos', 'label' => 'movimientos de caja', 'model' => \Aero\Pos\Models\CashMovement::class,
        'fields' => 'id,shift_id,type,amount,reason,user_id,created_at', 'filters' => 'shift_id,type',
    ],
    'pos_tables' => [
        'plugin' => 'Aero.Pos', 'label' => 'mesas del POS', 'model' => \Aero\Pos\Models\PosTable::class,
        'fields' => 'id,zone,name,seats,sort_order,is_active', 'filters' => 'zone,is_active',
    ],
    'pos_terminals' => [
        'plugin' => 'Aero.Pos', 'label' => 'terminales del POS', 'model' => \Aero\Pos\Models\Terminal::class,
        'fields' => 'id,name,code,is_active', 'filters' => 'is_active',
    ],
];
