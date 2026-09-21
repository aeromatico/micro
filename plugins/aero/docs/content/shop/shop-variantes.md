# Formulario: Variantes y opciones

**Ruta:** `Tienda → Productos → (abrir producto) → pestaña Variantes`
**Controlador:** `Aero\Shop\Controllers\Products`
**Modelos:** `Aero\Shop\Models\ProductOption`, `Aero\Shop\Models\ProductVariant`

Las variantes permiten vender un mismo producto en distintas combinaciones
(talla, color...) con su propio precio, SKU y stock. Para usarlas, activa
**Este producto tiene variantes** en la pestaña *Precios e inventario*.

## Opciones

Define primero las opciones del producto (`Opciones (Talla, Color...)`):

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Nombre de la opción** | requerido | Ej. *Talla*, *Color*. |
| **Valores** | — | Lista de valores, ej. *S*, *M*, *L* o *Rojo*, *Azul*. |

## Variantes

Cada variante es una combinación concreta (`Variantes (SKU por combinación)`).

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **SKU** | requerido | Código único de la variante. |
| **Activa** | — | Si está apagada, no se ofrece. |
| **Precio** | requerido | Precio de esta combinación. |
| **Precio comparativo** | — | Precio tachado. |
| **Costo** | — | Costo interno. |
| **Stock** | — | Existencias de la variante. |
| **Peso (gramos)** | — | Para envíos. |
| **Combinación (opciones)** | — | El valor de cada opción que define la variante. |
| **Imagen** | — | Imagen específica de la variante. |

```text
Opciones:  Talla = [S, M, L]      Color = [Rojo, Azul]
Variantes: S/Rojo · S/Azul · M/Rojo · M/Azul · L/Rojo · L/Azul
```

> [!NOTE]
> Al crear la variante, selecciona el valor correspondiente de **cada opción**
> del producto para formar la combinación.

> [!TIP]
> Si un producto tiene muchas opciones, crea solo las combinaciones que
> realmente vendes: no hace falta generar el producto cartesiano completo.
