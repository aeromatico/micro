---
title: Comerciantes (Marketplace)
sort: 85
---
# Formulario: Comerciantes (modo Marketplace)

**Ruta:** `Tienda → Comerciantes` (solo con el tipo de tienda *Marketplace*)
**Controlador:** `Aero\Shop\Controllers\Sellers`
**Modelo:** `Aero\Shop\Models\Seller`
**Permiso:** `aero.shop.manage_sellers`

En el modo **Marketplace** convierte a miembros de tu equipo en **comerciantes**:
cada uno vende sus propios productos, cobra en su propia cuenta y paga a la tienda
una comisión. Se activa en [Configuración de tienda](shop-configuracion), opción
**Marketplace** del tipo de tienda.

> [!IMPORTANT]
> Solo el **administrador de la tienda** (dueño, administrador del equipo o
> superusuario) gestiona comerciantes. Si el modo Marketplace no está activo, la
> pantalla responde 404; si no eres administrador, 403.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Miembro del equipo** | requerido | Quién es el comerciante. Se gestiona en CRM → Equipo. La lista solo ofrece miembros del equipo (y al dueño) que aún no son comerciantes. Solo ve y cobra sus propios productos. |
| **Nombre visible** | máx. 120 | Lo ve el comprador junto a los productos. Vacío = el nombre del miembro (o su usuario). |
| **Cuenta de cobro** | requerido | Cuenta activa de tu tienda (Bolivia Pay → Cuentas bancarias). Los pagos de sus pedidos van directo a esta cuenta. |
| **Activo** | — | Valor por defecto: activo. Un comerciante desactivado deja de aparecer en la tienda pública con sus productos. |
| **Tipo de comisión** | requerido | **Porcentaje (%)** o **Monto fijo por pedido**. Por defecto, porcentaje. |
| **Valor de la comisión** | número ≥ 0 | Lo que retiene la tienda sobre los productos de cada pedido. Por defecto 0. |

Reglas reales:

- Con tipo *Porcentaje*, un valor mayor de 100 se corrige a 100.
- La comisión nunca supera el monto de los productos del pedido.
- La cuenta de cobro debe ser de tu propia tienda; si no, el guardado se rechaza.

## Cómo afecta al resto de la tienda

- **Productos:** el administrador elige el **Comerciante** de cada producto (o lo deja
  de la tienda). Un comerciante solo ve y crea sus propios productos, que quedan a su nombre.
- **Pedidos:** una compra con productos de varios comerciantes se **divide en un
  pedido por comerciante**, cada uno con su comisión y su neto. El QR de cobro se
  emite con la cuenta del comerciante. Un comerciante solo ve los pedidos propios
  en [Pedidos](shop-pedidos).
- **Mis ventas:** cada comerciante activo ve su propio **balance** y sus pedidos
  (permiso `aero.shop.sell`), con filtro por fechas. El administrador ve el mismo
  detalle de cada comerciante desde la lista de Comerciantes.

## Balance

| Dato | Cómo se calcula |
|------|-----------------|
| **Cobrado** | Total de pedidos pagados o despachados. |
| **Comisión** | Suma de la comisión de la tienda en esos pedidos. |
| **Neto** | Lo que corresponde al comerciante. |
| **Por cobrar** | Pedidos pendientes o esperando pago. |
| **Reembolsado** | Pedidos reembolsados. |

> [!NOTE]
> El cobro es directo a la cuenta de cada comerciante: el balance es
> **informativo**; no hay libro ni liquidaciones dentro del sistema.

**Versión documentada:** 1.10.0
