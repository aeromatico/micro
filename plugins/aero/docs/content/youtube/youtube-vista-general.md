---
title: YouTube · Vista general
sort: 1
---
# YouTube · Vista general

Busca videos y canales de YouTube desde Market usando **tu propia llave de la API de YouTube** (YouTube Data API v3).

**Ruta:** `YouTube` (menú lateral: **Explorar** y **Llaves de API**)
**Permiso:** `aero.youtube.use`

## Cómo empezar

1. Crea una llave de API en Google Cloud y habilita **YouTube Data API v3** para ese proyecto.
2. Regístrala en [Llaves de API](youtube-llaves-api) y pulsa **Probar la llave**.
3. Usa [Explorar](youtube-explorar) para buscar videos o canales.

> [!NOTE]
> Cada tenant usa únicamente su propia llave; no hay llave de respaldo de la plataforma. Sin una llave activa, Explorar te invita a agregar la tuya.

> [!WARNING]
> Si la prueba de la llave devuelve un error 403, normalmente falta habilitar YouTube Data API v3 en el proyecto de Google Cloud donde creaste la llave.

**Versión documentada:** 1.1.0
