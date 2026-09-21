#!/usr/bin/env python3
"""Compila el demo de OmniChat (empresa ficticia "Altiplano Café").

Usa el CSS, Alpine, app.js y los partials REALES de themes/whatsapp, con una API simulada
(mock.js). Así el demo no se desfasa del tema: basta volver a ejecutar este script.

Salidas en bin/whatsapp-demo/dist/:
  altiplano-demo.html   página completa, para incrustar (iframe) en la web principal
  altiplano-demo.body.html   fragmento sin <html>/<head>, para publicar como artifact
"""
import pathlib
import re

HERE = pathlib.Path(__file__).resolve().parent
ROOT = HERE.parents[1]
THEME = ROOT / 'themes' / 'whatsapp'
DIST = HERE / 'dist'


def read(p):
    return pathlib.Path(p).read_text(encoding='utf-8')


def body_of(htm):
    """Quita la cabecera INI de October (todo hasta la línea '==')."""
    return htm.split('\n==\n', 1)[1] if '\n==\n' in htm else htm.split('==\n', 1)[1]


def patch(src, old, new):
    assert src.count(old) == 1, 'app.js cambió y el parche ya no aplica: ' + old[:60]
    return src.replace(old, new)


# --- app.js real, con tres ajustes mínimos para funcionar fuera del CMS ---
js = read(THEME / 'assets/js/app.js')
js = patch(js, "var parts = location.pathname.split('/');", "var parts = ['', 'altiplano'];")
js = patch(js, 'async pushInit() {', "async pushInit() { this.pushState = 'unsupported'; return;")

# --- shell real: página + partials ---
def partial(m):
    txt = body_of(read(THEME / 'partials' / (m.group(1) + '.htm')))
    assert '{{' not in txt and '{%' not in txt, 'el partial %s usa Twig' % m.group(1)
    return txt

page = body_of(read(THEME / 'pages/app.htm'))
page = re.sub(r"\{%\s*partial '(\w+)'\s*%\}", partial, page)
assert '{%' not in page and '{{' not in page

css = read(THEME / 'assets/css/app.css') + '\n' + read(HERE / 'demo.css')
alpine = read(THEME / 'assets/js/alpine.min.js')
for name, src in (('app.js', js), ('alpine', alpine), ('mock.js', read(HERE / 'mock.js')), ('shim.js', read(HERE / 'shim.js'))):
    assert '</script' not in src.lower(), name + ' contiene </script'

# Atajos de la barra: abren la conversación y la pestaña donde se ve cada función.
guide = """
window.demoGo = function (step) {
    var a = Alpine.$data(document.querySelector('.app'));
    var steps = {
        sale:   [101, 'sale'],
        pay:    [107, 'pay'],
        crm:    [102, 'lead'],
        ticket: [103, 'ticket'],
    };
    if (step === 'delegate') {
        var c = a.convs.find(function (x) { return x.id === 104; });
        if (c) { a.openConv(c).then(function () { a.sheet = 'delegate'; }); }
        return;
    }
    var s = steps[step], conv = a.convs.find(function (x) { return x.id === s[0]; });
    if (!conv) return;
    a.sheet = '';
    a.openConv(conv).then(function () { a.openCrm(); a.crmTab = s[1]; });
};
"""

bar = """<div class="demo-bar" role="toolbar" aria-label="Guía del demo">
    <span class="flag">Demo</span>
    <span class="say">Altiplano Café es una empresa ficticia; todo funciona con datos simulados.</span>
    <span class="sep" aria-hidden="true"></span>
    <button type="button" onclick="demoGo('sale')">Vender por chat</button>
    <button type="button" onclick="demoGo('pay')">Cobrar con QR</button>
    <button type="button" onclick="demoGo('crm')">CRM y negocios</button>
    <button type="button" onclick="demoGo('ticket')">Soporte y tickets</button>
    <button type="button" onclick="demoGo('delegate')">Delegar chat</button>
    <button type="button" onclick="location.reload()">Reiniciar</button>
</div>"""

fragment = """<title>OmniChat Altiplano Café</title>
<style>
%s
</style>
<script>%s</script>
%s
%s
<script>%s</script>
<script>%s</script>
<script>%s</script>
<script>%s</script>
<script>%s</script>
""" % (css, read(HERE / 'shim.js'), bar, page, read(HERE / 'mock.js'), js, guide, alpine, '')

full = ('<!doctype html>\n<html lang="es">\n<head>\n<meta charset="utf-8">\n'
        '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">\n'
        '<meta name="robots" content="noindex">\n</head>\n<body>\n' + fragment + '</body>\n</html>\n')

DIST.mkdir(exist_ok=True)
(DIST / 'altiplano-demo.html').write_text(full, encoding='utf-8')
(DIST / 'altiplano-demo.body.html').write_text(fragment, encoding='utf-8')
# Copia servida por el landing (themes/master) dentro de un iframe.
MASTER = ROOT / 'themes' / 'master' / 'assets' / 'demo'
MASTER.mkdir(parents=True, exist_ok=True)
(MASTER / 'altiplano-demo.html').write_text(full, encoding='utf-8')
print('ok', len(full) // 1024, 'KB')
