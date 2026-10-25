#!/usr/bin/env python3
"""Compila la demo del POS para restaurantes (negocio ficticio «Sabor Andino»).

Usa el CSS, Alpine, app.js y los partials REALES de themes/pos, con una API simulada (mock.js):
la demo no se desfasa de la app. Para actualizarla, vuelve a ejecutar este script.

Salida: themes/master/assets/demo/pos-demo.html (se incrusta en un iframe en la ficha del servicio).
Las ilustraciones de los productos están en themes/master/assets/demo/pos/ (se generan con gen-icons.py).
"""
import pathlib

HERE = pathlib.Path(__file__).resolve().parent
ROOT = HERE.parents[1]
THEME = ROOT / 'themes' / 'pos'
OUT = ROOT / 'themes' / 'master' / 'assets' / 'demo' / 'pos-demo.html'


def read(p):
    return pathlib.Path(p).read_text(encoding='utf-8')


def body_of(htm):
    return htm.split('\n==\n', 1)[1] if '\n==\n' in htm else htm


def patch(src, old, new):
    assert src.count(old) == 1, 'app.js cambió y el parche ya no aplica: ' + old[:70]
    return src.replace(old, new)


js = read(THEME / 'assets/js/app.js')
js = patch(js, "this.handle = (location.pathname.split('/')[1] || '').toLowerCase().replace(/[^a-z0-9-]/g, '');", "this.handle = 'sabor-andino';")
js = patch(js, "if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js').catch(function () {});", '')
js = patch(js, "this.token = store.get('aero.pos.token.' + this.handle, null);", "this.token = 'demo';")
js = patch(js, "prefs: store.get('aero.pos.prefs', { thumbs: false, paper: '', theme: 'auto' }),", "prefs: { thumbs: true, paper: '', theme: 'auto' },")

page = body_of(read(THEME / 'pages/app.htm'))
for name in ('gate', 'login', 'shift', 'sale', 'pay', 'sheets'):
    page = page.replace("{% partial '" + name + "' %}", body_of(read(THEME / f'partials/{name}.htm')))

html = f"""<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex">
<title>Market POS — demo para restaurantes</title>
<style>{read(THEME / 'assets/css/pos.css')}</style>
</head>
<body>
{page}
<script>{read(HERE / 'mock.js')}</script>
<script>{js}</script>
<script>{read(THEME / 'assets/js/alpine.min.js')}</script>
</body>
</html>
"""
OUT.write_text(html, encoding='utf-8')
print('OK', OUT.relative_to(ROOT), len(html) // 1024, 'KB')
