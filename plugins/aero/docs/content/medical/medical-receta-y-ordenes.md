---
title: Receta y órdenes
sort: 70
---
# Receta y órdenes

Se escriben dentro de la nota y se imprimen con firma y sello cuando la nota está firmada.

**Modelos:** `Prescription`, `PrescriptionItem`, `Order`, `OrderItem`, `Medication`, `ExamCatalog`
**Permiso:** `aero.medical.professional` (catálogos: también `.settings`)
**Catálogos:** `Historia clínica → Medicamentos` y `Exámenes`

## Receta

Medicamento, dosis, vía, frecuencia, duración, cantidad e indicaciones. El medicamento se busca en LINAME (cuando esté cargada) y en los **medicamentos del negocio**, o se escribe libre. En animal solo hay medicamentos del negocio o texto libre. Dosis, frecuencia y demás las escribe siempre el profesional.

## Órdenes

Se elige del **catálogo de exámenes** (editable: hemograma, glucosa, hemoglobina glicosilada, creatinina, perfil lipídico, examen de orina, etc.) o se escribe otro, con indicaciones.

> [!NOTE]
> Receta y órdenes se bloquean al firmar la nota y se imprimen desde la nota firmada (**Imprimir receta**, **Imprimir órdenes**).

**Versión documentada:** 1.12.0
