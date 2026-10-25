#!/usr/bin/env python3
"""Compila la demo de la pantalla de Cocina (Tienda → Cocina) con datos ficticios de «Sabor Andino».

Mismo tablero y clases que plugins/aero/shop/controllers/kitchen, con la apariencia de la caja (POS):
estilos propios en kitchen.css (claro y oscuro). Los íconos salen de KitchenIcons.php y las reglas del
tablero (tiempos estimados, modo ocupado, retrasos, rondas) se reproducen en app.js.
Salida: themes/master/assets/demo/cocina-demo.html
"""
import json
import pathlib
import re

HERE = pathlib.Path(__file__).resolve().parent
ROOT = HERE.parents[1]
SHOP = ROOT / 'plugins' / 'aero' / 'shop'
OUT = ROOT / 'themes' / 'master' / 'assets' / 'demo' / 'cocina-demo.html'

css = (HERE / 'kitchen.css').read_text(encoding='utf-8')
icons_php = (SHOP / 'classes/KitchenIcons.php').read_text(encoding='utf-8')
icons = dict(re.findall(r"'([a-z-]+)'\s*=>\s*'(.*?)',\n", icons_php))
icons['moon'] = '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>'
icons['sun'] = '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2m-7.07-2.93 1.41-1.41m11.32-11.32 1.41-1.41M2 12h2m16 0h2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41"/>'
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
{css}
</style>
</head>
<body>
<header class="top">
  <div class="brand"><b>M</b><span>{{ICON_UTENSILS}} Cocina</span></div>
  <span class="kx-live" id="kx-live"><i class="kx-dot"></i><span id="kx-updated">En vivo</span></span>
  <span class="kx-spacer"></span>
  <label class="kx-toggle"><input type="checkbox" id="kitchen-sound" checked> {{ICON_BELL}} <span class="hide-xs">Sonido</span></label>
  <button type="button" class="ibtn" id="kx-test" title="Probar sonido">{{ICON_PLAY}} <span class="hide-xs">Probar</span></button>
  <button type="button" class="ibtn" id="kx-theme" aria-label="Cambiar modo claro u oscuro" title="Modo claro u oscuro">{{ICON_MOON}}</button>
</header>
<div class="kx">
  <div class="kx-wrap" id="kitchen-wrap">
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
html = html.replace('{ICON_UTENSILS}', svg('utensils')).replace('{ICON_BELL}', svg('bell')).replace('{ICON_PLAY}', svg('play')).replace('{ICON_MOON}', svg('moon'))
OUT.write_text(html, encoding='utf-8')
print('OK', OUT.relative_to(ROOT), len(html) // 1024, 'KB')
