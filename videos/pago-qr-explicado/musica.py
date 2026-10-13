#!/usr/bin/env python3
"""Colchón musical minimalista generado en local (sin licencias): pad cálido + arpegio suave.
Uso: python3 musica.py [segundos]  -> assets/musica.mp3"""
import array, math, os, subprocess, sys, wave
HERE = os.path.dirname(os.path.abspath(__file__))
SECS = float(sys.argv[1]) if len(sys.argv) > 1 else 40
SR = 44100
BPM = 84
BAR = 4 * 60 / BPM
N = lambda m: 440 * 2 ** ((m - 69) / 12)
CH = [[48, 52, 55, 59], [45, 48, 52, 55], [41, 45, 48, 52], [43, 47, 50, 52]]  # Cmaj7 Am7 Fmaj7 G6
PAT = [0, 2, 1, 3, 2, 1, 3, 2]
total = int(SECS * SR)
buf = array.array('f', [0.0]) * total
step = BAR / 8
for b in range(int(SECS / BAR) + 1):
    ch = CH[b % 4]
    t0 = b * BAR
    # pad: cada acorde dura una barra con cruce de 0.6 s
    s0, s1 = int((t0 - .6) * SR), int((t0 + BAR + .6) * SR)
    for m in ch:
        f = N(m)
        for i in range(max(s0, 0), min(s1, total)):
            t = i / SR - t0
            e = min(max((t + .6) / .9, 0), 1) * min(max((BAR + .6 - t) / .9, 0), 1)
            if e <= 0: continue
            buf[i] += .035 * e * (math.sin(6.2832 * f * t) + .5 * math.sin(6.2832 * f * 1.004 * t) + .15 * math.sin(6.2832 * f * 2 * t))
    # arpegio
    for k in range(8):
        f = N(ch[PAT[k]] + 12)
        ts = t0 + k * step
        n0 = int(ts * SR)
        for i in range(n0, min(n0 + int(1.4 * SR), total)):
            t = (i - n0) / SR
            buf[i] += .11 * math.exp(-t * 4.5) * (math.sin(6.2832 * f * t) + .3 * math.sin(6.2832 * f * 2 * t))
pk = max(abs(x) for x in buf) or 1
raw = os.path.join(HERE, "assets", "musica.wav")
with wave.open(raw, "wb") as w:
    w.setnchannels(1); w.setsampwidth(2); w.setframerate(SR)
    w.writeframes(array.array('h', [int(x / pk * 20000) for x in buf]).tobytes())
out = os.path.join(HERE, "assets", "musica.mp3")
subprocess.check_call(["ffmpeg", "-v", "error", "-y", "-i", raw, "-af", "lowpass=f=5200,aecho=0.8:0.6:380|620:0.28|0.18,aformat=channel_layouts=stereo", "-b:a", "128k", out])
os.remove(raw)
print("ok", out)
