<?= $record->commission_type === 'fixed'
    ? 'Bs ' . number_format($record->commission_value, 2) . ' por pedido'
    : rtrim(rtrim(number_format($record->commission_value, 2), '0'), '.') . ' %' ?>
