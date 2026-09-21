# Formulario: Identidad visual (Branding)

**Ruta:** `Sitio Web → Página de inicio → pestaña Identidad`
**Origen:** trait `Aero\Sites\Traits\HasBrandingForm`
**Modelo:** `Aero\Sites\Models\Tenant`
**Permiso:** `aero.sites.manage_pages`

Define *cómo se ve* el micrositio: logo, nombre, paleta y tipografía. Es un
formulario compartido (vive en un trait) porque lo montan tanto el editor de
contenidos como —históricamente— la configuración del sitio, siempre sobre el
tenant activo. Los cambios se ven en vivo mientras se diseña.

## Campos

### Estado y nombre

| Campo | Para qué sirve |
|-------|----------------|
| **Sitio activado** (`is_site_active`) | Interruptor maestro. Apagado, el sitio deja de mostrarse y el menú **Sitio Web** se oculta. |
| **Nombre del sitio** | Nombre público (reutiliza el `name` del tenant). |
| **Color principal (legacy)** | Solo se usa si no hay un tema visual asignado. |

### Marca

| Campo | Para qué sirve |
|-------|----------------|
| **Logo** | Imagen (400×200). Si existe, reemplaza al logo de texto. |
| **Favicon** | Ícono de pestaña (64×64). |
| **Logo de texto (opcional)** | Se muestra en header/footer solo si no hay logo de imagen. Vacío = usa el nombre. |
| **Fuente del logo de texto** | Nombre exacto de una Google Font. Vacío = fuente de encabezado del tema. |

### Paleta de colores

| Campo | Para qué sirve |
|-------|----------------|
| **Tema visual** | Galería de `DesignTheme`. Es de **solo lectura**: se elige como punto de partida. |
| **Personalizar color primario** | Sobreescribe el primario del tema en ambos modos (claro y oscuro). |
| **Personalizar color de acento** | Sobreescribe el acento del tema en ambos modos. |

### Tipografía

| Campo | Para qué sirve |
|-------|----------------|
| **Fuente de encabezado principal (H1)** | Google Font exacta. Vacío = la del tema. |
| **Fuente de subtítulos (H2–H6)** | Google Font exacta. Vacío = la misma que H1. |
| **Fuente de texto** | Google Font exacta. Vacío = la del tema. |

## Cómo se guardan los cambios

No se crea ninguna tabla nueva: las personalizaciones se guardan como
**overrides** dentro de `Tenant.theme_overrides`, con esta forma:

```json
{
  "colors": { "primary": "#4f46e5", "accent": "#f59e0b" },
  "fonts":  { "heading": "Plus Jakarta Sans", "body": "Inter" }
}
```

Al renderizar, el tema del tenant combina la paleta del `DesignTheme` con estos
overrides y emite variables CSS (`--color-primary`, `--font-heading`, …) para
los modos claro y oscuro.

> [!NOTE]
> Los colores del tema son intencionalmente de solo lectura. Para personalizar
> se usan los campos de override, no se edita el tema del catálogo.

> [!TIP]
> Vaciar un override (dejar el color o la fuente en blanco) hace que el sitio
> vuelva a heredar el valor del tema visual elegido.
