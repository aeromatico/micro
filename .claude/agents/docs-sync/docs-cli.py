#!/usr/bin/env python3
"""
docs-cli — elige qué documentación y guías interactivas generar con Claude. Nada se genera solo.

El backlog sale de `artisan docs:backlog` (sin IA, sin coste). Aquí lo ves, decides qué incorporar,
omites lo que no lo merece, añades instrucciones para guiar a Claude y lanzas SOLO lo marcado.
Todo lo generado queda como borrador (Docs → Guías interactivas / Artículos → Cambios pendientes).

  docs-cli                       modo interactivo (lista, elegir, omitir, anotar, generar)
  docs-cli list [--plugin P] [--all] [--json]
  docs-cli generate <id…> [--note "texto"] [--yes]      ids: guide:guia-pay-x · doc:pay · bootstrap:notify · service:22
  docs-cli generate service:22 --what dgo [--forms all|slug,…] [--apply]   d=documentación g=guías o=página del servicio
  docs-cli servicio <id> [--si] [--aplicar]             ★ guía paso a paso para completar UN servicio (recomendado)
  docs-cli offer show|apply|revert <id-servicio>         revisar / aplicar / deshacer la propuesta de página
  docs-cli skip <id…>            no necesita guía / actualización (persistente, versionado en git)
  docs-cli unskip <id…>
  docs-cli scan                  recalcula el backlog y actualiza el contador del menú (lo usa el cron)
"""
import argparse
import importlib.util
import json
import re
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location("docs_sync_watch", HERE / "docs-sync-watch.py")
W = importlib.util.module_from_spec(spec)
spec.loader.exec_module(W)

ROOT = W.ROOT
EXCLUDE = ROOT / "plugins/aero/docs/content/guides/exclude.json"
SKIPDOCS = ROOT / "plugins/aero/docs/content/docs-skip.json"
COST = {"guide": 0.35, "doc": 0.6, "bootstrap": 2.0, "offer": 1.0}     # estimación por elemento con Sonnet (USD)
KIND_LABEL = {"guide": "guía", "doc": "doc", "bootstrap": "doc nueva", "service": "servicio"}
STATE_LABEL = {"missing": "sin guía", "stale": "cambió", "outdated": "desactualizada", "undocumented": "sin documentar", "current": "al día"}


def backlog(c, all_plugins=False):
    args = ["docs:backlog"] + (["--all"] if all_plugins else [])
    return W.artisan_json(c, *args, timeout=240)


def read_json(path, default):
    try:
        return json.loads(path.read_text())
    except (OSError, ValueError):
        return default


def write_json(path, data):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n")


# ─────────────────────────── presentación ───────────────────────────
def item_cost(it):
    if it["kind"] != "service":
        return COST[it["kind"]]
    total = COST["offer"] if it["offer_state"] == "missing" else 0
    for p in it["plugins"]:
        total += COST["bootstrap"] if p["doc_state"] == "undocumented" else COST["doc"] if p["doc_state"] == "outdated" else 0
        total += COST["guide"] * sum(1 for f in p["forms"] if f["state"] != "current")
    return total


def render(items):
    if not items:
        print("Nada pendiente: documentación y guías al día.")
        return
    print(f"\n{'#':>3}  {'TIPO':<9} {'PLUGIN':<12} {'ELEMENTO':<36} MOTIVO")
    for i, it in enumerate(items, 1):
        if it["kind"] == "service":
            name = f"{it['title']} (id {it['service_id']})" + ("" if it.get("public") else " · no público")
            plug = "+".join(p["plugin"] for p in it["plugins"])
        elif it["kind"] == "guide":
            name = f"{it['name']} ({it['controller']})" if it["name"] != it["controller"] else it["controller"]
            plug = it["plugin"]
        else:
            name, plug = "documentación", it["plugin"]
        note = f"  ✎ {it['note']}" if it.get("note") else ""
        pend = "  [ya hay borrador sin revisar]" if it.get("pending") else ""
        print(f"{i:>3}  {KIND_LABEL[it['kind']]:<9} {plug[:12]:<12} {name[:36]:<36} {it['reason']}{pend}{note}")
    total = sum(item_cost(i) for i in items)
    print(f"\n{len(items)} elemento(s). Generar TODO costaría ~${total:.2f} (Sonnet). Por elemento: guía ~$0.35, doc ~$0.60, doc nueva ~$2, página de servicio ~$1.")
    if any(i["kind"] == "service" for i in items):
        print("Los servicios (identificados por su plugin «Construido con») te preguntan qué crear: documentación, guías y/o la página.")


def parse_selection(text, n):
    """'1,3,5-7' o 'all' → conjunto de índices base 1."""
    text = text.strip().lower()
    if text in ("all", "todo", "*"):
        return set(range(1, n + 1))
    out = set()
    for part in re.split(r"[,\s]+", text):
        if not part:
            continue
        m = re.fullmatch(r"(\d+)(?:-(\d+))?", part)
        if not m:
            raise ValueError(f"no entiendo «{part}»")
        a, b = int(m.group(1)), int(m.group(2) or m.group(1))
        out.update(range(min(a, b), max(a, b) + 1))
    bad = [x for x in out if not 1 <= x <= n]
    if bad:
        raise ValueError(f"fuera de rango: {sorted(bad)}")
    return out


# ─────────────────────────── acciones ───────────────────────────
def do_skip(items):
    ex = read_json(EXCLUDE, [])
    sk = read_json(SKIPDOCS, {})
    for it in items:
        if it["kind"] == "guide":
            key = f"{it['plugin']}/{it['controller']}"
            if key not in ex:
                ex.append(key)
        else:
            sk[it["plugin"]] = it["current"]
    write_json(EXCLUDE, sorted(set(ex)))
    write_json(SKIPDOCS, sk)
    print(f"Omitidos {len(items)}. Se guardó en {EXCLUDE.relative_to(ROOT)} / {SKIPDOCS.relative_to(ROOT)} (versionado en git). "
          "Las guías omitidas vuelven con `unskip`; la documentación omitida reaparece sola en la siguiente versión del plugin.")


def do_unskip(ids, all_items):
    ex = read_json(EXCLUDE, [])
    sk = read_json(SKIPDOCS, {})
    for raw in ids:
        kind, _, key = raw.partition(":")
        if kind == "guide":
            m = re.fullmatch(r"guia-([a-z0-9]+)-(.+)", key)
            if m and f"{m.group(1)}/{m.group(2)}" in ex:
                ex.remove(f"{m.group(1)}/{m.group(2)}")
        else:
            sk.pop(key, None)
    write_json(EXCLUDE, sorted(set(ex)))
    write_json(SKIPDOCS, sk)
    print("Restaurados. Vuelven a aparecer en el backlog.")


def manual_blockers(c, st):
    """Solo lo que impediría una corrida correcta (no las esperas de calma: aquí manda la persona)."""
    out = []
    if st.get("paused"):
        out.append(f"vigilante PAUSADO: {(st.get('alert') or {}).get('why', 'manual')} (`docs-sync-watch.py resume`)")
    if not Path(W.CLAUDE).exists():
        out.append("no encuentro el binario de claude")
    if W.lock_holder():
        out.append("ya hay una generación corriendo")
    if (ROOT / ".git" / "index.lock").exists():
        out.append("git ocupado (index.lock); reintenta en unos segundos")
    return out


def build_plan_from(selected):
    """Agrupa los elementos elegidos por plugin en el formato que espera watcher.execute()."""
    by = {}
    for it in selected:
        cur = by.setdefault(it["plugin"], {"plugin": it["plugin"], "current": it["current"] if "current" in it else W.plugins_current().get(it["plugin"], "?"),
                                           "documented": None, "docs": False, "guides": [], "notes": []})
        if it["kind"] in ("doc", "bootstrap"):
            cur["docs"] = True
            cur["bootstrap"] = it["kind"] == "bootstrap"
            cur["documented"] = it.get("documented")
            cur["current"] = it["current"]
            cur["notes"] = W.version_notes_since(it["plugin"], it.get("documented"))
            if it.get("note"):
                cur["doc_note"] = it["note"]
        else:
            g = dict(it)
            cur["guides"].append(g)
    return list(by.values())


def generate(c, st, selected, assume_yes=False):
    if not selected:
        print("Nada seleccionado.")
        return 1
    blockers = manual_blockers(c, st)
    if blockers:
        print("No puedo generar ahora:\n - " + "\n - ".join(blockers))
        return 1
    est = sum(COST[i["kind"]] for i in selected)
    print("\nSe generará (todo como BORRADOR):")
    for it in selected:
        print(f"  · {KIND_LABEL[it['kind']]:<9} {it['id']}" + (f"   ✎ {it['note']}" if it.get("note") else ""))
    print(f"Coste estimado: ~${est:.2f} · tiempo: ~{max(1, round(len(selected) * 1.5))} min")
    if not assume_yes and input("¿Continuar? [s/N] ").strip().lower() not in ("s", "si", "sí", "y", "yes"):
        print("Cancelado.")
        return 0
    plan = {"todo": build_plan_from(selected), "deferred": [], "undocumented": [], "skipped": {}}
    bootstrap = any(i["kind"] == "bootstrap" for i in selected)
    W.QUIET = False
    W.execute(c, st, plan, bootstrap=bootstrap, dry=False)
    W.save_state(st)
    st2 = W.load_state()
    if st2.get("paused"):
        print("\n⚠ El vigilante quedó pausado:", (st2.get("alert") or {}).get("why"))
        return 1
    print("\nListo. Revisa los borradores en el backend: Docs → Guías interactivas / Artículos → Cambios pendientes.")
    return 0


# ─────────────────────────── servicios ───────────────────────────
def service_options(it):
    """Qué se puede crear para este servicio y en qué estado está cada cosa."""
    docs = [p for p in it["plugins"] if p["doc_state"] != "current"]
    forms = [(p, f) for p in it["plugins"] for f in p["forms"]]
    todo_forms = [f for _, f in forms if f["state"] != "current"]
    return {
        "d": ("documentación", ", ".join(f"{p['plugin']} {STATE_LABEL[p['doc_state']]}" for p in docs) if docs else "al día", bool(docs)),
        "g": ("guías interactivas", f"{len(todo_forms)} de {len(forms)} formulario(s) por generar" if forms else "el plugin no tiene formularios candidatos", bool(forms)),
        "o": ("página del servicio", "vacía" if it["offer_state"] == "missing" else "completa (se propondría una versión nueva)", True),
    }


def ask_service(it, forms_arg=None):
    """Pregunta qué crear. Devuelve (what, guías_elegidas) o None si se cancela."""
    opts = service_options(it)
    print(f"\nServicio #{it['service_id']} · {it['title']}  ·  plugin «Construido con»: {', '.join(p['code'] for p in it['plugins'])}")
    for k, (label, state, avail) in opts.items():
        print(f"  {k}  {label:<20} {state}" + ("" if avail else "  (nada que crear)"))
    raw = input("¿Qué creamos? d, g, o (ej. dg) · t = todo lo pendiente · enter = cancelar > ").strip().lower()
    if not raw:
        return None
    what = "".join(k for k in "dgo" if k in raw) if raw != "t" else "".join(k for k, (_, _, a) in opts.items() if a and (k != "o" or it["offer_state"] == "missing"))
    if not what:
        print("No entendí; cancelado.")
        return None
    return what, (choose_forms(it, forms_arg) if "g" in what else [])


def choose_forms(it, forms_arg=None):
    forms = [(p, f) for p in it["plugins"] for f in p["forms"]]
    if not forms:
        return []
    if forms_arg is not None:
        if forms_arg == "all":
            return [f["slug"] for _, f in forms]
        wanted = [x.strip() for x in forms_arg.split(",") if x.strip()]
        return [f["slug"] for _, f in forms if f["slug"] in wanted or f["controller"] in wanted]
    print("\nFormularios del plugin (se generan y se muestran en la página del servicio):")
    for i, (p, f) in enumerate(forms, 1):
        extra = "  · ya vinculada" if f.get("linked") else ""
        print(f"  {i:>2}. {f['name']} ({f['controller']}) — {STATE_LABEL[f['state']]}{extra}")
    raw = input("Elige cuáles (ej. 1,3 · all · enter = ninguno) > ").strip()
    if not raw:
        return []
    return [forms[k - 1][1]["slug"] for k in sorted(parse_selection(raw, len(forms)))]


def service_flow(c, it, what, guides, note=None, assume_yes=False, apply_offer=False):
    st = W.load_state()
    blockers = manual_blockers(c, st)
    if blockers:
        print("No puedo generar ahora:\n - " + "\n - ".join(blockers))
        return 1

    step1, cost = [], 0.0
    for p in it["plugins"]:
        if "d" in what and p["doc_state"] != "current":
            undoc = p["doc_state"] == "undocumented"
            step1.append({"id": f"{'bootstrap' if undoc else 'doc'}:{p['plugin']}", "kind": "bootstrap" if undoc else "doc", "plugin": p["plugin"],
                          "current": p["current"], "documented": p["documented"], "note": note})
            cost += COST["bootstrap" if undoc else "doc"]
        for f in p["forms"]:
            if f["slug"] in guides and f["state"] != "current":
                step1.append(dict(f, kind="guide", id="guide:" + f["slug"], plugin=p["plugin"], note=note))
                cost += COST["guide"]
    do_offer = "o" in what
    cost += COST["offer"] if do_offer else 0

    print(f"\nServicio «{it['title']}» — se generará (todo como BORRADOR / propuesta):")
    for x in step1:
        print(f"  · {KIND_LABEL[x['kind']]:<9} {x['id']}")
    linked = [g for g in guides]
    if linked:
        print(f"  · vincular al servicio: {len(linked)} guía(s) y los artículos de {', '.join(p['plugin'] for p in it['plugins'])}")
    if do_offer:
        print("  · página del servicio: PROPUESTA (el servicio no se modifica hasta que la apruebes aquí)")
    if note:
        print(f"  ✎ {note}")
    if not (step1 or linked or do_offer):
        print("Nada que hacer.")
        return 0
    print(f"Coste estimado: ~${cost:.2f}")
    if not assume_yes and input("¿Continuar? [s/N] ").strip().lower() not in ("s", "si", "sí", "y", "yes"):
        print("Cancelado.")
        return 0
    W.QUIET = False

    # 1) documentación + guías
    if step1:
        plan = {"todo": build_plan_from(step1), "deferred": [], "undocumented": [], "skipped": {}}
        if not W.execute(c, st, plan, bootstrap=False, dry=False):
            print("\n⚠ Falló la generación de documentación/guías; no sigo con el resto.")
            return 1
        W.save_state(st)

    # 2) vincular (solo añade vínculos)
    link_args = ["services:link-docs", str(it["service_id"])]
    if "d" in what or do_offer:
        link_args += [f"--plugin={p['plugin']}" for p in it["plugins"]]
    if linked:
        link_args += ["--guides=" + ",".join(linked)]
    if len(link_args) > 2:
        r = W.sh(["sudo", "-u", c["web_user"], W.PHP_BIN, "artisan", *link_args], timeout=120)
        print("Vínculos:", (r.stdout or r.stderr).strip().splitlines()[-1] if (r.stdout or r.stderr).strip() else "(sin salida)")

    # 3) página del servicio
    if do_offer:
        offer = {"id": it["service_id"], "slug": it["slug"], "name": it["title"], "public": it.get("public"), "note": note}
        plan = {"todo": [{"plugin": it["plugins"][0]["plugin"], "current": it["plugins"][0]["current"], "documented": None, "docs": False,
                          "guides": [], "notes": [], "offer": offer}], "deferred": [], "undocumented": [], "skipped": {}}
        if not W.execute(c, W.load_state(), plan, bootstrap=False, dry=False):
            print("\n⚠ No se pudo generar la propuesta de la página.")
            return 1
        return offer_review(c, it["service_id"], it["slug"], apply_now=apply_offer, assume_yes=assume_yes)
    print("\nListo. Revisa los borradores en el backend: Docs → Guías interactivas / Artículos → Cambios pendientes.")
    return 0


def offer_review(c, service_id, slug, apply_now=False, assume_yes=False):
    try:
        info = W.artisan_json(c, "services:offer-apply", str(service_id), "--show")
    except RuntimeError as e:
        print("No hay propuesta para revisar:", e)
        return 1
    print("\n── Propuesta de la página ──")
    print("Resumen:", info["summary"])
    print(f"Descripción: {info['description'][:300]}…")
    print(f"{info['features']} características · {info['requirements']} requisitos · HTML {info['html_bytes']} bytes · {info['docs']} documento(s) enlazado(s)")
    print("Servicio público:", "SÍ (se vería de inmediato al aplicar)" if info["public"] else "no")
    print("Propuesta completa (JSON):", info["file"])
    if not apply_now:
        if assume_yes or not sys.stdin.isatty():
            print(f"\nNo se aplicó. Para aplicarla: docs-cli offer apply {service_id}")
            return 0
        if input("\n¿Aplicarla al servicio ahora? (se puede deshacer con `docs-cli offer revert`) [s/N] ").strip().lower() not in ("s", "si", "sí", "y", "yes"):
            print(f"Queda como propuesta. Para aplicarla luego: docs-cli offer apply {service_id}")
            return 0
    return offer_apply(c, service_id, slug)


def offer_apply(c, service_id, slug):
    r = W.sh(["sudo", "-u", c["web_user"], W.PHP_BIN, "artisan", "services:offer-apply", str(service_id)], timeout=120)
    print((r.stdout + r.stderr).strip()[-300:])
    if r.returncode != 0:
        return 1
    print("Recompilando el CSS del tema (las clases Tailwind de la página viven en la BD)…")
    b = subprocess.run(["npm", "run", "build"], cwd=ROOT / "themes/master", capture_output=True, text=True, timeout=300)
    print("CSS:", "ok" if b.returncode == 0 else "FALLÓ\n" + b.stderr[-300:])
    W.sh(["sudo", "-u", c["web_user"], W.PHP_BIN, "artisan", "cache:clear"], timeout=60)
    print(f"Listo: https://market.com.bo/plugin/{slug}")
    return 0


def ask_yn(question, default):
    """Pregunta sí/no con valor por defecto (enter = default)."""
    hint = "[S/n]" if default else "[s/N]"
    raw = input(f"{question} {hint} ").strip().lower()
    if not raw:
        return default
    return raw in ("s", "si", "sí", "y", "yes")


def servicio_wizard(c, it, assume_yes=False, apply_offer=False):
    """Guía paso a paso para completar UN servicio: qué falta, qué crear, y aplicar al final."""
    forms = [f for p in it["plugins"] for f in p["forms"]]
    todo_docs = [p for p in it["plugins"] if p["doc_state"] != "current"]
    todo_forms = [f for f in forms if f["state"] != "current"]
    page_missing = it["offer_state"] == "missing"

    print(f"\n── Servicio #{it['service_id']} · {it['title']} ──")
    print(f"Plugin «Construido con»: {', '.join(p['code'] for p in it['plugins']) or 'ninguno (¡falta ligarlo en Backend → Servicios!)'}")
    print(f"Público: {'sí' if it.get('public') else 'no'}")
    print("Pendiente:")
    print(f"  · Página de venta: {'vacía' if page_missing else 'ya tiene una versión'}")
    print(f"  · Documentación: {', '.join(p['plugin'] + ' desactualizada' for p in todo_docs) or 'al día'}")
    print(f"  · Guías de formularios: {len(todo_forms)} de {len(forms)} por generar")

    if not it["plugins"]:
        print("\nNo puedo seguir: el servicio no tiene plugin «Construido con». Ligálo primero en el backend.")
        return 1
    if not assume_yes and not sys.stdin.isatty():
        print("Sin terminal interactiva: usa `docs-cli servicio <id> --si` (o `docs-cli generate service:<id> --what o --yes`).")
        return 1
    if assume_yes:
        # sin preguntas: página si falta y documentación si está desactualizada; las guías solo con --forms
        what = ("o" if page_missing else "") + ("d" if todo_docs else "")
        guides = []
    else:
        print()
        what = ""
        if ask_yn("¿Escribir la página de venta (propuesta, no se publica sola)?", page_missing):
            what += "o"
        if todo_docs and ask_yn("¿Actualizar la documentación de los plugins?", True):
            what += "d"
        guides = []
        if forms and ask_yn(f"¿Generar guías interactivas de formularios ({len(forms)} disponibles)?", False):
            what += "g"
            guides = choose_forms(it, "all" if ask_yn("  ¿Todas las guías?", True) else None)
    if not what:
        print("Nada que crear. Listo.")
        return 0
    return service_flow(c, it, what, guides, assume_yes=assume_yes, apply_offer=apply_offer)


# ─────────────────────────── modo interactivo ───────────────────────────
HELP = """Comandos:
  <números>        elegir para generar, ej. 1,3,5-7 · all  (en un servicio pregunta: documentación, guías y/o página)
  n <nº> <texto>   añadir una instrucción para Claude en ese elemento, ej. n 3 enfócate en el flujo de pago
  s <números>      omitir (no necesita guía/actualización)
  f <plugin>       filtrar por plugin · f  (sin argumento) quita el filtro
  a                incluir también guías de plugins aún sin documentar
  r                recalcular      ?  ayuda      q  salir"""


def interactive(c):
    flt, allp, notes = None, False, {}
    items = backlog(c, allp)
    while True:
        view = [i for i in items if not flt or i["plugin"] == flt]
        for it in view:
            it["note"] = notes.get(it["id"])
        print("\033[2J\033[H" if sys.stdout.isatty() else "", end="")
        print(f"docs-cli · backlog{f' de {flt}' if flt else ''}{' (todos los plugins)' if allp else ''}")
        render(view)
        print("\nElige números para generar, o `?` para ver los comandos.")
        try:
            line = input("> ").strip()
        except (EOFError, KeyboardInterrupt):
            print()
            return 0
        if not line:
            continue
        cmd, _, rest = line.partition(" ")
        try:
            if line in ("q", "quit", "salir"):
                return 0
            if line == "?":
                print(HELP)
                input("\n(enter para volver)")
            elif line == "r":
                items = backlog(c, allp)
            elif line == "a":
                allp = not allp
                items = backlog(c, allp)
            elif cmd == "f":
                flt = rest.strip() or None
            elif cmd == "n":
                num, _, text = rest.strip().partition(" ")
                idx = parse_selection(num, len(view))
                for k in idx:
                    notes[view[k - 1]["id"]] = text.strip() or None
            elif cmd == "s":
                idx = parse_selection(rest, len(view))
                do_skip([view[k - 1] for k in sorted(idx)])
                input("(enter)")
                items = backlog(c, allp)
            else:
                idx = parse_selection(line, len(view))
                sel = [dict(view[k - 1], note=notes.get(view[k - 1]["id"])) for k in sorted(idx)]
                plain = [x for x in sel if x["kind"] != "service"]
                for svc in [x for x in sel if x["kind"] == "service"]:
                    ans = ask_service(svc)
                    if ans:
                        service_flow(c, svc, ans[0], ans[1], note=svc.get("note"))
                        input("\n(enter para continuar)")
                if plain:
                    generate(c, W.load_state(), plain)
                input("\n(enter para volver)")
                items = backlog(c, allp)
        except ValueError as e:
            print("⚠", e)
            input("(enter)")


# ─────────────────────────── main ───────────────────────────
def main():
    ap = argparse.ArgumentParser(prog="docs-cli", description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = ap.add_subparsers(dest="cmd")
    ls = sub.add_parser("list"); ls.add_argument("--plugin"); ls.add_argument("--all", action="store_true"); ls.add_argument("--json", action="store_true")
    g = sub.add_parser("generate"); g.add_argument("ids", nargs="+"); g.add_argument("--note"); g.add_argument("--yes", action="store_true")
    g.add_argument("--what", help="solo servicios: d=documentación g=guías o=página del servicio (ej. dgo)")
    g.add_argument("--forms", help="solo servicios con g: 'all' o lista de slugs/controladores separados por coma")
    g.add_argument("--apply", action="store_true", help="solo servicios con o: aplicar la página al servicio sin preguntar")
    sv = sub.add_parser("servicio", help="completar un servicio paso a paso")
    sv.add_argument("service", help="id del servicio (ej. 22)")
    sv.add_argument("--si", action="store_true", help="sin preguntas: página y documentación pendientes")
    sv.add_argument("--aplicar", action="store_true", help="aplicar la página al terminar sin preguntar")
    of = sub.add_parser("offer"); of.add_argument("action", choices=["show", "apply", "revert"]); of.add_argument("service")
    sk = sub.add_parser("skip"); sk.add_argument("ids", nargs="+")
    us = sub.add_parser("unskip"); us.add_argument("ids", nargs="+")
    sub.add_parser("scan")
    args = ap.parse_args()
    c = W.cfg()
    W.STATE_DIR.mkdir(parents=True, exist_ok=True)

    if args.cmd is None:
        if not sys.stdin.isatty():
            render(backlog(c))
            return 0
        return interactive(c)
    if args.cmd == "list":
        items = [i for i in backlog(c, args.all) if not args.plugin or i["plugin"] == args.plugin]
        if args.json:
            print(json.dumps(items, ensure_ascii=False, indent=2))
        else:
            render(items)
        return 0
    if args.cmd == "scan":
        items = backlog(c)
        subprocess.run(["sudo", "-u", c["web_user"], W.PHP_BIN, "artisan", "docs:backlog", "--cache"], cwd=ROOT, capture_output=True, timeout=240)
        print(f"{len(items)} elemento(s) por generar (guías {sum(i['kind'] == 'guide' for i in items)}, docs {sum(i['kind'] != 'guide' for i in items)}).")
        return 0
    items = {i["id"]: i for i in backlog(c, True)}
    if args.cmd in ("generate", "skip"):
        missing = [x for x in args.ids if x not in items]
        if missing:
            print("No están en el backlog:", ", ".join(missing), "\n(usa `docs-cli list --all` para ver los ids)")
            return 1
        chosen = [dict(items[x], note=args.note) if args.cmd == "generate" else items[x] for x in args.ids]
        if args.cmd == "skip":
            do_skip([x for x in chosen if x["kind"] != "service"])
            return 0
        rc = 0
        for svc in [x for x in chosen if x["kind"] == "service"]:
            if args.what:
                what = "".join(k for k in "dgo" if k in args.what.lower())
                guides = choose_forms(svc, args.forms or "all") if "g" in what else []
            elif args.yes:
                print(f"{svc['id']}: indica qué crear con --what (d, g, o).")
                return 1
            else:
                ans = ask_service(svc, args.forms)
                if not ans:
                    continue
                what, guides = ans
            rc |= service_flow(c, svc, what, guides, note=args.note, assume_yes=args.yes, apply_offer=args.apply)
        plain = [x for x in chosen if x["kind"] != "service"]
        return rc | (generate(c, W.load_state(), plain, assume_yes=args.yes) if plain else 0)
    if args.cmd == "servicio":
        svc = next((i for i in items.values() if i["kind"] == "service" and str(i["service_id"]) == args.service), None)
        if not svc:
            print(f"No hay un servicio con id {args.service} en el backlog (usa `docs-cli list --all`).")
            return 1
        return servicio_wizard(c, svc, assume_yes=args.si, apply_offer=args.aplicar)
    if args.cmd == "offer":
        sid = args.service if args.service.isdigit() else None
        row = W.artisan_json(c, "services:offer-apply", args.service, "--show") if args.action == "show" else None
        if args.action == "show":
            print(json.dumps(row, ensure_ascii=False, indent=2))
            return 0
        slug = args.service
        if sid:
            svc = next((i for i in items.values() if i["kind"] == "service" and str(i["service_id"]) == sid), None)
            slug = svc["slug"] if svc else args.service
        if args.action == "apply":
            return offer_review(c, args.service, slug, apply_now=True)
        r = W.sh(["sudo", "-u", c["web_user"], W.PHP_BIN, "artisan", "services:offer-apply", args.service, "--revert"], timeout=120)
        print((r.stdout + r.stderr).strip()[-300:])
        return r.returncode
    if args.cmd == "unskip":
        do_unskip(args.ids, items)
        return 0


if __name__ == "__main__":
    sys.exit(main())
