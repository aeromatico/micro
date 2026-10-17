---
title: "Historia clínica: vista general"
sort: 0
---
# Historia clínica: vista general

Historia clínica electrónica para consultorios (personas) y veterinarias (animales). Un solo módulo: el negocio elige su **modo** en [Configuración](medical-configuracion) y el módulo ajusta textos, campos y secciones.

**Ruta:** `Historia clínica` (`/backend/aero/medical/patients`)
**Permisos:** `aero.medical.use`, `.settings`, `.professional`, `.reception` (ver [Permisos y bitácora](medical-bitacora-y-permisos))
**Plan:** incluido en el plan PRO.

## Qué incluye

| Función | Dónde |
|---------|-------|
| Pacientes (persona o animal con su tutor) | [Pacientes](medical-pacientes) |
| Consultas y controles con autoguardado, firma inmutable e impresión | [Consultas](medical-consultas) |
| Plantillas de nota editables | [Plantillas de nota](medical-plantillas-de-nota) |
| Diagnósticos con CIE-10 (personas) o catálogo propio (animales) | [Diagnósticos](medical-diagnosticos) |
| Receta y órdenes de exámenes | [Receta y órdenes](medical-receta-y-ordenes) |
| Cuestionarios con puntaje | [Cuestionarios](medical-cuestionarios) |
| Rangos alto/bajo del negocio | [Rangos alto/bajo](medical-rangos) |
| Hoja de seguimiento y ficha | [Hoja de seguimiento](medical-hoja-de-seguimiento) |
| Vacunación con esquema y calendario | [Vacunación](medical-vacunacion) |
| Desparasitaciones y peluquería (solo animales) | [Modo animal](medical-modo-animal) |
| Imágenes, laboratorio y archivos | [Estudios y archivos](medical-estudios-y-archivos) |
| Certificados, constancias, informes y consentimientos | [Documentos](medical-documentos) |
| Agenda de hoy y conexión con Reservas | [Agenda y Reservas](medical-agenda-y-reservas) |
| Importar historias anteriores y exportar todo | [Importar y exportar](medical-importar-y-exportar) |

> [!IMPORTANT]
> Los datos de salud son sensibles: el texto clínico y los archivos se guardan **cifrados**, recepción no ve el contenido clínico y cada apertura, edición, firma, impresión y descarga queda en la bitácora.

> [!NOTE]
> El módulo no trae cifras clínicas de fábrica: los rangos alto/bajo, los esquemas de vacunación, los intervalos de desparasitación y los cuestionarios los define el negocio. El catálogo CIE-10 viene de la edición en español de la OPS/OMS. La lista LINAME (Bolivia) aún no está cargada: la receta usa los medicamentos del negocio o texto libre.

**Versión documentada:** 1.10.1
