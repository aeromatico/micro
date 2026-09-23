#!/usr/bin/env python3
"""
docs-sync-watch — mantiene al día la documentación del tenant llamando al agente
`docs-sync` SOLO cuando hace falta y sin estorbar a nadie.

Por qué existe: el disparo por hook post-commit ejecutaba el agente (un modelo de
lenguaje) en cada commit, con permisos totales, publicando en producción por SSH y sin
poder correr `artisan` (la skill mandaba REDIS_PASSWORD vacío → NOAUTH). Con el
auto-commit cada 10 min eso sería casi continuo. Este vigilante lo reemplaza:

  * criterio DETERMINISTA, sin IA: un plugin ya documentado cuya `plugin_version` en
    aero_docs_articles es menor que la última versión de su updates/version.yaml
    (no depende de commits ni de hooks; sobrevive a un reset del repo);
  * el modelo se invoca solo para esos plugins, con el "brief" ya calculado (notas de
    versión desde la versión documentada): menos pasos, menos costo;
  * nunca corre si alguien está editando: docs con cambios sin commitear o tocados hace
    < docs_quiet_minutes, o el plugin fuente en movimiento (source_quiet_minutes);
  * un solo proceso a la vez (lock que git-agent respeta), tope de corridas por día,
    reintentos con backoff y pausa tras varios fallos;
  * verifica al terminar que el agente solo tocó plugins/aero/docs y .opencode; si tocó
    otra cosa se PAUSA y se avisa (no se revierte nada solo);
  * solo local. La publicación a producción es siempre manual (git push + october:migrate).

Uso:  docs-sync-watch.py status | run [--dry-run] [--force] | bootstrap <plugin…> |
                         pause | resume | reset <plugin> | install-cron | uninstall-cron
"""
import argparse
import datetime as dt
import fcntl
import hashlib
import json
import os
import re
import shutil
import subprocess
import sys
import time
from pathlib import Path

SCRIPT_DIR = Path(__file__).resolve().parent
ROOT = Path(os.environ.get("DOCS_SYNC_REPO", SCRIPT_DIR.parent)).resolve()
STATE_DIR = ROOT / ".git" / "docs-sync"          # dentro de .git: nunca se versiona
STATE_FILE = STATE_DIR / "state.json"
LOG_FILE = ROOT / ".opencode" / "docs-sync.log"    # ya está en .gitignore
LOCK_FILE = ROOT / ".opencode" / ".docs-sync.lock"  # git-agent lo respeta (no commitea docs mientras exista)
OPENCODE = os.environ.get("OPENCODE_BIN") or shutil.which("opencode") or "/root/.opencode/bin/opencode"
PHP_BIN = os.environ.get("PHP_BIN", "/www/server/php/84/bin/php")
CRON_MARK = "# docs-sync-watch (auto)"

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
}
ALLOWED_WRITE_PREFIXES = ("plugins/aero/docs/", ".opencode/")
# Zona de riesgo real: código de plugins (incluida la propia BD/lógica de otros plugins). Fuera de
# "plugins/" (temas, bin, .claude, config…) el agente ya no tiene permiso de bash/edit para escribir
# (ver agent/docs-sync.md); si algo cambia ahí durante la corrida, casi siempre es OTRA sesión
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


def documented_versions(c):
    """{plugin: (n_articulos, versión_documentada_mínima|None)} desde aero_docs_articles (globales)."""
    fake = os.environ.get("DOCS_SYNC_DOCUMENTED_JSON")          # solo para pruebas
    if fake:
        return {k: (v[0], v[1]) for k, v in json.loads(Path(fake).read_text()).items()}
    code = ('$o=[];foreach(DB::table("aero_docs_articles")->whereNull("tenant_id")->get(["slug","plugin_version"]) as $r){'
            '$p=explode("-",$r->slug)[0];$o[$p][]=$r->plugin_version;}echo "@@".json_encode($o)."@@";')
    r = sh(["sudo", "-u", c["web_user"], PHP_BIN, "artisan", "tinker", "--execute=" + code], timeout=90)
    m = re.search(r"@@(.*)@@", r.stdout, re.S)
    if not m:
        raise RuntimeError(f"no pude leer los artículos documentados: {(r.stdout + r.stderr).strip()[:200]}")
    res = {}
    for plugin, vs in json.loads(m.group(1)).items():
        vs = [v for v in vs if v]
        res[plugin] = (len(vs) + 0, min(vs, key=vkey) if vs else None)
    return res


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
    if not Path(OPENCODE).exists() and not shutil.which("opencode"):
        reasons.append("no encuentro el binario de opencode")
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
def build_plan(c, st, bootstrap=None):
    current = plugins_current()
    docs = documented_versions(c)
    todo, undocumented, skipped = [], [], {}
    for plugin, cur in current.items():
        n, dv = docs.get(plugin, (0, None))
        if n == 0:
            undocumented.append(plugin)
            if not bootstrap or plugin not in bootstrap:
                continue
        elif bootstrap:
            continue
        else:
            if dv and vkey(dv) >= vkey(cur):
                continue                                   # al día
            if st["checked"].get(plugin) == cur:
                continue                                   # ya revisado en esta versión (el agente decidió que no aplica)
        why = plugin_blocker(c, st, plugin, cur)
        if why and not (bootstrap and why.startswith("el plugin tiene cambios")):
            skipped[plugin] = why
            continue
        todo.append({"plugin": plugin, "current": cur, "documented": dv, "notes": version_notes_since(plugin, dv)})
    return {"todo": todo[: c["max_plugins_per_run"]], "deferred": todo[c["max_plugins_per_run"]:],
            "undocumented": sorted(undocumented), "skipped": skipped}


def build_prompt(items, bootstrap):
    lines = ["Sincroniza la documentación del TENANT (solo panel del tenant) de estos plugins. "
             "El alcance ya está calculado por el vigilante: NO repitas el Paso 1 ni consultes la BD para decidirlo.\n"]
    for it in items:
        head = f"- aero/{it['plugin']}: versión actual {it['current']}; "
        head += "SIN documentar (documéntalo completo)" if bootstrap else f"documentada {it['documented']} → actualizar"
        lines.append(head)
        for v, n in it["notes"][-8:]:
            lines.append(f"    · {v}: {n[:220]}")
    lines.append("\nReglas de esta corrida: solo local (NO producción, NO ssh, NO git add/commit/push). Escribe únicamente en "
                 "plugins/aero/docs/. Ejecuta artisan SIEMPRE como `sudo -u www /www/server/php/84/bin/php artisan …` "
                 "(sin tocar REDIS_PASSWORD). Si una versión no cambia nada visible para el tenant, no crees artículos: "
                 "dilo en el reporte. Termina con el reporte corto de la skill.")
    return "\n".join(lines)


def execute(c, st, plan, bootstrap=False, dry=False):
    items = plan["todo"]
    if not items:
        return
    names = ", ".join(i["plugin"] for i in items)
    if dry:
        log(f"[simulación] documentaría: {names}")
        for it in items:
            log(f"[simulación]   aero/{it['plugin']}: documentada {it['documented']} → {it['current']} ({len(it['notes'])} notas de versión)")
        st["last_plan"] = {"ts": dt.datetime.now().isoformat(timespec="seconds"), "plugins": [i["plugin"] for i in items]}
        return
    before = pending_paths()
    LOCK_FILE.parent.mkdir(parents=True, exist_ok=True)
    LOCK_FILE.write_text(json.dumps({"pid": os.getpid(), "ts": time.time(), "plugins": [i["plugin"] for i in items]}))
    log(f"Ejecuto docs-sync para: {names}")
    rc, out = 1, ""
    try:
        env = dict(os.environ)
        env["PATH"] = f"{Path(OPENCODE).parent}:/usr/local/bin:/usr/bin:/bin:" + env.get("PATH", "")
        env.setdefault("HOME", "/root")
        p = subprocess.run([OPENCODE, "run", "--agent", "docs-sync", build_prompt(items, bootstrap)], cwd=ROOT, env=env,
                           capture_output=True, text=True, timeout=c["agent_timeout_minutes"] * 60)
        rc, out = p.returncode, (p.stdout + p.stderr)
    except subprocess.TimeoutExpired as e:
        out = f"TIMEOUT tras {c['agent_timeout_minutes']} min\n" + ((e.stdout or b"").decode(errors="ignore")[-1500:] if isinstance(e.stdout, bytes) else str(e.stdout or "")[-1500:])
    finally:
        LOCK_FILE.unlink(missing_ok=True)
    with LOG_FILE.open("a") as fh:
        fh.write(f"=== salida del agente ({names}) rc={rc} ===\n{out[-6000:]}\n")
    new = pending_paths() - before
    unexpected = sorted(p for p in new if not p.startswith(ALLOWED_WRITE_PREFIXES))
    # Solo pausa por escrituras en zona de riesgo real (código de plugins). Fuera de "plugins/" el
    # agente ya no tiene permiso de escritura (bash/edit deny); un cambio ahí durante la corrida casi
    # siempre es otra sesión trabajando en paralelo, no el agente — se audita, no se detiene todo
    # (caso real 2026-09-21: themes/master/... de otra sesión pausó el vigilante 2 días sin motivo).
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
    for it in items:
        if rc == 0:
            st["checked"][it["plugin"]] = it["current"]
            st["fail"].pop(it["plugin"], None)
        else:
            f = st["fail"].setdefault(it["plugin"], {"n": 0, "ts": 0})
            f["n"] += 1
            f["ts"] = time.time()
    log(f"docs-sync {'terminó bien' if rc == 0 else f'FALLÓ (rc={rc})'}: {names}; archivos nuevos/cambiados en docs: {len(new)}",
        "INFO" if rc == 0 else "ERROR")


# ─────────────────────────── comandos ───────────────────────────
def cmd_run(args):
    c, st = cfg(), load_state()
    dry = args.dry_run or (c["dry_run"] and not args.force)
    reasons = global_blockers(c, st)
    plan = build_plan(c, st)
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
    print(f"modo: {'SIMULACIÓN (dry_run=true: no llama al modelo)' if c['dry_run'] else 'ACTIVO'} · cron: "
          f"{'programado' if CRON_MARK in sh(['crontab', '-l']).stdout else 'NO programado'}")
    holder = lock_holder()
    print(f"agente: {'CORRIENDO ' + json.dumps(holder) if holder else 'inactivo'}")
    if st.get("paused"):
        print(f"⚠ PAUSADO: {(st.get('alert') or {}).get('why', 'manual')}")
    for r in global_blockers(c, st):
        print(f"• aplazaría por: {r}")
    plan = build_plan(c, st)
    print(f"\ndesactualizados listos: {[i['plugin'] + ' ' + str(i['documented']) + '→' + i['current'] for i in plan['todo']] or 'ninguno'}")
    for p, why in plan["skipped"].items():
        print(f"  en espera: aero/{p} — {why}")
    print(f"sin documentar (solo con `bootstrap`): {', '.join(plan['undocumented']) or 'ninguno'}")
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
    line = f"*/{every} * * * * {SCRIPT_DIR / 'docs-sync-watch.py'} run --quiet >/dev/null 2>&1 {CRON_MARK}"
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
    r = sub.add_parser("run"); r.add_argument("--dry-run", action="store_true"); r.add_argument("--force", action="store_true", help="ejecuta aunque dry_run=true"); r.add_argument("--quiet", action="store_true"); r.add_argument("--from-hook", action="store_true")
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
