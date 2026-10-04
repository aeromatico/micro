import React from 'react';
import { createRoot } from 'react-dom/client';
import { flushSync } from 'react-dom';
import { Puck, Render, Button } from '@puckeditor/core';
import '@puckeditor/core/puck.css';
import { components, categories, DynamicBlock } from './components';

// Config de Puck. El bloque «Contenido dinámico» toma sus fuentes del servidor
// (DynamicSources::forEditor). Sin fuentes, el bloque no se ofrece.
function buildConfig(sources) {
  const list = Array.isArray(sources) ? sources : [];
  if (!list.length) {
    const { DynamicBlock: _unused, ...rest } = components;
    return { components: rest, categories };
  }
  const labelOf = (value) => (list.find((s) => s.value === value) || {}).label || value;
  const dynamic = {
    ...DynamicBlock,
    fields: { ...DynamicBlock.fields, source: { ...DynamicBlock.fields.source, options: list } },
    // Las variantes son siempre 3, pero sus nombres cambian según la fuente elegida.
    resolveFields: (data, { fields }) => {
      const source = list.find((s) => s.value === data.props?.source);
      return {
        ...fields,
        variant: { ...fields.variant, options: source?.variantes || fields.variant.options },
      };
    },
    render: (props) => DynamicBlock.render({ ...props, label: labelOf(props.source) }),
  };
  return {
    components: { ...components, DynamicBlock: dynamic },
    categories: { ...categories, dynamic: { title: 'Dinámico', components: ['DynamicBlock'] } },
  };
}

let config = buildConfig([]);

function generateHtml(data) {
  const container = document.createElement('div');
  const root = createRoot(container);
  try {
    flushSync(() => {
      root.render(React.createElement(Render, { config, data }));
    });
    return container.innerHTML;
  } catch (e) {
    console.warn('[PuckEditor] HTML generation failed:', e);
    return '';
  } finally {
    root.unmount();
  }
}

function debounce(fn, delay) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), delay);
  };
}

// Puck requiere que cada bloque tenga un `props.id` único (lo asigna él mismo
// cuando se arma a mano en el editor). El JSON generado por la IA no lo trae,
// así que sin esto todos los bloques colisionan bajo el mismo id: Puck solo
// conserva el último y duplica entradas al guardar. Se aplica siempre al
// cargar, para arreglar también datos ya guardados sin id.
let idCounter = 0;
function nextId(type) {
  idCounter += 1;
  return `${type}-${Date.now().toString(36)}-${idCounter}`;
}

function normalizeIds(data) {
  const seen = new Set();
  const content = Array.isArray(data.content) ? data.content : [];

  content.forEach((block) => {
    if (!block.props) block.props = {};
    if (!block.props.id || seen.has(block.props.id)) {
      block.props.id = nextId(block.type || 'block');
    }
    seen.add(block.props.id);
  });

  return { ...data, content };
}

window.AeroPuckEditor = {
  instances: {},

  init(containerId, puckDataId, contentId, existingData, siteUrl, dynamicSources) {
    const container = document.getElementById(containerId);
    if (!container) return;

    config = buildConfig(dynamicSources);

    // Evita montar dos veces el mismo editor (ej. si el partial se vuelve a
    // ejecutar) — createRoot() sobre un contenedor ya montado duplica el render.
    if (container.dataset.puckMounted === '1') return;
    container.dataset.puckMounted = '1';

    const form = container.closest('form');

    const puckDataEl = document.getElementById(puckDataId);
    const contentEl  = document.getElementById(contentId);

    let initialData = { content: [], root: { props: {} } };
    if (existingData) {
      try {
        initialData = typeof existingData === 'string' ? JSON.parse(existingData) : existingData;
      } catch (e) {
        console.warn('[PuckEditor] Could not parse existing data:', e);
      }
    }
    initialData = normalizeIds(initialData);

    let latestData = initialData;

    const writeToDom = (data) => {
      if (puckDataEl) puckDataEl.value = JSON.stringify(data);
      if (contentEl)  contentEl.value  = generateHtml(data);
    };

    const syncData = debounce(writeToDom, 400);

    // Dispara el mismo guardado que el botón «Guardar» de October. Ese botón
    // tiene data-request="onSave" (AJAX): un form.requestSubmit() nativo no
    // guarda nada, October lo trata como una visita y re-renderiza la página.
    // La barra de acciones vive fuera del <form>, por eso se busca en el documento.
    const submitForm = () => {
      if (!form) return;
      const save = Array.from(document.querySelectorAll('[data-request="onSave"]'))
        .find((btn) => btn.getAttribute('data-tooltip-text') === 'Guardar');
      if (save) {
        save.click();
      } else if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      }
    };

    // Flush writes the most recent data synchronously, bypassing the
    // debounce. Called by the form's submit handler so a save triggered
    // right after an edit never sends stale textarea values.
    this.instances[containerId] = {
      flush: () => writeToDom(latestData),
      submit: () => {
        writeToDom(latestData);
        submitForm();
      },
    };

    createRoot(container).render(
      React.createElement(Puck, {
        config,
        data: initialData,
        onChange: (data) => {
          latestData = data;
          syncData(data);
        },
        onPublish: (data) => {
          latestData = data;
          writeToDom(data);
          submitForm();
        },
        iframe: { enabled: false },
        overrides: {
          // Puck's built-in Publish button text is hardcoded ("Publish") with
          // no label override prop, so the only way to relabel it is to
          // replace the header actions entirely. También agregamos "Ver
          // sitio" y "Pantalla completa" acá al lado.
          //
          // headerActions se invoca como componente de React (ver
          // CustomHeaderActions en @puckeditor/core), así que puede usar
          // hooks — el toggle de fullscreen necesita estado propio para
          // refrescar la etiqueta del botón.
          headerActions: () => {
            const [isFullscreen, setIsFullscreen] = React.useState(false);

            // Escape ya cierra paneles propios de Puck; acá además sale de
            // nuestro overlay de fullscreen si estaba activo.
            React.useEffect(() => {
              if (!isFullscreen) return undefined;
              const onKeyDown = (e) => {
                if (e.key === 'Escape') setIsFullscreen(false);
              };
              document.addEventListener('keydown', onKeyDown);
              return () => document.removeEventListener('keydown', onKeyDown);
            }, [isFullscreen]);

            React.useEffect(() => {
              container.classList.toggle('is-puck-fullscreen', isFullscreen);
              document.body.classList.toggle('aero-puck-fullscreen-lock', isFullscreen);
            }, [isFullscreen]);

            return (
              <>
                {siteUrl && (
                  <Button href={siteUrl} newTab variant="secondary">
                    Ver sitio
                  </Button>
                )}
                <Button variant="secondary" onClick={() => setIsFullscreen((v) => !v)}>
                  {isFullscreen ? 'Salir de pantalla completa' : 'Pantalla completa'}
                </Button>
                <Button
                  onClick={() => {
                    writeToDom(latestData);
                    submitForm();
                  }}
                >
                  Publicar
                </Button>
              </>
            );
          },
        },
      })
    );
  },
};
