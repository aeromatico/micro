---
title: Asistente de Google Sheets (IA)
sort: 5
---
# Asistente de Google Sheets (IA)

**Ruta:** agente «Asistente de Google Sheets» de Workspaces
**Permiso:** `aero.sheets.use`

Desde la versión 1.1.0, el agente de IA de Workspaces puede trabajar con tus hojas de Google **en tu nombre**: usa **tu** cuenta de Google (la que conectaste en **Cuenta de Google**) y **tu** permiso `aero.sheets.use`. Si aún no la has conectado, el asistente te da el enlace para conectarla.

## Qué puede hacer

| Acción | Detalle |
|--------|---------|
| **Leer hojas** | Ver las pestañas y las primeras filas de una hoja, o leer un rango (por ejemplo `A1:F60`) con su número de fila. Máximo 3 000 celdas por lectura. |
| **Corregir celdas** | Muestra el **antes → después** de cada celda y solo escribe cuando confirmas. Se puede **deshacer**. |
| **Agregar filas** | Añade filas al final de los datos de una pestaña. Se puede **deshacer**. |
| **Crear hojas nuevas** | Crea una hoja en tu Google (por ejemplo como destino de una exportación). |
| **Proponer y guardar mapeos** | Sugiere cómo emparejar columnas con los campos de una fuente y guarda el [mapeo](sheets-mapeos). Guardar un mapeo no mueve datos. |
| **Probar y ejecutar mapeos** | Hace una prueba previa (dry-run) y, con tu confirmación, ejecuta la importación o exportación. |
| **Deshacer e historial** | Consulta las ejecuciones y las ediciones recientes y deshace una edición. |

## Cómo te protege

- **Confirmación:** nada se escribe, agrega, crea ni ejecuta sin tu confirmación explícita. Antes ves qué pasaría.
- **Prueba previa obligatoria:** un mapeo solo se ejecuta si se probó antes con la misma configuración; si lo cambias después de la prueba, hay que repetirla.
- **Deshacer:** cada edición de celdas o fila agregada guarda el estado anterior y se revierte con exactitud.
- **Fórmulas:** por defecto solo se escriben valores en texto plano. Las fórmulas únicamente si las pides, y se rechazan las que leen o envían datos fuera (`IMPORT*`, `IMAGE`, `HYPERLINK`).
- **Datos, no instrucciones:** el contenido de las celdas se trata siempre como datos; el asistente no obedece lo que digan.

> [!IMPORTANT]
> **Importar no se deshace.** Crea o actualiza registros reales; revisa la prueba antes de confirmar. Las ediciones de celdas y las filas agregadas sí se deshacen. Una hoja creada no se deshace desde aquí: se borra en Google Drive.

## Fuentes disponibles

Los mapeos usan fuentes que el superadmin de la plataforma autoriza en **Fuentes y campos**. Por defecto, para los tenants:

| Fuente | Notas |
|--------|-------|
| **Productos** (tienda) | El stock solo se exporta. |
| **Colecciones** (tienda) | |
| **Contactos** (CRM) | |

Si no ves fuentes, la plataforma debe autorizarlas: el asistente no puede crearlas.

Relacionado: [Mapeos](sheets-mapeos), [Historial](sheets-historial), [Vista general](sheets-vista-general).

**Versión documentada:** 1.1.0
