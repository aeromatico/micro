---
title: Diagnósticos
sort: 60
---
# Diagnósticos

Se agregan dentro de la nota. Cada uno es **principal o secundario** (solo uno principal) y **presuntivo o confirmado**.

**Modelos:** `Aero\Medical\Models\Diagnosis`, `DiagnosisCatalog`, `Cie10`
**Permiso:** `aero.medical.professional` (catálogo del negocio: también `.settings`)
**Catálogo del negocio:** `Historia clínica → Diagnósticos del negocio`

| Modo | Cómo se busca |
|------|---------------|
| Humano | Por nombre o código **CIE-10** (OPS/OMS, español). También hay **Frecuentes** (los más usados en notas firmadas y los que el negocio fija) y texto libre. |
| Animal | CIE-10 **no aplica** y el sistema no lo permite. Se usa el catálogo propio del negocio (que también arma sus Frecuentes) o texto libre. |

> [!NOTE]
> El nombre guardado de un código CIE-10 sale del catálogo oficial, no de lo que se escriba. Algunos códigos de 4 caracteres de los grupos con subdivisión común (por ejemplo E11.9) están compuestos a partir de ese texto; ver `data/README.md` del módulo.

**Versión documentada:** 1.10.1
