import React from 'react';
import { FieldLabel } from '@puckeditor/core';
import { TABLER_ICONS, TABLER_ICON_NAMES, TABLER_PREFIX, isTablerIconValue, getTablerIconComponent } from './icons';

/**
 * Librería de componentes Puck — estandar "Aero Sites" (Tailwind dark + Alpine.js).
 *
 * IMPORTANTE (sync con Tailwind):
 * Las clases usadas aquí se renderizan desde la BD, fuera del escaneo estático
 * de Tailwind. Mantener sincronizado el `safelist` en
 * themes/microsites/tailwind.config.js al añadir/quitar clases.
 *
 * Para la generación con IA (Fase 3), los nombres de componente y su `fields`
 * actúan como schema de referencia (catálogo) que el generador debe respetar.
 */

// ---------------------------------------------------------------------------
// LAYOUT — contenedores estructurales (Grid/Flex admiten anidar cualquier
// otro bloque vía `slot`; Space es un espaciador simple). A diferencia de
// los bloques de sección, no traen fondo/tipografía propios — son
// estructura pura.
// ---------------------------------------------------------------------------

const LAYOUT_GAP_CLASSES = { small: 'gap-2', medium: 'gap-4', large: 'gap-8' };

export const Grid = {
  label: 'Grid',
  desc: 'Contenedor en grilla (2-4 columnas) donde podés arrastrar cualquier otro bloque adentro.',
  fields: {
    columns: {
      type: 'select',
      label: 'Columnas',
      options: [
        { label: '2 columnas', value: '2' },
        { label: '3 columnas', value: '3' },
        { label: '4 columnas', value: '4' },
      ],
    },
    gap: {
      type: 'select',
      label: 'Espaciado',
      options: [
        { label: 'Pequeño', value: 'small' },
        { label: 'Mediano', value: 'medium' },
        { label: 'Grande', value: 'large' },
      ],
    },
    content: { type: 'slot' },
  },
  defaultProps: {
    columns: '2',
    gap: 'medium',
    content: [],
  },
  render: ({ columns, gap, content: Content }) => {
    const colClass = { '2': 'md:grid-cols-2', '3': 'md:grid-cols-3', '4': 'md:grid-cols-4' }[columns] || 'md:grid-cols-2';
    const gapClass = LAYOUT_GAP_CLASSES[gap] || 'gap-4';
    return (
      <section className="reveal py-8 px-4">
        <div className="max-w-6xl mx-auto">
          <Content className={`grid grid-cols-1 ${colClass} ${gapClass}`} />
        </div>
      </section>
    );
  },
};

export const Flex = {
  label: 'Flex',
  desc: 'Contenedor flexible (fila o columna) donde podés arrastrar cualquier otro bloque adentro.',
  fields: {
    direction: {
      type: 'radio',
      label: 'Dirección',
      options: [
        { label: 'Fila', value: 'row' },
        { label: 'Columna', value: 'column' },
      ],
    },
    wrap: {
      type: 'radio',
      label: 'Ajustar línea',
      options: [
        { label: 'Sí', value: 'yes' },
        { label: 'No', value: 'no' },
      ],
    },
    justify: {
      type: 'select',
      label: 'Justificar',
      options: [
        { label: 'Inicio', value: 'start' },
        { label: 'Centro', value: 'center' },
        { label: 'Fin', value: 'end' },
        { label: 'Espaciado', value: 'between' },
      ],
    },
    align: {
      type: 'select',
      label: 'Alinear',
      options: [
        { label: 'Inicio', value: 'start' },
        { label: 'Centro', value: 'center' },
        { label: 'Fin', value: 'end' },
        { label: 'Estirar', value: 'stretch' },
      ],
    },
    gap: {
      type: 'select',
      label: 'Espaciado',
      options: [
        { label: 'Pequeño', value: 'small' },
        { label: 'Mediano', value: 'medium' },
        { label: 'Grande', value: 'large' },
      ],
    },
    content: { type: 'slot' },
  },
  defaultProps: {
    direction: 'row',
    wrap: 'yes',
    justify: 'start',
    align: 'stretch',
    gap: 'medium',
    content: [],
  },
  render: ({ direction, wrap, justify, align, gap, content: Content }) => {
    const dirClass = direction === 'column' ? 'flex-col' : 'flex-row';
    const wrapClass = wrap === 'yes' ? 'flex-wrap' : 'flex-nowrap';
    const justifyClass = { start: 'justify-start', center: 'justify-center', end: 'justify-end', between: 'justify-between' }[justify] || 'justify-start';
    const alignClass = { start: 'items-start', center: 'items-center', end: 'items-end', stretch: 'items-stretch' }[align] || 'items-stretch';
    const gapClass = LAYOUT_GAP_CLASSES[gap] || 'gap-4';
    return (
      <section className="reveal py-8 px-4">
        <div className="max-w-6xl mx-auto">
          <Content className={`flex ${dirClass} ${wrapClass} ${justifyClass} ${alignClass} ${gapClass}`} />
        </div>
      </section>
    );
  },
};

export const Space = {
  label: 'Space',
  desc: 'Espaciador simple (sin línea) para separar bloques verticalmente.',
  fields: {
    height: {
      type: 'select',
      label: 'Altura',
      options: [
        { label: 'Pequeño (16px)', value: 'h-4' },
        { label: 'Mediano (32px)', value: 'h-8' },
        { label: 'Grande (64px)', value: 'h-16' },
        { label: 'Extra grande (128px)', value: 'h-32' },
      ],
    },
  },
  defaultProps: {
    height: 'h-8',
  },
  render: ({ height }) => <div className={height} />,
};

// ---------------------------------------------------------------------------
// BLOQUES — secciones principales
//
// El color/tipografía de estos bloques ya NO se elige por bloque: se hereda
// del `DesignTheme` del tenant vía variables CSS (--color-primary, etc.),
// mapeadas a las clases `brand-*`/`font-heading`/`font-body`/`rounded-brand`
// en tailwind.config.js. Ver plugins/aero/sites/models/DesignTheme.php.
// ---------------------------------------------------------------------------

// Opciones de fondo compartidas por los bloques de alto impacto (Hero, Banner,
// Stats): "transparent"/"surface" heredan el fondo neutro del tema (se adaptan
// solos a claro/oscuro), "brand" rellena con el color de marca sólido. El
// color personalizado (customBgColor, ver colorField) NO es una opción más
// de esta lista — es el propio color picker el que actúa como toggle: en
// cuanto tiene un hex válido, gana sobre cualquiera de estas opciones (ver
// resolveSectionStyle). Así el usuario no tiene que elegir "Personalizado" Y
// ADEMÁS el color en dos controles separados.
const BACKGROUND_OPTIONS = [
  { label: 'Transparente', value: 'transparent' },
  { label: 'Superficie (neutro)', value: 'surface' },
  { label: 'Color de marca (sólido)', value: 'brand' },
];

// Igual que BACKGROUND_OPTIONS pero con una opción inicial "sin cambios" para
// bloques que hoy no tienen ningún control de fondo — el default sigue
// siendo el aspecto actual del bloque (opt-in real, no un default nuevo).
const BACKGROUND_OPTIONS_OPTIONAL = [
  { label: 'Sin cambios (usar el estilo por defecto)', value: '' },
  ...BACKGROUND_OPTIONS,
];

// Igual que con el fondo: el color de texto personalizado (customTextColor)
// no es una opción de este radio — gana automáticamente sobre estas 3 en
// cuanto tiene un hex válido (ver resolveSectionStyle).
const TEXT_COLOR_OPTIONS = [
  { label: 'Automático', value: 'auto' },
  { label: 'Claro', value: 'light' },
  { label: 'Oscuro', value: 'dark' },
];

function isValidHex(hex) {
  return typeof hex === 'string' && /^#[0-9a-fA-F]{6}$/.test(hex);
}

// Mismos tokens de diseño que Puck usa en sus propios inputs de texto
// (campos `type: 'text'`), para que los <input> de nuestros campos `custom`
// se vean idénticos — Puck no expone la clase CSS de sus inputs vía API
// pública, así que replicamos el estilo con sus propias custom properties.
const TEXT_INPUT_STYLE = {
  background: 'var(--puck-field-color-bg, var(--puck-color-surface))',
  border: 'var(--puck-field-border-width, 1px) solid var(--puck-field-color-border, var(--puck-color-border))',
  borderRadius: 'var(--puck-field-radius, var(--puck-radius-m))',
  boxSizing: 'border-box',
  color: 'var(--puck-field-color-text, var(--puck-color-text))',
  fontFamily: 'inherit',
  fontSize: 'var(--puck-field-font-size, var(--puck-font-size-xxs))',
  padding: 'var(--puck-field-space-y, 8px) var(--puck-field-space-x, 12px)',
  width: '100%',
  maxWidth: '100%',
};

// Campo `custom` con <input type="color"> nativo (sin dependencias) + un
// input de texto para el hex exacto — Puck no trae color picker nativo
// (solo text/textarea/select/radio/number/array/object/slot/custom).
function colorField(label) {
  return {
    type: 'custom',
    render: ({ name, value, onChange }) => (
      <FieldLabel label={label}>
        <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
          <input
            type="color"
            value={isValidHex(value) ? value : '#000000'}
            onChange={(e) => onChange(e.currentTarget.value)}
            style={{ width: '40px', height: '32px', padding: 0, border: '1px solid var(--puck-color-grey-05, #ccc)', borderRadius: '4px', cursor: 'pointer' }}
          />
          <input
            type="text"
            name={name}
            value={value || ''}
            placeholder="#rrggbb"
            onChange={(e) => onChange(e.currentTarget.value)}
            style={{ ...TEXT_INPUT_STYLE, flex: 1, minWidth: 0 }}
          />
        </div>
      </FieldLabel>
    ),
  };
}

// Campo `custom` que sube el archivo al filesystem propio del tenant (no a
// la Media Library de October — esa es una biblioteca única y global,
// cualquier backend user con permiso media.library navegaría los archivos
// de TODOS los tenants). Pega directo al handler AJAX
// ContentEditor::onPuckUploadImage(), que crea un System\Models\File
// adjunto solo a Tenant::puck_uploads. Incluye un input de texto como
// fallback para pegar una URL externa directamente.
function imageField(label) {
  return {
    type: 'custom',
    render: ({ name, value, onChange }) => {
      const [uploading, setUploading] = React.useState(false);
      const [error, setError] = React.useState('');
      const inputRef = React.useRef(null);

      const handleFile = async (file) => {
        if (!file) return;
        setUploading(true);
        setError('');
        try {
          const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
          const formData = new FormData();
          formData.append('file_data', file);

          // Header X-AJAX-HANDLER (no X-OCTOBER-REQUEST-HANDLER): este October
          // usa el framework AJAX de Larajax, ver vendor/larajax/larajax/src/Classes/AjaxRequest.php.
          const res = await fetch(window.location.href, {
            method: 'POST',
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'X-AJAX-HANDLER': 'onPuckUploadImage',
              'X-CSRF-TOKEN': csrfToken,
              Accept: 'application/json',
            },
            body: formData,
          });
          const data = await res.json();
          if (!res.ok || data.error) {
            throw new Error(data.error || 'Error al subir la imagen.');
          }
          onChange(data.url);
        } catch (err) {
          setError(err.message || 'Error al subir la imagen.');
        } finally {
          setUploading(false);
          if (inputRef.current) inputRef.current.value = '';
        }
      };

      return (
        <FieldLabel label={label}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
            {value && (
              <img
                src={value}
                alt=""
                style={{ width: '100%', maxHeight: '120px', objectFit: 'cover', borderRadius: '4px' }}
              />
            )}
            <input
              ref={inputRef}
              type="file"
              accept="image/*"
              style={{ display: 'none' }}
              onChange={(e) => handleFile(e.currentTarget.files?.[0])}
            />
            <div style={{ display: 'flex', gap: '6px' }}>
              <button
                type="button"
                disabled={uploading}
                onClick={() => inputRef.current?.click()}
                style={{ flex: 1, padding: '6px 10px', cursor: uploading ? 'wait' : 'pointer' }}
              >
                {uploading ? 'Subiendo…' : value ? 'Cambiar imagen…' : 'Subir imagen…'}
              </button>
              {value && !uploading && (
                <button type="button" onClick={() => onChange('')} style={{ padding: '6px 10px', cursor: 'pointer' }}>
                  Quitar
                </button>
              )}
            </div>
            {error && <div style={{ color: '#dc2626', fontSize: '12px' }}>{error}</div>}
            <input
              type="text"
              name={name}
              value={value || ''}
              placeholder="o pegar una URL"
              onChange={(e) => onChange(e.currentTarget.value)}
              style={TEXT_INPUT_STYLE}
            />
          </div>
        </FieldLabel>
      );
    },
  };
}

// Campo `custom` que admite emoji libre (compatibilidad con datos ya
// guardados) o un ícono Tabler (https://tabler.io/icons) elegido de un
// selector con búsqueda. Se guarda como texto: un emoji literal, o
// `tabler:<nombre>` cuando es un ícono Tabler — `PickedIcon` (más abajo)
// resuelve cuál renderizar en cada bloque.
function iconField(label) {
  return {
    type: 'custom',
    render: ({ value, onChange }) => {
      const [open, setOpen] = React.useState(false);
      const [query, setQuery] = React.useState('');
      const SelectedIcon = getTablerIconComponent(value);
      const q = query.trim().toLowerCase();
      const filtered = q ? TABLER_ICON_NAMES.filter((n) => n.includes(q)) : TABLER_ICON_NAMES;

      return (
        <FieldLabel label={label}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
            <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
              <div
                style={{
                  width: '36px',
                  height: '36px',
                  flexShrink: 0,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  fontSize: '20px',
                  border: 'var(--puck-field-border-width, 1px) solid var(--puck-field-color-border, var(--puck-color-border))',
                  borderRadius: 'var(--puck-field-radius, var(--puck-radius-m))',
                  background: 'var(--puck-field-color-bg, var(--puck-color-surface))',
                }}
              >
                {SelectedIcon ? <SelectedIcon size={20} stroke={1.75} /> : value || '—'}
              </div>
              <input
                type="text"
                value={isTablerIconValue(value) ? '' : value || ''}
                placeholder="Emoji, ej: ⭐"
                onChange={(e) => onChange(e.currentTarget.value)}
                style={{ ...TEXT_INPUT_STYLE, flex: 1, minWidth: 0 }}
              />
              <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                style={{ padding: '6px 10px', cursor: 'pointer', whiteSpace: 'nowrap' }}
              >
                {open ? 'Cerrar' : 'Íconos…'}
              </button>
            </div>

            {open && (
              <div
                style={{
                  border: 'var(--puck-field-border-width, 1px) solid var(--puck-field-color-border, var(--puck-color-border))',
                  borderRadius: 'var(--puck-field-radius, var(--puck-radius-m))',
                  padding: '8px',
                }}
              >
                <input
                  type="text"
                  value={query}
                  placeholder="Buscar ícono (en inglés)…"
                  onChange={(e) => setQuery(e.currentTarget.value)}
                  style={{ ...TEXT_INPUT_STYLE, marginBottom: '8px' }}
                />
                <div
                  style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fill, minmax(36px, 1fr))',
                    gap: '4px',
                    maxHeight: '180px',
                    overflowY: 'auto',
                  }}
                >
                  {filtered.map((name) => {
                    const Icon = TABLER_ICONS[name];
                    const active = value === TABLER_PREFIX + name;
                    return (
                      <button
                        key={name}
                        type="button"
                        title={name}
                        onClick={() => {
                          onChange(TABLER_PREFIX + name);
                          setOpen(false);
                          setQuery('');
                        }}
                        style={{
                          display: 'flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          width: '36px',
                          height: '36px',
                          cursor: 'pointer',
                          border: active
                            ? '1px solid var(--puck-color-azure-06, #3b74d1)'
                            : '1px solid transparent',
                          borderRadius: '4px',
                          background: active ? 'var(--puck-color-azure-11, #eef4ff)' : 'transparent',
                          color: 'var(--puck-field-color-text, var(--puck-color-text))',
                        }}
                      >
                        <Icon size={18} stroke={1.75} />
                      </button>
                    );
                  })}
                  {filtered.length === 0 && (
                    <div style={{ gridColumn: '1 / -1', fontSize: '12px', opacity: 0.7, padding: '8px 0' }}>
                      Sin resultados.
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>
        </FieldLabel>
      );
    },
  };
}

// Resuelve un valor de `iconField` a lo que corresponda renderizar: ícono
// Tabler (SVG) o el emoji/texto literal guardado.
function PickedIcon({ icon, size = 48, className = '', style }) {
  const Icon = getTablerIconComponent(icon);
  if (Icon) return <Icon size={size} stroke={1.5} className={className} style={style} />;
  return (
    <span className={className} style={{ fontSize: size, lineHeight: 1, ...style }}>
      {icon}
    </span>
  );
}

// Campos reutilizables de personalización opt-in (fondo custom + color de
// texto). Se agregan junto al campo `background` propio de cada bloque.
const CUSTOM_COLOR_FIELDS = {
  customBgColor: colorField('Fondo personalizado'),
  textColor: { type: 'radio', label: 'Color de texto', options: TEXT_COLOR_OPTIONS },
  customTextColor: colorField('Texto personalizado'),
};

const CUSTOM_COLOR_DEFAULTS = {
  customBgColor: '',
  textColor: 'auto',
  customTextColor: '',
};

// Resuelve fondo/texto de una sección: clases Tailwind fijas (comportamiento
// idéntico al actual) salvo que el color picker tenga un hex válido, en cuyo
// caso ese hex GANA siempre sobre el preset y se devuelve como `style`
// inline — el color picker mismo es el toggle de "personalizado", no hace
// falta un radio separado. `autoTextClass` es la clase de texto que ya se
// usaba por defecto para ese fondo — se mantiene si `textColor` sigue en
// 'auto' y no hay customTextColor, para no alterar nada cuando no hay opt-in.
function resolveSectionStyle(background, autoTextClass, { customBgColor, textColor, customTextColor } = {}) {
  const classes = [];
  const style = {};

  if (isValidHex(customBgColor)) {
    style.backgroundColor = customBgColor;
  } else if (background === 'brand') {
    classes.push('bg-brand-primary-dark');
  } else if (background === 'surface') {
    classes.push('bg-surface-alt');
  }

  if (isValidHex(customTextColor)) {
    style.color = customTextColor;
  } else if (textColor === 'light') {
    classes.push('text-white');
  } else if (textColor === 'dark') {
    classes.push('text-ink');
  } else {
    classes.push(autoTextClass);
  }

  return { className: classes.join(' '), style };
}

function heroButtonClasses(background) {
  return background === 'brand'
    ? 'bg-white text-brand-primary-dark'
    : 'bg-brand-primary text-white';
}

// Botón secundario ("Botón 2"): siempre outline, para no competir visualmente
// con el botón principal (sólido). El color del borde/texto se invierte
// igual que heroButtonClasses según el fondo de la sección.
function heroSecondaryButtonClasses(background) {
  return background === 'brand'
    ? 'border-white text-white'
    : 'border-brand-primary text-brand-primary';
}

// Las 5 opciones de layout real (markup distinto) de Hero. El value es lo
// que ve la IA en el prompt (translateFieldsForPrompt en SiteGenerator.php
// expone los values de cualquier `select` como hint) — por eso son
// descriptivos, no v1..v5.
const HERO_VARIANT_OPTIONS = [
  { label: 'Centrado (clásico)', value: 'centrado' },
  { label: 'Imagen a la derecha', value: 'imagen-derecha' },
  { label: 'Imagen a la izquierda', value: 'imagen-izquierda' },
  { label: 'Fondo completo (alto impacto)', value: 'fondo-completo' },
  { label: 'Minimal (solo texto)', value: 'minimal' },
];

function HeroButtons({ ctaLabel, ctaUrl, cta2Label, cta2Url, background, justify }) {
  if ((!ctaLabel || !ctaUrl) && (!cta2Label || !cta2Url)) return null;
  return (
    <div className={`flex flex-col sm:flex-row gap-4 ${justify}`}>
      {ctaLabel && ctaUrl && (
        <a
          href={ctaUrl}
          className={`inline-block font-semibold px-8 py-4 rounded-brand hover:opacity-90 transition-opacity ${heroButtonClasses(background)}`}
        >
          {ctaLabel}
        </a>
      )}
      {cta2Label && cta2Url && (
        <a
          href={cta2Url}
          className={`inline-block font-semibold px-8 py-4 rounded-brand border-2 hover:opacity-90 transition-opacity ${heroSecondaryButtonClasses(background)}`}
        >
          {cta2Label}
        </a>
      )}
    </div>
  );
}

export const Hero = {
  label: 'Hero',
  desc: 'Sección principal (hero) con título grande, subtítulo, descripción, hasta 2 botones e imagen. 5 variantes de layout real (no solo color/fondo): centrado, imagen a la derecha/izquierda, fondo completo y minimal.',
  fields: {
    variant: { type: 'select', label: 'Variante de layout', options: HERO_VARIANT_OPTIONS },
    title: { type: 'text', label: 'Título principal' },
    subtitle: { type: 'textarea', label: 'Subtítulo' },
    description: { type: 'textarea', label: 'Descripción (opcional, párrafo más largo)' },
    ctaLabel: { type: 'text', label: 'Botón 1: texto' },
    ctaUrl: { type: 'text', label: 'Botón 1: URL' },
    cta2Label: { type: 'text', label: 'Botón 2: texto (opcional)' },
    cta2Url: { type: 'text', label: 'Botón 2: URL (opcional)' },
    bgImage: imageField('Imagen de fondo (opcional)'),
    image: imageField('Imagen de contenido (para variantes con imagen a un lado)'),
    background: { type: 'radio', label: 'Fondo', options: BACKGROUND_OPTIONS },
    ...CUSTOM_COLOR_FIELDS,
  },
  defaultProps: {
    title: 'Bienvenido a nuestro sitio',
    subtitle: 'Descubre todo lo que tenemos para ofrecerte.',
    description: '',
    ctaLabel: 'Contáctanos',
    ctaUrl: '/contacto',
    cta2Label: '',
    cta2Url: '',
    bgImage: '',
    image: '',
    variant: 'centrado',
    background: 'surface',
    ...CUSTOM_COLOR_DEFAULTS,
  },
  render: (props) => {
    const {
      title, subtitle, description, ctaLabel, ctaUrl, cta2Label, cta2Url,
      bgImage, image, variant, background, customBgColor, textColor, customTextColor,
    } = props;
    const autoText = background === 'brand' || !!bgImage ? 'text-white' : 'text-ink';
    const { className: styleClass, style: colorStyle } = resolveSectionStyle(background, autoText, {
      customBgColor,
      textColor,
      customTextColor,
    });
    const sectionStyle = { ...(bgImage ? { backgroundImage: `url(${bgImage})` } : {}), ...colorStyle };
    const buttons = (justify) => (
      <HeroButtons
        ctaLabel={ctaLabel} ctaUrl={ctaUrl} cta2Label={cta2Label} cta2Url={cta2Url}
        background={background} justify={justify}
      />
    );

    // ---- imagen-derecha / imagen-izquierda: split de texto + imagen -------
    if (variant === 'imagen-derecha' || variant === 'imagen-izquierda') {
      const textCol = (
        <div className="text-left">
          <h1 className="font-heading text-4xl md:text-6xl font-bold mb-6 leading-tight">{title}</h1>
          <p className="text-xl mb-6 opacity-90 leading-relaxed">{subtitle}</p>
          {description && <p className="text-lg mb-8 opacity-75 leading-relaxed">{description}</p>}
          {buttons('justify-start')}
        </div>
      );
      const imageCol = image ? (
        <div className="rounded-2xl overflow-hidden shadow-md aspect-video">
          <img src={image} alt="" className="w-full h-full object-cover" />
        </div>
      ) : <div />;
      return (
        <section className={`reveal relative ${styleClass} py-20 px-4 bg-cover bg-center`} style={sectionStyle}>
          {bgImage && <div className="absolute inset-0 bg-black/50" />}
          <div className="relative max-w-6xl mx-auto grid md:grid-cols-2 gap-8 items-center">
            {variant === 'imagen-derecha' ? (<>{textCol}{imageCol}</>) : (<>{imageCol}{textCol}</>)}
          </div>
        </section>
      );
    }

    // ---- fondo-completo: bgImage full-bleed, alto impacto -----------------
    if (variant === 'fondo-completo') {
      return (
        <section className={`reveal relative ${styleClass} py-32 px-4 text-center bg-cover bg-center`} style={sectionStyle}>
          {bgImage && <div className="absolute inset-0 bg-black/50" />}
          <div className="relative max-w-4xl mx-auto">
            <h1 className="font-heading text-4xl md:text-6xl font-bold mb-6 leading-tight">{title}</h1>
            <p className="text-xl md:text-2xl mb-6 opacity-90 leading-relaxed">{subtitle}</p>
            {description && <p className="text-lg mb-10 opacity-75 leading-relaxed">{description}</p>}
            {buttons('justify-center')}
          </div>
        </section>
      );
    }

    // ---- minimal: solo texto, sin imágenes, mucho espacio -----------------
    if (variant === 'minimal') {
      return (
        <section className={`reveal relative ${styleClass} py-32 px-4 text-center`} style={colorStyle}>
          <div className="relative max-w-3xl mx-auto">
            <h1 className="font-heading text-5xl md:text-6xl font-bold mb-8 leading-tight">{title}</h1>
            <p className="text-xl mb-10 opacity-75 leading-relaxed">{subtitle}</p>
            {buttons('justify-center')}
          </div>
        </section>
      );
    }

    // ---- centrado: layout clásico (default) --------------------------------
    return (
      <section className={`reveal relative ${styleClass} py-24 px-4 text-center bg-cover bg-center`} style={sectionStyle}>
        {bgImage && <div className="absolute inset-0 bg-black/50" />}
        <div className="relative max-w-4xl mx-auto">
          <h1 className="font-heading text-4xl md:text-6xl font-bold mb-6 leading-tight">{title}</h1>
          <p className="text-xl md:text-2xl mb-6 opacity-90 leading-relaxed">{subtitle}</p>
          {description && <p className="text-lg mb-10 opacity-75 leading-relaxed">{description}</p>}
          {buttons('justify-center')}
        </div>
      </section>
    );
  },
};

export const TextBlock = {
  label: 'Texto',
  desc: 'Bloque de texto con formato HTML (puede incluir negritas, cursivas, enlaces, listas). Encabezado opcional. Fondo transparente (hereda el fondo de la página) o superficie (panel sutil de contraste).',
  fields: {
    heading: { type: 'text', label: 'Encabezado (opcional)' },
    content: { type: 'textarea', label: 'Contenido (HTML permitido)' },
    alignment: {
      type: 'radio',
      label: 'Alineación',
      options: [
        { label: 'Izquierda', value: 'text-left' },
        { label: 'Centro', value: 'text-center' },
      ],
    },
    background: {
      type: 'radio',
      label: 'Fondo',
      options: [
        { label: 'Transparente', value: 'transparent' },
        { label: 'Superficie (panel)', value: 'surface' },
      ],
    },
    ...CUSTOM_COLOR_FIELDS,
  },
  defaultProps: {
    heading: '',
    content:
      '<p>Escribe tu contenido aquí. Puedes incluir HTML básico como <strong>negritas</strong>, <em>cursivas</em> y <a href="#">enlaces</a>.</p>',
    alignment: 'text-left',
    background: 'transparent',
    ...CUSTOM_COLOR_DEFAULTS,
  },
  render: ({ heading, content, alignment, background, customBgColor, textColor, customTextColor }) => {
    const { className: styleClass, style: colorStyle } = resolveSectionStyle(background, '', {
      customBgColor,
      textColor,
      customTextColor,
    });
    const textOverride = isValidHex(customTextColor);
    return (
      <section className={`reveal py-14 px-4 ${styleClass}`} style={colorStyle}>
        <div className={`max-w-4xl mx-auto ${alignment}`}>
          {heading && (
            <h2 className={`font-heading2 text-3xl font-bold mb-6 ${textOverride ? '' : 'text-brand-text'}`}>
              {heading}
            </h2>
          )}
          <div
            className={`prose prose-lg dark:prose-invert max-w-none ${textOverride ? '' : 'text-ink-muted'}`}
            dangerouslySetInnerHTML={{ __html: content }}
          />
        </div>
      </section>
    );
  },
};

// Las 5 opciones de layout real de FeatureGrid. 'tarjetas' es el default —
// preserva el markup exacto de antes de que existiera `variant`, así el
// contenido guardado previamente (sin esta prop) no cambia de aspecto.
const FEATURE_GRID_VARIANT_OPTIONS = [
  { label: 'Tarjetas (clásico)', value: 'tarjetas' },
  { label: 'Lista vertical', value: 'lista' },
  { label: 'Pasos numerados', value: 'numeradas' },
  { label: 'Imagen + lista al lado', value: 'imagen-lateral' },
  { label: 'Encabezado destacado + íconos', value: 'destacado' },
];

export const FeatureGrid = {
  label: 'Características',
  desc: 'Grupo de características/beneficios (cada una con ícono, título y descripción). 5 variantes de layout real: tarjetas en grid, lista vertical, pasos numerados, imagen al lado o encabezado destacado.',
  fields: {
    variant: { type: 'select', label: 'Variante de layout', options: FEATURE_GRID_VARIANT_OPTIONS },
    title: { type: 'text', label: 'Título de sección (opcional)' },
    subtitle: { type: 'textarea', label: 'Subtítulo (opcional)' },
    description: { type: 'textarea', label: 'Descripción (opcional, según variante)' },
    ctaLabel: { type: 'text', label: 'Botón: texto (opcional, según variante)' },
    ctaUrl: { type: 'text', label: 'Botón: URL (opcional)' },
    image: imageField('Imagen (opcional, variante "imagen al lado")'),
    features: {
      type: 'array',
      label: 'Características',
      arrayFields: {
        icon: iconField('Ícono'),
        title: { type: 'text', label: 'Título' },
        description: { type: 'textarea', label: 'Descripción' },
      },
      getItemSummary: (item) => item.title || 'Característica',
      defaultItemProps: {
        icon: 'tabler:star',
        title: 'Nueva característica',
        description: 'Descripción del beneficio.',
      },
    },
    columns: {
      type: 'select',
      label: 'Columnas (variantes en grid)',
      options: [
        { label: '2 columnas', value: '2' },
        { label: '3 columnas', value: '3' },
        { label: '4 columnas', value: '4' },
      ],
    },
    background: { type: 'radio', label: 'Fondo de sección', options: BACKGROUND_OPTIONS_OPTIONAL },
    ...CUSTOM_COLOR_FIELDS,
  },
  defaultProps: {
    title: 'Todo lo que necesitás',
    subtitle: 'Pensado para que empieces a ver resultados desde el primer día.',
    description: '',
    ctaLabel: '',
    ctaUrl: '',
    image: '',
    features: [
      { icon: 'tabler:star', title: 'Característica 1', description: 'Descripción del primer beneficio.' },
      { icon: 'tabler:rocket', title: 'Característica 2', description: 'Descripción del segundo beneficio.' },
      { icon: 'tabler:bulb', title: 'Característica 3', description: 'Descripción del tercer beneficio.' },
    ],
    columns: '3',
    variant: 'tarjetas',
    background: '',
    ...CUSTOM_COLOR_DEFAULTS,
  },
  render: (props) => {
    const {
      title, subtitle, description, ctaLabel, ctaUrl, image, features,
      columns, variant, background, customBgColor, textColor, customTextColor,
    } = props;
    const colClass =
      { '2': 'md:grid-cols-2', '3': 'md:grid-cols-3', '4': 'md:grid-cols-4' }[columns] ||
      'md:grid-cols-3';
    const { className: styleClass, style: colorStyle } = resolveSectionStyle(background, '', {
      customBgColor,
      textColor,
      customTextColor,
    });
    const textOverride = isValidHex(customTextColor);
    const button = ctaLabel && ctaUrl ? (
      <a
        href={ctaUrl}
        className={`inline-block font-semibold px-8 py-4 rounded-brand hover:opacity-90 transition-opacity ${heroButtonClasses(background)}`}
      >
        {ctaLabel}
      </a>
    ) : null;

    // ---- lista: ícono a la izquierda, título+descripción a la derecha ---
    if (variant === 'lista') {
      return (
        <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
          <div className="max-w-3xl mx-auto">
            {title && <h2 className={`font-heading2 text-3xl font-bold mb-4 ${textOverride ? '' : 'text-ink'}`}>{title}</h2>}
            {subtitle && <p className={`text-lg mb-10 leading-relaxed ${textOverride ? '' : 'text-ink-muted'}`}>{subtitle}</p>}
            <div className="flex flex-col gap-8">
              {features.map((feature, i) => (
                <div key={i} className="flex items-start gap-4">
                  <div className="flex-shrink-0 w-12 h-12 rounded-brand bg-brand-primary/10 flex items-center justify-center">
                    <PickedIcon icon={feature.icon} size={24} />
                  </div>
                  <div>
                    <h3 className={`font-heading2 text-lg font-bold mb-1 ${textOverride ? '' : 'text-ink'}`}>{feature.title}</h3>
                    <p className={textOverride ? '' : 'text-ink-muted leading-relaxed'}>{feature.description}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>
      );
    }

    // ---- numeradas: pasos 01/02/03 en vez de ícono ----------------------
    if (variant === 'numeradas') {
      return (
        <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
          <div className="max-w-6xl mx-auto">
            {title && <h2 className={`font-heading2 text-3xl font-bold text-center mb-4 ${textOverride ? '' : 'text-ink'}`}>{title}</h2>}
            {subtitle && <p className={`text-lg text-center max-w-2xl mx-auto mb-12 ${textOverride ? '' : 'text-ink-muted'}`}>{subtitle}</p>}
            <div className={`grid grid-cols-1 ${colClass} gap-8`}>
              {features.map((feature, i) => (
                <div key={i}>
                  <div className="font-heading2 text-4xl font-bold text-brand-primary mb-3">{String(i + 1).padStart(2, '0')}</div>
                  <h3 className={`font-heading2 text-xl font-bold mb-3 ${textOverride ? '' : 'text-ink'}`}>{feature.title}</h3>
                  <p className={textOverride ? '' : 'text-ink-muted leading-relaxed'}>{feature.description}</p>
                </div>
              ))}
            </div>
          </div>
        </section>
      );
    }

    // ---- imagen-lateral: título+subtítulo+lista compacta+botón, imagen --
    if (variant === 'imagen-lateral') {
      return (
        <section className={`reveal py-20 px-4 ${styleClass}`} style={colorStyle}>
          <div className="max-w-6xl mx-auto grid md:grid-cols-2 gap-12 items-center">
            <div>
              {title && <h2 className="font-heading2 text-3xl font-bold mb-4">{title}</h2>}
              {subtitle && <p className="text-lg opacity-75 leading-relaxed mb-8">{subtitle}</p>}
              <div className="flex flex-col gap-5 mb-8">
                {features.map((feature, i) => (
                  <div key={i} className="flex items-center gap-3">
                    <PickedIcon icon={feature.icon} size={24} />
                    <span className="font-semibold">{feature.title}</span>
                  </div>
                ))}
              </div>
              {button}
            </div>
            {image ? (
              <div className="rounded-2xl overflow-hidden shadow-md aspect-video">
                <img src={image} alt="" className="w-full h-full object-cover" />
              </div>
            ) : <div />}
          </div>
        </section>
      );
    }

    // ---- destacado: encabezado grande + grid minimal de íconos ----------
    if (variant === 'destacado') {
      return (
        <section className={`reveal py-20 px-4 text-center ${styleClass || 'bg-brand-primary-dark text-white'}`} style={colorStyle}>
          <div className="max-w-3xl mx-auto mb-14">
            {title && <h2 className="font-heading2 text-3xl md:text-4xl font-bold mb-4">{title}</h2>}
            {subtitle && <p className="text-xl opacity-90 leading-relaxed mb-4">{subtitle}</p>}
            {description && <p className="opacity-75 leading-relaxed mb-8">{description}</p>}
            {button}
          </div>
          <div className={`max-w-6xl mx-auto grid grid-cols-1 ${colClass} gap-8 text-left`}>
            {features.map((feature, i) => (
              <div key={i}>
                <PickedIcon icon={feature.icon} size={32} className="mb-3" />
                <h3 className="font-heading2 text-lg font-bold mb-2">{feature.title}</h3>
                <p className="opacity-75 leading-relaxed">{feature.description}</p>
              </div>
            ))}
          </div>
        </section>
      );
    }

    // ---- tarjetas: layout clásico (default) ------------------------------
    return (
      <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
        <div className="max-w-6xl mx-auto">
          {title && (
            <h2 className={`font-heading2 text-3xl font-bold text-center mb-12 ${textOverride ? '' : 'text-ink'}`}>
              {title}
            </h2>
          )}
          <div className={`grid grid-cols-1 ${colClass} gap-8`}>
            {features.map((feature, i) => (
              <div key={i} className="bg-surface-alt p-8 rounded-2xl shadow-sm text-center transition-all duration-300 hover:-translate-y-1 hover:shadow-lg">
                <div className="flex justify-center mb-4">
                  <PickedIcon icon={feature.icon} size={48} />
                </div>
                <h3 className="font-heading2 text-xl font-bold mb-3 text-ink">{feature.title}</h3>
                <p className="text-ink-muted leading-relaxed">{feature.description}</p>
              </div>
            ))}
          </div>
        </div>
      </section>
    );
  },
};

export const ImageBlock = {
  label: 'Imagen',
  desc: 'Imagen individual con texto alternativo y pie de foto opcional. Puede ser ancho completo o centrada.',
  fields: {
    imageUrl: imageField('Imagen'),
    alt: { type: 'text', label: 'Texto alternativo (SEO)' },
    caption: { type: 'text', label: 'Pie de foto (opcional)' },
    size: {
      type: 'radio',
      label: 'Ancho',
      options: [
        { label: 'Completo', value: 'full' },
        { label: 'Centrado', value: 'centered' },
      ],
    },
  },
  defaultProps: {
    imageUrl: 'https://placehold.co/1200x600/e2e8f0/94a3b8?text=Imagen',
    alt: 'Imagen',
    caption: '',
    size: 'full',
  },
  render: ({ imageUrl, alt, caption, size }) => (
    <div className="reveal py-8 px-4">
      <figure className={size === 'centered' ? 'max-w-3xl mx-auto' : 'w-full'}>
        <img src={imageUrl} alt={alt} className="w-full rounded-xl object-cover" />
        {caption && (
          <figcaption className="text-center text-ink-muted text-sm mt-3 italic">{caption}</figcaption>
        )}
      </figure>
    </div>
  ),
};

// Las 5 opciones de layout real de CTASection. 'clasico' es el default —
// preserva el markup exacto de antes de que existiera `variant`, así el
// contenido guardado previamente (sin esta prop) no cambia de aspecto.
const CTA_VARIANT_OPTIONS = [
  { label: 'Clásico (un botón)', value: 'clasico' },
  { label: 'Doble botón', value: 'doble-boton' },
  { label: 'Con ícono', value: 'con-icono' },
  { label: 'Imagen al lado', value: 'imagen-lateral' },
  { label: 'Franja minimal', value: 'franja-minimal' },
];

export const CTASection = {
  label: 'Llamado a la acción',
  desc: 'Sección de llamado a la acción: título, subtítulo, descripción, hasta 2 botones, ícono/emoji e imagen opcional. 5 variantes de layout real, cada una usando una combinación distinta de estos campos.',
  fields: {
    variant: { type: 'select', label: 'Variante de layout', options: CTA_VARIANT_OPTIONS },
    heading: { type: 'text', label: 'Título' },
    subtitle: { type: 'textarea', label: 'Subtítulo (opcional)' },
    body: { type: 'textarea', label: 'Descripción' },
    buttonLabel: { type: 'text', label: 'Botón 1: texto' },
    buttonUrl: { type: 'text', label: 'Botón 1: URL' },
    cta2Label: { type: 'text', label: 'Botón 2: texto (opcional)' },
    cta2Url: { type: 'text', label: 'Botón 2: URL (opcional)' },
    icon: iconField('Ícono / emoji (opcional, según variante)'),
    image: imageField('Imagen (opcional, variante "imagen al lado")'),
    style: {
      type: 'radio',
      label: 'Estilo (variante clásica)',
      options: [
        { label: 'Sólido', value: 'solid' },
        { label: 'Contorno', value: 'outline' },
      ],
    },
    background: { type: 'radio', label: 'Fondo de sección', options: BACKGROUND_OPTIONS_OPTIONAL },
    ...CUSTOM_COLOR_FIELDS,
  },
  defaultProps: {
    heading: '¿Listo para comenzar?',
    subtitle: '',
    body: 'Contáctanos hoy y descubre cómo podemos ayudarte.',
    buttonLabel: 'Comenzar ahora',
    buttonUrl: '/contacto',
    cta2Label: '',
    cta2Url: '',
    icon: 'tabler:rocket',
    image: '',
    variant: 'clasico',
    style: 'solid',
    background: '',
    ...CUSTOM_COLOR_DEFAULTS,
  },
  render: (props) => {
    const {
      heading, subtitle, body, buttonLabel, buttonUrl, cta2Label, cta2Url,
      icon, image, variant, style, background, customBgColor, textColor, customTextColor,
    } = props;
    const solid = style !== 'outline';
    const buttonClasses = solid
      ? 'bg-white text-brand-primary'
      : 'bg-brand-primary text-white';

    const hasOverride = !!background || isValidHex(customBgColor) || (textColor && textColor !== 'auto');
    let section;
    let colorStyle = {};
    if (hasOverride) {
      const autoText = solid ? 'text-white' : 'text-brand-text';
      const resolved = resolveSectionStyle(background, autoText, { customBgColor, textColor, customTextColor });
      section = resolved.className + (!solid ? ' border-2 border-brand-primary' : '');
      colorStyle = resolved.style;
    } else {
      section = solid
        ? 'bg-brand-primary text-white'
        : 'bg-brand-bg text-brand-text border-2 border-brand-primary';
    }

    const button1 = buttonLabel && buttonUrl ? (
      <a href={buttonUrl} className={`inline-block font-semibold px-8 py-4 rounded-brand transition-opacity hover:opacity-90 ${buttonClasses}`}>
        {buttonLabel}
      </a>
    ) : null;
    const button2 = cta2Label && cta2Url ? (
      <a href={cta2Url} className={`inline-block font-semibold px-8 py-4 rounded-brand border-2 transition-opacity hover:opacity-90 ${solid ? 'border-white text-white' : 'border-brand-primary text-brand-primary'}`}>
        {cta2Label}
      </a>
    ) : null;

    // ---- doble-boton: título+subtítulo+descripción+2 botones ------------
    if (variant === 'doble-boton') {
      return (
        <section className={`reveal ${section} py-20 px-4 text-center`} style={colorStyle}>
          <div className="max-w-2xl mx-auto">
            <h2 className="font-heading2 text-3xl font-bold mb-4">{heading}</h2>
            {subtitle && <p className="text-xl mb-4 opacity-90 leading-relaxed">{subtitle}</p>}
            <p className="text-lg mb-10 opacity-90 leading-relaxed">{body}</p>
            <div className="flex flex-col sm:flex-row gap-4 justify-center">{button1}{button2}</div>
          </div>
        </section>
      );
    }

    // ---- con-icono: ícono grande arriba, título+descripción+botón 1 -----
    if (variant === 'con-icono') {
      return (
        <section className={`reveal ${section} py-20 px-4 text-center`} style={colorStyle}>
          <div className="max-w-2xl mx-auto">
            <PickedIcon icon={icon} size={48} className="mx-auto mb-6" />
            <h2 className="font-heading2 text-3xl font-bold mb-4">{heading}</h2>
            <p className="text-lg mb-10 opacity-90 leading-relaxed">{body}</p>
            {button1}
          </div>
        </section>
      );
    }

    // ---- imagen-lateral: texto+2 botones a un lado, imagen al otro ------
    if (variant === 'imagen-lateral') {
      return (
        <section className={`reveal ${section} py-20 px-4`} style={colorStyle}>
          <div className="max-w-6xl mx-auto grid md:grid-cols-2 gap-12 items-center">
            <div className="text-left">
              <h2 className="font-heading2 text-3xl font-bold mb-4">{heading}</h2>
              {subtitle && <p className="text-xl mb-4 opacity-90 leading-relaxed">{subtitle}</p>}
              <p className="text-lg mb-8 opacity-90 leading-relaxed">{body}</p>
              <div className="flex flex-col sm:flex-row gap-4">{button1}{button2}</div>
            </div>
            {image ? (
              <div className="rounded-2xl overflow-hidden shadow-md aspect-video">
                <img src={image} alt="" className="w-full h-full object-cover" />
              </div>
            ) : <div />}
          </div>
        </section>
      );
    }

    // ---- franja-minimal: ícono+título en línea a la izquierda, botón ----
    if (variant === 'franja-minimal') {
      return (
        <section className={`reveal ${section} py-8 px-4`} style={colorStyle}>
          <div className="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
            <div className="flex items-center gap-3">
              <PickedIcon icon={icon} size={28} />
              <h2 className="font-heading2 text-xl font-bold">{heading}</h2>
            </div>
            {button1}
          </div>
        </section>
      );
    }

    // ---- clasico: layout original (default) ------------------------------
    return (
      <section className={`reveal ${section} py-20 px-4 text-center`} style={colorStyle}>
        <div className="max-w-2xl mx-auto">
          <h2 className="font-heading2 text-3xl font-bold mb-4">{heading}</h2>
          <p className="text-lg mb-10 opacity-90 leading-relaxed">{body}</p>
          {button1}
        </div>
      </section>
    );
  },
};

// Parsea el textarea de "Características incluidas (una por línea)" de cada
// plan a un array de strings — se evita un `array` anidado dentro de otro
// `array` (arrayFields de `plans`) porque no hay precedente probado de eso
// en este editor Puck; una línea por feature es más simple y suficiente.
function parsePlanFeatures(features) {
  return (features || '')
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean);
}

function PlanFeatureList({ features, className = '' }) {
  const items = parsePlanFeatures(features);
  if (items.length === 0) return null;
  return (
    <ul className={`flex flex-col gap-3 text-left ${className}`}>
      {items.map((f, i) => (
        <li key={i} className="flex items-start gap-2">
          <span className="text-brand-primary font-bold flex-shrink-0">✓</span>
          <span>{f}</span>
        </li>
      ))}
    </ul>
  );
}

// Las 5 opciones de layout real de Pricing: 3 variantes de 3 planes (grid
// clásico, contraste con plan destacado sólido, tabla minimal con
// divisores), 1 de 2 planes y 1 de un solo plan/producto — cubre los casos
// de uso más comunes de una sección de precios.
const PRICING_VARIANT_OPTIONS = [
  { label: '3 planes — tarjetas (clásico)', value: 'tres-planes' },
  { label: '3 planes — contraste destacado', value: 'tres-planes-contraste' },
  { label: '3 planes — tabla minimal', value: 'tres-planes-tabla' },
  { label: '2 planes', value: 'dos-planes' },
  { label: '1 plan (producto/servicio único)', value: 'un-plan' },
];

export const Pricing = {
  label: 'Planes y precios',
  desc: 'Sección de precios con lista de planes (nombre, precio, período, características, botón). 5 variantes: 3 planes en tarjetas, 3 planes con contraste, 3 planes en tabla minimal, 2 planes o 1 solo plan/producto.',
  fields: {
    variant: { type: 'select', label: 'Variante de layout', options: PRICING_VARIANT_OPTIONS },
    title: { type: 'text', label: 'Título de sección (opcional)' },
    subtitle: { type: 'textarea', label: 'Subtítulo (opcional)' },
    description: { type: 'textarea', label: 'Descripción (opcional)' },
    plans: {
      type: 'array',
      label: 'Planes',
      arrayFields: {
        name: { type: 'text', label: 'Nombre del plan' },
        price: { type: 'text', label: 'Precio (ej: $29, Gratis, Personalizado)' },
        period: { type: 'text', label: 'Período (opcional, ej: /mes)' },
        description: { type: 'textarea', label: 'Descripción breve (opcional)' },
        features: { type: 'textarea', label: 'Características incluidas (una por línea)' },
        ctaLabel: { type: 'text', label: 'Botón: texto' },
        ctaUrl: { type: 'text', label: 'Botón: URL' },
        highlighted: {
          type: 'radio',
          label: 'Destacar este plan (recomendado)',
          options: [
            { label: 'No', value: 'no' },
            { label: 'Sí', value: 'yes' },
          ],
        },
        icon: iconField('Ícono / emoji (opcional, variante "1 plan")'),
      },
      getItemSummary: (item) => item.name || 'Plan',
      defaultItemProps: {
        name: 'Nuevo plan',
        price: '$0',
        period: '/mes',
        description: '',
        features: '',
        ctaLabel: 'Elegir plan',
        ctaUrl: '/contacto',
        highlighted: 'no',
        icon: 'tabler:star',
      },
    },
    background: { type: 'radio', label: 'Fondo de sección', options: BACKGROUND_OPTIONS_OPTIONAL },
    ...CUSTOM_COLOR_FIELDS,
  },
  defaultProps: {
    title: 'Planes y precios',
    subtitle: 'Elegí el plan que mejor se adapte a tu negocio.',
    description: '',
    plans: [
      {
        name: 'Básico', price: '$19', period: '/mes',
        description: 'Para empezar.',
        features: 'Hasta 1.000 visitas\nSoporte por email\n1 usuario',
        ctaLabel: 'Elegir Básico', ctaUrl: '/contacto', highlighted: 'no', icon: 'tabler:star',
      },
      {
        name: 'Pro', price: '$49', period: '/mes',
        description: 'El más elegido.',
        features: 'Visitas ilimitadas\nSoporte prioritario\n5 usuarios\nReportes avanzados',
        ctaLabel: 'Elegir Pro', ctaUrl: '/contacto', highlighted: 'yes', icon: 'tabler:rocket',
      },
      {
        name: 'Premium', price: '$99', period: '/mes',
        description: 'Para equipos grandes.',
        features: 'Todo lo de Pro\nUsuarios ilimitados\nSoporte 24/7\nIntegraciones a medida',
        ctaLabel: 'Elegir Premium', ctaUrl: '/contacto', highlighted: 'no', icon: 'tabler:diamond',
      },
    ],
    variant: 'tres-planes',
    background: '',
    ...CUSTOM_COLOR_DEFAULTS,
  },
  render: (props) => {
    const { title, subtitle, description, plans, variant, background, customBgColor, textColor, customTextColor } = props;
    const { className: styleClass, style: colorStyle } = resolveSectionStyle(background, '', {
      customBgColor,
      textColor,
      customTextColor,
    });
    const textOverride = isValidHex(customTextColor);
    const list = Array.isArray(plans) ? plans : [];

    const head = (
      <>
        {title && <h2 className={`font-heading2 text-3xl font-bold text-center mb-4 ${textOverride ? '' : 'text-ink'}`}>{title}</h2>}
        {subtitle && <p className={`text-lg text-center max-w-2xl mx-auto mb-4 ${textOverride ? '' : 'text-ink-muted'}`}>{subtitle}</p>}
        {description && <p className={`text-center max-w-2xl mx-auto mb-12 ${textOverride ? '' : 'text-ink-muted'}`}>{description}</p>}
      </>
    );

    // `forceOnDark` es para tarjetas que fuerzan su propio fondo sólido
    // oscuro (ej. el plan destacado en 'tres-planes-contraste') sin importar
    // el fondo de la sección; el resto sigue el contraste de la sección
    // (heroButtonClasses), igual criterio que Hero/CTASection.
    const planButton = (plan, forceOnDark = false) => plan.ctaLabel && plan.ctaUrl ? (
      <a
        href={plan.ctaUrl}
        className={`block text-center font-semibold px-6 py-3 rounded-brand transition-opacity hover:opacity-90 ${
          forceOnDark ? 'bg-white text-brand-primary' : heroButtonClasses(background)
        }`}
      >
        {plan.ctaLabel}
      </a>
    ) : null;

    // ---- tres-planes-contraste: plan destacado con fondo sólido invertido
    if (variant === 'tres-planes-contraste') {
      return (
        <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
          <div className="max-w-6xl mx-auto">
            {head}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-8 items-center">
              {list.slice(0, 3).map((plan, i) => {
                const hl = plan.highlighted === 'yes';
                return (
                  <div
                    key={i}
                    className={`p-8 rounded-2xl ${hl ? 'bg-brand-primary-dark text-white shadow-lg md:scale-105' : 'bg-surface-alt text-ink border border-surface-border'}`}
                  >
                    <h3 className="font-heading2 text-xl font-bold mb-2">{plan.name}</h3>
                    {plan.description && <p className={`mb-6 ${hl ? 'opacity-90' : 'text-ink-muted'}`}>{plan.description}</p>}
                    <div className="mb-6">
                      <span className="font-heading2 text-4xl font-bold">{plan.price}</span>
                      {plan.period && <span className="opacity-75">{plan.period}</span>}
                    </div>
                    <PlanFeatureList features={plan.features} className={`mb-8 ${hl ? '' : 'text-ink-muted'}`} />
                    {planButton(plan, hl)}
                  </div>
                );
              })}
            </div>
          </div>
        </section>
      );
    }

    // ---- tres-planes-tabla: minimal, columnas separadas por divisores ----
    if (variant === 'tres-planes-tabla') {
      return (
        <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
          <div className="max-w-6xl mx-auto">
            {head}
            <div className="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-surface-border">
              {list.slice(0, 3).map((plan, i) => (
                <div key={i} className="text-center px-8 py-6">
                  {plan.highlighted === 'yes' && (
                    <span className="inline-block text-xs font-semibold uppercase tracking-wide text-brand-primary mb-2">Recomendado</span>
                  )}
                  <h3 className={`font-heading2 text-xl font-bold mb-2 ${textOverride ? '' : 'text-ink'}`}>{plan.name}</h3>
                  <div className="mb-4">
                    <span className="font-heading2 text-4xl font-bold text-brand-primary">{plan.price}</span>
                    {plan.period && <span className={textOverride ? '' : 'text-ink-muted'}>{plan.period}</span>}
                  </div>
                  <PlanFeatureList features={plan.features} className={`mb-8 justify-center ${textOverride ? '' : 'text-ink-muted'}`} />
                  {planButton(plan)}
                </div>
              ))}
            </div>
          </div>
        </section>
      );
    }

    // ---- dos-planes: 2 columnas, tarjetas más anchas ----------------------
    if (variant === 'dos-planes') {
      return (
        <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
          <div className="max-w-4xl mx-auto">
            {head}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
              {list.slice(0, 2).map((plan, i) => {
                const hl = plan.highlighted === 'yes';
                return (
                  <div
                    key={i}
                    className={`p-10 rounded-2xl ${hl ? 'bg-surface-alt border-2 border-brand-primary shadow-lg' : 'bg-surface-alt border border-surface-border'}`}
                  >
                    <h3 className={`font-heading2 text-2xl font-bold mb-2 ${textOverride ? '' : 'text-ink'}`}>{plan.name}</h3>
                    {plan.description && <p className={`mb-6 ${textOverride ? '' : 'text-ink-muted'}`}>{plan.description}</p>}
                    <div className="mb-6">
                      <span className="font-heading2 text-5xl font-bold text-brand-primary">{plan.price}</span>
                      {plan.period && <span className={textOverride ? '' : 'text-ink-muted'}>{plan.period}</span>}
                    </div>
                    <PlanFeatureList features={plan.features} className={`mb-8 ${textOverride ? '' : 'text-ink-muted'}`} />
                    {planButton(plan)}
                  </div>
                );
              })}
            </div>
          </div>
        </section>
      );
    }

    // ---- un-plan: producto/servicio único, caja centrada ------------------
    if (variant === 'un-plan') {
      const plan = list[0] || {};
      return (
        <section className={`reveal py-20 px-4 text-center ${styleClass}`} style={colorStyle}>
          <div className="max-w-md mx-auto bg-surface-alt border border-surface-border rounded-2xl p-10 shadow-md">
            <PickedIcon icon={plan.icon} size={40} className="mx-auto mb-4" />
            <h3 className={`font-heading2 text-2xl font-bold mb-2 ${textOverride ? '' : 'text-ink'}`}>{plan.name}</h3>
            {plan.description && <p className={`mb-6 ${textOverride ? '' : 'text-ink-muted'}`}>{plan.description}</p>}
            <div className="mb-8">
              <span className="font-heading2 text-5xl font-bold text-brand-primary">{plan.price}</span>
              {plan.period && <span className={textOverride ? '' : 'text-ink-muted'}>{plan.period}</span>}
            </div>
            <PlanFeatureList features={plan.features} className={`mb-8 ${textOverride ? '' : 'text-ink-muted'}`} />
            {planButton(plan)}
          </div>
        </section>
      );
    }

    // ---- tres-planes: layout clásico en tarjetas (default) ----------------
    return (
      <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
        <div className="max-w-6xl mx-auto">
          {head}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8 items-center">
            {list.slice(0, 3).map((plan, i) => {
              const hl = plan.highlighted === 'yes';
              return (
                <div
                  key={i}
                  className={`relative p-8 rounded-2xl bg-surface-alt ${hl ? 'border-2 border-brand-primary shadow-lg md:scale-105' : 'border border-surface-border'}`}
                >
                  {hl && (
                    <span className="absolute -top-3 left-1/2 -translate-x-1/2 bg-brand-primary text-white text-xs font-semibold uppercase tracking-wide px-3 py-1 rounded-full">
                      Recomendado
                    </span>
                  )}
                  <h3 className={`font-heading2 text-xl font-bold mb-2 ${textOverride ? '' : 'text-ink'}`}>{plan.name}</h3>
                  {plan.description && <p className={`mb-6 ${textOverride ? '' : 'text-ink-muted'}`}>{plan.description}</p>}
                  <div className="mb-6">
                    <span className="font-heading2 text-4xl font-bold text-brand-primary">{plan.price}</span>
                    {plan.period && <span className={textOverride ? '' : 'text-ink-muted'}>{plan.period}</span>}
                  </div>
                  <PlanFeatureList features={plan.features} className={`mb-8 ${textOverride ? '' : 'text-ink-muted'}`} />
                  {planButton(plan)}
                </div>
              );
            })}
          </div>
        </div>
      </section>
    );
  },
};

export const Divider = {
  label: 'Separador',
  desc: 'Separador visual (espacio vacío) con altura configurable y línea horizontal opcional.',
  fields: {
    height: {
      type: 'select',
      label: 'Altura',
      options: [
        { label: 'Pequeño (16px)', value: 'h-4' },
        { label: 'Mediano (32px)', value: 'h-8' },
        { label: 'Grande (64px)', value: 'h-16' },
        { label: 'Extra grande (128px)', value: 'h-32' },
      ],
    },
    showLine: {
      type: 'radio',
      label: 'Línea divisoria',
      options: [
        { label: 'Sí', value: 'yes' },
        { label: 'No', value: 'no' },
      ],
    },
  },
  defaultProps: {
    height: 'h-8',
    showLine: 'no',
  },
  render: ({ height, showLine }) => (
    <div className={`${height} flex items-center px-8`}>
      {showLine === 'yes' && <hr className="w-full border-surface-border" />}
    </div>
  ),
};

// ---------------------------------------------------------------------------
// COMPONENTES DE CONTENIDO — curado de Pines (thedevdojo/pines)
// ---------------------------------------------------------------------------

export const Banner = {
  label: 'Banner (CTA)',
  desc: 'Banner de anuncio/promoción con título, texto y botón. Por defecto usa un fondo neutro; se puede elegir sólido de marca para mensajes de alto impacto.',
  fields: {
    title: { type: 'text', label: 'Título' },
    body: { type: 'textarea', label: 'Texto' },
    buttonLabel: { type: 'text', label: 'Texto del botón (opcional)' },
    buttonUrl: { type: 'text', label: 'URL del botón (opcional)' },
    align: {
      type: 'radio',
      label: 'Alineación',
      options: [
        { label: 'Izquierda', value: 'text-left' },
        { label: 'Centro', value: 'text-center' },
      ],
    },
    background: { type: 'radio', label: 'Fondo', options: BACKGROUND_OPTIONS },
    ...CUSTOM_COLOR_FIELDS,
  },
  defaultProps: {
    title: 'Título del anuncio',
    body: 'Describe la promoción o mensaje importante de forma breve.',
    buttonLabel: 'Saber más',
    buttonUrl: '/contacto',
    align: 'text-center',
    background: 'surface',
    ...CUSTOM_COLOR_DEFAULTS,
  },
  render: ({ title, body, buttonLabel, buttonUrl, align, background, customBgColor, textColor, customTextColor }) => {
    const autoText = background === 'brand' ? 'text-white' : 'text-ink';
    const { className: styleClass, style: colorStyle } = resolveSectionStyle(background, autoText, {
      customBgColor,
      textColor,
      customTextColor,
    });
    return (
    <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
      <div className={`max-w-4xl mx-auto ${align}`}>
        <h2 className="font-heading2 text-3xl font-bold mb-4">{title}</h2>
        <p className="text-lg mb-8 opacity-90 leading-relaxed">{body}</p>
        {buttonLabel && buttonUrl && (
          <a
            href={buttonUrl}
            className={`inline-block font-semibold px-8 py-4 rounded-brand hover:opacity-90 transition-opacity ${heroButtonClasses(background)}`}
          >
            {buttonLabel}
          </a>
        )}
      </div>
    </section>
    );
  },
};

export const Badge = {
  label: 'Badge',
  desc: 'Etiqueta pequeña. "brand" usa el color del tema; verde/rojo/gris son colores semánticos fijos (éxito/alerta/neutro). Útil para destacar "Nuevo", "Oferta", etc.',
  fields: {
    text: { type: 'text', label: 'Texto' },
    variant: {
      type: 'select',
      label: 'Color',
      options: [
        { label: 'Marca', value: 'brand' },
        { label: 'Verde (éxito)', value: 'green' },
        { label: 'Rojo (alerta)', value: 'red' },
        { label: 'Gris (neutro)', value: 'gray' },
      ],
    },
  },
  defaultProps: { text: 'Nuevo', variant: 'brand' },
  render: ({ text, variant }) => {
    const styles = {
      brand: 'bg-brand-primary text-white',
      green: 'bg-green-100 text-green-800',
      red: 'bg-red-100 text-red-800',
      gray: 'bg-gray-100 text-gray-800',
    };
    return (
      <span
        className={`inline-flex items-center px-3 py-1 rounded-brand text-sm font-semibold ${
          styles[variant] || styles.brand
        }`}
      >
        {text}
      </span>
    );
  },
};

// Convierte "Texto | URL" (una por línea) en [{label, url}] — mismo patrón
// que parsePlanFeatures() en Pricing, reutilizado para enlaces de FAQ.
function parseFaqLinks(text) {
  return (text || '')
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line) => {
      const [label, url] = line.split('|').map((s) => (s || '').trim());
      return { label: label || url || '', url: url || '#' };
    });
}

function FaqLinks({ links, className = '' }) {
  const items = parseFaqLinks(links);
  if (items.length === 0) return null;
  return (
    <div className={`flex flex-wrap gap-3 mt-3 ${className}`}>
      {items.map((l, i) => (
        <a key={i} href={l.url} className="text-sm font-semibold text-brand-primary hover:opacity-80 inline-flex items-center gap-1">
          {l.label} <span aria-hidden="true">→</span>
        </a>
      ))}
    </div>
  );
}

// Hash corto y determinístico (sin dependencias) para agrupar/aislar
// instancias repetidas del mismo bloque en una misma página (ej. acordeón
// exclusivo con varios FAQ en la página, o el name de los radios de Tabs).
function shortHash(str) {
  let h = 0;
  for (let i = 0; i < str.length; i++) {
    h = (h * 31 + str.charCodeAt(i)) | 0;
  }
  return Math.abs(h).toString(36);
}

const FAQ_VARIANT_OPTIONS = [
  { label: 'Acordeón clásico', value: 'acordeon-clasico' },
  { label: 'Acordeón exclusivo (numerado)', value: 'acordeon-exclusivo' },
  { label: 'Tarjetas en grid', value: 'tarjetas-grid' },
  { label: 'Conversacional (chat)', value: 'conversacional' },
  { label: 'Dividido — panel lateral', value: 'dividido-lateral' },
];

export const FAQ = {
  label: 'FAQ (Preguntas frecuentes)',
  desc: 'Preguntas frecuentes con título de sección. Cada ítem tiene ícono opcional, pregunta, respuesta en HTML y enlaces relacionados opcionales. 5 variantes: acordeón clásico, acordeón exclusivo, tarjetas en grid, conversacional y dividido con panel lateral.',
  fields: {
    variant: { type: 'select', label: 'Variante', options: FAQ_VARIANT_OPTIONS },
    title: { type: 'text', label: 'Título de sección (opcional)' },
    subtitle: { type: 'text', label: 'Subtítulo (opcional)' },
    description: { type: 'textarea', label: 'Descripción (opcional)' },
    items: {
      type: 'array',
      label: 'Preguntas',
      arrayFields: {
        icon: iconField('Ícono / emoji (opcional)'),
        question: { type: 'text', label: 'Pregunta' },
        answer: { type: 'textarea', label: 'Respuesta (HTML permitido)' },
        links: { type: 'textarea', label: 'Enlaces relacionados (opcional, uno por línea: Texto | URL)' },
      },
      getItemSummary: (item) => item.question || 'Pregunta',
      defaultItemProps: {
        icon: '',
        question: '¿Nueva pregunta?',
        answer: '<p>Escribe la respuesta aquí.</p>',
        links: '',
      },
    },
    background: { type: 'radio', label: 'Fondo de sección', options: BACKGROUND_OPTIONS_OPTIONAL },
    ...CUSTOM_COLOR_FIELDS,
  },
  defaultProps: {
    title: 'Preguntas Frecuentes',
    subtitle: '',
    description: '',
    items: [
      { icon: '', question: '¿Cómo funciona el servicio?', answer: '<p>Explicación breve de la respuesta.</p>', links: '' },
      { icon: '', question: '¿Cuáles son los precios?', answer: '<p>Detalle de precios o planes.</p>', links: '' },
    ],
    variant: 'acordeon-clasico',
    background: '',
    ...CUSTOM_COLOR_DEFAULTS,
  },
  render: ({ title, subtitle, description, items, variant, background, customBgColor, textColor, customTextColor }) => {
    const { className: styleClass, style: colorStyle } = resolveSectionStyle(background, '', {
      customBgColor,
      textColor,
      customTextColor,
    });
    const textOverride = isValidHex(customTextColor);
    const hasExtra = Boolean(subtitle || description);

    // Encabezado compartido — cuando subtitle/description están vacíos
    // (contenido ya guardado antes de agregar estos campos), el <h2>
    // conserva exactamente sus clases originales (mb-10 text-center).
    const head = (
      <>
        {title && (
          <h2 className={`font-heading2 text-3xl font-bold text-center ${hasExtra ? 'mb-3' : 'mb-10'} ${textOverride ? '' : 'text-ink'}`}>
            {title}
          </h2>
        )}
        {subtitle && (
          <p className={`text-center font-semibold text-brand-primary ${description ? 'mb-3' : 'mb-10'}`}>{subtitle}</p>
        )}
        {description && (
          <p className={`max-w-2xl mx-auto text-center mb-10 ${textOverride ? '' : 'text-ink-muted'}`}>{description}</p>
        )}
      </>
    );

    // ---- acordeon-exclusivo: solo una pregunta abierta a la vez ----------
    if (variant === 'acordeon-exclusivo') {
      const groupName = 'faq-' + shortHash(items.map((it) => it.question).join('|'));
      return (
        <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
          <div className="max-w-3xl mx-auto">
            {head}
            <div className="space-y-3">
              {items.map((item, i) => (
                <details key={i} name={groupName} className="group bg-surface-alt rounded-xl border border-surface-border px-6 py-4 open:border-brand-primary">
                  <summary className="flex items-center gap-3 cursor-pointer font-semibold text-ink list-none">
                    <span className="flex-shrink-0 w-8 h-8 rounded-full bg-brand-primary/10 text-brand-primary text-sm font-bold flex items-center justify-center">
                      {i + 1}
                    </span>
                    {item.icon && <PickedIcon icon={item.icon} size={18} className="text-brand-primary flex-shrink-0" />}
                    <span className="flex-1">{item.question}</span>
                    <span className="text-ink-muted group-open:rotate-180 transition-transform flex-shrink-0">▾</span>
                  </summary>
                  <div className="mt-3 ml-11 text-ink-muted prose prose-sm dark:prose-invert max-w-none" dangerouslySetInnerHTML={{ __html: item.answer }} />
                  <div className="ml-11"><FaqLinks links={item.links} /></div>
                </details>
              ))}
            </div>
          </div>
        </section>
      );
    }

    // ---- tarjetas-grid: preguntas siempre visibles en grid ---------------
    if (variant === 'tarjetas-grid') {
      return (
        <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
          <div className="max-w-5xl mx-auto">
            {head}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {items.map((item, i) => (
                <div key={i} className="bg-surface-alt rounded-2xl border border-surface-border p-6">
                  <div className="flex items-center gap-3 mb-3">
                    {item.icon && <PickedIcon icon={item.icon} size={28} className="text-brand-primary flex-shrink-0" />}
                    <h3 className={`font-heading2 text-lg font-bold ${textOverride ? '' : 'text-ink'}`}>{item.question}</h3>
                  </div>
                  <div
                    className={`prose prose-sm dark:prose-invert max-w-none ${textOverride ? '' : 'text-ink-muted'}`}
                    dangerouslySetInnerHTML={{ __html: item.answer }}
                  />
                  <FaqLinks links={item.links} />
                </div>
              ))}
            </div>
          </div>
        </section>
      );
    }

    // ---- conversacional: preguntas y respuestas como burbujas de chat ----
    if (variant === 'conversacional') {
      return (
        <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
          <div className="max-w-2xl mx-auto">
            {head}
            <div className="flex flex-col gap-6">
              {items.map((item, i) => (
                <div key={i} className="flex flex-col gap-2">
                  <div className="flex justify-end">
                    <div className="max-w-md bg-brand-primary text-white rounded-2xl rounded-br-sm px-5 py-3 font-semibold">
                      {item.question}
                    </div>
                  </div>
                  <div className="flex items-start gap-3">
                    <div className="w-9 h-9 rounded-full bg-surface-alt border border-surface-border flex items-center justify-center flex-shrink-0">
                      {item.icon ? <PickedIcon icon={item.icon} size={18} className="text-brand-primary" /> : <span className="text-sm">💬</span>}
                    </div>
                    <div className="max-w-md bg-surface-alt border border-surface-border rounded-2xl rounded-tl-sm px-5 py-3">
                      <div className="prose prose-sm dark:prose-invert max-w-none text-ink-muted" dangerouslySetInnerHTML={{ __html: item.answer }} />
                      <FaqLinks links={item.links} />
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>
      );
    }

    // ---- dividido-lateral: panel de intro + acordeón a la derecha --------
    if (variant === 'dividido-lateral') {
      return (
        <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
          <div className="max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-10">
            <div className="md:col-span-1">
              {title && <h2 className={`font-heading2 text-3xl font-bold mb-4 ${textOverride ? '' : 'text-ink'}`}>{title}</h2>}
              {subtitle && <p className="text-lg font-semibold mb-3 text-brand-primary">{subtitle}</p>}
              {description && <p className={`mb-6 ${textOverride ? '' : 'text-ink-muted'}`}>{description}</p>}
            </div>
            <div className="md:col-span-2 space-y-3">
              {items.map((item, i) => (
                <details key={i} className="group bg-surface-alt rounded-xl border border-surface-border px-6 py-4">
                  <summary className="flex items-center justify-between gap-3 cursor-pointer font-semibold text-ink list-none">
                    <span className="flex items-center gap-3">
                      {item.icon && <PickedIcon icon={item.icon} size={18} className="text-brand-primary flex-shrink-0" />}
                      <span>{item.question}</span>
                    </span>
                    <span className="text-ink-muted group-open:rotate-180 transition-transform flex-shrink-0">▾</span>
                  </summary>
                  <div className="mt-3 text-ink-muted prose prose-sm dark:prose-invert max-w-none" dangerouslySetInnerHTML={{ __html: item.answer }} />
                  <FaqLinks links={item.links} />
                </details>
              ))}
            </div>
          </div>
        </section>
      );
    }

    // ---- acordeon-clasico: layout original (default) ---------------------
    return (
      <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
        <div className="max-w-3xl mx-auto">
          {head}
          <div className="space-y-3">
            {items.map((item, i) => (
              <details
                key={i}
                className="group bg-surface-alt rounded-xl border border-surface-border px-6 py-4"
              >
                <summary className="flex items-center justify-between gap-3 cursor-pointer font-semibold text-ink list-none">
                  <span className="flex items-center gap-3">
                    {item.icon && <PickedIcon icon={item.icon} size={18} className="text-brand-primary flex-shrink-0" />}
                    <span>{item.question}</span>
                  </span>
                  <span className="text-ink-muted group-open:rotate-180 transition-transform flex-shrink-0">▾</span>
                </summary>
                <div
                  className="mt-3 text-ink-muted prose prose-sm dark:prose-invert max-w-none"
                  dangerouslySetInnerHTML={{ __html: item.answer }}
                />
                <FaqLinks links={item.links} />
              </details>
            ))}
          </div>
        </div>
      </section>
    );
  },
};

// Radios ocultos (uno por pestaña) que activan cada panel via CSS :has()
// (ver .puck-tabs en themes/microsites/assets/css/app.css) — sin JS. Hasta
// 8 pestañas por bloque; el primero trae `defaultChecked` nativo.
const TABS_MAX_INDEXED = 8;
function TabRadios({ tabs, groupName }) {
  return tabs.slice(0, TABS_MAX_INDEXED).map((_, i) => (
    <input
      key={i}
      type="radio"
      name={groupName}
      id={`${groupName}-${i}`}
      defaultChecked={i === 0}
      className={`sr-only puck-tabs-radio puck-tabs-radio-${i}`}
    />
  ));
}

const TABS_VARIANT_OPTIONS = [
  { label: 'Clásicas — subrayado', value: 'clasicas' },
  { label: 'Píldoras', value: 'pildoras' },
  { label: 'Verticales (lateral)', value: 'verticales' },
  { label: 'Tarjetas', value: 'tarjetas' },
  { label: 'Numeradas (pasos)', value: 'numeradas' },
];

export const Tabs = {
  label: 'Pestañas',
  desc: 'Pestañas con contenido HTML, 100% CSS (sin JS). Cada pestaña tiene ícono opcional, etiqueta y contenido. 5 variantes: subrayado clásico, píldoras, verticales, tarjetas y numeradas (pasos).',
  fields: {
    variant: { type: 'select', label: 'Variante', options: TABS_VARIANT_OPTIONS },
    title: { type: 'text', label: 'Título de sección (opcional)' },
    tabs: {
      type: 'array',
      label: 'Pestañas',
      arrayFields: {
        icon: iconField('Ícono / emoji (opcional)'),
        label: { type: 'text', label: 'Etiqueta' },
        content: { type: 'textarea', label: 'Contenido (HTML permitido)' },
      },
      getItemSummary: (item) => item.label || 'Pestaña',
      defaultItemProps: { icon: '', label: 'Pestaña', content: '<p>Contenido…</p>' },
    },
    background: { type: 'radio', label: 'Fondo de sección', options: BACKGROUND_OPTIONS_OPTIONAL },
    ...CUSTOM_COLOR_FIELDS,
  },
  defaultProps: {
    title: '',
    tabs: [
      { icon: '', label: 'Descripción', content: '<p>Contenido de la primera pestaña.</p>' },
      { icon: '', label: 'Detalles', content: '<p>Contenido de la segunda pestaña.</p>' },
    ],
    variant: 'clasicas',
    background: '',
    ...CUSTOM_COLOR_DEFAULTS,
  },
  render: ({ title, tabs, variant, background, customBgColor, textColor, customTextColor }) => {
    const { className: styleClass, style: colorStyle } = resolveSectionStyle(background, '', {
      customBgColor,
      textColor,
      customTextColor,
    });
    const textOverride = isValidHex(customTextColor);
    const groupName = 'tabs-' + shortHash(tabs.map((t) => t.label).join('|'));
    const headTitle = title && (
      <h2 className={`font-heading2 text-3xl font-bold mb-8 text-center ${textOverride ? '' : 'text-ink'}`}>{title}</h2>
    );

    // ---- pildoras: nav en píldoras redondeadas -----------------------
    if (variant === 'pildoras') {
      return (
        <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
          <div className="max-w-4xl mx-auto puck-tabs" data-variant="pildoras">
            <TabRadios tabs={tabs} groupName={groupName} />
            {headTitle}
            <div className="flex flex-wrap gap-2 mb-8 justify-center">
              {tabs.map((tab, i) => (
                <label
                  key={i}
                  htmlFor={`${groupName}-${i}`}
                  className={`puck-tabs-label puck-tabs-label-${i} inline-flex items-center gap-2 cursor-pointer px-5 py-2 rounded-full text-sm font-semibold border border-surface-border text-ink-muted`}
                >
                  {tab.icon && <PickedIcon icon={tab.icon} size={16} className="flex-shrink-0" />}
                  <span>{tab.label}</span>
                </label>
              ))}
            </div>
            {tabs.map((tab, i) => (
              <div
                key={i}
                className={`puck-tabs-panel puck-tabs-panel-${i} text-ink-muted prose prose-lg dark:prose-invert max-w-none`}
                dangerouslySetInnerHTML={{ __html: tab.content }}
              />
            ))}
          </div>
        </section>
      );
    }

    // ---- verticales: nav lateral + contenido a la derecha ------------
    if (variant === 'verticales') {
      return (
        <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
          <div className="max-w-4xl mx-auto puck-tabs" data-variant="verticales">
            <TabRadios tabs={tabs} groupName={groupName} />
            {headTitle}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
              <div className="flex md:flex-col gap-2 md:col-span-1">
                {tabs.map((tab, i) => (
                  <label
                    key={i}
                    htmlFor={`${groupName}-${i}`}
                    className={`puck-tabs-label puck-tabs-label-${i} flex items-center gap-3 cursor-pointer px-4 py-3 rounded-xl border border-transparent text-ink-muted font-semibold`}
                  >
                    {tab.icon && <PickedIcon icon={tab.icon} size={18} className="flex-shrink-0" />}
                    <span>{tab.label}</span>
                  </label>
                ))}
              </div>
              <div className="md:col-span-3">
                {tabs.map((tab, i) => (
                  <div
                    key={i}
                    className={`puck-tabs-panel puck-tabs-panel-${i} text-ink-muted prose prose-lg dark:prose-invert max-w-none`}
                    dangerouslySetInnerHTML={{ __html: tab.content }}
                  />
                ))}
              </div>
            </div>
          </div>
        </section>
      );
    }

    // ---- tarjetas: nav como mini-tarjetas + panel debajo --------------
    if (variant === 'tarjetas') {
      return (
        <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
          <div className="max-w-4xl mx-auto puck-tabs" data-variant="tarjetas">
            <TabRadios tabs={tabs} groupName={groupName} />
            {headTitle}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
              {tabs.map((tab, i) => (
                <label
                  key={i}
                  htmlFor={`${groupName}-${i}`}
                  className={`puck-tabs-label puck-tabs-label-${i} cursor-pointer flex flex-col items-center gap-2 text-center p-4 rounded-2xl border-2 border-surface-border bg-surface-alt`}
                >
                  {tab.icon && <PickedIcon icon={tab.icon} size={24} className="flex-shrink-0" />}
                  <span className="text-sm font-semibold">{tab.label}</span>
                </label>
              ))}
            </div>
            <div className="bg-surface-alt border border-surface-border rounded-2xl p-8">
              {tabs.map((tab, i) => (
                <div
                  key={i}
                  className={`puck-tabs-panel puck-tabs-panel-${i} text-ink-muted prose prose-lg dark:prose-invert max-w-none`}
                  dangerouslySetInnerHTML={{ __html: tab.content }}
                />
              ))}
            </div>
          </div>
        </section>
      );
    }

    // ---- numeradas: nav como pasos numerados ---------------------------
    if (variant === 'numeradas') {
      return (
        <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
          <div className="max-w-4xl mx-auto puck-tabs" data-variant="numeradas">
            <TabRadios tabs={tabs} groupName={groupName} />
            {headTitle}
            <div className="flex flex-wrap justify-center gap-6 mb-10">
              {tabs.map((tab, i) => (
                <label
                  key={i}
                  htmlFor={`${groupName}-${i}`}
                  className={`puck-tabs-label puck-tabs-label-${i} cursor-pointer flex flex-col items-center gap-2 text-center`}
                >
                  <span className="w-10 h-10 rounded-full bg-surface-alt border-2 border-surface-border text-ink-muted font-bold flex items-center justify-center">
                    {i + 1}
                  </span>
                  <span className="text-sm font-semibold text-ink-muted">{tab.label}</span>
                </label>
              ))}
            </div>
            <div className="max-w-3xl mx-auto bg-surface-alt border border-surface-border rounded-2xl p-8">
              {tabs.map((tab, i) => (
                <div
                  key={i}
                  className={`puck-tabs-panel puck-tabs-panel-${i} text-ink-muted prose prose-lg dark:prose-invert max-w-none`}
                  dangerouslySetInnerHTML={{ __html: tab.content }}
                />
              ))}
            </div>
          </div>
        </section>
      );
    }

    // ---- clasicas: nav subrayada (default) ------------------------------
    return (
      <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
        <div className="max-w-4xl mx-auto puck-tabs" data-variant="clasicas">
          <TabRadios tabs={tabs} groupName={groupName} />
          {headTitle}
          <div className="border-b border-surface-border mb-6">
            <div className="flex flex-wrap gap-2">
              {tabs.map((tab, i) => (
                <label
                  key={i}
                  htmlFor={`${groupName}-${i}`}
                  className={`puck-tabs-label puck-tabs-label-${i} inline-flex items-center gap-2 cursor-pointer px-4 py-2 text-sm font-semibold text-ink-muted border-b-2 border-transparent`}
                >
                  {tab.icon && <PickedIcon icon={tab.icon} size={16} className="flex-shrink-0" />}
                  <span>{tab.label}</span>
                </label>
              ))}
            </div>
          </div>
          {tabs.map((tab, i) => (
            <div
              key={i}
              className={`puck-tabs-panel puck-tabs-panel-${i} text-ink-muted prose prose-lg dark:prose-invert max-w-none`}
              dangerouslySetInnerHTML={{ __html: tab.content }}
            />
          ))}
        </div>
      </section>
    );
  },
};

export const Testimonials = {
  label: 'Testimonios',
  desc: 'Sección de testimonios con citas de clientes. Cada uno tiene cita entrecomillada, autor y rol/empresa.',
  fields: {
    title: { type: 'text', label: 'Título de sección (opcional)' },
    testimonials: {
      type: 'array',
      label: 'Testimonios',
      arrayFields: {
        quote: { type: 'textarea', label: 'Cita' },
        author: { type: 'text', label: 'Autor' },
        role: { type: 'text', label: 'Cargo / Empresa' },
      },
      getItemSummary: (item) => item.author || 'Testimonio',
      defaultItemProps: {
        quote: 'Excelente servicio, totalmente recomendado.',
        author: 'Nombre',
        role: 'Cliente',
      },
    },
    background: { type: 'radio', label: 'Fondo de sección', options: BACKGROUND_OPTIONS_OPTIONAL },
    ...CUSTOM_COLOR_FIELDS,
  },
  defaultProps: {
    title: 'Lo que dicen nuestros clientes',
    testimonials: [
      { quote: 'Excelente servicio, totalmente recomendado.', author: 'Ana G.', role: 'Cliente' },
      { quote: 'Muy profesionales y atentos al detalle.', author: 'Carlos M.', role: 'Empresario' },
    ],
    background: '',
    ...CUSTOM_COLOR_DEFAULTS,
  },
  render: ({ title, testimonials, background, customBgColor, textColor, customTextColor }) => {
    const { className: styleClass, style: colorStyle } = resolveSectionStyle(background, '', {
      customBgColor,
      textColor,
      customTextColor,
    });
    const textOverride = isValidHex(customTextColor);
    return (
    <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
      <div className="max-w-6xl mx-auto">
        {title && (
          <h2 className={`font-heading2 text-3xl font-bold text-center mb-12 ${textOverride ? '' : 'text-ink'}`}>
            {title}
          </h2>
        )}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
          {testimonials.map((t, i) => (
            <blockquote key={i} className="bg-surface-alt p-8 rounded-2xl shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg">
              <p className="text-ink-muted text-lg leading-relaxed mb-6">“{t.quote}”</p>
              <footer>
                <div className="font-bold text-ink">{t.author}</div>
                <div className="text-ink-muted text-sm">{t.role}</div>
              </footer>
            </blockquote>
          ))}
        </div>
      </div>
    </section>
    );
  },
};

const GALLERY_VARIANT_OPTIONS = [
  { label: 'Grid uniforme (clásico)', value: 'grid-uniforme' },
  { label: 'Masonry (alturas variables)', value: 'masonry' },
  { label: 'Carrusel horizontal', value: 'carrusel' },
  { label: 'Lightbox (click para ampliar)', value: 'lightbox' },
  { label: 'Editorial (alternada)', value: 'editorial-alterno' },
];

export const Gallery = {
  label: 'Galería',
  desc: 'Galería de imágenes con texto alternativo y leyenda opcional. 5 variantes: grid uniforme, masonry, carrusel horizontal, lightbox (ampliar con click, sin JS) y editorial alternada.',
  fields: {
    variant: { type: 'select', label: 'Variante', options: GALLERY_VARIANT_OPTIONS },
    title: { type: 'text', label: 'Título de sección (opcional)' },
    images: {
      type: 'array',
      label: 'Imágenes',
      arrayFields: {
        url: imageField('Imagen'),
        alt: { type: 'text', label: 'Texto alternativo' },
        caption: { type: 'text', label: 'Leyenda (opcional)' },
      },
      getItemSummary: (item) => item.alt || 'Imagen',
      defaultItemProps: {
        url: 'https://placehold.co/600x400/e2e8f0/94a3b8?text=Imagen',
        alt: 'Imagen',
        caption: '',
      },
    },
    background: { type: 'radio', label: 'Fondo de sección', options: BACKGROUND_OPTIONS_OPTIONAL },
    ...CUSTOM_COLOR_FIELDS,
  },
  defaultProps: {
    variant: 'grid-uniforme',
    title: 'Galería',
    images: [
      { url: 'https://placehold.co/600x400/e2e8f0/94a3b8?text=1', alt: 'Imagen 1', caption: '' },
      { url: 'https://placehold.co/600x400/e2e8f0/94a3b8?text=2', alt: 'Imagen 2', caption: '' },
      { url: 'https://placehold.co/600x400/e2e8f0/94a3b8?text=3', alt: 'Imagen 3', caption: '' },
    ],
    background: '',
    ...CUSTOM_COLOR_DEFAULTS,
  },
  render: ({ title, images, variant, background, customBgColor, textColor, customTextColor }) => {
    const { className: styleClass, style: colorStyle } = resolveSectionStyle(background, '', {
      customBgColor,
      textColor,
      customTextColor,
    });
    const textOverride = isValidHex(customTextColor);
    const head = title && (
      <h2 className={`font-heading2 text-3xl font-bold text-center mb-12 ${textOverride ? '' : 'text-ink'}`}>{title}</h2>
    );
    const groupName = 'gallery-' + shortHash(images.map((im) => im.url).join('|'));

    // ---- masonry: columnas CSS, altura natural por imagen -----------------
    if (variant === 'masonry') {
      return (
        <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
          <div className="max-w-6xl mx-auto">
            {head}
            <div className="columns-2 md:columns-3 gap-4">
              {images.map((img, i) => (
                <div key={i} className="mb-4 break-inside-avoid relative group">
                  <img src={img.url} alt={img.alt} className="w-full rounded-xl object-cover" />
                  {img.caption && (
                    <div className="absolute inset-0 flex items-end rounded-xl bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity">
                      <span className="text-white text-sm p-3">{img.caption}</span>
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>
        </section>
      );
    }

    // ---- carrusel: scroll horizontal con snap, sin JS ----------------------
    if (variant === 'carrusel') {
      return (
        <section className={`reveal py-16 ${styleClass}`} style={colorStyle}>
          <div className="max-w-6xl mx-auto px-4">{head}</div>
          <div className="flex gap-4 overflow-x-auto snap-x snap-mandatory px-4 pb-2">
            {images.map((img, i) => (
              <div key={i} className="snap-center flex-shrink-0 w-72 md:w-80">
                <img src={img.url} alt={img.alt} className="w-full aspect-square rounded-xl object-cover" />
                {img.caption && <p className={`text-sm mt-2 ${textOverride ? '' : 'text-ink-muted'}`}>{img.caption}</p>}
              </div>
            ))}
          </div>
        </section>
      );
    }

    // ---- lightbox: click para ampliar (CSS puro, :target) ------------------
    if (variant === 'lightbox') {
      return (
        <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
          <div className="max-w-6xl mx-auto">
            {head}
            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
              {images.map((img, i) => (
                <a key={i} href={`#${groupName}-${i}`} className="block cursor-pointer">
                  <img src={img.url} alt={img.alt} className="w-full aspect-square rounded-xl object-cover hover:opacity-90 transition-opacity" />
                </a>
              ))}
            </div>
            {images.map((img, i) => (
              <div key={i} id={`${groupName}-${i}`} className="puck-lightbox fixed inset-0 z-50 items-center justify-center bg-black/90 p-4">
                <a href="#" className="absolute inset-0" aria-label="Cerrar"></a>
                <div className="relative max-w-3xl w-full">
                  <img src={img.url} alt={img.alt} className="w-full rounded-xl" />
                  {img.caption && <p className="text-white text-center mt-3">{img.caption}</p>}
                </div>
              </div>
            ))}
          </div>
        </section>
      );
    }

    // ---- editorial-alterno: 2 columnas, imagen grande cada 3 --------------
    if (variant === 'editorial-alterno') {
      return (
        <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
          <div className="max-w-5xl mx-auto">
            {head}
            <div className="grid grid-cols-2 gap-4">
              {images.map((img, i) => (
                <div key={i} className={`relative group ${i % 3 === 0 ? 'col-span-2' : ''}`}>
                  <img
                    src={img.url}
                    alt={img.alt}
                    className={`w-full rounded-xl object-cover ${i % 3 === 0 ? 'aspect-video' : 'aspect-square'}`}
                  />
                  {img.caption && (
                    <div className="absolute inset-0 flex items-end rounded-xl bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity">
                      <span className="text-white text-sm p-3">{img.caption}</span>
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>
        </section>
      );
    }

    // ---- grid-uniforme: layout original (default) --------------------------
    return (
      <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
        <div className="max-w-6xl mx-auto">
          {head}
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            {images.map((img, i) => (
              <img key={i} src={img.url} alt={img.alt} className="w-full rounded-xl object-cover" />
            ))}
          </div>
        </div>
      </section>
    );
  },
};

export const Video = {
  label: 'Video',
  desc: 'Video embebido desde YouTube o Vimeo con pie de texto opcional.',
  fields: {
    url: { type: 'text', label: 'URL de YouTube o Vimeo' },
    caption: { type: 'text', label: 'Pie (opcional)' },
  },
  defaultProps: { url: '', caption: '' },
  render: ({ url, caption }) => {
    const embed = (() => {
      if (!url) return null;
      const yt = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([A-Za-z0-9_-]{6,})/);
      if (yt) return `https://www.youtube.com/embed/${yt[1]}`;
      const vm = url.match(/vimeo\.com\/(\d+)/);
      if (vm) return `https://player.vimeo.com/video/${vm[1]}`;
      return url;
    })();
    return (
      <section className="reveal py-16 px-4">
        <div className="max-w-4xl mx-auto">
          {embed ? (
            <div className="rounded-2xl overflow-hidden">
              <div className="w-full aspect-video">
                <iframe
                  src={embed}
                  title={caption || 'Video'}
                  className="w-full h-full"
                  frameBorder="0"
                  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                  allowFullScreen
                />
              </div>
            </div>
          ) : (
            <div className="w-full rounded-2xl bg-surface-alt text-ink-muted text-center py-24">
              Añade la URL de un video de YouTube o Vimeo
            </div>
          )}
          {caption && <p className="text-center text-ink-muted text-sm mt-3 italic">{caption}</p>}
        </div>
      </section>
    );
  },
};

export const LogoCloud = {
  label: 'Logos (Marquee)',
  desc: 'Fila horizontal de logos de marcas/partners. Título de sección opcional.',
  fields: {
    title: { type: 'text', label: 'Título de sección (opcional)' },
    logos: {
      type: 'array',
      label: 'Logos',
      arrayFields: {
        url: imageField('Logo'),
        alt: { type: 'text', label: 'Nombre' },
      },
      getItemSummary: (item) => item.alt || 'Logo',
      defaultItemProps: {
        url: 'https://placehold.co/160x60/e2e8f0/94a3b8?text=Logo',
        alt: 'Logo',
      },
    },
  },
  defaultProps: {
    title: 'Confían en nosotros',
    logos: [
      { url: 'https://placehold.co/160x60/e2e8f0/94a3b8?text=A', alt: 'Marca A' },
      { url: 'https://placehold.co/160x60/e2e8f0/94a3b8?text=B', alt: 'Marca B' },
      { url: 'https://placehold.co/160x60/e2e8f0/94a3b8?text=C', alt: 'Marca C' },
      { url: 'https://placehold.co/160x60/e2e8f0/94a3b8?text=D', alt: 'Marca D' },
    ],
  },
  render: ({ title, logos }) => (
    <section className="reveal py-16 px-4">
      <div className="max-w-6xl mx-auto">
        {title && <h2 className="font-heading2 text-2xl font-bold text-center mb-10 text-ink">{title}</h2>}
        <div className="flex flex-wrap items-center justify-center gap-8">
          {logos.map((logo, i) => (
            <img key={i} src={logo.url} alt={logo.alt} className="h-12 w-auto opacity-75" />
          ))}
        </div>
      </div>
    </section>
  ),
};

const STATS_VARIANT_OPTIONS = [
  { label: '3 columnas (clásico)', value: 'tres-columnas' },
  { label: 'Con íconos', value: 'con-iconos' },
  { label: 'Franja destacada', value: 'franja-destacada' },
  { label: 'Contador destacado', value: 'contador-destacado' },
  { label: 'Tarjetas elevadas', value: 'tarjetas-elevadas' },
];

export const Stats = {
  label: 'Estadísticas',
  desc: 'Sección con números/estadísticas, ícono y descripción opcionales por cada una. 5 variantes: 3 columnas clásico, con íconos, franja destacada de alto contraste, un contador principal destacado, y tarjetas elevadas.',
  fields: {
    variant: { type: 'select', label: 'Variante', options: STATS_VARIANT_OPTIONS },
    title: { type: 'text', label: 'Título de sección (opcional)' },
    stats: {
      type: 'array',
      label: 'Estadísticas',
      arrayFields: {
        icon: iconField('Ícono / emoji (opcional)'),
        value: { type: 'text', label: 'Valor (ej. +500)' },
        label: { type: 'text', label: 'Etiqueta' },
        description: { type: 'text', label: 'Descripción breve (opcional)' },
      },
      getItemSummary: (item) => item.label || 'Estadística',
      defaultItemProps: { icon: '', value: '100+', label: 'Clientes', description: '' },
    },
    background: { type: 'radio', label: 'Fondo', options: BACKGROUND_OPTIONS },
    ...CUSTOM_COLOR_FIELDS,
  },
  defaultProps: {
    variant: 'tres-columnas',
    title: '',
    stats: [
      { icon: 'tabler:users', value: '+500', label: 'Clientes', description: '' },
      { icon: 'tabler:calendar', value: '10', label: 'Años de experiencia', description: '' },
      { icon: 'tabler:headset', value: '24/7', label: 'Soporte', description: '' },
    ],
    background: 'surface',
    ...CUSTOM_COLOR_DEFAULTS,
  },
  render: ({ title, stats, variant, background, customBgColor, textColor, customTextColor }) => {
    const autoText = background === 'brand' ? 'text-white' : 'text-ink';
    const { className: styleClass, style: colorStyle } = resolveSectionStyle(background, autoText, {
      customBgColor,
      textColor,
      customTextColor,
    });
    const head = title && <h2 className="font-heading2 text-3xl font-bold text-center mb-12">{title}</h2>;

    // ---- con-iconos: ícono arriba de cada número --------------------------
    if (variant === 'con-iconos') {
      return (
        <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
          <div className="max-w-6xl mx-auto">
            {head}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-8 text-center">
              {stats.map((s, i) => (
                <div key={i}>
                  {s.icon && <PickedIcon icon={s.icon} size={32} className="mx-auto mb-3" />}
                  <div className="text-5xl font-bold mb-2">{s.value}</div>
                  <div className="text-lg opacity-90">{s.label}</div>
                  {s.description && <div className="text-sm opacity-75 mt-1">{s.description}</div>}
                </div>
              ))}
            </div>
          </div>
        </section>
      );
    }

    // ---- franja-destacada: franja sólida de marca, divisores verticales ---
    if (variant === 'franja-destacada') {
      return (
        <section className="reveal py-16 px-4 bg-brand-primary text-white">
          <div className="max-w-6xl mx-auto">
            {title && <h2 className="font-heading2 text-3xl font-bold text-center mb-12">{title}</h2>}
            <div className="grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-white/20 text-center">
              {stats.map((s, i) => (
                <div key={i} className="py-4 sm:py-0">
                  <div className="text-5xl font-bold mb-2">{s.value}</div>
                  <div className="text-lg opacity-90">{s.label}</div>
                  {s.description && <div className="text-sm opacity-75 mt-1">{s.description}</div>}
                </div>
              ))}
            </div>
          </div>
        </section>
      );
    }

    // ---- contador-destacado: un stat grande + el resto más chico ----------
    if (variant === 'contador-destacado') {
      const [first, ...rest] = stats;
      return (
        <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
          <div className="max-w-5xl mx-auto text-center">
            {head}
            {first && (
              <div className="mb-10">
                {first.icon && <PickedIcon icon={first.icon} size={40} className="mx-auto mb-3 text-brand-primary" />}
                <div className="text-7xl font-bold mb-2 text-brand-primary">{first.value}</div>
                <div className="text-xl opacity-90">{first.label}</div>
                {first.description && <div className="text-sm opacity-75 mt-1">{first.description}</div>}
              </div>
            )}
            {rest.length > 0 && (
              <div className="grid grid-cols-2 sm:grid-cols-3 gap-8 pt-8 border-t border-surface-border">
                {rest.map((s, i) => (
                  <div key={i}>
                    <div className="text-3xl font-bold mb-1">{s.value}</div>
                    <div className="text-sm opacity-75">{s.label}</div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </section>
      );
    }

    // ---- tarjetas-elevadas: cada estadística en su propia tarjeta ---------
    if (variant === 'tarjetas-elevadas') {
      return (
        <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
          <div className="max-w-6xl mx-auto">
            {head}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
              {stats.map((s, i) => (
                <div key={i} className="bg-surface-alt border border-surface-border rounded-2xl shadow-md p-8 text-center">
                  {s.icon && <PickedIcon icon={s.icon} size={32} className="mx-auto mb-3 text-brand-primary" />}
                  <div className="text-4xl font-bold mb-2 text-brand-primary">{s.value}</div>
                  <div className="text-lg font-semibold">{s.label}</div>
                  {s.description && <div className="text-sm opacity-75 mt-1">{s.description}</div>}
                </div>
              ))}
            </div>
          </div>
        </section>
      );
    }

    // ---- tres-columnas: layout original (default) --------------------------
    return (
    <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
      <div className="max-w-6xl mx-auto">
        {head}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-8 text-center">
          {stats.map((s, i) => (
            <div key={i}>
              <div className="text-5xl font-bold mb-2">{s.value}</div>
              <div className="text-lg opacity-90">{s.label}</div>
            </div>
          ))}
        </div>
      </div>
    </section>
    );
  },
};

export const Rating = {
  label: 'Valoración',
  desc: 'Estrellas de valoración (1-5) con texto opcional. Centrado.',
  fields: {
    score: {
      type: 'select',
      label: 'Estrellas',
      options: [
        { label: '1', value: '1' },
        { label: '2', value: '2' },
        { label: '3', value: '3' },
        { label: '4', value: '4' },
        { label: '5', value: '5' },
      ],
    },
    text: { type: 'text', label: 'Texto (opcional)' },
  },
  defaultProps: { score: '5', text: '' },
  render: ({ score, text }) => {
    const n = parseInt(score, 10) || 5;
    return (
      <div className="py-8 px-4 text-center">
        <div className="text-3xl mb-2">
          <span className="text-brand-accent">{'★'.repeat(n)}</span>
          <span className="text-surface-border">{'★'.repeat(Math.max(0, 5 - n))}</span>
        </div>
        {text && <p className="text-ink-muted">{text}</p>}
      </div>
    );
  },
};

// ---------------------------------------------------------------------------
// Config exportada
// ---------------------------------------------------------------------------

export const components = {
  Grid,
  Flex,
  Space,
  Hero,
  TextBlock,
  FeatureGrid,
  ImageBlock,
  CTASection,
  Pricing,
  Divider,
  Banner,
  Badge,
  FAQ,
  Tabs,
  Testimonials,
  Gallery,
  Video,
  LogoCloud,
  Stats,
  Rating,
};

export const categories = {
  layout: {
    title: 'Layout',
    components: ['Grid', 'Flex', 'Space'],
  },
  sections: {
    title: 'Secciones',
    components: ['Hero', 'CTASection', 'Pricing', 'Banner', 'FeatureGrid', 'Stats', 'Divider'],
  },
  content: {
    title: 'Contenido',
    components: ['TextBlock', 'ImageBlock', 'Video', 'Gallery', 'Badge'],
  },
  social: {
    title: 'Prueba social',
    components: ['Testimonials', 'LogoCloud', 'Rating'],
  },
  interactive: {
    title: 'Interactivo',
    components: ['FAQ', 'Tabs'],
  },
};
