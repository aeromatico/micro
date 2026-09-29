#!/usr/bin/env python3
"""
docs-cli — elige qué documentación y guías interactivas generar con Claude. Nada se genera solo.

El backlog sale de `artisan docs:backlog` (sin IA, sin coste). Aquí lo ves, decides qué incorporar,
omites lo que no lo merece, añades instrucciones para guiar a Claude y lanzas SOLO lo marcado.
Todo lo generado queda como borrador (Docs → Guías interactivas / Artículos → Cambios pendientes).

  docs-cli                       modo interactivo (lista, elegir, omitir, anotar, generar)
  docs-cli list [--plugin P] [--all] [--json]
  docs-cli generate <id…> [--note "texto"] [--yes]      ids: guide:guia-pay-x · doc:pay · bootstrap:notify
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
COST = {"guide": 0.35, "doc": 0.6, "bootstrap": 2.0}     # estimación por elemento con Sonnet (USD)
KIND_LABEL = {"guide": "guía", "doc": "doc", "bootstrap": "doc nueva"}
STATE_LABEL = {"missing": "sin guía", "stale": "cambió", "outdated": "desactualizada", "undocumented": "sin documentar"}


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
def render(items):
    if not items:
        print("Nada pendiente: documentación y guías al día.")
        return
    print(f"\n{'#':>3}  {'TIPO':<9} {'PLUGIN':<10} {'ELEMENTO':<38} MOTIVO")
    for i, it in enumerate(items, 1):
        if it["kind"] == "guide":
            name = f"{it['name']} ({it['controller']})" if it["name"] != it["controller"] else it["controller"]
        else:
            name = "documentación"
        note = f"  ✎ {it['note']}" if it.get("note") else ""
        pend = "  [ya hay borrador sin revisar]" if it.get("pending") else ""
        print(f"{i:>3}  {KIND_LABEL[it['kind']]:<9} {it['plugin']:<10} {name[:38]:<38} {it['reason']}{pend}{note}")
    total = sum(COST[i["kind"]] for i in items)
    print(f"\n{len(items)} elemento(s). Generar TODO costaría ~${total:.2f} (Sonnet). Por elemento: guía ~$0.35, doc ~$0.60, doc nueva ~$2.")


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


# ─────────────────────────── modo interactivo ───────────────────────────
HELP = """Comandos:
  <números>        elegir para generar, ej. 1,3,5-7 · all
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
                st = W.load_state()
                generate(c, st, sel)
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
            do_skip(chosen)
            return 0
        return generate(c, W.load_state(), chosen, assume_yes=args.yes)
    if args.cmd == "unskip":
        do_unskip(args.ids, items)
        return 0


if __name__ == "__main__":
    sys.exit(main())
