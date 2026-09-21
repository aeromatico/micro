# Formulario: Artículos

**Ruta:** `Docs → Artículos`
**Controlador:** `Aero\Docs\Controllers\Articles`
**Modelo:** `Aero\Docs\Models\Article`
**Permiso:** `aero.docs.manage`

Crea y edita el contenido de tu documentación. El texto se escribe en
**Markdown** y se organiza por categorías.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Título** | requerido | Título del artículo. |
| **Slug** | requerido, `alpha_dash` | URL del artículo. Se propone desde el título. |
| **Categoría** | — | Dónde se ubica en el árbol. |
| **Orden** | — | Menor número aparece primero dentro de su categoría. |
| **Versión del plugin** | — | Versión del plugin que se documentó (ej. `1.21.0`). Sirve para detectar docs desactualizadas. |
| **Global** | — | Se muestra tal cual en el centro de ayuda de **todos** los sitios. |
| **Resumen** | — | Opcional; si lo dejas vacío se genera del contenido. |
| **Contenido (Markdown)** | — | El texto del artículo. |
| **Publicado** | — | Si está apagado, no se sirve. |
| **Destacado en portada** | — | Se resalta en la portada del centro de ayuda. |
| **Fecha de publicación** | — | Se completa sola al publicar. |

## Pestaña Actividad

| Campo | Para qué sirve |
|-------|----------------|
| **Versión actual** | Número de revisión del artículo. **Se incrementa solo** en cada actualización. |
| **Visitas** | Contador de vistas (arranca en 1000). |
| **¿Fue útil? (Sí/No)** | Valoraciones de los lectores. |
| **Historial de versiones** | Instantáneas de cada versión anterior, para comparar o recuperar. |

## Markdown admitido

Títulos, listas, **negrita**, _cursiva_, ~~tachado~~, tablas, listas de tareas,
código y callouts:

```markdown
> [!TIP]
> Usa el buscador del centro de ayuda para navegar rápido.
```

> [!NOTE]
> La **versión** (revisión) es automática y no se edita. En cambio, **Versión
> del plugin** la eliges tú para dejar registrado sobre qué versión del producto
> escribiste.

> [!TIP]
> Deja el **Resumen** vacío y el sistema lo genera de las primeras líneas; así
> ahorras tiempo y mantienes las tarjetas del listado al día.
