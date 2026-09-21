# Formulario: Configuración de tienda

**Ruta:** `Tienda → Configuración de tienda`
**Controlador:** `Aero\Shop\Controllers\ShopSettings`
**Modelo:** `Aero\Shop\Models\ShopSettings`
**Permiso:** `aero.shop.manage_settings`

Activa la tienda del sitio y define sus reglas generales. Mientras la tienda
esté apagada, el menú de catálogo y pedidos no aparece.

## Campos

| Campo | Para qué sirve |
|-------|----------------|
| **Tienda activada** | Interruptor maestro del módulo. |
| **Moneda base** | Moneda en la que se expresan los precios. |
| **Usar sistema de inventario** | Si se apaga, la tienda no valida ni descuenta stock (útil para catálogos bajo pedido, servicios, etc.) y se oculta el menú *Inventario*. |
| **Permitir compra como invitado** | Habilita el checkout sin registrarse. |
| **Prefijo de número de pedido** | Prefijo de los números de pedido (ej. `ORD-`). |
| **Umbral de stock bajo** | Cantidad a partir de la cual se considera stock bajo. |

## Monedas

Además de la moneda base, puedes agregar otras monedas con su **tipo de cambio**.
Se administran en la misma pantalla:

| Acción | Qué hace |
|--------|----------|
| **Agregar / actualizar** | Añade una moneda con su tipo de cambio. |
| **Eliminar** | Quita la moneda del sitio. |

> [!NOTE]
> Si el inventario está apagado, el sistema no reserva ni descuenta stock al
> vender, y la sección *Inventario* deja de mostrarse.

> [!TIP]
> Deja la **compra como invitado** activada para reducir la fricción; puedes
> vincular al comprador con un cliente después.
