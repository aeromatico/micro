<?php

/** Aero.Finance — solo lectura: los asientos pasan siempre por el libro diario, nunca por escritura directa. */
return [
    'finance_accounts' => [
        'plugin' => 'Aero.Finance', 'label' => 'cuentas contables', 'model' => \Aero\Finance\Models\Account::class,
        'fields' => 'id,code,name,type,parent_id,is_active', 'search' => 'code,name', 'filters' => 'type,is_active,parent_id',
        'order' => ['code', 'asc'],
    ],
    'finance_movements' => [
        'plugin' => 'Aero.Finance', 'label' => 'movimientos de ingresos y egresos', 'model' => \Aero\Finance\Models\Movement::class,
        'fields' => 'id,kind,date,amount,currency,exchange_rate,category_account_id,cash_account_id,description,counterparty,nit,document_no,tax_amount,status,created_at',
        'search' => 'description,counterparty,document_no,nit', 'filters' => 'kind,status,currency,category_account_id,cash_account_id',
        'order' => ['date', 'desc'],
    ],
    'finance_journal_entries' => [
        'plugin' => 'Aero.Finance', 'label' => 'asientos del libro diario', 'model' => \Aero\Finance\Models\JournalEntry::class,
        'fields' => 'id,number,date,description,status,reversal_of_id,source_type,source_id,source_event,created_at',
        'search' => 'description', 'filters' => 'status,source_type', 'order' => ['date', 'desc'],
    ],
    'finance_journal_lines' => [
        'plugin' => 'Aero.Finance', 'label' => 'líneas de asientos', 'model' => \Aero\Finance\Models\JournalLine::class,
        'fields' => 'id,entry_id,account_id,debit,credit,memo', 'filters' => 'entry_id,account_id',
    ],
    'finance_petty_funds' => [
        'plugin' => 'Aero.Finance', 'label' => 'fondos de caja chica', 'model' => \Aero\Finance\Models\PettyFund::class,
        'fields' => 'id,name,custodian,imprest_amount,account_id,is_active', 'filters' => 'is_active',
    ],
    'finance_petty_operations' => [
        'plugin' => 'Aero.Finance', 'label' => 'operaciones de caja chica', 'model' => \Aero\Finance\Models\PettyOperation::class,
        'fields' => 'id,fund_id,kind,date,amount,difference,counter_account_id,description,status,created_at',
        'search' => 'description', 'filters' => 'fund_id,kind,status', 'order' => ['date', 'desc'],
    ],
];
