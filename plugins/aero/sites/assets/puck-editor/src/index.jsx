import React from 'react';
import { createRoot } from 'react-dom/client';
import { flushSync } from 'react-dom';
import { Puck, Render } from '@puckeditor/core';
import '@puckeditor/core/puck.css';
import { components, categories } from './components';

const config = {
  components,
  categories,
};

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

  init(containerId, puckDataId, contentId, existingData) {
    const container = document.getElementById(containerId);
    if (!container) return;

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

    // Triggers the same form submit as the "Guardar" button. The form uses
    // October's data-request, so this performs the AJAX save (onSaveIndex).
    const submitForm = () => {
      if (!form) return;
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      } else {
        const submit = form.querySelector('button[type="submit"]');
        if (submit) submit.click();
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
      })
    );
  },
};
