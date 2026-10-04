<?php

return [
    'plugin' => ['name' => 'Punto de venta'],
    'menu' => [
        'top'             => 'Punto de venta',
        'sales'           => 'Ventas',
        'shifts'          => 'Turnos de caja',
        'tables'          => 'Mesas',
        'payment_methods' => 'Métodos de pago',
        'terminals'       => 'Terminales',
        'cashiers'        => 'Cajeros',
        'settings'        => 'Configuración',
    ],
    'permissions' => [
        'use'           => 'Vender y cobrar en el POS',
        'discount'      => 'Dar descuentos',
        'void'          => 'Anular y devolver ventas',
        'manage_shifts' => 'Gestionar turnos de caja (cerrar ajenos)',
        'reports'       => 'Ver reportes de ventas y caja',
        'manage'        => 'Administrar el POS (mesas, métodos, cajeros, ajustes)',
    ],
];
