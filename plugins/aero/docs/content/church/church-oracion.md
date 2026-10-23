---
title: Peticiones de oración
sort: 60
---
# Peticiones de oración

**Ruta:** `Iglesia → Oración`
**Controlador:** `Prayers`
**Modelos:** `Prayer`, `PrayerNote`
**Permiso:** `aero.church.use`, `pastor`, `leader` (solo las asignadas)

## Formulario público

Activa **Formulario de oración público** en *Configuración → Visitantes y oración* para publicar `/oracion`. La persona escribe su petición, puede dejarla **privada** (marcada por defecto), pedir **contacto pastoral** y debe autorizar el guardado.

## Privacidad

- Solo la dirección pastoral (administración y pastor) ve todas las peticiones de la iglesia.
- Un **responsable asignado** (rol líder) ve únicamente las que se le asignaron.
- Secretaría y tesorería **no ven esta pantalla**.
- Las peticiones nunca aparecen en el resumen, en los reportes ni en los avisos: el aviso interno solo dice que llegó una y si pide contacto.
- Cada vez que se abre una petición queda anotado en la [auditoría](church-reportes-auditoria).

## Seguimiento

| Campo | Descripción |
|-------|-------------|
| **Estado** | Abierta, en oración, respondida o cerrada. |
| **Responsable asignado** | Usuario con permiso pastoral o de líder. |
| **Notas de acompañamiento** | Registro de lo hecho, visible solo para quienes acceden a la petición. |

**Versión documentada:** 1.6.0
