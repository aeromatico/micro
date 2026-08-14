import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { Render } from '@puckeditor/core';
import { components, categories } from './components';

const config = { components, categories };

export function renderPuckData(jsonString) {
  const data = JSON.parse(jsonString || '{"content":[],"root":{"props":{}}}');
  return renderToStaticMarkup(React.createElement(Render, { config, data }));
}
