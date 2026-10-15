---
title: Historial de ejecuciones
sort: 20
---
# Historial de ejecuciones

**Ruta:** `Google Sheets → Historial`
**Controlador:** `Aero\Sheets\Controllers\Runs`
**Modelo:** `Aero\Sheets\Models\Run`
**Permiso:** `aero.sheets.use`

Lista de solo lectura con cada vez que se ejecutó (o probó) un [mapeo](sheets-mapeos). Solo ves las ejecuciones de tu cuenta.

## Columnas

| Columna | Significado |
|---------|-------------|
| **#** | Número de ejecución. |
| **Mapeo** | Mapeo ejecutado. |
| **Dirección** | Importar o exportar. |
| **Estado** | Resultado: correcto, con errores o fallido. |
| **Leídas** | Filas leídas. |
| **Creadas** / **Actualizadas** | Registros creados o actualizados. |
| **Errores** | Filas con error. |
| **Cuándo** | Fecha y hora. |

Al abrir una ejecución se ve su detalle, incluidos los mensajes de error.

> [!NOTE]
> Las ediciones de celdas hechas por el [asistente de IA](sheets-asistente-ia) (con su antes→después) las consulta el propio asistente, que también puede deshacerlas. Las importaciones no se deshacen.

**Versión documentada:** 1.1.0
