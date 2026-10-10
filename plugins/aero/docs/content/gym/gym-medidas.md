---
title: Medidas y progreso
sort: 60
---
# Formulario: Medida

**Ruta:** `Gimnasio → Medidas`
**Controlador:** `Aero\Gym\Controllers\Measurements`
**Modelo:** `Aero\Gym\Models\Measurement`
**Permiso:** `aero.gym.use`

Guarda la evolución corporal del socio por fecha. El socio también puede registrar las suyas desde su portal en `/gym/progreso`.

## Campos

| Campo | Para qué sirve |
|-------|----------------|
| **Socio** y **Fecha** | Una medición por socio y día. |
| **Peso (kg)** | Peso corporal. |
| **Estatura (cm)** | Vacío = la última conocida del socio. |
| **Grasa corporal (%)** y **Músculo (%)** | Composición corporal. |
| **Cintura, pecho, cadera, brazo, muslo (cm)** | Perímetros. |
| **Notas** | Observaciones. |
| **Origen** | Si la registró el gimnasio o el propio socio. |

El **IMC** se calcula solo con las categorías de la OMS y se muestra junto a los gráficos de evolución.

> [!NOTE]
> El socio solo puede borrar las medidas que él mismo registró; las del gimnasio no se modifican desde su portal.

**Versión documentada:** 1.3.0
