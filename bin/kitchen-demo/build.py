#!/usr/bin/env python3
"""Compila la demo de la pantalla de Cocina (Tienda → Cocina) con datos ficticios de «Sabor Andino».

Usa el CSS REAL de plugins/aero/shop/controllers/kitchen/index.php y los íconos de KitchenIcons.php,
y reproduce las reglas del tablero (tiempos estimados, modo ocupado, retrasos, rondas) en app.js.
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
assert len(icons) >= 15, 'KitchenIcons cambió: no se pudieron leer los íconos'

js = (HERE / 'app.js').read_text(encoding='utf-8').replace('__ICONS__', json.dumps(icons, ensure_ascii=False))

html = f"""<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Cocina — demo para restaurantes</title>
<style>
html,body{{margin:0;background:#f3f4f6;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;font-size:14px;line-height:1.4;color:#111827}}
body{{padding:10px}}
button{{font-family:inherit}}
a{{color:inherit}}
{css}
</style>
</head>
<body>
<div class="kx">
  <div class="kx-wrap" id="kitchen-wrap">
    <div class="kx-bar">
      <h3 class="kx-title">{{ICON_UTENSILS}} Cocina</h3>
      <span class="kx-live" id="kx-live"><i class="kx-dot"></i><span id="kx-updated">En vivo</span></span>
      <span class="kx-spacer"></span>
      <label class="kx-toggle"><input type="checkbox" id="kitchen-sound" checked> {{ICON_BELL}} Sonido</label>
      <button type="button" class="kx-btn" id="kx-test" title="Probar sonido">{{ICON_PLAY}} Probar</button>
    </div>
    <div id="kitchen-board" data-filter="all"></div>
  </div>
</div>
<script>{js}</script>
</body>
</html>
"""
def svg(name):
    return ('<svg class="kx-ic" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" '
            'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + icons[name] + '</svg>')
html = html.replace('{ICON_UTENSILS}', svg('utensils')).replace('{ICON_BELL}', svg('bell')).replace('{ICON_PLAY}', svg('play'))
OUT.write_text(html, encoding='utf-8')
print('OK', OUT.relative_to(ROOT), len(html) // 1024, 'KB')
