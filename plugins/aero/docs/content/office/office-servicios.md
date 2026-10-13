---
title: Servicios
sort: 50
---
# Servicios

Lo que tu negocio ofrece para reservar: consulta, corte, clase, etc.

**Ruta:** `Reservas → Servicios` (`/backend/aero/office/services`)
**Controlador:** `Aero\Office\Controllers\Services`
**Modelo:** `Aero\Office\Models\Service`
**Permiso:** `aero.office.use`

## Campos

| Campo | Descripción |
|-------|-------------|
| Nombre (obligatorio) | Nombre del servicio. |
| Categoría | Agrupa los servicios en el portal. |
| Descripción | Texto que ve el cliente. |
| Duración (minutos, obligatorio) | Por defecto 30. |
| Descanso posterior (minutos) | Tiempo de preparación entre una atención y la siguiente. |
| Precio | Se copia a cada reserva; cambiarlo no altera las ya hechas. |
| Requiere aprobación | Apagado = las reservas públicas quedan confirmadas al instante. Encendido = quedan Pendientes. |
| Activo | Un servicio inactivo no se puede reservar. |
| Visible en el portal público | Oculto = solo se reserva manualmente desde el panel. |
| Sucursales | Dónde se ofrece. |
| Profesionales que lo realizan | Quién puede atenderlo. |

> [!IMPORTANT]
> Un servicio solo se puede reservar en las sucursales y con los profesionales que le asignes aquí (o desde la ficha de la sucursal o del profesional).

Relacionados: [Profesionales](office-profesionales), [Sucursales](office-sucursales).

**Versión documentada:** 1.1.0
