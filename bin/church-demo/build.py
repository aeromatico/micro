#!/usr/bin/env python3
"""Compila las tres demos del rubro Iglesias (iglesia ficticia «Comunidad Vida Nueva»).

Reproducen las pantallas y reglas de plugins/aero/church (textos y estados de sus vistas y modelos) con
datos ficticios en memoria. Si cambian esas pantallas, ajustar aquí, ejecutar y subir `ver` en
themes/master/partials/site/landing/iglesia-demos.htm.
Salida: themes/master/assets/demo/iglesia-{app,secretaria,tesoreria}-demo.html
"""
import pathlib

HERE = pathlib.Path(__file__).resolve().parent
ROOT = HERE.parents[1]
OUT = ROOT / 'themes' / 'master' / 'assets' / 'demo'


def read(name):
    return (HERE / name).read_text(encoding='utf-8')


def page(title, styles, scripts, body):
    css = ''.join(read(s) for s in styles)
    js = ''.join(read(s) for s in scripts)
    assert '</script' not in js
    return f"""<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{title}</title>
<style>{css}</style>
</head>
<body>
{body}
<script>{js}</script>
</body>
</html>
"""


APP = '<header><div class="brand"><i>✝</i> Comunidad Vida Nueva</div><nav id="nav" aria-label="Secciones"></nav></header><main id="main"></main>'


def panel(role):
    return f'<div class="shell"><aside><div class="who">Comunidad Vida Nueva<span>{role}</span></div><nav id="menu" aria-label="Iglesia"></nav></aside><main id="main"></main></div>'


DEMOS = {
    'iglesia-app-demo': page('Iglesia — demo del sitio y portal del miembro', ['base.css', 'app.css'], ['common.js', 'app.js'], APP),
    'iglesia-secretaria-demo': page('Iglesia — demo de secretaría', ['base.css', 'admin.css'], ['common.js', 'admin.js', 'secretaria.js'], panel('Secretaría y pastoral')),
    'iglesia-tesoreria-demo': page('Iglesia — demo de tesorería', ['base.css', 'admin.css'], ['common.js', 'admin.js', 'tesoreria.js'], panel('Tesorería')),
}
for name, html in DEMOS.items():
    (OUT / f'{name}.html').write_text(html, encoding='utf-8')
    print('OK', name, len(html) // 1024, 'KB')
