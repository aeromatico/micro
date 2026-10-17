---
title: "Modo animal: desparasitaciones y peluquería"
sort: 110
---
# Modo animal: desparasitaciones y peluquería

Solo existen en negocios en **modo animal** y para pacientes animales.

## Desparasitaciones

En la ficha del paciente: producto, tipo (interna, externa, ambas), dosis, fecha, lote y **próxima fecha**.

- La próxima fecha se indica a mano o se calcula con el intervalo habitual del negocio ([Configuración](medical-configuracion)); sin intervalo no se inventa.
- Calendario de próximas con **Agendar** y aviso al tutor (apagado por defecto), una vez por fecha y solo si no se repitió el producto después.

## Peluquería y spa

**Ruta:** `Historia clínica → Peluquería y spa` (`/backend/aero/medical/groomings`)
**Controlador:** `Aero\Medical\Controllers\Groomings`
**Modelo:** `Aero\Medical\Models\GroomingVisit`
**Permiso:** `aero.medical.reception`, `.use`, `.settings` o `.professional`

Servicio, fecha, notas (cifradas), **fotos antes y después** (hasta 10 por lado, PNG/JPG/WebP, cifradas) y próxima cita (se puede **agendar en Reservas** desde la visita).

> [!NOTE]
> Peluquería la opera recepción o quien atienda ese servicio: esas personas ven solo las fotos de peluquería, no el resto de los archivos clínicos.

**Versión documentada:** 1.12.0
