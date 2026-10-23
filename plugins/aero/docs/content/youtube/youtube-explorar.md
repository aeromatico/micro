---
title: Explorar
sort: 20
---
# Función: Explorar

**Ruta:** `YouTube → Explorar`
**Controlador:** `Aero\Youtube\Controllers\Explore`
**Permiso:** `aero.youtube.use`

Buscador de videos y canales de YouTube con tu llave.

## Cómo se usa

1. Escribe lo que buscas en **Buscar videos o canales…**.
2. Elige **Videos** o **Canales**.
3. Pulsa **Buscar**: se muestran hasta 12 resultados.

Si no escribes nada, la pantalla pide qué buscar. Si YouTube rechaza la consulta (llave inválida, API sin habilitar, cuota agotada) verás el mensaje de error en lugar de los resultados.

> [!WARNING]
> Cada búsqueda gasta **100 unidades** de las 10.000 diarias de la llave: unas 100 búsquedas al día.

Si no tienes llave activa, la pantalla te lleva a [Llaves de API](youtube-llaves-api).

**Versión documentada:** 1.1.0
