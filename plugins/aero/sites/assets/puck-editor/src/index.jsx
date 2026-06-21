import React from 'react';
import { createRoot, flushSync } from 'react-dom/client';
import { Puck, Render } from '@measured/puck';
import '@measured/puck/puck.css';
import { Hero, TextBlock, FeatureGrid, ImageBlock, CTASection, Divider } from './components';

const config = {
  components: { Hero, TextBlock, FeatureGrid, ImageBlock, CTASection, Divider },
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

window.AeroPuckEditor = {
  init(containerId, puckDataId, contentId, existingData) {
    const container = document.getElementById(containerId);
    if (!container) return;

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

    const syncData = debounce((data) => {
      if (puckDataEl) puckDataEl.value = JSON.stringify(data);
      if (contentEl)  contentEl.value  = generateHtml(data);
    }, 400);

    createRoot(container).render(
      React.createElement(Puck, {
        config,
        data: initialData,
        onChange: syncData,
        iframe: { enabled: false },
      })
    );
  },
};
