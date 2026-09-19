<?php namespace Aero\Shop\Classes\Api;

class Scopes
{
    public const PRODUCTS_READ = 'shop.products.read';
    public const ORDERS_READ = 'shop.orders.read';
    public const ORDERS_WRITE = 'shop.orders.write';

    public static function all(): array
    {
        return [
            self::PRODUCTS_READ => 'Consultar el catálogo y los métodos de pago',
            self::ORDERS_READ   => 'Consultar pedidos',
            self::ORDERS_WRITE  => 'Crear y cancelar pedidos',
        ];
    }
}
