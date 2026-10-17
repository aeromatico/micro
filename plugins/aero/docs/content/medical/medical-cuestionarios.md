---
title: Cuestionarios
sort: 80
---
# Cuestionarios

Definiciones configurables con preguntas, puntaje sumado por el sistema e interpretación por rangos (por ejemplo leve/moderado/severo).

**Ruta:** `Historia clínica → Cuestionarios` (`/backend/aero/medical/questionnaires`)
**Controlador:** `Aero\Medical\Controllers\Questionnaires`
**Modelo:** `Aero\Medical\Models\Questionnaire`
**Permiso:** `aero.medical.professional` o `aero.medical.settings`

| Campo | Qué hace |
|-------|----------|
| Preguntas | Cada una con al menos dos opciones y el puntaje de cada opción. |
| Interpretación por rangos | «Desde – Hasta → etiqueta» sobre el puntaje total (los límites incluyen mínimo y máximo). |

Se responden dentro de la nota ([Consultas](medical-consultas)): todas las preguntas son obligatorias; el puntaje y la interpretación los calcula el sistema y quedan en la nota con una copia de la definición.

> [!IMPORTANT]
> El módulo no trae cuestionarios clínicos de fábrica: el negocio carga los que usa.

**Versión documentada:** 1.10.1
