---
title: Sitio público y portal del miembro
sort: 80
---
# Sitio público y portal del miembro

**Ruta:** `Iglesia → Predicaciones`, `Series`, `Recursos`, `Noticias` y `Configuración → Sitio público`
**Controladores:** `Sermons`, `Series`, `Resources`, `Posts`
**Modelos:** `Sermon`, `Series`, `Resource`, `Post`
**Permiso:** `aero.church.use`, `pastor`, `secretary`

## Activación

En *Configuración → Sitio público* enciende:

- **Sitio público de la iglesia:** habilita `/predicaciones`, `/noticias`, `/eventos`, `/en-vivo` y `/recursos`.
- **Portal del miembro:** habilita `/miembro`.

> [!NOTE]
> Con una sección apagada, esa dirección sigue siendo la página normal de tu sitio. Si ya tenías una página "noticias", no la pierdes.

## Contenido

| Sección | Qué publicas |
|---------|--------------|
| **Predicaciones** | Título, predicador, tema, fecha, serie, resumen y enlace de **video** (YouTube, Facebook o Vimeo) o audio. |
| **Series** | Agrupan predicaciones. |
| **Recursos** | Estudios bíblicos y guías en archivo (PDF, Word, PowerPoint, Excel, imágenes, mp3; máx. 20 MB). |
| **Noticias** | Noticias y comunicados; se pueden fijar arriba y programar la fecha. |
| **En vivo** | Enlace, título y horarios de la transmisión. |
| **Eventos** | Los eventos marcados *Mostrar en el sitio público*, con inscripción. |

Cada predicación, recurso y noticia puede ser **Público** o **Solo miembros**. Los enlaces deben empezar con `https://`.

> [!IMPORTANT]
> Solo se incrusta el video de YouTube, Facebook y Vimeo. Cualquier otro enlace se muestra como vínculo.

Los archivos son privados: se descargan únicamente desde el sitio y respetando la visibilidad (un recurso de miembros da "no encontrado" a un visitante).

## Portal del miembro

El miembro ingresa con su cuenta (creada desde su ficha en [Miembros](church-miembros)) y ve:

- **Mis datos:** puede cambiar teléfono, correo, dirección y su consentimiento de mensajes. Nombre, estado, sede y familia los gestiona la secretaría.
- **Mis grupos** y **próximos eventos**, con inscripción y cancelación.
- Noticias y recursos **solo para miembros**.

Solo cuenta como miembro quien está vinculado a una persona **activa o nueva** de esta iglesia.

**Versión documentada:** 1.6.0
