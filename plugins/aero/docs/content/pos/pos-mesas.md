---
title: Mesas
sort: 30
---
# Formulario: Mesa

**Ruta:** `Punto de venta → Mesas`
**Controlador:** `Aero\Pos\Controllers\Tables`
**Modelo:** `Aero\Pos\Models\PosTable`
**Permiso:** `aero.pos.manage`

Mesas del local para el mapa de mesas y las cuentas por mesa de la app de venta (módulo *Mesas* de la [Configuración](pos-configuracion)).

## Campos

| Campo | Para qué sirve |
|-------|----------------|
| **Mesa** | Obligatorio. Ej: 1, 2, Barra 1, VIP. |
| **Zona** | Ej: Salón, Terraza, Barra. |
| **Asientos** | Capacidad de la mesa. |
| **Orden** | Posición en la lista (por defecto 0). |
| **Activa** | Las mesas inactivas dejan de usarse. |

**Versión documentada:** 1.0.0
