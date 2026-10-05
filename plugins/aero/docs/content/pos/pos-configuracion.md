---
title: Configuración del POS
sort: 10
---
# Formulario: Configuración del punto de venta

**Ruta:** `Punto de venta → Configuración`
**Controlador:** `Aero\Pos\Controllers\Settings`
**Modelo:** `Aero\Pos\Models\PosSettings`
**Permiso:** `aero.pos.manage`

Define qué tipo de negocio eres y qué módulos muestra la app de venta. Si la tienda está desactivada, la pantalla lo avisa con un enlace a *Tienda → Configuración*.

## Campos

| Campo | Para qué sirve |
|-------|----------------|
| **Tipo de negocio** | *Restaurante*, *Tienda / comercio* o *Cobro rápido (servicios)*. Al cambiarlo se activan los módulos recomendados; luego puedes ajustarlos uno por uno. |
| **Mesas** | Mapa de mesas y cuentas por mesa. |
| **Cuentas abiertas** | Agregar platos a una cuenta y cobrar al final. |
| **Enviar a cocina** | Las comandas llegan a la pantalla de [Cocina](shop-cocina). |
| **Propina** | Habilita la propina al cobrar. |
| **Código de barras** | Agregar productos escaneando. |
| **Venta libre (monto abierto)** | Cobrar un monto sin producto del catálogo. |
| **Exigir turno de caja abierto para vender** | Sin turno abierto no se puede vender. |
| **Descuento máximo sin autorización (%)** | Entre 0 y 100 (por defecto 10). Por encima se pide el PIN de un supervisor. |
| **Propinas sugeridas (%)** | Porcentajes separados por coma (ej. `0,5,10`). Solo valores entre 0 y 100; si queda vacío se usa `0,5,10`. |
| **Ancho del papel** | 58 mm u 80 mm. |
| **Encabezado** / **Pie** | Texto del ticket (nombre, dirección, teléfono; mensaje de agradecimiento). |

## Módulos por tipo de negocio

| Módulo | Restaurante | Tienda / comercio | Cobro rápido |
|--------|:-----------:|:-----------------:|:------------:|
| Mesas | Sí | No | No |
| Cuentas abiertas | Sí | No | No |
| Enviar a cocina | Sí | No | No |
| Propina | Sí | No | Sí |
| Código de barras | No | Sí | No |
| Venta libre | No | Sí | Sí |

> [!NOTE]
> Si cambias el tipo de negocio y guardas, los módulos se reemplazan por los del nuevo tipo. Si guardas sin cambiarlo, se respetan tus interruptores.

**Versión documentada:** 1.0.0
