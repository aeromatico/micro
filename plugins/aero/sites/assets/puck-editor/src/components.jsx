import React from 'react';

export const Hero = {
  fields: {
    title: { type: 'text', label: 'Título principal' },
    subtitle: { type: 'textarea', label: 'Subtítulo' },
    ctaLabel: { type: 'text', label: 'Botón: texto' },
    ctaUrl: { type: 'text', label: 'Botón: URL' },
    bgColor: {
      type: 'select',
      label: 'Color de fondo',
      options: [
        { label: 'Índigo oscuro', value: 'bg-indigo-900' },
        { label: 'Azul oscuro', value: 'bg-blue-900' },
        { label: 'Gris oscuro', value: 'bg-gray-900' },
        { label: 'Verde oscuro', value: 'bg-emerald-900' },
        { label: 'Rojo oscuro', value: 'bg-rose-900' },
      ],
    },
  },
  defaultProps: {
    title: 'Bienvenido a nuestro sitio',
    subtitle: 'Descubre todo lo que tenemos para ofrecerte.',
    ctaLabel: 'Contáctanos',
    ctaUrl: '/contacto',
    bgColor: 'bg-indigo-900',
  },
  render: ({ title, subtitle, ctaLabel, ctaUrl, bgColor }) => (
    <section className={`${bgColor} text-white py-24 px-4 text-center`}>
      <div className="max-w-4xl mx-auto">
        <h1 className="text-4xl md:text-6xl font-bold mb-6 leading-tight">{title}</h1>
        <p className="text-xl md:text-2xl mb-10 opacity-90 leading-relaxed">{subtitle}</p>
        {ctaLabel && ctaUrl && (
          <a
            href={ctaUrl}
            className="inline-block bg-white text-indigo-900 font-semibold px-8 py-4 rounded-full hover:bg-indigo-50 transition-colors"
          >
            {ctaLabel}
          </a>
        )}
      </div>
    </section>
  ),
};

export const TextBlock = {
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
    bgWhite: {
      type: 'radio',
      label: 'Fondo',
      options: [
        { label: 'Blanco', value: 'white' },
        { label: 'Gris claro', value: 'gray' },
      ],
    },
  },
  defaultProps: {
    heading: '',
    content: '<p>Escribe tu contenido aquí. Puedes incluir HTML básico como <strong>negritas</strong>, <em>cursivas</em> y <a href="#">enlaces</a>.</p>',
    alignment: 'text-left',
    bgWhite: 'white',
  },
  render: ({ heading, content, alignment, bgWhite }) => (
    <section className={`py-14 px-4 ${bgWhite === 'gray' ? 'bg-gray-50' : 'bg-white'}`}>
      <div className={`max-w-4xl mx-auto ${alignment}`}>
        {heading && <h2 className="text-3xl font-bold mb-6 text-gray-900">{heading}</h2>}
        <div
          className="prose prose-lg max-w-none text-gray-700"
          dangerouslySetInnerHTML={{ __html: content }}
        />
      </div>
    </section>
  ),
};

export const FeatureGrid = {
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
  },
  defaultProps: {
    title: '',
    features: [
      { icon: '⭐', title: 'Característica 1', description: 'Descripción del primer beneficio.' },
      { icon: '🚀', title: 'Característica 2', description: 'Descripción del segundo beneficio.' },
      { icon: '💡', title: 'Característica 3', description: 'Descripción del tercer beneficio.' },
    ],
    columns: '3',
  },
  render: ({ title, features, columns }) => {
    const colClass = { '2': 'md:grid-cols-2', '3': 'md:grid-cols-3', '4': 'md:grid-cols-4' }[columns] || 'md:grid-cols-3';
    return (
      <section className="py-16 px-4 bg-gray-50">
        <div className="max-w-6xl mx-auto">
          {title && <h2 className="text-3xl font-bold text-center mb-12 text-gray-900">{title}</h2>}
          <div className={`grid grid-cols-1 ${colClass} gap-8`}>
            {features.map((feature, i) => (
              <div key={i} className="bg-white p-8 rounded-2xl shadow-sm text-center">
                <div className="text-5xl mb-4">{feature.icon}</div>
                <h3 className="text-xl font-bold mb-3 text-gray-900">{feature.title}</h3>
                <p className="text-gray-600 leading-relaxed">{feature.description}</p>
              </div>
            ))}
          </div>
        </div>
      </section>
    );
  },
};

export const ImageBlock = {
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
    <div className="py-8 px-4">
      <figure className={size === 'centered' ? 'max-w-3xl mx-auto' : 'w-full'}>
        <img src={imageUrl} alt={alt} className="w-full rounded-xl object-cover" />
        {caption && (
          <figcaption className="text-center text-gray-500 text-sm mt-3 italic">{caption}</figcaption>
        )}
      </figure>
    </div>
  ),
};

export const CTASection = {
  fields: {
    heading: { type: 'text', label: 'Título' },
    body: { type: 'textarea', label: 'Descripción' },
    buttonLabel: { type: 'text', label: 'Texto del botón' },
    buttonUrl: { type: 'text', label: 'URL del botón' },
    variant: {
      type: 'select',
      label: 'Estilo',
      options: [
        { label: 'Índigo', value: 'indigo' },
        { label: 'Oscuro', value: 'dark' },
        { label: 'Claro', value: 'light' },
      ],
    },
  },
  defaultProps: {
    heading: '¿Listo para comenzar?',
    body: 'Contáctanos hoy y descubre cómo podemos ayudarte.',
    buttonLabel: 'Comenzar ahora',
    buttonUrl: '/contacto',
    variant: 'indigo',
  },
  render: ({ heading, body, buttonLabel, buttonUrl, variant }) => {
    const styles = {
      indigo: { section: 'bg-indigo-600 text-white', button: 'bg-white text-indigo-600 hover:bg-indigo-50' },
      dark:   { section: 'bg-gray-900 text-white',   button: 'bg-white text-gray-900 hover:bg-gray-100' },
      light:  { section: 'bg-gray-50 text-gray-900', button: 'bg-indigo-600 text-white hover:bg-indigo-700' },
    };
    const s = styles[variant] || styles.indigo;
    return (
      <section className={`${s.section} py-20 px-4 text-center`}>
        <div className="max-w-2xl mx-auto">
          <h2 className="text-3xl font-bold mb-4">{heading}</h2>
          <p className="text-lg mb-10 opacity-90 leading-relaxed">{body}</p>
          {buttonLabel && buttonUrl && (
            <a
              href={buttonUrl}
              className={`inline-block font-semibold px-8 py-4 rounded-full transition-colors ${s.button}`}
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
      {showLine === 'yes' && <hr className="w-full border-gray-200" />}
    </div>
  ),
};
