<?php namespace Aero\Shop\Classes;

use Aero\Shop\Classes\Exceptions\InsufficientStockException;
use Aero\Shop\Models\Order;
use Aero\Shop\Models\Product;
use Aero\Shop\Models\ProductVariant;
use Aero\Shop\Models\ShopSettings;
use Aero\Shop\Models\StockMovement;
use Db;

class InventoryService
{
    /**
     * Verifica si hay stock suficiente para vender $qty de este producto/variante,
     * sin mutar nada. Usado por el carrito/checkout público para validar ANTES
     * de intentar reservar (mejor UX que descubrirlo tras el intento de compra).
     */
    public function checkAvailability(Product $product, ?ProductVariant $variant, int $qty): bool
    {
        if (!$this->tracksInventory($variant ?: $product) || $product->allow_backorder) {
            return true;
        }

        $stock = $variant ? $variant->stock_quantity : $product->stock_quantity;
        return $stock >= $qty;
    }

    /**
     * Igual que reserveForOrder(), pero para el checkout público: bloquea cada
     * fila (lockForUpdate), valida stock suficiente dentro de la MISMA
     * transacción (evita overselling por condición de carrera entre dos
     * checkouts simultáneos) y lanza InsufficientStockException si no alcanza,
     * revirtiendo toda la reserva parcial. reserveForOrder() (uso del backend
     * al confirmar pago manual) no valida — asume que ya se reservó al crear
     * el pedido vía este método.
     */
    public function reserveForOrderStrict(Order $order): void
    {
        Db::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                if ($item->product_variant_id) {
                    $stockItem = ProductVariant::query()->lockForUpdate()->find($item->product_variant_id);
                    $product = $stockItem?->product;
                } else {
                    $stockItem = Product::query()->lockForUpdate()->find($item->product_id);
                    $product = $stockItem;
                }

                if (!$stockItem || !$product || !$this->tracksInventory($stockItem)) {
                    continue;
                }

                if (!$product->allow_backorder && $stockItem->stock_quantity < $item->quantity) {
                    throw new InsufficientStockException(
                        "Stock insuficiente para \"{$item->product_name_snapshot}\"."
                    );
                }

                $quantityAfter = max(0, $stockItem->stock_quantity - $item->quantity);
                $stockItem->stock_quantity = $quantityAfter;
                $stockItem->save();

                StockMovement::create([
                    'tenant_id'          => $order->tenant_id,
                    'product_id'         => $product->id,
                    'product_variant_id' => $item->product_variant_id,
                    'type'               => 'sale',
                    'quantity_delta'     => -$item->quantity,
                    'quantity_after'     => $quantityAfter,
                    'order_id'           => $order->id,
                    'note'               => "Pedido {$order->order_number}",
                ]);
            }
        });
    }

    /**
     * Ajusta el stock de un producto o variante y registra el movimiento.
     * Única vía autorizada para mutar stock_quantity — nunca escribir la
     * columna directamente desde un controlador.
     */
    public function adjustStock(
        Product|ProductVariant $item,
        int $delta,
        string $type,
        ?string $note = null,
        ?Order $order = null,
        ?int $backendUserId = null
    ): StockMovement {
        return Db::transaction(function () use ($item, $delta, $type, $note, $order, $backendUserId) {
            $locked = $item::query()->lockForUpdate()->findOrFail($item->id);
            $quantityAfter = max(0, $locked->stock_quantity + $delta);
            $locked->stock_quantity = $quantityAfter;
            $locked->save();

            $isVariant = $item instanceof ProductVariant;

            return StockMovement::create([
                'tenant_id'                  => $locked->tenant_id,
                'product_id'                 => $isVariant ? $locked->product_id : $locked->id,
                'product_variant_id'         => $isVariant ? $locked->id : null,
                'type'                       => $type,
                'quantity_delta'             => $delta,
                'quantity_after'             => $quantityAfter,
                'order_id'                   => $order?->id,
                'note'                       => $note,
                'created_by_backend_user_id' => $backendUserId,
            ]);
        });
    }

    public function reserveForOrder(Order $order, ?int $backendUserId = null): void
    {
        foreach ($order->items as $item) {
            $stockItem = $item->variant ?: $item->product;
            if (!$stockItem || !$this->tracksInventory($stockItem)) {
                continue;
            }
            $this->adjustStock($stockItem, -$item->quantity, 'sale', "Pedido {$order->order_number}", $order, $backendUserId);
        }
    }

    /**
     * ProductVariant no tiene columna track_inventory propia — hereda la del producto padre.
     * El switch "Usar sistema de inventario" del tenant (ShopSettings) manda
     * sobre el track_inventory de cada producto: si el tenant lo apagó, nada
     * se valida ni descuenta, sin importar la configuración individual.
     */
    protected function tracksInventory(Product|ProductVariant $item): bool
    {
        $product = $item instanceof ProductVariant ? $item->product : $item;

        if (!$product || !ShopSettings::inventoryEnabledForTenant($product->tenant_id)) {
            return false;
        }

        return (bool) ($product->track_inventory ?? true);
    }

    public function releaseForOrder(Order $order, ?int $backendUserId = null): void
    {
        foreach ($order->items as $item) {
            $stockItem = $item->variant ?: $item->product;
            if (!$stockItem || !$this->tracksInventory($stockItem)) {
                continue;
            }
            $this->adjustStock($stockItem, $item->quantity, 'return', "Cancelación pedido {$order->order_number}", $order, $backendUserId);
        }
    }

    public function restock(Product|ProductVariant $item, int $quantity, ?string $note, int $backendUserId): StockMovement
    {
        return $this->adjustStock($item, abs($quantity), 'restock', $note, null, $backendUserId);
    }

    public function manualAdjustment(Product|ProductVariant $item, int $delta, ?string $note, int $backendUserId): StockMovement
    {
        return $this->adjustStock($item, $delta, 'manual_adjustment', $note, null, $backendUserId);
    }
}
