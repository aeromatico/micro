---
title: Documentos y formatos
sort: 130
---
# Documentos y formatos

Certificados, constancias, informes y consentimientos generados desde formatos con variables.

**Formatos:** `Historia clínica → Formatos de documentos` (`/backend/aero/medical/documenttemplates`)
**Modelos:** `Aero\Medical\Models\DocumentTemplate`, `MedicalDocument`
**Permiso:** `aero.medical.professional` (formatos: también `.settings`)

## Formatos

Texto con variables entre llaves dobles: `{{paciente}}`, `{{documento}}`, `{{edad}}`, `{{fecha_nacimiento}}`, `{{sexo}}`, `{{tutor}}`, `{{especie}}`, `{{raza}}`, `{{microchip}}`, `{{fecha}}`, `{{hora}}`, `{{profesional}}`, `{{matricula}}`, `{{especialidad}}`, `{{negocio}}`, `{{diagnostico}}`, `{{diagnosticos}}`, `{{motivo}}`. Una variable desconocida se rechaza al guardar.

## Generar y emitir

1. En la ficha: elija el formato (y, si quiere, de qué nota tomar diagnóstico y motivo) → **Generar borrador**.
2. Revise y corrija el texto. Los datos que faltan salen como `__________`.
3. **Emitir con firma y sello:** queda inmutable, con código de integridad, y se imprime con la firma y el sello de quien lo emite.

> [!WARNING]
> Un documento con datos sin completar no se emite, salvo un **consentimiento** (se completa a mano al imprimir y lleva línea de firma del paciente o representante).

Cada documento generado queda en la ficha del paciente.

**Versión documentada:** 1.10.1
