---
title: Llaves de API
sort: 10
---
# Formulario: Llaves de API

**Ruta:** `YouTube → Llaves de API`
**Controlador:** `Aero\Youtube\Controllers\Credentials`
**Modelo:** `Aero\Youtube\Models\Credential`
**Permiso:** `aero.youtube.use`

Aquí guardas la llave con la que tu cuenta consulta YouTube. Puedes registrar varias; solo ves las tuyas.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Nombre** | requerido, máx. 120 | Identifica la llave (ej. «Canal de la marca»). |
| **Activa** | activada por defecto | Solo las llaves activas se usan al explorar. |
| **Llave de API (YouTube Data API v3)** | requerida al crear | Se guarda **cifrada**. Al editar, déjala vacía para conservar la actual. |
| **Llave actual** | solo lectura (al editar) | Muestra solo el inicio y el final de la llave. |
| **Resultado de la última prueba** | solo lectura (al editar) | Mensaje de la última comprobación. |

## Probar la llave

Al editar, el botón de prueba hace una consulta real a YouTube y guarda el resultado. Cada prueba gasta **1 unidad** de la cuota diaria de la llave.

> [!IMPORTANT]
> Al cambiar la llave se borra el resultado de la prueba anterior: vuelve a probarla.

Relacionado: [Explorar](youtube-explorar) · [Vista general](youtube-vista-general)

**Versión documentada:** 1.1.0
