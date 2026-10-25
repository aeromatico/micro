import os
W, H = 200, 120
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'icons')


def svg(bg, body):
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}">'
            f'<rect width="{W}" height="{H}" fill="{bg}"/><ellipse cx="100" cy="108" rx="54" ry="6" fill="#000" opacity=".10"/>{body}</svg>')


def bottle(body, cap, label, x=100, h=84, w=34):
    t = 108 - h
    return (f'<rect x="{x-w/2}" y="{t+14}" width="{w}" height="{h-14}" rx="10" fill="{body}"/>'
            f'<rect x="{x-9}" y="{t}" width="18" height="18" rx="4" fill="{body}"/><rect x="{x-10}" y="{t-6}" width="20" height="9" rx="3" fill="{cap}"/>'
            f'<rect x="{x-w/2}" y="{t+h*0.42}" width="{w}" height="{h*0.3}" fill="{label}"/>'
            f'<rect x="{x-w/2+5}" y="{t+20}" width="5" height="{h-36}" rx="2.5" fill="#fff" opacity=".35"/>')


def glass(liq, x=100, straw=None, slc=None):
    s = (f'<path d="M{x-24} 28 L{x+24} 28 L{x+18} 106 L{x-18} 106 Z" fill="#fff" opacity=".55"/>'
         f'<path d="M{x-21} 46 L{x+21} 46 L{x+17} 104 L{x-17} 104 Z" fill="{liq}"/>'
         f'<path d="M{x-24} 28 L{x+24} 28 L{x+18} 106 L{x-18} 106 Z" fill="none" stroke="#fff" stroke-width="3" opacity=".8"/>')
    if straw:
        s += f'<rect x="{x+8}" y="8" width="6" height="62" rx="3" fill="{straw}"/>'
    if slc:
        s += f'<circle cx="{x+24}" cy="30" r="13" fill="{slc}"/><circle cx="{x+24}" cy="30" r="9" fill="#fff" opacity=".35"/>'
    return s


def cup(foam=None, coffee="#6B3F24"):
    s = ('<ellipse cx="100" cy="100" rx="52" ry="9" fill="#fff"/><path d="M64 52 L136 52 L128 94 Q100 104 72 94 Z" fill="#fff"/>'
         f'<ellipse cx="100" cy="52" rx="36" ry="8" fill="{coffee}"/>')
    if foam:
        s += f'<ellipse cx="100" cy="52" rx="28" ry="5.5" fill="{foam}"/><path d="M92 52 q8 -4 16 0" stroke="{coffee}" stroke-width="2.5" fill="none"/>'
    s += ('<path d="M136 60 q22 2 14 20 q-6 10 -18 8" stroke="#fff" stroke-width="7" fill="none" stroke-linecap="round"/>'
          '<path d="M86 38 q-6 -8 0 -14 M104 38 q-6 -8 0 -14" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round" opacity=".7"/>')
    return s


def bag(color, accent):
    return (f'<path d="M62 22 L138 22 L142 104 L58 104 Z" fill="{color}"/><path d="M62 22 L138 22 L136 32 L64 32 Z" fill="{accent}"/>'
            f'<path d="M58 104 L142 104 L141 96 L59 96Z" fill="{accent}" opacity=".6"/>'
            f'<circle cx="100" cy="62" r="18" fill="{accent}"/><circle cx="100" cy="62" r="10" fill="#fff" opacity=".7"/>')


def bar(color, wrap):
    s = f'<g transform="rotate(-6 100 64)"><rect x="54" y="36" width="92" height="56" rx="6" fill="{wrap}"/>'
    for i in range(4):
        for j in range(3):
            s += f'<rect x="{60+i*21}" y="{42+j*16}" width="18" height="13" rx="2" fill="{color}"/>'
    return s + '</g>'


def cookies(square=False):
    s = ''
    for x, y, r in [(76, 72, 26), (124, 68, 26), (100, 44, 26)]:
        if square:
            s += f'<rect x="{x-r}" y="{y-r+6}" width="{r*2}" height="{r*2-8}" rx="5" fill="#4A2C1B"/><rect x="{x-r+4}" y="{y-r+10}" width="{r*2-8}" height="{r*2-16}" rx="3" fill="#5E3922"/>'
        else:
            s += f'<circle cx="{x}" cy="{y}" r="{r}" fill="#D9A15C"/><circle cx="{x}" cy="{y}" r="{r-4}" fill="#E5B672"/>'
        for dx, dy in [(-8, -6), (7, -9), (2, 8), (-9, 7), (10, 5)]:
            s += f'<circle cx="{x+dx}" cy="{y+dy}" r="2.6" fill="{"#E8C9A0" if square else "#5A3A22"}"/>'
    return s


def jar(body, lid, label):
    return (f'<rect x="66" y="40" width="68" height="62" rx="12" fill="{body}"/><rect x="62" y="26" width="76" height="20" rx="6" fill="{lid}"/>'
            f'<rect x="66" y="58" width="68" height="28" fill="{label}"/><rect x="72" y="46" width="6" height="48" rx="3" fill="#fff" opacity=".3"/>')


def tube(body, cap):
    return (f'<path d="M72 30 L128 30 L122 94 L78 94 Z" fill="{body}"/><rect x="82" y="94" width="36" height="12" rx="3" fill="{cap}"/>'
            f'<rect x="70" y="22" width="60" height="10" rx="3" fill="{body}"/><rect x="88" y="52" width="24" height="20" rx="4" fill="#fff" opacity=".75"/>')


ICONS = {}
ICONS['agua'] = ('#DCEFFA', bottle('#7CC4EE', '#2B7FB8', '#fff'))
ICONS['gaseosa'] = ('#FBE1DC', bottle('#C8352B', '#7A1E18', '#F7E3D4', h=100, w=40))
ICONS['jugo'] = ('#FCEBC8', glass('#F7A52B', straw='#E8734A', slc='#F7C873'))
ICONS['energizante'] = ('#E3E0F7', '<rect x="72" y="28" width="56" height="78" rx="9" fill="#3A2E8F"/><rect x="72" y="28" width="56" height="12" rx="5" fill="#B9B2F0"/>'
                        '<path d="M104 46 L90 74 L100 74 L96 96 L112 66 L102 66 Z" fill="#F2A93B"/><rect x="78" y="34" width="5" height="64" rx="2.5" fill="#fff" opacity=".25"/>')
ICONS['galletas'] = ('#F8EBD7', cookies())
ICONS['papas'] = ('#FBEFC4', bag('#E8A417', '#C2410C'))
ICONS['chocolate'] = ('#EBDDD3', bar('#5A3322', '#C8352B'))
ICONS['mani'] = ('#F4E7D2', bag('#A0642F', '#E8C48A'))
ICONS['detergente'] = ('#DDF1EA', '<rect x="62" y="40" width="66" height="64" rx="10" fill="#17A58A"/><rect x="82" y="22" width="26" height="22" rx="5" fill="#0B6552"/>'
                       '<path d="M112 26 L132 26 L132 46 L112 46" fill="none" stroke="#0B6552" stroke-width="7" stroke-linejoin="round"/>'
                       '<rect x="62" y="62" width="66" height="26" fill="#fff"/><circle cx="95" cy="75" r="8" fill="#F2A93B"/>')
ICONS['papel'] = ('#EFEAF8', ''.join(f'<rect x="{x}" y="46" width="44" height="52" rx="6" fill="#fff"/><ellipse cx="{x+22}" cy="46" rx="22" ry="7" fill="#EDE7F7"/><ellipse cx="{x+22}" cy="46" rx="8" ry="3" fill="#B9B2F0"/><rect x="{x}" y="70" width="44" height="6" fill="#B9B2F0"/>' for x in (48, 108)))
ICONS['jabon'] = ('#E3F1F7', '<rect x="56" y="44" width="88" height="50" rx="16" fill="#8FD0E8"/><rect x="62" y="50" width="76" height="16" rx="8" fill="#fff" opacity=".4"/>'
                  '<circle cx="150" cy="38" r="9" fill="#fff" opacity=".8"/><circle cx="164" cy="56" r="5" fill="#fff" opacity=".7"/><circle cx="138" cy="24" r="4" fill="#fff" opacity=".7"/>')
ICONS['bolsa'] = ('#E3F5E8', '<path d="M76 46 Q76 22 100 22 Q124 22 124 46" fill="none" stroke="#3B7A4B" stroke-width="7" stroke-linecap="round"/>'
                  '<path d="M60 44 L140 44 L146 106 L54 106 Z" fill="#4FA566"/><circle cx="100" cy="76" r="16" fill="#fff" opacity=".85"/><path d="M92 76 l6 6 l11 -12" stroke="#3B7A4B" stroke-width="4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>')
ICONS['cafe'] = ('#EEDFD2', cup())
ICONS['cappuccino'] = ('#F3E6D8', cup(foam='#F3E1C4'))
ICONS['limonada'] = ('#EEF5C9', glass('#D9E04A', straw='#2F9E6B', slc='#A8D63E'))
ICONS['empanada'] = ('#FBE9CC', '<path d="M46 86 Q46 38 100 38 Q154 38 154 86 Z" fill="#E0A04E"/><path d="M46 86 Q46 44 100 44 Q154 44 154 86" fill="none" stroke="#C47F2E" stroke-width="4" stroke-dasharray="7 5"/>'
                     '<path d="M70 80 Q100 56 130 80" fill="none" stroke="#F3C583" stroke-width="5" stroke-linecap="round"/><ellipse cx="100" cy="92" rx="60" ry="8" fill="#C47F2E"/>')
ICONS['sandwich'] = ('#FCEBC8', '<path d="M52 56 Q52 28 100 28 Q148 28 148 56 Z" fill="#E0A04E"/><rect x="52" y="56" width="96" height="8" rx="4" fill="#7FB54B"/>'
                     '<rect x="52" y="64" width="96" height="10" rx="3" fill="#F2A93B"/><rect x="52" y="74" width="96" height="9" rx="4" fill="#E87A7A"/><path d="M52 83 L148 83 L148 92 Q148 100 140 100 L60 100 Q52 100 52 92 Z" fill="#E0A04E"/>')
ICONS['plato'] = ('#E6EEFB', '<ellipse cx="100" cy="76" rx="64" ry="26" fill="#fff"/><ellipse cx="100" cy="74" rx="46" ry="17" fill="#EAF0FA"/>'
                  '<ellipse cx="82" cy="70" rx="18" ry="10" fill="#E0A04E"/><ellipse cx="112" cy="72" rx="16" ry="9" fill="#F7F0D5"/><path d="M100 60 q8 -10 16 -2 q-4 8 -16 2z" fill="#4FA566"/><circle cx="124" cy="76" r="5" fill="#C8352B"/>')
ICONS['ensalada'] = ('#E3F5E8', '<path d="M50 62 L150 62 Q146 102 100 102 Q54 102 50 62 Z" fill="#fff"/><ellipse cx="100" cy="62" rx="50" ry="9" fill="#E6EFE8"/>'
                     '<circle cx="78" cy="54" r="15" fill="#4FA566"/><circle cx="104" cy="48" r="17" fill="#6DBF7B"/><circle cx="126" cy="56" r="14" fill="#4FA566"/><circle cx="94" cy="56" r="7" fill="#E0554F"/><circle cx="118" cy="50" r="6" fill="#F2A93B"/>')
ICONS['torta'] = ('#F9E3EC', '<path d="M54 98 L146 98 L146 62 L54 62 Z" fill="#F4C9A4"/><path d="M54 62 L146 62 L146 74 L54 74 Z" fill="#fff"/><path d="M54 80 L146 80" stroke="#E0A06A" stroke-width="3"/>'
                   '<path d="M54 62 Q70 46 100 46 Q130 46 146 62 Z" fill="#E0558F"/><circle cx="100" cy="38" r="9" fill="#C8352B"/><path d="M100 29 q4 -8 10 -8" stroke="#3B7A4B" stroke-width="3" fill="none" stroke-linecap="round"/>')
ICONS['flan'] = ('#FBEFD0', '<ellipse cx="100" cy="96" rx="52" ry="9" fill="#fff"/><path d="M58 94 Q58 40 100 40 Q142 40 142 94 Z" fill="#F4CC6A"/>'
                 '<path d="M58 62 Q58 40 100 40 Q142 40 142 62 Q128 72 114 62 Q100 74 86 62 Q72 72 58 62 Z" fill="#8A4B1F"/>')
ICONS['helado'] = ('#FBE1EC', '<path d="M78 62 L122 62 L100 108 Z" fill="#E0A04E"/><path d="M84 70 L116 70 M90 82 L110 82" stroke="#C47F2E" stroke-width="3"/>'
                   '<circle cx="100" cy="46" r="24" fill="#F7A8C8"/><circle cx="82" cy="58" r="14" fill="#F7E1A8"/><circle cx="118" cy="58" r="14" fill="#8FD0B8"/><circle cx="100" cy="24" r="6" fill="#C8352B"/>')
ICONS['brownie'] = ('#EBDDD3', cookies(square=True))
ICONS['tijera'] = ('#E6E3F7', '<g stroke-linecap="round"><path d="M70 88 L128 28" stroke="#9AA3B2" stroke-width="9"/><path d="M130 88 L72 28" stroke="#C3C9D2" stroke-width="9"/></g>'
                   '<circle cx="66" cy="94" r="14" fill="none" stroke="#4B3D9A" stroke-width="8"/><circle cx="134" cy="94" r="14" fill="none" stroke="#4B3D9A" stroke-width="8"/><circle cx="100" cy="58" r="5" fill="#4B3D9A"/>')
ICONS['navaja'] = ('#E3E7EE', '<g transform="rotate(-18 100 64)"><rect x="52" y="52" width="70" height="22" rx="5" fill="#C3C9D2"/><rect x="52" y="52" width="70" height="7" rx="3" fill="#E6E9EF"/>'
                   '<rect x="120" y="50" width="38" height="26" rx="10" fill="#4B3D9A"/></g><circle cx="150" cy="30" r="8" fill="#fff" opacity=".8"/><circle cx="164" cy="44" r="5" fill="#fff" opacity=".7"/>')
ICONS['esmalte'] = ('#F9E3EC', '<rect x="74" y="50" width="52" height="54" rx="14" fill="#E0558F"/><rect x="88" y="26" width="24" height="28" rx="5" fill="#2A2219"/><rect x="92" y="14" width="16" height="14" rx="4" fill="#4A3F33"/>'
                    '<rect x="80" y="58" width="6" height="38" rx="3" fill="#fff" opacity=".35"/>')
ICONS['tinte'] = ('#F4E4F2', '<path d="M54 60 L146 60 Q142 100 100 100 Q58 100 54 60 Z" fill="#fff"/><ellipse cx="100" cy="60" rx="46" ry="9" fill="#C85BB5"/>'
                  '<g transform="rotate(26 140 40)"><rect x="132" y="12" width="9" height="58" rx="4" fill="#2A2219"/><rect x="126" y="62" width="21" height="22" rx="4" fill="#C85BB5"/></g>')
ICONS['estetoscopio'] = ('#DDF1EA', '<path d="M64 24 L64 62 Q64 84 90 84 Q116 84 116 62 L116 24" fill="none" stroke="#17695A" stroke-width="7" stroke-linecap="round"/>'
                         '<path d="M90 84 Q90 100 126 96" fill="none" stroke="#17695A" stroke-width="7" stroke-linecap="round"/><circle cx="136" cy="94" r="12" fill="#F2A93B"/><circle cx="136" cy="94" r="5" fill="#fff"/>'
                         '<circle cx="64" cy="22" r="6" fill="#0B6552"/><circle cx="116" cy="22" r="6" fill="#0B6552"/>')
ICONS['planilla'] = ('#E6EEFB', '<rect x="62" y="20" width="76" height="88" rx="8" fill="#fff"/><rect x="82" y="14" width="36" height="14" rx="5" fill="#2850A0"/>'
                     '<path d="M74 52 l6 6 l10 -12" stroke="#17B890" stroke-width="4" fill="none" stroke-linecap="round" stroke-linejoin="round"/><rect x="98" y="50" width="30" height="5" rx="2.5" fill="#C3CCE0"/>'
                     '<path d="M74 76 l6 6 l10 -12" stroke="#17B890" stroke-width="4" fill="none" stroke-linecap="round" stroke-linejoin="round"/><rect x="98" y="74" width="30" height="5" rx="2.5" fill="#C3CCE0"/>')
ICONS['reloj'] = ('#FCEBC8', '<circle cx="100" cy="62" r="42" fill="#fff"/><circle cx="100" cy="62" r="42" fill="none" stroke="#F2A93B" stroke-width="7"/>'
                  '<path d="M100 36 L100 62 L120 74" stroke="#3A2600" stroke-width="5" fill="none" stroke-linecap="round" stroke-linejoin="round"/><circle cx="100" cy="62" r="4" fill="#3A2600"/>')
ICONS['grafico'] = ('#E3F5E8', '<rect x="50" y="22" width="100" height="82" rx="8" fill="#fff"/><rect x="62" y="68" width="16" height="28" rx="3" fill="#8FD0B8"/><rect x="86" y="52" width="16" height="44" rx="3" fill="#4FA566"/>'
                    '<rect x="110" y="38" width="16" height="58" rx="3" fill="#17695A"/><path d="M60 50 L90 36 L118 28" stroke="#F2A93B" stroke-width="4" fill="none" stroke-linecap="round"/>')
ICONS['shampoo'] = ('#E3E7F7', bottle('#7C6CF0', '#3F2FA6', '#fff', h=92, w=38))
ICONS['crema'] = ('#FBE1EC', jar('#F7C0D4', '#E0558F', '#fff'))
ICONS['gel'] = ('#DDF1EA', tube('#17B890', '#0B6552'))
ICONS['regalo'] = ('#FBE1DC', '<rect x="56" y="52" width="88" height="54" rx="6" fill="#C8352B"/><rect x="50" y="38" width="100" height="18" rx="5" fill="#E0554F"/><rect x="92" y="38" width="16" height="68" fill="#F2A93B"/>'
                   '<path d="M100 38 Q76 12 70 30 Q72 42 100 38 Z M100 38 Q124 12 130 30 Q128 42 100 38 Z" fill="#F2A93B"/>')

os.makedirs(OUT, exist_ok=True)
for name, (bg, body) in ICONS.items():
    with open(os.path.join(OUT, name + '.svg'), 'w') as f:
        f.write(svg(bg, body))
print(len(ICONS), 'iconos')
