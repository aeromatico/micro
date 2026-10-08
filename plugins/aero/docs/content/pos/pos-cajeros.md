---
title: Cajeros
sort: 50
---
# Formulario: Cajero

**Ruta:** `Punto de venta → Cajeros`
**Controlador:** `Aero\Pos\Controllers\Cashiers`
**Modelo:** `Aero\Pos\Models\Cashier`
**Permiso:** `aero.pos.manage`

Un cajero es un usuario de tu negocio habilitado para vender en la app, con PIN propio y, opcionalmente, rol de supervisor.

## Campos

| Campo | Para qué sirve |
|-------|----------------|
| **Usuario** | Obligatorio. Personas con acceso a este negocio. |
| **PIN rápido (4 a 6 dígitos)** | Permite cambiar de usuario en la app sin escribir la contraseña. Vacío = no cambia el PIN actual. |
| **Descuento máximo sin autorización (%)** | Vacío = se usa el del negocio ([Configuración](pos-configuracion)). Por encima se pide el PIN de un supervisor. |
| **Supervisor** | Puede autorizar descuentos y anulaciones con su PIN. |
| **Activo** | Los cajeros inactivos no pueden vender. |

**Versión documentada:** 1.0.0
