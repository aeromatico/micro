#!/usr/bin/env python3
"""Compila la demo de la pantalla de Cocina (Tienda → Cocina) con datos ficticios de «Sabor Andino».

Misma pantalla que plugins/aero/shop/controllers/kitchen: el CSS (apariencia de la caja, claro y oscuro)
se lee de index.php y los íconos de KitchenIcons.php. Las reglas del tablero (tiempos estimados, modo
ocupado, retrasos, rondas) se reproducen en app.js con pedidos ficticios.
Salida: themes/master/assets/demo/cocina-demo.html
"""
import json
import pathlib
import re

HERE = pathlib.Path(__file__).resolve().parent
ROOT = HERE.parents[1]
SHOP = ROOT / 'plugins' / 'aero' / 'shop'
OUT = ROOT / 'themes' / 'master' / 'assets' / 'demo' / 'cocina-demo.html'

index = (SHOP / 'controllers/kitchen/index.php').read_text(encoding='utf-8')
css = re.search(r'<style>(.*?)</style>', index, re.S).group(1)
icons_php = (SHOP / 'classes/KitchenIcons.php').read_text(encoding='utf-8')
icons = dict(re.findall(r"'([a-z-]+)'\s*=>\s*'(.*?)',\n", icons_php))
assert len(icons) >= 17 and 'moon' in icons and 'sun' in icons, 'KitchenIcons cambió: no se pudieron leer los íconos'

js = (HERE / 'app.js').read_text(encoding='utf-8').replace('__ICONS__', json.dumps(icons, ensure_ascii=False))


def svg(name):
    return ('<svg class="kx-ic" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" '
            'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + icons[name] + '</svg>')


html = """<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Cocina — demo para restaurantes</title>
<style>
html,body{margin:0;font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
__CSS__
/* Solo la demo: el tablero ocupa todo el marco */
.kx{min-height:100vh}
.kx-wrap{min-height:100vh;border-radius:0}
</style>
</head>
<body>
<div class="kx" data-theme="light">
  <div class="kx-wrap" id="kitchen-wrap">
    <div class="kx-bar kx-head">
      <h3 class="kx-title">__UTENSILS__ Cocina</h3>
      <span class="kx-live" id="kx-live"><i class="kx-dot"></i><span id="kx-updated">En vivo</span></span>
      <span class="kx-spacer"></span>
      <label class="kx-toggle"><input type="checkbox" id="kitchen-sound" checked> __BELL__ <span class="hide-xs">Sonido</span></label>
      <button type="button" class="kx-btn ibtn" id="kx-test" title="Probar sonido">__PLAY__ <span class="hide-xs">Probar</span></button>
      <button type="button" class="kx-btn ibtn" id="kx-theme" title="Modo claro u oscuro" aria-label="Cambiar entre modo claro y oscuro"><span class="kx-th-moon">__MOON__</span><span class="kx-th-sun">__SUN__</span></button>
    </div>
    <div id="kitchen-board" data-filter="all"></div>
  </div>
</div>
<script>__JS__</script>
</body>
</html>
"""
for k, v in {'__CSS__': css, '__JS__': js, '__UTENSILS__': svg('utensils'), '__BELL__': svg('bell'), '__PLAY__': svg('play'),
             '__MOON__': svg('moon'), '__SUN__': svg('sun')}.items():
    html = html.replace(k, v)
OUT.write_text(html, encoding='utf-8')
print('OK', OUT.relative_to(ROOT), len(html) // 1024, 'KB')
