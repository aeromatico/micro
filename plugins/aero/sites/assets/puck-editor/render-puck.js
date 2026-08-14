#!/usr/bin/env node

/**
 * CLI wrapper for the Puck headless renderer.
 * Reads Puck JSON from stdin, writes HTML to stdout.
 *
 * Uso:  echo '{...}' | node render-puck.js
 *       node render-puck.js --inline '{"content":[...]}'
 *       node render-puck.js data.json
 */

const path = require('path');

// The CJS render-server and its deps (react, @puckeditor/core) live under
// this puck-editor directory. Let Node resolve them here.
process.env.NODE_PATH = path.resolve(__dirname, 'node_modules');
require('module').Module._initPaths();

const { renderPuckData } = require(
  path.resolve(__dirname, '../../bin/render-server.js')
);

let input = '';

const arg = process.argv[2];
if (arg === '--inline') {
  input = process.argv[3] || '';
} else if (arg && !arg.startsWith('-')) {
  input = require('fs').readFileSync(arg, 'utf8');
}

if (input) {
  try {
    process.stdout.write(renderPuckData(input));
  } catch (e) {
    process.stderr.write('Puck render error: ' + (e && (e.message || String(e))) + '\n');
    process.exit(1);
  }
} else {
  process.stdin.setEncoding('utf8');
  process.stdin.on('data', (chunk) => (input += chunk));
  process.stdin.on('end', () => {
    try {
      process.stdout.write(renderPuckData(input));
    } catch (e) {
      process.stderr.write('Puck render error: ' + (e && (e.message || String(e))) + '\n');
      process.exit(1);
    }
  });
}
