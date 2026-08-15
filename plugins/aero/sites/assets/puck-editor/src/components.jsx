import React from 'react';

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
// solos a claro/oscuro), "brand" rellena con el color de marca sólido para
// cuando se quiere un bloque de alto contraste puntual, "custom" abre un color
// arbitrario (hex) — opt-in, ver resolveSectionStyle.
const BACKGROUND_OPTIONS = [
  { label: 'Transparente', value: 'transparent' },
  { label: 'Superficie (neutro)', value: 'surface' },
  { label: 'Color de marca (sólido)', value: 'brand' },
  { label: 'Personalizado', value: 'custom' },
];

// Igual que BACKGROUND_OPTIONS pero con una opción inicial "sin cambios" para
// bloques que hoy no tienen ningún control de fondo — el default sigue
// siendo el aspecto actual del bloque (opt-in real, no un default nuevo).
const BACKGROUND_OPTIONS_OPTIONAL = [
  { label: 'Sin cambios (usar el estilo por defecto)', value: '' },
  ...BACKGROUND_OPTIONS,
];

const TEXT_COLOR_OPTIONS = [
  { label: 'Automático', value: 'auto' },
  { label: 'Claro', value: 'light' },
  { label: 'Oscuro', value: 'dark' },
  { label: 'Personalizado', value: 'custom' },
];

// Campos reutilizables de personalización opt-in (fondo custom + color de
// texto). Se agregan junto al campo `background` propio de cada bloque.
const CUSTOM_COLOR_FIELDS = {
  customBgColor: { type: 'text', label: 'Fondo personalizado (hex, ej: #1a2b3c)' },
  textColor: { type: 'radio', label: 'Color de texto', options: TEXT_COLOR_OPTIONS },
  customTextColor: { type: 'text', label: 'Texto personalizado (hex, ej: #ffffff)' },
};

const CUSTOM_COLOR_DEFAULTS = {
  customBgColor: '',
  textColor: 'auto',
  customTextColor: '',
};

function isValidHex(hex) {
  return typeof hex === 'string' && /^#[0-9a-fA-F]{6}$/.test(hex);
}

// Resuelve fondo/texto de una sección: clases Tailwind fijas (comportamiento
// idéntico al actual) salvo que el preset sea "custom" con un hex válido, en
// cuyo caso se devuelve `style` inline. `autoTextClass` es la clase de texto
// que ya se usaba por defecto para ese fondo — se mantiene si `textColor`
// sigue en 'auto' (o vacío), para no alterar nada cuando no hay opt-in.
function resolveSectionStyle(background, autoTextClass, { customBgColor, textColor, customTextColor } = {}) {
  const classes = [];
  const style = {};

  if (background === 'custom') {
    if (isValidHex(customBgColor)) style.backgroundColor = customBgColor;
  } else if (background === 'brand') {
    classes.push('bg-brand-primary-dark');
  } else if (background === 'surface') {
    classes.push('bg-surface-alt');
  }

  if (textColor === 'custom' && isValidHex(customTextColor)) {
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

export const Hero = {
  label: 'Hero',
  desc: 'Sección principal (hero) con título grande, subtítulo y botón CTA. Por defecto usa un fondo neutro/transparente; el color de marca queda como acento del botón (o se puede elegir sólido para más impacto). Opcionalmente puede llevar una foto de fondo.',
  fields: {
    title: { type: 'text', label: 'Título principal' },
    subtitle: { type: 'textarea', label: 'Subtítulo' },
    ctaLabel: { type: 'text', label: 'Botón: texto' },
    ctaUrl: { type: 'text', label: 'Botón: URL' },
    bgImage: { type: 'text', label: 'Imagen de fondo (URL, opcional)' },
    background: { type: 'radio', label: 'Fondo', options: BACKGROUND_OPTIONS },
    ...CUSTOM_COLOR_FIELDS,
  },
  defaultProps: {
    title: 'Bienvenido a nuestro sitio',
    subtitle: 'Descubre todo lo que tenemos para ofrecerte.',
    ctaLabel: 'Contáctanos',
    ctaUrl: '/contacto',
    bgImage: '',
    background: 'surface',
    ...CUSTOM_COLOR_DEFAULTS,
  },
  render: ({ title, subtitle, ctaLabel, ctaUrl, bgImage, background, customBgColor, textColor, customTextColor }) => {
    const autoText = background === 'brand' || !!bgImage ? 'text-white' : 'text-ink';
    const { className: styleClass, style: colorStyle } = resolveSectionStyle(background, autoText, {
      customBgColor,
      textColor,
      customTextColor,
    });
    return (
    <section
      className={`reveal relative ${styleClass} py-24 px-4 text-center bg-cover bg-center`}
      style={{ ...(bgImage ? { backgroundImage: `url(${bgImage})` } : {}), ...colorStyle }}
    >
      {bgImage && <div className="absolute inset-0 bg-black/50" />}
      <div className="relative max-w-4xl mx-auto">
        <h1 className="font-heading text-4xl md:text-6xl font-bold mb-6 leading-tight">{title}</h1>
        <p className="text-xl md:text-2xl mb-10 opacity-90 leading-relaxed">{subtitle}</p>
        {ctaLabel && ctaUrl && (
          <a
            href={ctaUrl}
            className={`inline-block font-semibold px-8 py-4 rounded-brand hover:opacity-90 transition-opacity ${heroButtonClasses(background)}`}
          >
            {ctaLabel}
          </a>
        )}
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
        { label: 'Personalizado', value: 'custom' },
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
    const textOverride = textColor && textColor !== 'auto';
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

export const FeatureGrid = {
  label: 'Características',
  desc: 'Grid de características (2, 3 o 4 columnas). Cada una tiene ícono (emoji), título y descripción.',
  fields: {
    title: { type: 'text', label: 'Título de sección (opcional)' },
    features: {
      type: 'array',
      label: 'Características',
      arrayFields: {
        icon: { type: 'text', label: 'Ícono (emoji)' },
        title: { type: 'text', label: 'Título' },
        description: { type: 'textarea', label: 'Descripción' },
      },
      getItemSummary: (item) => item.title || 'Característica',
      defaultItemProps: {
        icon: '⭐',
        title: 'Nueva característica',
        description: 'Descripción del beneficio.',
      },
    },
    columns: {
      type: 'select',
      label: 'Columnas',
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
    title: '',
    features: [
      { icon: '⭐', title: 'Característica 1', description: 'Descripción del primer beneficio.' },
      { icon: '🚀', title: 'Característica 2', description: 'Descripción del segundo beneficio.' },
      { icon: '💡', title: 'Característica 3', description: 'Descripción del tercer beneficio.' },
    ],
    columns: '3',
    background: '',
    ...CUSTOM_COLOR_DEFAULTS,
  },
  render: ({ title, features, columns, background, customBgColor, textColor, customTextColor }) => {
    const colClass =
      { '2': 'md:grid-cols-2', '3': 'md:grid-cols-3', '4': 'md:grid-cols-4' }[columns] ||
      'md:grid-cols-3';
    const { className: styleClass, style: colorStyle } = resolveSectionStyle(background, '', {
      customBgColor,
      textColor,
      customTextColor,
    });
    const textOverride = textColor && textColor !== 'auto';
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
                <div className="text-5xl mb-4">{feature.icon}</div>
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
    imageUrl: { type: 'text', label: 'URL de la imagen' },
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

export const CTASection = {
  label: 'Llamado a la acción',
  desc: 'Sección de llamado a la acción con título, descripción y botón. El color lo define el tema visual; "style" solo controla si es sólido o de contorno.',
  fields: {
    heading: { type: 'text', label: 'Título' },
    body: { type: 'textarea', label: 'Descripción' },
    buttonLabel: { type: 'text', label: 'Texto del botón' },
    buttonUrl: { type: 'text', label: 'URL del botón' },
    style: {
      type: 'radio',
      label: 'Estilo',
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
    body: 'Contáctanos hoy y descubre cómo podemos ayudarte.',
    buttonLabel: 'Comenzar ahora',
    buttonUrl: '/contacto',
    style: 'solid',
    background: '',
    ...CUSTOM_COLOR_DEFAULTS,
  },
  render: ({ heading, body, buttonLabel, buttonUrl, style, background, customBgColor, textColor, customTextColor }) => {
    const solid = style !== 'outline';
    const button = solid
      ? 'bg-white text-brand-primary'
      : 'bg-brand-primary text-white';

    let section;
    let colorStyle = {};
    if (background) {
      const autoText = solid ? 'text-white' : 'text-brand-text';
      const resolved = resolveSectionStyle(background, autoText, { customBgColor, textColor, customTextColor });
      section = resolved.className + (!solid ? ' border-2 border-brand-primary' : '');
      colorStyle = resolved.style;
    } else if (textColor && textColor !== 'auto') {
      const autoText = solid ? 'text-white' : 'text-brand-text';
      const resolved = resolveSectionStyle('', autoText, { textColor, customTextColor });
      section = (solid ? 'bg-brand-primary' : 'bg-brand-bg border-2 border-brand-primary') + ' ' + resolved.className;
      colorStyle = resolved.style;
    } else {
      section = solid
        ? 'bg-brand-primary text-white'
        : 'bg-brand-bg text-brand-text border-2 border-brand-primary';
    }

    return (
      <section className={`reveal ${section} py-20 px-4 text-center`} style={colorStyle}>
        <div className="max-w-2xl mx-auto">
          <h2 className="font-heading2 text-3xl font-bold mb-4">{heading}</h2>
          <p className="text-lg mb-10 opacity-90 leading-relaxed">{body}</p>
          {buttonLabel && buttonUrl && (
            <a
              href={buttonUrl}
              className={`inline-block font-semibold px-8 py-4 rounded-brand transition-opacity hover:opacity-90 ${button}`}
            >
              {buttonLabel}
            </a>
          )}
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

export const FAQ = {
  label: 'FAQ (Acordeón)',
  desc: 'Acordeón de preguntas frecuentes con título de sección. Cada ítem tiene pregunta y respuesta en HTML.',
  fields: {
    title: { type: 'text', label: 'Título de sección (opcional)' },
    items: {
      type: 'array',
      label: 'Preguntas',
      arrayFields: {
        question: { type: 'text', label: 'Pregunta' },
        answer: { type: 'textarea', label: 'Respuesta (HTML permitido)' },
      },
      getItemSummary: (item) => item.question || 'Pregunta',
      defaultItemProps: {
        question: '¿Nueva pregunta?',
        answer: '<p>Escribe la respuesta aquí.</p>',
      },
    },
    background: { type: 'radio', label: 'Fondo de sección', options: BACKGROUND_OPTIONS_OPTIONAL },
    ...CUSTOM_COLOR_FIELDS,
  },
  defaultProps: {
    title: 'Preguntas frecuentes',
    items: [
      { question: '¿Cómo funciona el servicio?', answer: '<p>Explicación breve de la respuesta.</p>' },
      { question: '¿Cuáles son los precios?', answer: '<p>Detalle de precios o planes.</p>' },
    ],
    background: '',
    ...CUSTOM_COLOR_DEFAULTS,
  },
  render: ({ title, items, background, customBgColor, textColor, customTextColor }) => {
    const { className: styleClass, style: colorStyle } = resolveSectionStyle(background, '', {
      customBgColor,
      textColor,
      customTextColor,
    });
    const textOverride = textColor && textColor !== 'auto';
    return (
    <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
      <div className="max-w-3xl mx-auto">
        {title && (
          <h2 className={`font-heading2 text-3xl font-bold mb-10 text-center ${textOverride ? '' : 'text-ink'}`}>
            {title}
          </h2>
        )}
        <div className="space-y-3">
          {items.map((item, i) => (
            <details
              key={i}
              className="group bg-surface-alt rounded-xl border border-surface-border px-6 py-4"
            >
              <summary className="flex items-center justify-between cursor-pointer font-semibold text-ink list-none">
                <span>{item.question}</span>
                <span className="text-ink-muted group-open:rotate-180 transition-transform">▾</span>
              </summary>
              <div
                className="mt-3 text-ink-muted prose prose-sm dark:prose-invert max-w-none"
                dangerouslySetInnerHTML={{ __html: item.answer }}
              />
            </details>
          ))}
        </div>
      </div>
    </section>
    );
  },
};

export const Tabs = {
  label: 'Pestañas',
  desc: 'Pestañas con contenido HTML. Cada pestaña tiene etiqueta y contenido. Muy útil para describir servicios o productos con múltiples facetas.',
  fields: {
    tabs: {
      type: 'array',
      label: 'Pestañas',
      arrayFields: {
        label: { type: 'text', label: 'Etiqueta' },
        content: { type: 'textarea', label: 'Contenido (HTML permitido)' },
      },
      getItemSummary: (item) => item.label || 'Pestaña',
      defaultItemProps: { label: 'Pestaña', content: '<p>Contenido…</p>' },
    },
    background: { type: 'radio', label: 'Fondo de sección', options: BACKGROUND_OPTIONS_OPTIONAL },
    ...CUSTOM_COLOR_FIELDS,
  },
  defaultProps: {
    tabs: [
      { label: 'Descripción', content: '<p>Contenido de la primera pestaña.</p>' },
      { label: 'Detalles', content: '<p>Contenido de la segunda pestaña.</p>' },
    ],
    background: '',
    ...CUSTOM_COLOR_DEFAULTS,
  },
  render: ({ tabs, background, customBgColor, textColor, customTextColor }) => {
    const { className: styleClass, style: colorStyle } = resolveSectionStyle(background, '', {
      customBgColor,
      textColor,
      customTextColor,
    });
    return (
    <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
      <div className="max-w-4xl mx-auto">
        <div className="border-b border-surface-border mb-6">
          <div className="flex flex-wrap gap-2">
            {tabs.map((tab, i) => (
              <a
                key={i}
                href={`#tab-${i}`}
                className="px-4 py-2 text-sm font-semibold text-ink-muted border-b-2 border-transparent"
              >
                {tab.label}
              </a>
            ))}
          </div>
        </div>
        {tabs.map((tab, i) => (
          <div
            key={i}
            id={`tab-${i}`}
            className="text-ink-muted prose prose-lg dark:prose-invert max-w-none"
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
    const textOverride = textColor && textColor !== 'auto';
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

export const Gallery = {
  label: 'Galería',
  desc: 'Galería de imágenes en grid (1-3 columnas) con texto alternativo.',
  fields: {
    title: { type: 'text', label: 'Título de sección (opcional)' },
    images: {
      type: 'array',
      label: 'Imágenes',
      arrayFields: {
        url: { type: 'text', label: 'URL de la imagen' },
        alt: { type: 'text', label: 'Texto alternativo' },
      },
      getItemSummary: (item) => item.alt || 'Imagen',
      defaultItemProps: {
        url: 'https://placehold.co/600x400/e2e8f0/94a3b8?text=Imagen',
        alt: 'Imagen',
      },
    },
  },
  defaultProps: {
    title: 'Galería',
    images: [
      { url: 'https://placehold.co/600x400/e2e8f0/94a3b8?text=1', alt: 'Imagen 1' },
      { url: 'https://placehold.co/600x400/e2e8f0/94a3b8?text=2', alt: 'Imagen 2' },
      { url: 'https://placehold.co/600x400/e2e8f0/94a3b8?text=3', alt: 'Imagen 3' },
    ],
  },
  render: ({ title, images }) => (
    <section className="reveal py-16 px-4">
      <div className="max-w-6xl mx-auto">
        {title && <h2 className="font-heading2 text-3xl font-bold text-center mb-12 text-ink">{title}</h2>}
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
          {images.map((img, i) => (
            <img key={i} src={img.url} alt={img.alt} className="w-full rounded-xl object-cover" />
          ))}
        </div>
      </div>
    </section>
  ),
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
        url: { type: 'text', label: 'URL del logo' },
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

export const Stats = {
  label: 'Estadísticas',
  desc: 'Sección con números/estadísticas en 3 columnas. Por defecto usa un fondo neutro; se puede elegir sólido de marca para más impacto.',
  fields: {
    title: { type: 'text', label: 'Título de sección (opcional)' },
    stats: {
      type: 'array',
      label: 'Estadísticas',
      arrayFields: {
        value: { type: 'text', label: 'Valor (ej. +500)' },
        label: { type: 'text', label: 'Etiqueta' },
      },
      getItemSummary: (item) => item.label || 'Estadística',
      defaultItemProps: { value: '100+', label: 'Clientes' },
    },
    background: { type: 'radio', label: 'Fondo', options: BACKGROUND_OPTIONS },
    ...CUSTOM_COLOR_FIELDS,
  },
  defaultProps: {
    title: '',
    stats: [
      { value: '+500', label: 'Clientes' },
      { value: '10', label: 'Años de experiencia' },
      { value: '24/7', label: 'Soporte' },
    ],
    background: 'surface',
    ...CUSTOM_COLOR_DEFAULTS,
  },
  render: ({ title, stats, background, customBgColor, textColor, customTextColor }) => {
    const autoText = background === 'brand' ? 'text-white' : 'text-ink';
    const { className: styleClass, style: colorStyle } = resolveSectionStyle(background, autoText, {
      customBgColor,
      textColor,
      customTextColor,
    });
    return (
    <section className={`reveal py-16 px-4 ${styleClass}`} style={colorStyle}>
      <div className="max-w-6xl mx-auto">
        {title && <h2 className="font-heading2 text-3xl font-bold text-center mb-12">{title}</h2>}
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
    components: ['Hero', 'CTASection', 'Banner', 'FeatureGrid', 'Stats', 'Divider'],
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
