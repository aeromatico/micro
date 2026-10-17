---
title: Rangos alto/bajo
sort: 55
---
# Rangos alto/bajo

Límites con los que la [hoja de seguimiento](medical-hoja-de-seguimiento) marca en rojo los valores fuera de rango.

**Ruta:** `Historia clínica → Rangos alto/bajo` (`/backend/aero/medical/ranges`)
**Controlador:** `Aero\Medical\Controllers\Ranges`
**Modelo:** `Aero\Medical\Models\Range`
**Permiso:** `aero.medical.professional` o `aero.medical.settings`

| Campo | Qué hace |
|-------|----------|
| Modo | Humano o animal: los rangos son independientes. |
| Especie | Solo animal. Un rango de una especie manda sobre el rango «Todas». |
| Medición | Un signo vital del modo o un [analito](medical-estudios-y-archivos) del negocio. |
| Límite bajo / alto | Se marca ▼ si el valor es menor al bajo y ▲ si es mayor al alto. Basta uno de los dos. |

> [!IMPORTANT]
> El sistema **no trae rangos de fábrica**: los define el negocio (un veterinario para cada especie, un médico para personas). Mientras no haya un rango, no se marca nada en rojo.

**Versión documentada:** 1.10.1
