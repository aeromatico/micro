# Formulario: Página de inicio (editor de contenidos)

**Ruta:** `Sitio Web → Página de inicio`
**Controlador:** `Aero\Sites\Controllers\ContentEditor`
**Modelo:** `Aero\Sites\Models\Page` (la fila con `slug = ''`)
**Permiso:** `aero.sites.manage_pages`

Es el editor principal del micrositio: la portada. Está pensado para que el
dueño del sitio pueda generar toda la página con IA o armarla a mano con
bloques, sin escribir HTML.

> [!NOTE]
> La página de inicio se identifica por tener **slug vacío**. Por eso no aparece
> en *Páginas*: aquí es donde se edita.

## Campos del formulario de portada

| Campo | Tipo | Para qué sirve |
|-------|------|----------------|
| **Tipo de contenido** | balloon-selector | `Editor Visual` (bloques + IA) o `Código` (HTML propio). |
| **Panel de IA** | partial | Aparece solo en modo *Editor Visual*. Genera o regenera la página. |
| **Título de la página** | text (requerido) | Título de la portada. |
| **Editor Visual** (`puck_data`) | puckEditor | Lienzo de bloques arrastrables. |
| **Código** (`content_raw`) | codeeditor | HTML propio, se sirve tal cual. |

## Generación con IA

El panel de IA vive **dentro** del mismo form widget, justo debajo de *Tipo de
contenido*, para que el mostrado/ocultado por trigger funcione sincronizado.

Para generar usa, en este orden:

1. Un **arquetipo**: secuencia de bloques preconfigurada por la plataforma
   según el rubro del negocio.
2. El **tema visual** recomendado por ese arquetipo.
3. La descripción del negocio, que puede autocompletarse desde el arquetipo y
   luego editarse.
4. Los **conectores de IA** disponibles.

El resultado se deposita como bloques en el editor visual, donde se puede
ajustar manualmente. La última generación se recuerda para poder repetirla de
un clic ("Rehacer con IA").

## Las demás pestañas de *Contenidos*

- **Identidad**: formulario de [Branding](sites-formulario-branding).
- **Plantilla**: layout, header/footer y HTML propio (ver [Plantilla del sitio](sites-formulario-plantilla)).
- **Componentes**: galería de referencia de bloques Puck disponibles.
- **Referencia**: HTML por defecto que sirve hoy la plataforma, para saber qué
  se reemplaza antes de tocar nada.

> [!TIP]
> Mientras el sitio no tenga su primer landing generado ni contenido propio,
> `Page.is_placeholder` es verdadero y el layout oculta el navbar/footer del
> sitio público: así no se ve "en construcción" con elementos sin configurar.
