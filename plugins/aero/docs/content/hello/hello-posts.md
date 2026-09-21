# Formulario: Publicaciones

**Ruta:** `Hello → Redes sociales`
**Controlador:** `Aero\Hello\Controllers\Posts`
**Modelo:** `Aero\Hello\Models\Post`
**Permiso:** `aero.hello.manage_posts`

Publica contenido en tus cuentas de redes conectadas, de inmediato o
programado. Una misma publicación puede salir en **varias cuentas a la vez**.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Contenido** | — | Texto de la publicación (Markdown). |
| **Imágenes** | — | Una o varias; casi todas las redes muestran mejor con imagen. |
| **Publicar en** | requerido | Marca en cuáles de tus cuentas se publica, todas al mismo tiempo. |
| **Programar para** | — | Fecha y hora. Vacío = se publica de inmediato al confirmar. |
| **Estado** | solo lectura | Resultado de la publicación. |
| **Configuración avanzada (JSON)** | — | Solo si necesitas algo especial para una red en particular. |
| **Publicada el** | solo lectura | Cuándo salió. |
| **Error** | solo lectura | Mensaje si falló. |

## Publicar

- El botón **Publicar** envía la publicación; si está programada, espera la fecha.
- Una publicación solo se puede enviar una vez (si ya salió, avisa).

> [!NOTE]
> Las cuentas disponibles son las que hayas conectado en Hello. Para redes,
> suelen ser cuentas de plataforma (perfiles) administradas desde el superadmin.

> [!TIP]
> Deja la **configuración avanzada** vacía salvo que la red lo requiera; está
> pensada solo para casos especiales.
