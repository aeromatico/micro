#!/usr/bin/env python3
"""Genera la locución (ElevenLabs, una por escena) y escribe index.html con los tiempos reales.
Uso: python3 build.py   (lee la clave de /root/.elevenlabs.env; nunca va al repo)."""
import json, os, subprocess, urllib.request

HERE = os.path.dirname(os.path.abspath(__file__))
VOICE = os.environ.get("VOICE_ID", "qWWAqFomnJ99VwQLREfT")  # voz de la biblioteca del usuario para este video; Brian=U9TSK9KHMlMU2qkeXlQP, Daniela=wBnAJRbu3cj93gnAm02O
MUSIC = os.environ.get("MUSIC", "1") == "1"  # fondo musical bajo la voz (assets/fondo.mp3)
MUSIC_VOL = float(os.environ.get("MUSIC_VOL", "0.16"))
SCENES = [
    "Con la API de Bolivia Pay, tu sistema cobra por QR sin abrir el panel. Una tienda, una app, lo que construyas.",
    "Primero creas un token, y decides qué puede hacer: crear, consultar o anular cobros.",
    "Tu sistema envía el monto y tu referencia, por ejemplo el número de pedido, y recibe un QR listo para mostrar.",
    "El cliente paga y el banco avisa. Apenas se confirma, Bolivia Pay avisa a tu sistema con un webhook firmado.",
    "Si tu servidor no responde, hay hasta seis intentos con esperas crecientes. Y con la firma compruebas que el aviso es auténtico.",
    "Con Bolivia Pay ya cobran WooCommerce, el OmniChat y los Workflows. Tu sistema puede sumarse.",
    "Cobros, confirmaciones y automatización. Todo por API. Market.",
]

def key():
    for l in open("/root/.elevenlabs.env"):
        if l.startswith("ELEVENLABS_API_KEY="):
            return l.split("=", 1)[1].strip()

def tts(i, text):
    out = os.path.join(HERE, "assets", f"s{i+1}.mp3")
    if os.path.exists(out):
        return out
    # naturalidad: estabilidad media, sin exageración de estilo y contexto de las escenas vecinas (continuidad de entonación)
    payload = {"text": text, "model_id": "eleven_multilingual_v2", "language_code": "es",
               "voice_settings": {"stability": .42, "similarity_boost": .75, "style": 0.0, "use_speaker_boost": True}}
    if i > 0: payload["previous_text"] = SCENES[i - 1]
    if i < len(SCENES) - 1: payload["next_text"] = SCENES[i + 1]
    body = json.dumps(payload).encode()
    req = urllib.request.Request(f"https://api.elevenlabs.io/v1/text-to-speech/{VOICE}?output_format=mp3_44100_128",
                                 data=body, headers={"xi-api-key": key(), "Content-Type": "application/json"})
    open(out, "wb").write(urllib.request.urlopen(req, timeout=90).read())
    return out

def dur(p):
    return float(subprocess.check_output(["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", p]).decode())

COVER = 3.4  # carátula antes de la escena 1
t, scenes = COVER + 0.3, []
for i, txt in enumerate(SCENES):
    d = dur(tts(i, txt))
    scenes.append({"t0": round(t, 2), "d": round(d, 2), "text": txt})
    t += 0.3 + d + 0.55
total = round(t + 0.9, 1)

def beats(S):
    """Instantes absolutos de cada acción de la animación (una sola fuente para animación y efectos)."""
    g = lambda i, f, off=0.3: round(S[i]["t0"] + off + S[i]["d"] * f, 2)
    return {
        "boxes": g(0, .08), "arrow": g(0, .55),
        "token": g(1, .15), "perm": g(1, .55),
        "req": g(2, .15), "res": g(2, .62),
        "phone": g(3, .08), "bank": g(3, .3), "hook": g(3, .6),
        "retry": g(4, .08), "dots": g(4, .22), "sig": g(4, .68),
        "tiles": g(5, .2),
        "brand": round(S[6]["t0"] + .4, 2),
    }


def make_mix(scenes, total):
    """Voz + efectos (en los tiempos de la animación) + música con ducking -> assets/mezcla.mp3"""
    S = scenes
    T = beats(scenes)
    ev = [("pop", .35, .7),
          ("pop", T["boxes"], .5), ("tick", T["boxes"] + .3, .5), ("tick", T["boxes"] + .6, .5), ("tick", T["arrow"], .6),
          ("pop", T["token"], .6), ("tick", T["perm"], .5), ("tick", T["perm"] + .3, .5), ("tick", T["perm"] + .6, .5),
          ("tick", T["req"], .6), ("chirp", T["res"], .5),
          ("pop", T["phone"], .6), ("chirp", T["bank"], .5), ("success", T["hook"], .8),
          ("tick", T["dots"], .5), ("tick", T["dots"] + .45, .5), ("tick", T["dots"] + .9, .5), ("pop", T["sig"], .6),
          ("pop", T["tiles"], .6), ("tick", T["tiles"] + .3, .5), ("tick", T["tiles"] + .6, .5),
          ("sting", T["brand"], .7)]
    inputs, f, lab_v, lab_s = [], [], [], []
    for i, sc in enumerate(S):
        inputs += ["-i", os.path.join(HERE, "assets", f"s{i+1}.mp3")]
        ms = int((sc["t0"] + 0.3) * 1000)
        f.append(f"[{len(lab_v)}:a]adelay={ms}|{ms},volume=1.0[v{i}]"); lab_v.append(f"[v{i}]")
    n = len(S)
    sfx_files = {}
    for j, (name, at, vol) in enumerate(ev):
        inputs += ["-i", os.path.join(HERE, "assets", "sfx", f"{name}.mp3")]
        ms = int(at * 1000); idx = n + j
        f.append(f"[{idx}:a]adelay={ms}|{ms},volume={vol}[e{j}]"); lab_s.append(f"[e{j}]")
    if MUSIC:
        inputs += ["-i", os.path.join(HERE, "assets", "fondo.mp3")]
    mi = n + len(ev)
    f.append("".join(lab_v) + f"amix=inputs={n}:normalize=0,aformat=channel_layouts=stereo,apad=whole_dur={total},asplit=2[vm1][vm2]")
    if MUSIC:
        f.append(f"[{mi}:a]atrim=0:{total},volume={MUSIC_VOL},afade=t=in:d=1.5,afade=t=out:st={total-2.5}:d=2.5[mus]")
        f.append("[mus][vm1]sidechaincompress=threshold=0.02:ratio=10:attack=15:release=450[md]")
    else:
        f.append("[vm1]anullsink")
    f.append("".join(lab_s) + f"amix=inputs={len(ev)}:normalize=0,aformat=channel_layouts=stereo[sx]")
    if MUSIC:
        f.append(f"[vm2][md][sx]amix=inputs=3:normalize=0,alimiter=limit=0.95,atrim=0:{total}[out]")
    else:
        f.append(f"[vm2][sx]amix=inputs=2:normalize=0,alimiter=limit=0.95,atrim=0:{total}[out]")
    out = os.path.join(HERE, "assets", "mezcla.mp3")
    subprocess.check_call(["ffmpeg", "-v", "error", "-y"] + inputs + ["-filter_complex", ";".join(f), "-map", "[out]", "-b:a", "192k", out])
    return dur(out)

mix_dur = make_mix(scenes, total)
VARIANTS = {
    "": dict(vw=1920, vh=1080, vert="false", viewbox="0 0 1920 1080", svgw=1920, svgh=1080, css="", out=HERE),
    # 9:16 de verdad: escena ampliada al ancho, título fijo arriba y subtítulos grandes debajo
    "vertical": dict(vw=1080, vh=1920, vert="true", viewbox="430 -244 1060 1884", svgw=1080, svgh=1920,
                     out=os.path.join(os.path.dirname(HERE), os.path.basename(HERE) + "-vertical"),
                     css=".cap{left:60px;width:960px;bottom:auto;top:1440px;font-size:46px}"
                         "#vtitle{display:block;top:70px}#vtitle b{font-size:78px}#vtitle span{font-size:46px}"
                         "#cover h1{font-size:150px}#cover .by{font-size:70px;margin-top:44px}#cover .by .flag{font-size:76px}"
                         "#brand{top:560px}#brand b{font-size:220px}#brand em{font-size:56px}#brand p{font-size:46px}#brand span{font-size:40px}"),
}
tpl = open(os.path.join(HERE, "plantilla.tpl")).read()
audio = f'<audio id="mezcla" src="assets/mezcla.mp3" data-start="0" data-duration="{round(mix_dur - 0.05, 2)}" data-track-index="1"></audio>'
caps = "\n".join(f'<div class="cap" id="cap{i+1}">{s["text"]}</div>' for i, s in enumerate(scenes))
for name, v in VARIANTS.items():
    html = (tpl.replace("%%TOTAL%%", str(total)).replace("%%AUDIO%%", audio).replace("%%CAPS%%", caps)
            .replace("%%SCENES%%", json.dumps(scenes, ensure_ascii=False)).replace("%%COVER%%", str(COVER)).replace("%%BEATS%%", json.dumps(beats(scenes))).replace("%%FONTS%%", open(os.path.join(HERE, "assets", "fonts", "urbanist.css")).read()).replace("%%VW%%", str(v["vw"]))
            .replace("%%VH%%", str(v["vh"])).replace("%%VARCSS%%", v["css"]).replace("%%VERT%%", v["vert"])
            .replace("%%VIEWBOX%%", v["viewbox"]).replace("%%SVGW%%", str(v["svgw"])).replace("%%SVGH%%", str(v["svgh"])))
    os.makedirs(os.path.join(v["out"], "assets"), exist_ok=True)
    open(os.path.join(v["out"], "index.html"), "w").write(html)
    if v["out"] != HERE:
        import shutil
        shutil.copy(os.path.join(HERE, "assets", "mezcla.mp3"), os.path.join(v["out"], "assets", "mezcla.mp3"))
        shutil.copytree(os.path.join(HERE, "assets", "fonts"), os.path.join(v["out"], "assets", "fonts"), dirs_exist_ok=True)
        for fn in ("hyperframes.json", "package.json", "meta.json"):
            shutil.copy(os.path.join(HERE, fn), os.path.join(v["out"], fn))
print("total", total, "mezcla", round(mix_dur, 2), [(s["t0"], s["d"]) for s in scenes])
