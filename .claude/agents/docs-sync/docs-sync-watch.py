#!/usr/bin/env python3
"""
docs-sync-watch (Claude) — mantiene al día la documentación del tenant y sus guías interactivas
llamando a `claude -p` SOLO cuando hace falta y sin estorbar a nadie.

Sustituye al vigilante de opencode (.opencode/docs-sync-watch.py). Mismo criterio determinista, sin IA:

  * documentación: un plugin ya documentado cuya versión documentada (aero_docs_articles, incluidas las
    ediciones pendientes de aprobar) es menor que la última de su updates/version.yaml;
  * guías: un formulario principal sin guía, o cuyo `source_hash` (YAML + opciones del modelo + partials)
    cambió desde que se generó (`artisan docs:guides`). Cubre cambios sin bump de versión;
  * Claude se invoca solo para esos plugins, con el "brief" ya calculado;
  * nunca corre si alguien está editando plugins/aero/docs o el plugin fuente; un solo proceso a la vez
    (lock que git-agent respeta), tope de corridas por día, backoff, pausa tras varios fallos;
  * todo lo que produce es BORRADOR: `docs:import` deja artículos nuevos sin publicar y las ediciones
    como cambios pendientes; las guías quedan pendientes de revisión en Docs → Guías interactivas;
  * verifica que solo se escribió bajo plugins/aero/docs; si tocó código de otros plugins se PAUSA;
  * un fallo de autenticación de Claude pausa el vigilante (no gasta los reintentos de ningún plugin).

Uso:  docs-sync-watch.py status | run [--dry-run] [--force] | bootstrap <plugin…> |
                         pause | resume | reset <plugin> | install-cron | uninstall-cron
"""
import argparse
import datetime as dt
import fcntl
import json
import os
import re
import shutil
import subprocess
import sys
import time
from pathlib import Path

SCRIPT_DIR = Path(__file__).resolve().parent
ROOT = Path(os.environ.get("DOCS_SYNC_REPO", SCRIPT_DIR.parents[2])).resolve()
STATE_DIR = ROOT / ".git" / "docs-sync-claude"   # dentro de .git: nunca se versiona
STATE_FILE = STATE_DIR / "state.json"
LOG_FILE = STATE_DIR / "docs-sync.log"
LOCK_FILE = STATE_DIR / "agent.lock"              # git-agent lo respeta (no commitea docs mientras exista)
CLAUDE = os.environ.get("CLAUDE_BIN") or shutil.which("claude") or "/root/.local/bin/claude"
SETTINGS_FILE = SCRIPT_DIR / "settings.json"
AGENT_FILE = SCRIPT_DIR / "agent.md"
PHP_BIN = os.environ.get("PHP_BIN", "/www/server/php/84/bin/php")
CRON_MARK = "# docs-sync-claude (auto)"

DEFAULTS = {
    "dry_run": True,                 # arranca en simulación: registra qué haría, sin llamar al modelo
    "docs_quiet_minutes": 15,        # nadie editó plugins/aero/docs en este tiempo
    "source_quiet_minutes": 30,      # el plugin a documentar no se está editando
    "max_plugins_per_run": 3,
    "max_runs_per_day": 4,
    "retry_backoff_minutes": 180,
    "max_failures": 3,
    "agent_timeout_minutes": 45,
    "web_user": "www",
    "exclude_plugins": [],
    "cron_every_minutes": 30,
    "model": "sonnet",
    "max_turns": 80,
    "max_budget_usd": 4,             # tope de gasto por corrida (claude --max-budget-usd)
    "guides_enabled": True,
    "max_guides_per_run": 4,         # acota el coste de una corrida: el resto entra en las siguientes
}
ALLOWED_WRITE_PREFIXES = ("plugins/aero/docs/",)
# Zona de riesgo real: código de plugins. Fuera de "plugins/" (temas, bin, .claude, config…) el agente
# no tiene permiso de escritura (ver settings.json); si algo cambia ahí durante la corrida, casi siempre es OTRA sesión
# editando en paralelo por coincidencia de horario, no el agente. Se audita mas no se pausa el mundo.
RISK_PREFIX = "plugins/"
QUIET = False


def cfg():
    c = dict(DEFAULTS)
    p = SCRIPT_DIR / "docs-sync.json"
    if p.exists():
        c.update(json.loads(p.read_text()))
    return c


def log(msg, level="INFO"):
    line = f"{dt.datetime.now():%Y-%m-%d %H:%M:%S} [{level}] {msg}"
    if not QUIET or level in ("WARN", "ERROR"):
        print(line, flush=True)
    try:
        LOG_FILE.parent.mkdir(parents=True, exist_ok=True)
        if LOG_FILE.exists() and LOG_FILE.stat().st_size > 5 * 1024 * 1024:
            LOG_FILE.replace(LOG_FILE.with_suffix(".log.1"))
        with LOG_FILE.open("a") as fh:
            fh.write(line + "\n")
    except OSError:
        pass


def sh(cmd, cwd=None, timeout=120, env=None, check=False):
    p = subprocess.run(cmd, cwd=cwd or ROOT, capture_output=True, text=True, timeout=timeout, env=env)
    if check and p.returncode:
        raise RuntimeError(f"{' '.join(cmd)[:120]} → {p.stderr.strip()[:300]}")
    return p


def load_state():
    if STATE_FILE.exists():
        try:
            return json.loads(STATE_FILE.read_text())
        except ValueError:
            pass
    return {"checked": {}, "fail": {}, "runs": {}, "paused": False, "alert": None}


def save_state(st):
    STATE_DIR.mkdir(parents=True, exist_ok=True)
    st["updated"] = dt.datetime.now().isoformat(timespec="seconds")
    STATE_FILE.write_text(json.dumps(st, ensure_ascii=False, indent=2))


def vkey(v):
    return tuple(int(x) for x in v.split("."))


def semver_all(text):
    return sorted(set(re.findall(r"^(\d+\.\d+\.\d+):", text, re.M)), key=vkey)


# ─────────────────────────── inventario ───────────────────────────
def plugins_current():
    out = {}
    for vy in sorted((ROOT / "plugins" / "aero").glob("*/updates/version.yaml")):
        vs = semver_all(vy.read_text())
        if vs:
            out[vy.parent.parent.name] = vs[-1]
    return out


def artisan_json(c, *args, timeout=120):
    """Ejecuta `artisan <args>` como el usuario web y devuelve el último JSON impreso."""
    r = sh(["sudo", "-u", c["web_user"], PHP_BIN, "artisan", *args], timeout=timeout)
    for line in reversed((r.stdout or "").strip().splitlines()):
        line = line.strip()
        if line[:1] in "[{":
            try:
                return json.loads(line)
            except ValueError:
                break
    raise RuntimeError(f"artisan {' '.join(args)} no devolvió JSON: {(r.stdout + r.stderr).strip()[:200]}")


def documented_versions(c):
    """{plugin: (n_articulos, versión_documentada_mínima|None)} (globales; cuenta ediciones pendientes)."""
    fake = os.environ.get("DOCS_SYNC_DOCUMENTED_JSON")          # solo para pruebas
    if fake:
        return {k: (v[0], v[1]) for k, v in json.loads(Path(fake).read_text()).items()}
    return {p: (v["n"], v["min"]) for p, v in artisan_json(c, "docs:versions").items()}


def guide_forms(c):
    """Formularios con guía faltante o desactualizada: {plugin: [form…]} (sin IA, por hash de fuentes)."""
    fake = os.environ.get("DOCS_SYNC_GUIDES_JSON")              # solo para pruebas
    rows = json.loads(Path(fake).read_text()) if fake else artisan_json(c, "docs:guides", timeout=180)
    out = {}
    for r in rows:
        if r["state"] in ("missing", "stale"):
            out.setdefault(r["plugin"], []).append(r)
    return out


def version_notes_since(plugin, documented):
    """Notas de updates/version.yaml de las versiones POSTERIORES a la documentada."""
    text = (ROOT / "plugins/aero" / plugin / "updates/version.yaml").read_text()
    notes, cur = [], None
    for line in text.splitlines():
        m = re.match(r"^(\d+\.\d+\.\d+):\s*(?:\"(.*)\")?\s*$", line)
        if m:
            cur = m.group(1)
            if m.group(2) and (not documented or vkey(cur) > vkey(documented)):
                notes.append((cur, m.group(2)))
                cur = None
            continue
        m2 = re.match(r'^\s+-\s+"(.*)"\s*$', line) or re.match(r"^\s+-\s+(?!\")(.+?)\s*$", line)
        if m2 and cur and not m2.group(1).endswith(".php"):
            if not documented or vkey(cur) > vkey(documented):
                notes.append((cur, m2.group(1)))
            cur = None
    return notes


# ─────────────────────────── guardas ───────────────────────────
def lock_holder():
    """None si no hay lock vigente; si es viejo/huérfano lo limpia."""
    if not LOCK_FILE.exists():
        return None
    try:
        info = json.loads(LOCK_FILE.read_text())
        pid = int(info.get("pid", 0))
        alive = pid > 0 and Path(f"/proc/{pid}").exists()
    except (ValueError, OSError):
        alive, info = False, {}
    age = time.time() - LOCK_FILE.stat().st_mtime
    if alive and age < (cfg()["agent_timeout_minutes"] + 15) * 60:
        return info
    log(f"Lock huérfano (pid {info.get('pid')}, {int(age // 60)} min): lo retiro", "WARN")
    LOCK_FILE.unlink(missing_ok=True)
    return None


def pending_paths():
    out = sh(["git", "-C", str(ROOT), "-c", "core.quotepath=off", "status", "--porcelain=v1", "-z", "-uall", "--no-renames"]).stdout
    return {e[3:] for e in out.split("\0") if e}


def recent_edit(path, minutes):
    p = ROOT / path
    if not p.exists():
        return False
    cutoff = time.time() - minutes * 60
    for f in p.rglob("*"):
        if f.is_file() and "node_modules" not in f.parts and f.stat().st_mtime > cutoff:
            return True
    return False


def global_blockers(c, st):
    reasons = []
    if st.get("paused"):
        reasons.append(f"PAUSADO ({(st.get('alert') or {}).get('why', 'manual')}); `docs-sync-watch resume` tras revisar")
    if not Path(CLAUDE).exists() and not shutil.which("claude"):
        reasons.append("no encuentro el binario de claude")
    if lock_holder():
        reasons.append("docs-sync ya está corriendo")
    git_dir = ROOT / ".git"
    if (git_dir / "index.lock").exists():
        reasons.append("git ocupado (index.lock)")
    dirty = sorted(p for p in pending_paths() if p.startswith("plugins/aero/docs/"))
    if dirty:
        reasons.append(f"docs con {len(dirty)} cambios sin commitear (los commitea git-agent; ej. {dirty[0]})")
    if recent_edit("plugins/aero/docs", c["docs_quiet_minutes"]):
        reasons.append(f"alguien editó plugins/aero/docs hace < {c['docs_quiet_minutes']} min")
    today = dt.date.today().isoformat()
    if st["runs"].get(today, 0) >= c["max_runs_per_day"]:
        reasons.append(f"tope de {c['max_runs_per_day']} corridas por día alcanzado")
    return reasons


def plugin_blocker(c, st, plugin, current):
    if plugin in c["exclude_plugins"]:
        return "excluido por configuración"
    f = st["fail"].get(plugin)
    if f and f["n"] >= c["max_failures"]:
        return f"{f['n']} fallos seguidos (`reset {plugin}` para reintentar)"
    if f and time.time() - f["ts"] < c["retry_backoff_minutes"] * 60:
        return f"reintento en espera (backoff {c['retry_backoff_minutes']} min tras el fallo)"
    rel = f"plugins/aero/{plugin}"
    if any(p.startswith(rel + "/") for p in pending_paths()):
        return "el plugin tiene cambios sin commitear (en desarrollo)"
    if recent_edit(rel, c["source_quiet_minutes"]):
        return f"el plugin se editó hace < {c['source_quiet_minutes']} min"
    return None


# ─────────────────────────── plan y ejecución ───────────────────────────
def build_plan(c, st, bootstrap=None, only=None):
    current = {k: v for k, v in plugins_current().items() if not only or k in only}
    docs = documented_versions(c)
    guides = guide_forms(c) if (c["guides_enabled"] and not bootstrap) else {}
    todo, undocumented, skipped = [], [], {}
    for plugin, cur in current.items():
        n, dv = docs.get(plugin, (0, None))
        need_docs = False
        if n == 0:
            undocumented.append(plugin)
            need_docs = bool(bootstrap and plugin in bootstrap)
        elif bootstrap:
            continue
        else:
            need_docs = not (dv and vkey(dv) >= vkey(cur)) and st["checked"].get(plugin) != cur
        # Guías solo de plugins ya documentados para el tenant (los demás son de plataforma o aún sin doc).
        forms = guides.get(plugin, []) if n > 0 else []
        if not need_docs and not forms:
            continue
        why = plugin_blocker(c, st, plugin, cur)
        if why and not (bootstrap and why.startswith("el plugin tiene cambios")):
            skipped[plugin] = why
            continue
        todo.append({"plugin": plugin, "current": cur, "documented": dv, "docs": need_docs, "guides": forms,
                     "notes": version_notes_since(plugin, dv) if need_docs else []})
    # Acota el coste: como mucho N guías por corrida; el resto entra en las siguientes.
    budget = c["max_guides_per_run"]
    for it in todo:
        it["guides"], budget = it["guides"][:budget], max(0, budget - len(it["guides"]))
    todo = [it for it in todo if it["docs"] or it["guides"]]
    return {"todo": todo[: c["max_plugins_per_run"]], "deferred": todo[c["max_plugins_per_run"]:],
            "undocumented": sorted(undocumented), "skipped": skipped}


def build_prompt(items, bootstrap):
    lines = ["Trabajo de esta corrida (el alcance ya está calculado por el vigilante: no lo recalcules).\n"]
    for it in items:
        lines.append(f"## aero/{it['plugin']} (versión actual {it['current']})")
        if it["docs"]:
            head = "- DOCUMENTACIÓN: SIN documentar, documéntalo completo (skill docs-plugin)" if bootstrap else \
                f"- DOCUMENTACIÓN: documentada {it['documented']} → actualizar (skill docs-plugin)"
            lines.append(head)
            for v, n in it["notes"][-8:]:
                lines.append(f"    · {v}: {n[:220]}")
            if it.get("doc_note"):
                lines.append(f"    · INSTRUCCIÓN DEL USUARIO: {it['doc_note']}")
        arts = sorted(f.stem for f in (ROOT / "plugins/aero/docs/content" / it["plugin"]).glob("*.md"))
        if it["guides"] and arts:
            lines.append(f"- Artículos de docs existentes de este plugin (usa su slug en `article` del metadato de la guía si corresponde): {', '.join(arts)}")
        for g in it["guides"]:
            why = "no tiene guía" if g["state"] == "missing" else "está desactualizada (cambió el formulario)"
            lines.append(f"- GUÍA (skill form-guide): slug `{g['slug']}` — {g['name']} — {why}")
            lines.append(f"    · form_ref {g['form_ref']} · YAML {g['form_yaml']} · config {g['config_yaml']}"
                         + (f" · modelo {g['model_file']}" if g.get("model_file") else ""))
            lines.append(f"    · archivo de salida: plugins/aero/docs/content/guides/{g['plugin']}/{g['slug']}.html")
            if g.get("note"):
                lines.append(f"    · INSTRUCCIÓN DEL USUARIO para esta guía: {g['note']}")
    lines.append("\nRecuerda: todo lo que produces es borrador. Escribe solo bajo plugins/aero/docs/content/ y termina "
                 "ejecutando `sudo -u www /www/server/php/84/bin/php artisan docs:import <plugin>` por cada plugin "
                 "(añade `--mark-reviewed` SOLO si el brief incluía DOCUMENTACIÓN de ese plugin y la revisaste; en plugins solo de guías, sin esa opción). "
                 "Cierra con el reporte corto.")
    return "\n".join(lines)


AUTH_PATTERNS = re.compile(r"(not logged in|please run /login|oauth token|invalid api key|authentication|401|credit balance)", re.I)


def run_claude(c, prompt):
    """Llama a `claude -p` con permisos propios. Devuelve (ok, texto, info)."""
    env = dict(os.environ)
    env["PATH"] = f"{Path(CLAUDE).parent}:/usr/local/bin:/usr/bin:/bin:" + env.get("PATH", "")
    env.setdefault("HOME", "/root")
    cmd = [CLAUDE, "-p", prompt, "--model", c["model"], "--max-turns", str(c["max_turns"]),
           "--max-budget-usd", str(c["max_budget_usd"]), "--output-format", "json",
           "--permission-mode", "dontAsk", "--setting-sources", "project", "--settings", str(SETTINGS_FILE),
           "--append-system-prompt", AGENT_FILE.read_text(), "--no-session-persistence"]
    p = subprocess.run(cmd, cwd=ROOT, env=env, capture_output=True, text=True, timeout=c["agent_timeout_minutes"] * 60)
    raw = (p.stdout or "") + (p.stderr or "")
    info = {}
    try:
        info = json.loads(p.stdout)
    except ValueError:
        pass
    ok = p.returncode == 0 and info and not info.get("is_error")
    return bool(ok), (info.get("result") or raw), info


def execute(c, st, plan, bootstrap=False, dry=False):
    items = plan["todo"]
    if not items:
        return
    names = ", ".join(i["plugin"] for i in items)
    n_guides = sum(len(i["guides"]) for i in items)
    if dry:
        log(f"[simulación] trabajaría en: {names}")
        for it in items:
            if it["docs"]:
                log(f"[simulación]   aero/{it['plugin']} documentación: {it['documented']} → {it['current']} ({len(it['notes'])} notas de versión)")
            for g in it["guides"]:
                log(f"[simulación]   aero/{it['plugin']} guía {g['slug']} ({g['state']})")
        st["last_plan"] = {"ts": dt.datetime.now().isoformat(timespec="seconds"), "plugins": [i["plugin"] for i in items], "guides": n_guides}
        return
    before = pending_paths()
    LOCK_FILE.parent.mkdir(parents=True, exist_ok=True)
    LOCK_FILE.write_text(json.dumps({"pid": os.getpid(), "ts": time.time(), "plugins": [i["plugin"] for i in items]}))
    log(f"Ejecuto Claude para: {names} ({n_guides} guía(s))")
    ok, out, info, auth_failed = False, "", {}, False
    try:
        ok, out, info = run_claude(c, build_prompt(items, bootstrap))
        auth_failed = (not ok) and bool(AUTH_PATTERNS.search(out[:600]))
        if ok:
            # Red de seguridad determinista: importar aunque el agente lo haya olvidado (es idempotente).
            for it in items:
                cmd = ["sudo", "-u", c["web_user"], PHP_BIN, "artisan", "docs:import", it["plugin"]] + (["--mark-reviewed"] if it["docs"] else [])
                r = sh(cmd, timeout=300)
                out += f"\n[docs:import {it['plugin']}] rc={r.returncode}\n{(r.stdout + r.stderr)[-1200:]}"
                if r.returncode != 0:
                    ok = False
    except subprocess.TimeoutExpired:
        out = f"TIMEOUT tras {c['agent_timeout_minutes']} min"
    finally:
        LOCK_FILE.unlink(missing_ok=True)
    with LOG_FILE.open("a") as fh:
        fh.write(f"=== salida de Claude ({names}) ok={ok} turnos={info.get('num_turns')} coste=${info.get('total_cost_usd')} ===\n{out[-6000:]}\n")
    new = pending_paths() - before
    unexpected = sorted(p for p in new if not p.startswith(ALLOWED_WRITE_PREFIXES))
    risky = [p for p in unexpected if p.startswith(RISK_PREFIX)]
    noise = [p for p in unexpected if not p.startswith(RISK_PREFIX)]
    if noise:
        log(f"aviso: cambiaron rutas ajenas fuera de plugins/ durante la corrida (probable otra sesión, no se pausa): {', '.join(noise[:5])}", "WARN")
    today = dt.date.today().isoformat()
    st["runs"][today] = st["runs"].get(today, 0) + 1
    if risky:
        st["paused"] = True
        st["alert"] = {"why": f"el agente modificó rutas de plugins fuera de docs: {', '.join(risky[:5])}", "ts": dt.datetime.now().isoformat(timespec="seconds")}
        log(st["alert"]["why"] + " — vigilante PAUSADO; revisa `git status` (no se revirtió nada)", "ERROR")
        return
    if auth_failed:
        # No quema los reintentos de ningún plugin: el problema es la sesión de Claude, no el plugin.
        st["paused"] = True
        st["alert"] = {"why": "Claude no está autenticado (token caducado?). Ejecuta `claude` y /login, luego `docs-sync-watch resume`",
                       "ts": dt.datetime.now().isoformat(timespec="seconds")}
        log(st["alert"]["why"], "ERROR")
        return
    for it in items:
        if ok:
            if it["docs"]:
                st["checked"][it["plugin"]] = it["current"]
            st["fail"].pop(it["plugin"], None)
        else:
            f = st["fail"].setdefault(it["plugin"], {"n": 0, "ts": 0})
            f["n"] += 1
            f["ts"] = time.time()
    log(f"docs-sync {'terminó bien' if ok else 'FALLÓ'}: {names}; turnos={info.get('num_turns')} coste=${info.get('total_cost_usd')}; "
        f"archivos nuevos/cambiados en docs: {len(new)}", "INFO" if ok else "ERROR")


# ─────────────────────────── comandos ───────────────────────────
def cmd_run(args):
    c, st = cfg(), load_state()
    dry = args.dry_run or (c["dry_run"] and not args.force)
    reasons = global_blockers(c, st)
    plan = build_plan(c, st, only=set(args.only) if args.only else None)
    if plan["skipped"]:
        for p, why in plan["skipped"].items():
            log(f"aero/{p} desactualizado pero en espera: {why}")
    if not plan["todo"]:
        log("Documentación al día: nada que hacer" if not plan["skipped"] and not reasons else "Nada que ejecutar ahora")
    if reasons and plan["todo"]:
        for r in reasons:
            log(f"Aplazado: {r}")
        save_state(st)
        return
    execute(c, st, plan, dry=dry)
    save_state(st)


def cmd_bootstrap(args):
    c, st = cfg(), load_state()
    reasons = global_blockers(c, st)
    if reasons:
        print("No se puede ahora:\n - " + "\n - ".join(reasons))
        return 1
    plan = build_plan(c, st, bootstrap=set(args.plugins))
    if not plan["todo"]:
        print("Nada que documentar (¿ya documentados o inexistentes?).")
        return 0
    execute(c, st, plan, bootstrap=True, dry=args.dry_run)
    save_state(st)


def cmd_status(args):
    c, st = cfg(), load_state()
    print(f"docs-sync-watch · repo {ROOT}")
    print(f"modo: {'SIMULACIÓN (dry_run=true: no llama a Claude)' if c['dry_run'] else 'ACTIVO'} · modelo {c['model']} · cron: "
          f"{'programado' if CRON_MARK in sh(['crontab', '-l']).stdout else 'NO programado'}")
    holder = lock_holder()
    print(f"agente: {'CORRIENDO ' + json.dumps(holder) if holder else 'inactivo'}")
    if st.get("paused"):
        print(f"⚠ PAUSADO: {(st.get('alert') or {}).get('why', 'manual')}")
    for r in global_blockers(c, st):
        print(f"• aplazaría por: {r}")
    plan = build_plan(c, st)
    print(f"\ndocumentación desactualizada: {[i['plugin'] + ' ' + str(i['documented']) + '→' + i['current'] for i in plan['todo'] if i['docs']] or 'ninguna'}")
    print(f"guías por generar/refrescar (esta corrida): {[g['slug'] + '(' + g['state'] + ')' for i in plan['todo'] for g in i['guides']] or 'ninguna'}")
    for p, why in plan["skipped"].items():
        print(f"  en espera: aero/{p} — {why}")
    print(f"plugins sin documentar (solo con `bootstrap`): {', '.join(plan['undocumented']) or 'ninguno'}")
    print(f"corridas hoy: {st['runs'].get(dt.date.today().isoformat(), 0)}/{c['max_runs_per_day']} · revisados: {st['checked'] or '—'} · fallos: {st['fail'] or '—'}")


def cmd_pause(args):
    st = load_state(); st["paused"] = True; st["alert"] = {"why": "pausa manual", "ts": dt.datetime.now().isoformat()}; save_state(st); print("Pausado.")


def cmd_resume(args):
    st = load_state(); st["paused"] = False; st["alert"] = None; save_state(st); print("Reanudado.")


def cmd_reset(args):
    st = load_state(); st["fail"].pop(args.plugin, None); st["checked"].pop(args.plugin, None); save_state(st); print(f"Estado de {args.plugin} reiniciado.")


def cmd_install_cron(args):
    cur = sh(["crontab", "-l"]).stdout
    if CRON_MARK in cur:
        print("Ya está programado."); return
    every = int(cfg()["cron_every_minutes"])
    # Solo DETECTA (sin IA, sin coste): recalcula el backlog y actualiza el contador del backend.
    # Generar con Claude es siempre una decisión manual (docs-cli).
    line = f"*/{every} * * * * {SCRIPT_DIR / 'docs-cli.py'} scan >/dev/null 2>&1 {CRON_MARK}"
    subprocess.run(["crontab", "-"], input=cur.rstrip("\n") + "\n" + line + "\n", text=True, check=True)
    print("Programado:\n  " + line)


def cmd_uninstall_cron(args):
    cur = sh(["crontab", "-l"]).stdout
    subprocess.run(["crontab", "-"], input="\n".join(l for l in cur.splitlines() if CRON_MARK not in l) + "\n", text=True, check=True)
    print("Cron de docs-sync-watch retirado.")


def main():
    global QUIET
    ap = argparse.ArgumentParser(prog="docs-sync-watch", description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = ap.add_subparsers(dest="cmd", required=True)
    r = sub.add_parser("run"); r.add_argument("--dry-run", action="store_true"); r.add_argument("--force", action="store_true", help="ejecuta aunque dry_run=true"); r.add_argument("--quiet", action="store_true"); r.add_argument("--only", nargs="+", help="limita la corrida a estos plugins"); r.add_argument("--from-hook", action="store_true")
    b = sub.add_parser("bootstrap"); b.add_argument("plugins", nargs="+"); b.add_argument("--dry-run", action="store_true")
    sub.add_parser("status"); sub.add_parser("pause"); sub.add_parser("resume")
    rs = sub.add_parser("reset"); rs.add_argument("plugin")
    sub.add_parser("install-cron"); sub.add_parser("uninstall-cron")
    args = ap.parse_args()
    QUIET = getattr(args, "quiet", False)
    STATE_DIR.mkdir(parents=True, exist_ok=True)
    if args.cmd in ("run", "bootstrap"):
        lock = open(STATE_DIR / "watch.lock", "w")
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except OSError:
            log("Otra instancia del vigilante está corriendo; salgo")
            return 0
    try:
        res = {"run": cmd_run, "bootstrap": cmd_bootstrap, "status": cmd_status, "pause": cmd_pause, "resume": cmd_resume,
               "reset": cmd_reset, "install-cron": cmd_install_cron, "uninstall-cron": cmd_uninstall_cron}[args.cmd](args)
    except (RuntimeError, subprocess.TimeoutExpired) as e:
        log(str(e), "ERROR")
        return 2
    return res if isinstance(res, int) else 0


if __name__ == "__main__":
    sys.exit(main())
