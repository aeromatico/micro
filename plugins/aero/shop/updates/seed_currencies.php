<?php

use October\Rain\Database\Updates\Seeder;

return new class extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'BOB', 'name' => 'Boliviano', 'symbol' => 'Bs', 'decimal_places' => 2],
            ['code' => 'USD', 'name' => 'Dólar estadounidense', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2],
        ];

        foreach ($currencies as $currency) {
            \Aero\Shop\Models\Currency::firstOrCreate(
                ['code' => $currency['code']],
                $currency + ['is_active' => true]
            );
        }
    }
};
