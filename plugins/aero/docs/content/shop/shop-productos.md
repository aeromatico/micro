# Formulario: Productos

**Ruta:** `Tienda → Productos`
**Controlador:** `Aero\Shop\Controllers\Products`
**Modelo:** `Aero\Shop\Models\Product`
**Permiso:** `aero.shop.manage_products`

El catálogo de la tienda. Cada producto puede ser **físico** (con inventario) o
**digital** (entrega tras el pago), y puede tener **variantes**.

## Pestaña General

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Nombre** | requerido | Nombre del producto. |
| **Slug** | — | URL. Vacío = se genera del nombre. |
| **Tipo de producto** | requerido | Físico (con inventario) o Digital (descarga/entrega tras pago). |
| **Estado** | — | Borrador, Activo o Archivado. |
| **Colección** | — | Colección principal. |
| **Destacado** | — | Se resalta en el catálogo. |
| **Descripción** | — | Texto enriquecido. |
| **Imágenes** | — | Galería del producto. |

## Pestaña Precios e inventario

Se usan **solo si el producto no tiene variantes** (con variantes, cada
combinación gestiona su precio y stock).

| Campo | Para qué sirve |
|-------|----------------|
| **Este producto tiene variantes** | Activa la pestaña *Variantes*. |
| **Precio** | Precio de venta (requerido). |
| **Precio comparativo** | Precio "tachado" de referencia. |
| **Costo** | Costo interno. |
| **SKU** | Código interno. |
| **Controlar inventario** | Descuenta stock al vender. |
| **Permitir venta sin stock** | Vende aunque no haya stock. |
| **Stock disponible** | Existencias. |
| **Peso (gramos)** | Para envíos. |
| **Archivo digital** | Solo para productos digitales; se entrega tras el pago. |

## Pestaña Variantes

- **Opciones** (Talla, Color…): define el nombre y sus valores.
- **Variantes**: un SKU por combinación, con su precio y stock. Ver
  [Variantes y opciones](shop-variantes).

## Pestaña Restaurante

Disponible cuando el tipo de tienda es *Restaurante*.

| Campo | Para qué sirve |
|-------|----------------|
| **Tiempo de preparación (min)** | Se muestra al cliente como tiempo estimado; vacío = no se indica (se usa el valor por defecto de la configuración en el cálculo). |
| **Pedido mínimo (unidades)** | Cantidad mínima del plato por pedido (por defecto 1). |
| **Extras del plato** | Grupos como «Tamaño» o «Adicionales». Guarda el plato primero. |

Cada grupo de extras tiene: **Nombre del grupo**, **Mínimo a elegir** (0 = opcional),
**Máximo a elegir** (1 = una opción; más de 1 = casillas) y una lista de
**Opciones** con nombre, **Costo extra** y **Disponible**.

> [!NOTE]
> Las pestañas *Variantes* y *Restaurante* se muestran según el tipo de tienda.

## Pestaña SEO

| Campo | Para qué sirve |
|-------|----------------|
| **Título SEO** | Título para buscadores. |
| **Descripción SEO** | Resumen para buscadores. |

> [!NOTE]
> En el catálogo, un producto con variantes muestra el **precio más bajo**
> ("Desde $X") y, si hay precios distintos, un rango.

> [!TIP]
> Usa **Borrador** mientras preparas un producto y publica en **Activo** solo
> cuando esté listo.

**Versión documentada:** 1.7.0
