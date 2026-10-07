import React, { useCallback, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import {
  ReactFlow, ReactFlowProvider, Background, Controls, MiniMap, Handle, Position,
  addEdge, useNodesState, useEdgesState, useReactFlow,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import './editor.css';

/* Un nodo del lienzo: color por categoría; la condición tiene dos salidas. */
function AeroNode({ data, selected }) {
  const cat = data.__category || 'action';
  const handles = data.__handles || [];

  return (
    <div className={`afe-node ${cat}${handles.length ? ' multi' : ''}${selected ? ' selected' : ''}`}>
      {cat !== 'trigger' && <Handle type="target" position={Position.Top} />}
      <b>{data.__label}</b>
      <small>{data.__summary || data.__type}</small>
      {handles.length ? (
        handles.map((h, i) => {
          const left = ((i + 1) / (handles.length + 1)) * 100;
          return (
            <React.Fragment key={h.id}>
              <Handle type="source" position={Position.Bottom} id={h.id} style={{ left: `${left}%` }} />
              <span className="afe-handle-label" style={{ left: `${left}%` }}>{h.label}</span>
            </React.Fragment>
          );
        })
      ) : (
        <Handle type="source" position={Position.Bottom} />
      )}
    </div>
  );
}

const nodeTypes = { aero: AeroNode };

const CATEGORY_LABELS = { trigger: 'Disparadores', logic: 'Lógica', action: 'Acciones' };

function summarize(def, data) {
  const first = (def.fields || []).find((f) => data[f.key]);
  if (!first) return '';
  const v = typeof data[first.key] === 'object' ? JSON.stringify(data[first.key]) : String(data[first.key]);
  return v.length > 28 ? v.slice(0, 28) + '…' : v;
}

/* ¿Hay un ciclo? Solo advertimos: el motor igual corta a los 50 pasos. */
function hasCycle(nodes, edges) {
  const adj = {};
  edges.forEach((e) => { (adj[e.source] = adj[e.source] || []).push(e.target); });
  const state = {};
  const visit = (id) => {
    if (state[id] === 1) return true;
    if (state[id] === 2) return false;
    state[id] = 1;
    for (const n of adj[id] || []) if (visit(n)) return true;
    state[id] = 2;
    return false;
  };
  return nodes.some((n) => visit(n.id));
}

/* Límites declarados por el nodo (`max`, `max_lines`, `max_line`). Las plantillas {{ }} cuentan como 1 carácter:
   su largo real se comprueba al ejecutar. Devuelve mensajes en español. */
function fieldIssues(def, data) {
  const issues = [];
  (def.fields || []).forEach((f) => {
    const raw = typeof data[f.key] === 'string' ? data[f.key] : '';
    const value = raw.replace(/\{\{.*?\}\}/g, 'x');
    if (f.max && value.length > f.max) issues.push(`«${f.label}» supera ${f.max} caracteres (${value.length}).`);
    if (f.max_lines || f.max_line) {
      const lines = value.split(/\r?\n/).map((l) => l.trim()).filter(Boolean);
      if (f.max_lines && lines.length > f.max_lines) issues.push(`«${f.label}» admite hasta ${f.max_lines} líneas (hay ${lines.length}).`);
      if (f.max_line) {
        const long = lines.filter((l) => l.split('|')[0].trim().length > f.max_line).length;
        if (long) issues.push(`«${f.label}»: ${long} texto(s) superan ${f.max_line} caracteres.`);
      }
    }
  });
  return issues;
}

function validate(nodes, edges, catalog) {
  const warnings = [];
  const triggers = nodes.filter((n) => (n.data.__type || '').startsWith('trigger.'));
  if (!triggers.length) warnings.push('Falta un nodo disparador.');
  if (triggers.length > 1) warnings.push('Solo se usa el primer disparador.');
  const linked = new Set(edges.flatMap((e) => [e.source, e.target]));
  const loose = nodes.filter((n) => nodes.length > 1 && !linked.has(n.id));
  if (loose.length) warnings.push(`${loose.length} nodo(s) sin conectar.`);
  if (hasCycle(nodes, edges)) warnings.push('Hay un ciclo: se cortará a los 50 pasos.');
  nodes.forEach((n) => { if (!catalog[n.data.__type]) warnings.push(`Tipo desconocido: ${n.data.__type}`); });
  nodes.forEach((n) => {
    const d = catalog[n.data.__type];
    if (d) fieldIssues(d, n.data).forEach((m) => warnings.push(`${d.label} (${n.id}): ${m}`));
  });
  return warnings;
}

/* Acomodo por capas (mismo algoritmo que GraphLayout.php): cada nodo bajo lo que lo alimenta y, dentro de la
   capa, ordenado por el promedio de posición de sus vecinos para cruzar menos líneas. Solo cambia posiciones. */
function autoLayout(nodes, edges, gapX = 300, gapY = 150) {
  const ids = nodes.map((n) => n.id);
  const known = new Set(ids);
  const valid = edges.filter((e) => known.has(e.source) && known.has(e.target));
  const children = {}; const parents = {};
  ids.forEach((id) => { children[id] = []; parents[id] = []; });
  valid.forEach((e) => { children[e.source].push(e.target); parents[e.target].push(e.source); });

  // Conexiones que cierran un ciclo: no cuentan para las capas.
  const state = {}; const back = new Set();
  const visit = (id) => {
    state[id] = 1;
    children[id].forEach((c) => { if (state[c] === 1) back.add(`${id}>${c}`); else if (!state[c]) visit(c); });
    state[id] = 2;
  };
  const triggers = nodes.filter((n) => (n.data.__type || '').startsWith('trigger.')).map((n) => n.id);
  [...triggers, ...ids].forEach((id) => { if (!state[id]) visit(id); });
  const forward = valid.filter((e) => !back.has(`${e.source}>${e.target}`));

  const level = {}; ids.forEach((id) => { level[id] = 0; });
  for (let pass = 0; pass < ids.length; pass++) {
    let changed = false;
    forward.forEach((e) => { if (level[e.target] < level[e.source] + 1) { level[e.target] = level[e.source] + 1; changed = true; } });
    if (!changed) break;
  }

  const layers = {};
  ids.forEach((id) => { (layers[level[id]] = layers[level[id]] || []).push(id); });
  const depths = Object.keys(layers).map(Number).sort((a, b) => a - b);
  const index = {};
  depths.forEach((d) => layers[d].forEach((id, i) => { index[id] = i; }));

  for (let sweep = 0; sweep < 4; sweep++) {
    const down = sweep % 2 === 0;
    (down ? depths : [...depths].reverse()).forEach((d) => {
      const bary = {};
      layers[d].forEach((id, pos) => {
        const near = (down ? parents : children)[id].filter((n) => level[n] === (down ? d - 1 : d + 1));
        bary[id] = near.length ? near.reduce((a, n) => a + index[n], 0) / near.length : pos;
      });
      layers[d].sort((a, b) => (bary[a] - bary[b]) || (index[a] - index[b]));
      layers[d].forEach((id, i) => { index[id] = i; });
    });
  }

  const pos = {};
  depths.forEach((d) => layers[d].forEach((id, i) => { pos[id] = { x: Math.round((i - (layers[d].length - 1) / 2) * gapX) + 700, y: d * gapY }; }));

  return nodes.map((n) => ({ ...n, position: pos[n.id] || n.position }));
}

function parseGraph(raw, catalog) {
  let graph = {};
  try { graph = raw ? JSON.parse(raw) : {}; } catch (e) { graph = {}; }
  const nodes = (graph.nodes || []).map((n, i) => {
    const def = catalog[n.type] || { label: n.type, category: 'action', fields: [] };
    const data = n.data || {};
    return {
      id: n.id, type: 'aero', position: n.position || { x: 40 + i * 30, y: 40 + i * 90 },
      data: { ...data, __type: n.type, __label: def.label, __category: def.category, __handles: def.handles || [] },
    };
  });
  const edges = (graph.edges || []).map((e) => ({
    id: e.id || `${e.source}-${e.sourceHandle || ''}-${e.target}`,
    source: e.source, target: e.target, sourceHandle: e.sourceHandle || undefined,
  }));
  return { nodes, edges };
}

function serialize(nodes, edges) {
  return JSON.stringify({
    nodes: nodes.map((n) => {
      const data = {};
      Object.keys(n.data).forEach((k) => { if (!k.startsWith('__')) data[k] = n.data[k]; });
      return { id: n.id, type: n.data.__type, position: { x: Math.round(n.position.x), y: Math.round(n.position.y) }, data };
    }),
    edges: edges.map((e) => ({ id: e.id, source: e.source, target: e.target, ...(e.sourceHandle ? { sourceHandle: e.sourceHandle } : {}) })),
  });
}

function Field({ field, value, onChange, connectors }) {
  const common = { value: value ?? '', onChange: (e) => onChange(e.target.value) };
  let control;

  if (field.type === 'select') {
    control = (
      <select {...common}>
        <option value="">—</option>
        {(field.options || []).map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
      </select>
    );
  } else if (field.type === 'connector') {
    control = (
      <select {...common}>
        <option value="">— ninguno (usar URL) —</option>
        {connectors.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
      </select>
    );
  } else if (field.type === 'json') {
    const text = typeof value === 'string' ? value : value ? JSON.stringify(value, null, 2) : '';
    control = (
      <textarea
        value={text}
        onChange={(e) => {
          try { onChange(e.target.value.trim() ? JSON.parse(e.target.value) : ''); } catch (err) { onChange(e.target.value); }
        }}
      />
    );
  } else if (field.type === 'textarea') {
    control = <textarea {...common} />;
  } else {
    control = <input type={field.type === 'number' ? 'number' : 'text'} {...common} />;
  }

  const text = typeof value === 'string' ? value.replace(/\{\{.*?\}\}/g, 'x') : '';
  const lines = text.split(/\r?\n/).map((l) => l.trim()).filter(Boolean).length;
  const over = (field.max && text.length > field.max) || (field.max_lines && lines > field.max_lines);

  return (
    <div className="afe-field">
      <label>{field.label}</label>
      {control}
      {(field.max || field.max_lines) && (
        <div className={`counter${over ? ' over' : ''}`}>
          {field.max ? `${text.length}/${field.max} caracteres` : `${lines}/${field.max_lines} líneas`}
        </div>
      )}
      {field.hint && <div className="hint">{field.hint}</div>}
    </div>
  );
}

function Editor({ textarea, catalog, connectors }) {
  const initial = useMemo(() => parseGraph(textarea.value, catalog), []);
  const [nodes, setNodes, onNodesChange] = useNodesState(initial.nodes);
  const [edges, setEdges, onEdgesChange] = useEdgesState(initial.edges);
  const [selectedId, setSelectedId] = useState(null);
  const [full, setFull] = useState(false);
  const { fitView } = useReactFlow();
  const counter = useRef(initial.nodes.length + 1);

  // Pantalla completa: el editor ocupa toda la ventana; Esc la cierra y la página de fondo no se desplaza.
  React.useEffect(() => {
    if (!full) return undefined;
    const onKey = (e) => { if (e.key === 'Escape') setFull(false); };
    document.addEventListener('keydown', onKey);
    document.body.classList.add('afe-lock');
    return () => { document.removeEventListener('keydown', onKey); document.body.classList.remove('afe-lock'); };
  }, [full]);

  const sync = useCallback((n, e) => { textarea.value = serialize(n, e); }, [textarea]);

  // Cada cambio se refleja en el textarea oculto que October envía al guardar.
  React.useEffect(() => { sync(nodes, edges); }, [nodes, edges, sync]);

  const onConnect = useCallback((c) => setEdges((es) => addEdge({ ...c, id: `e${Date.now()}` }, es)), [setEdges]);

  const addNode = (type) => {
    const def = catalog[type];
    const id = `n${counter.current++}`;
    // Debajo del nodo más bajo, para no caer encima de otro.
    const lowest = nodes.reduce((max, n) => Math.max(max, n.position.y), -60);
    setNodes((ns) => ns.concat({
      id, type: 'aero', position: { x: 80, y: lowest + 110 },
      data: { __type: type, __label: def.label, __category: def.category, __handles: def.handles || [] },
    }));
    setSelectedId(id);
  };

  const selected = nodes.find((n) => n.id === selectedId);
  const def = selected ? catalog[selected.data.__type] : null;

  const updateData = (key, value) => {
    setNodes((ns) => ns.map((n) => {
      if (n.id !== selectedId) return n;
      const data = { ...n.data, [key]: value };
      data.__summary = summarize(catalog[data.__type] || {}, data);
      return { ...n, data };
    }));
  };

  const arrange = () => {
    setNodes((ns) => autoLayout(ns, edges));
    setTimeout(() => fitView({ padding: 0.15, duration: 300 }), 50);
  };

  const removeSelected = () => {
    setNodes((ns) => ns.filter((n) => n.id !== selectedId));
    setEdges((es) => es.filter((e) => e.source !== selectedId && e.target !== selectedId));
    setSelectedId(null);
  };

  const warnings = validate(nodes, edges, catalog);
  const byCategory = {};
  Object.entries(catalog).forEach(([type, d]) => { (byCategory[d.category] = byCategory[d.category] || []).push([type, d]); });

  const prior = nodes.filter((n) => n.id !== selectedId).map((n) => n.id);

  return (
    <div className={`afe${full ? ' afe-full' : ''}`}>
      <div className="afe-side left">
        {['trigger', 'logic', 'action'].map((cat) => (byCategory[cat] ? (
          <div key={cat}>
            <h5>{CATEGORY_LABELS[cat]}</h5>
            {byCategory[cat].map(([type, d]) => (
              <button type="button" key={type} className="afe-add" onClick={() => addNode(type)}>+ {d.label}</button>
            ))}
          </div>
        ) : null))}
      </div>

      <div className="afe-canvas">
        <ReactFlow
          nodes={nodes.map((n) => ({ ...n, data: { ...n.data, __summary: n.data.__summary ?? summarize(catalog[n.data.__type] || {}, n.data) } }))}
          edges={edges}
          nodeTypes={nodeTypes}
          onNodesChange={onNodesChange}
          onEdgesChange={onEdgesChange}
          onConnect={onConnect}
          onNodeClick={(_, n) => setSelectedId(n.id)}
          onPaneClick={() => setSelectedId(null)}
          deleteKeyCode={['Delete', 'Backspace']}
          fitView
        >
          <Background />
          <Controls />
          <MiniMap pannable zoomable className="afe-minimap" nodeStrokeWidth={2}
            nodeColor={(n) => ({ trigger: '#16a34a', logic: '#d97706', action: '#2563eb' }[n.data.__category] || '#94a3b8')} />
        </ReactFlow>
        <button type="button" className="afe-arrangebtn" onClick={arrange} title="Acomoda los nodos por capas para que se crucen menos las líneas">⟲ Ordenar</button>
        <button type="button" className="afe-fullbtn" onClick={() => setFull((v) => !v)} title={full ? 'Salir de pantalla completa (Esc)' : 'Pantalla completa'}>
          {full ? '✕ Salir' : '⛶ Pantalla completa'}
        </button>
      </div>

      <div className="afe-side right">
        {warnings.map((w) => <div key={w} className="afe-warn">{w}</div>)}
        {selected && def ? (
          <>
            <h5>{def.label} · {selected.id}</h5>
            {def.note && <div className="afe-note">{def.note}</div>}
            {(def.fields || []).map((f) => (
              <Field key={f.key} field={f} value={selected.data[f.key]} onChange={(v) => updateData(f.key, v)} connectors={connectors} />
            ))}
            {!(def.fields || []).length && <p className="afe-vars">Este nodo no tiene opciones.</p>}
            <h5>Variables disponibles</h5>
            <div className="afe-vars">
              <code>{'{{ trigger.campo }}'}</code>
              <code>{'{{ vars.nombre }}'}</code>
              {prior.map((id) => <code key={id}>{`{{ nodes.${id}.… }}`}</code>)}
            </div>
            <button type="button" className="afe-del" onClick={removeSelected}>Eliminar nodo</button>
          </>
        ) : (
          <p className="afe-vars">Selecciona un nodo para editar sus opciones. Arrastra desde el punto inferior de un nodo al superior de otro para conectarlos.</p>
        )}
      </div>
    </div>
  );
}

function mount({ mountId, textareaId, catalog, connectors }) {
  const el = document.getElementById(mountId);
  const textarea = document.getElementById(textareaId);
  if (!el || !textarea || el.dataset.mounted) return;
  el.dataset.mounted = '1';
  createRoot(el).render(
    <ReactFlowProvider>
      <Editor textarea={textarea} catalog={catalog} connectors={connectors || []} />
    </ReactFlowProvider>,
  );
}

window.AeroFlowEditor = { mount };
