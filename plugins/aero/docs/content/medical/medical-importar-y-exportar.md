---
title: Importar y exportar
sort: 150
---
# Importar historias anteriores y exportar

**Importar:** `Historia clínica → Importar historias` (`/backend/aero/medical/imports`)
**Exportar:** `Historia clínica → Exportar datos` (`/backend/aero/medical/exports`) y botón en la ficha de cada paciente
**Permiso:** `aero.medical.professional` o `aero.medical.settings` (exportar todo el negocio: solo `.settings`)

## Importar

1. Suba hasta 40 archivos por lote (PDF, imágenes, Word o texto, 20 MB cada uno).
2. Cada archivo se guarda **cifrado y conserva el original**. El sistema **sugiere** el paciente por documento o nombre (en el nombre del archivo y, en Word y texto, dentro del contenido).
3. Una persona **confirma** cada asociación (o descarta el archivo). Hasta entonces el archivo no está en ninguna ficha.

> [!NOTE]
> En PDF e imágenes la sugerencia usa solo el nombre del archivo. Si dos pacientes coinciden, no se sugiere ninguno.

## Exportar

Los datos del paciente y del negocio son **siempre del negocio**. La exportación es un ZIP con todo en JSON (notas, signos vitales, diagnósticos, recetas, órdenes, cuestionarios, vacunas, desparasitaciones, peluquería, laboratorio, documentos) ya descifrado y los archivos originales. La del negocio incluye además plantillas, catálogos, rangos, la bitácora y los archivos aún sin asociar.

> [!WARNING]
> El ZIP contiene datos de salud sin cifrar: guárdelo en un lugar seguro. Cada exportación queda en la bitácora.

**Versión documentada:** 1.12.0
