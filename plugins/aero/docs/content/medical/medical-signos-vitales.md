---
title: Signos vitales
sort: 50
---
# Signos vitales

Se llenan dentro de la nota, en la sección de signos vitales de la plantilla.

**Modelo:** `Aero\Medical\Models\Vitals`
**Permiso:** `aero.medical.professional`

| Modo | Signos |
|------|--------|
| Humano | Presión arterial (sistólica/diastólica), frecuencia cardíaca, frecuencia respiratoria, temperatura, saturación de oxígeno, peso, talla e **IMC** |
| Animal | Temperatura, frecuencia cardíaca, frecuencia respiratoria, peso, mucosas, TLLC y condición corporal |

- El **IMC** lo calcula el sistema a partir de peso y talla (solo en humano; en animal no existe y se usa la condición corporal).
- Se aceptan decimales con coma o punto. Un valor imposible (por ejemplo una saturación de 950) se ignora y se conserva el anterior; son límites de captura, no rangos clínicos.
- Cada valor queda en la [hoja de seguimiento](medical-hoja-de-seguimiento).
- Al firmar la nota se bloquean.
- En animales, el último peso firmado pasa a la ficha del paciente.

**Versión documentada:** 1.10.1
