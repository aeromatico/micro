---
title: Imágenes, laboratorio y archivos
sort: 120
---
# Imágenes, laboratorio y archivos

Archivos del paciente (PDF, imágenes PNG/JPG/WebP, Word y texto, hasta 20 MB) ligados al paciente y, opcionalmente, a una nota.

**Dónde:** ficha del paciente → *Imágenes, laboratorio y archivos*
**Modelo:** `Aero\Medical\Models\MedicalFile`
**Permiso:** `aero.medical.professional`

| Campo | Qué hace |
|-------|----------|
| Tipo | Laboratorio, imagen diagnóstica, historia anterior u otro. |
| Fecha del estudio, título | Identifican el archivo. |
| Ligar a una nota | Opcional. |
| Resultados numéricos | Opcional (hasta 3 por carga): analito, valor y unidad; alimentan la [hoja de seguimiento](medical-hoja-de-seguimiento). |

> [!IMPORTANT]
> Los archivos se guardan **cifrados** y solo se abren desde el sistema (con permiso, por negocio y con registro en la bitácora). El tipo se verifica por su contenido: no se aceptan archivos que solo cambian la extensión.

Al quitar un archivo, los resultados numéricos anotados con él se conservan. Los analitos se editan en `Historia clínica → Analitos`.

**Versión documentada:** 1.12.0
