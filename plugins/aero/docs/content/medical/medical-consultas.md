---
title: Consultas y controles
sort: 30
---
# Consultas y controles

Notas clínicas del paciente. Hay dos tipos: **Consulta** y **Control**.

**Ruta:** `Historia clínica → Consultas` (`/backend/aero/medical/consultations`)
**Controlador:** `Aero\Medical\Controllers\Consultations`
**Modelo:** `Aero\Medical\Models\Consultation`
**Permiso:** `aero.medical.professional`

## Cómo se escribe

- Se abre desde la ficha del paciente (**Nueva consulta / Nuevo control**) o desde una reserva ([Agenda y Reservas](medical-agenda-y-reservas)).
- Sigue las secciones de la [plantilla](medical-plantillas-de-nota) del negocio. Los cambios en la plantilla no afectan a las notas ya abiertas.
- **Autoguardado:** mientras escribe, sin botón Guardar; el estado aparece junto a los botones.
- Arriba siempre se ven las **alergias**; a la derecha, antecedentes y medicación actual y **lo anotado la vez anterior**.
- Dentro de la nota: [signos vitales](medical-signos-vitales), [diagnósticos](medical-diagnosticos), [receta y órdenes](medical-receta-y-ordenes) y [cuestionarios](medical-cuestionarios).

## Firma, adendas e impresión

| Acción | Efecto |
|--------|--------|
| Firmar nota | La nota queda **inmutable** (texto, signos vitales, diagnósticos, receta, órdenes y cuestionarios) con un código de integridad. Solo quien abrió la nota puede editarla y firmarla. |
| Adenda | Es la única forma de cambiar algo después de firmar: queda con autor y hora, sin tocar la nota. |
| Imprimir | Con firma y sello del profesional (se cargan en **Mi firma**), matrícula y código de integridad. |
| Descartar borrador | Solo mientras es borrador. |

> [!WARNING]
> La firma es una imagen para la impresión, no una firma electrónica certificada. La integridad la da el código de la nota: si alguien altera el contenido guardado, la impresión y la pantalla lo avisan.

> [!NOTE]
> Si la nota viene de una reserva de [Reservas](office-reservas), al firmar la reserva pasa a **Completada**.

**Mi firma:** `Historia clínica → Mi firma` (título, matrícula, especialidad, imagen de firma y de sello; se guardan cifradas).

**Versión documentada:** 1.10.1
