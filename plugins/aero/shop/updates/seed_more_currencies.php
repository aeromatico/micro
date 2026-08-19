<?php

use October\Rain\Database\Updates\Seeder;

return new class extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'ARS',  'name' => 'Peso argentino',    'symbol' => '$',    'decimal_places' => 2],
            ['code' => 'BRL',  'name' => 'Real brasileño',    'symbol' => 'R$',   'decimal_places' => 2],
            ['code' => 'COP',  'name' => 'Peso colombiano',   'symbol' => '$',    'decimal_places' => 2],
            ['code' => 'CLP',  'name' => 'Peso chileno',      'symbol' => '$',    'decimal_places' => 0],
            ['code' => 'PEN',  'name' => 'Sol peruano',       'symbol' => 'S/',   'decimal_places' => 2],
            ['code' => 'USDT', 'name' => 'Tether (USDT)',     'symbol' => 'USDT', 'decimal_places' => 2],
            ['code' => 'USDC', 'name' => 'USD Coin (USDC)',   'symbol' => 'USDC', 'decimal_places' => 2],
        ];

        foreach ($currencies as $currency) {
            \Aero\Shop\Models\Currency::firstOrCreate(
                ['code' => $currency['code']],
                $currency + ['is_active' => true]
            );
        }
    }
};
