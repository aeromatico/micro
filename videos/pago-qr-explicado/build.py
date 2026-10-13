#!/usr/bin/env python3
"""Genera la locución (ElevenLabs, una por escena) y escribe index.html con los tiempos reales.
Uso: python3 build.py   (lee la clave de /root/.elevenlabs.env; nunca va al repo)."""
import json, os, subprocess, urllib.request

HERE = os.path.dirname(os.path.abspath(__file__))
VOICE = os.environ.get("VOICE_ID", "Xb7hH8MSUJpSbSDYk0k2")  # Alice; Matilda=XrExE9yKIg1WjnnlVkGX
SCENES = [
    "Ella es Ana. Tiene un negocio, y vende todos los días.",
    "Pero cuando un cliente quiere pagar, empiezan las dudas. ¿Ya transferiste? ¿Me mandas la captura?",
    "Con Market, Ana escribe el monto, y se genera un código QR solo para ese cobro.",
    "Se lo puede enviar por WhatsApp, y el cliente lo escanea con la app de su banco.",
    "El pago se confirma y queda registrado en su panel. Sin capturas. Sin dudas.",
    "Cobrar con QR, fácil. Market.",
]

def key():
    for l in open("/root/.elevenlabs.env"):
        if l.startswith("ELEVENLABS_API_KEY="):
            return l.split("=", 1)[1].strip()

def tts(i, text):
    out = os.path.join(HERE, "assets", f"s{i+1}.mp3")
    if os.path.exists(out):
        return out
    body = json.dumps({"text": text, "model_id": "eleven_multilingual_v2", "language_code": "es",
                       "voice_settings": {"stability": .5, "similarity_boost": .8, "style": .25, "use_speaker_boost": True}}).encode()
    req = urllib.request.Request(f"https://api.elevenlabs.io/v1/text-to-speech/{VOICE}?output_format=mp3_44100_128",
                                 data=body, headers={"xi-api-key": key(), "Content-Type": "application/json"})
    open(out, "wb").write(urllib.request.urlopen(req, timeout=90).read())
    return out

def dur(p):
    return float(subprocess.check_output(["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", p]).decode())

t, scenes = 0.3, []
for i, txt in enumerate(SCENES):
    d = dur(tts(i, txt))
    scenes.append({"t0": round(t, 2), "d": round(d, 2), "text": txt})
    t += 0.3 + d + 0.55
total = round(t + 0.9, 1)

tpl = open(os.path.join(HERE, "plantilla.tpl")).read()
audio = "\n".join(f'<audio id="voz{i+1}" src="assets/s{i+1}.mp3" data-start="{round(s["t0"]+0.3,2)}" data-duration="{s["d"]}" data-track-index="1"></audio>' for i, s in enumerate(scenes))
caps = "\n".join(f'<div class="cap" id="cap{i+1}">{s["text"]}</div>' for i, s in enumerate(scenes))
html = (tpl.replace("%%TOTAL%%", str(total)).replace("%%AUDIO%%", audio).replace("%%CAPS%%", caps)
        .replace("%%SCENES%%", json.dumps(scenes, ensure_ascii=False)))
open(os.path.join(HERE, "index.html"), "w").write(html)
print("total", total, [(s["t0"], s["d"]) for s in scenes])
