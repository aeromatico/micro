# Función: Inventario

**Ruta:** `Tienda → Inventario`
**Controlador:** `Aero\Shop\Controllers\StockMovements`
**Modelo:** `Aero\Shop\Models\StockMovement`
**Permiso:** `aero.shop.manage_inventory`

Historial de **movimientos de stock** y registro de **ajustes manuales**. Solo
aparece si *Usar sistema de inventario* está activo en la configuración.

## Columnas

| Columna | Para qué sirve |
|---------|----------------|
| **Producto** | Producto afectado. |
| **Variante** | Variante (si aplica). |
| **Tipo** | Venta, Ajuste manual, Reposición, Devolución o Inicial. |
| **Cambio** | Cuánto varió el stock (positivo o negativo). |
| **Stock resultante** | Stock después del movimiento. |
| **Nota** | Comentario del movimiento. |
| **Fecha** | Cuándo ocurrió. |

## Tipos de movimiento

| Tipo | Origen |
|------|--------|
| `sale` | Venta (generado por un pedido). |
| `manual_adjustment` | Ajuste manual desde esta pantalla. |
| `restock` | Reposición de mercadería. |
| `return` | Devolución. |
| `initial` | Carga inicial de stock. |

## Registrar un ajuste

El botón **Ajuste** abre un formulario: eliges el **producto** (y la variante, si
tiene), la **cantidad** (delta, puede ser negativa) y una **nota**. El sistema
calcula el stock resultante y deja el movimiento registrado.

> [!TIP]
> Usa ajustes manuales para corregir diferencias de conteo o dar de baja
> productos dañados, siempre con una nota clara.

> [!NOTE]
> Las ventas y cancelaciones generan movimientos solas: al cancelar un pedido,
> el stock reservado se libera.
