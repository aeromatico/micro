---
title: Mapeos
sort: 10
---
# Formulario: Mapeo

**Ruta:** `Google Sheets → Mapeos`
**Controlador:** `Aero\Sheets\Controllers\Mappings`
**Modelo:** `Aero\Sheets\Models\Mapping`
**Permiso:** `aero.sheets.use`

Un mapeo es un perfil reutilizable que une una **fuente de datos** con una **pestaña de una hoja de Google** y dice qué columna corresponde a cada campo. Cada mapeo pertenece a tu cuenta y solo tú (y tu equipo) lo ven.

## Campos

| Campo | Para qué sirve |
|-------|----------------|
| **Nombre del mapeo** | Obligatorio. |
| **Fuente de datos** | Obligatorio. Si la cambias, se vacían las columnas y el campo clave. Solo se aceptan fuentes autorizadas para ti. |
| **Dirección** | *Importar* (hoja → sistema) o *Exportar* (sistema → hoja). |
| **Hoja de cálculo (URL o ID)** | Pega la URL completa de Google Sheets. |

Al editar un mapeo existente aparecen además:

| Campo | Para qué sirve |
|-------|----------------|
| **Pestaña** | Pestaña de la hoja a usar. |
| **Fila de cabeceras** | Por defecto 1; usa 0 si la hoja no tiene cabeceras. |
| **Empezar en la fila** | Primera fila de datos (por defecto 2); salta cabeceras o filas de título. |
| **Máximo de filas** | Vacío = todas (tope de seguridad de 10 000). |
| **Al importar** | Solo en importar: *Crear o actualizar*, *Solo crear nuevos* o *Solo actualizar existentes*. |
| **Campo clave** | Identifica un registro (p. ej. email). Obligatorio para actualizar; al exportar define el orden. |
| **Escribir cabeceras** | Solo en exportar. |
| **Limpiar columnas mapeadas antes** | Solo en exportar. Borra únicamente las columnas mapeadas, no el resto de la hoja. |
| **Columnas** | Lista de pares *Campo del sistema* ↔ *Columna de la hoja* (letra o cabecera). |

## Acciones en la edición

| Acción | Qué hace |
|--------|----------|
| **Vista previa** | Muestra las primeras filas de la hoja desde la fila de cabeceras. Requiere haber guardado hoja y pestaña. |
| **Emparejar columnas automáticamente** | Al importar, une cada cabecera con el campo del mismo nombre o etiqueta; al exportar, propone una columna por campo. Si nada coincide, avisa. Reemplaza las columnas actuales. |
| **Ejecutar** | Lanza la sincronización y muestra filas leídas, creadas, actualizadas, omitidas y con error. |
| **Prueba** | Igual que ejecutar pero **no guarda nada**. |

> [!IMPORTANT]
> Si no has conectado tu cuenta de Google, la pantalla te lleva a conectarla. Sin conexión no se puede leer ni escribir la hoja.

> [!TIP]
> Haz siempre una prueba antes de la primera importación y revisa el [historial](sheets-historial).

**Versión documentada:** 1.0.0
