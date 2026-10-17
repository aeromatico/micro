---
title: Hoja de seguimiento y ficha
sort: 90
---
# Hoja de seguimiento y ficha

Pantalla del paciente para el personal clínico, con la hoja al centro.

**Ruta:** abrir un paciente en `Historia clínica → Pacientes`
**Permiso:** `aero.medical.professional`

## Hoja

Una columna por visita (más una por fecha que solo tenga laboratorio). Filas: mediciones (presión, peso, IMC…), laboratorio, diagnósticos, receta, órdenes y si la nota está firmada.

- Solo las notas **firmadas** aportan datos; un borrador aparece como columna sin contenido.
- Los valores fuera de los [rangos](medical-rangos) del negocio salen en rojo (▲ alto, ▼ bajo).
- Tocar una fecha abre la nota tal como se firmó.

## Laboratorio anotado a mano

En la ficha: elija el analito (editable en **Analitos**) o escriba otro, el valor, la unidad y la fecha. Entra a la hoja aunque no haya archivo; también puede venir de un [estudio con archivo](medical-estudios-y-archivos).

## Más en la ficha

Alergias siempre visibles, medicación y antecedentes, próximas reservas, notas, [vacunación](medical-vacunacion), [archivos](medical-estudios-y-archivos), [documentos](medical-documentos), agendar un control y exportar los datos del paciente.

**Versión documentada:** 1.12.0
