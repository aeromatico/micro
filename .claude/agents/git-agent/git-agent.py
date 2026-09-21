#!/usr/bin/env python3
"""
git-agent v2 — commits automáticos, seguros y por área para micro.clouds.com.bo.

Por qué existe (lecciones de 2026-09-21): con varias sesiones trabajando a la vez,
un `git add -A` o un commit gigante mezcla trabajos, y un índice compartido se
corrompe. Este agente:

  * commitea POR ÁREA (plugin, tema, submódulo…) con rutas explícitas
    (`git commit -o -- <rutas>`): jamás toca lo que otra sesión dejó preparado;
  * espera a que los archivos se "enfríen" (quiet_minutes) para no commitear
    trabajo a medio escribir;
  * valida antes de commitear: sintaxis PHP/JSON, version.yaml ↔ migraciones,
    secretos, archivos prohibidos o enormes; un área con problemas NO se commitea
    y se reporta;
  * escribe mensajes Conventional Commits en español usando las notas de
    version.yaml de cada plugin;
  * commitea dentro de los submódulos y luego actualiza el puntero del padre;
  * autorepara el índice si quedó con objetos inexistentes;
  * push separado, con compuertas (avance rápido, secretos, salud, versiones,
    libro de créditos) y siempre submódulos primero.

Uso:  git-agent status | commit | push | run | doctor | install-cron | uninstall-cron
"""
import argparse
import datetime as dt
import fcntl
import fnmatch
import json
import os
import re
import subprocess
import sys
import time
from pathlib import Path

VERSION = "2.0"
SCRIPT_DIR = Path(__file__).resolve().parent
DEFAULT_ROOT = SCRIPT_DIR.parents[2]          # .claude/agents/git-agent → raíz del repo
ROOT = Path(os.environ.get("GIT_AGENT_REPO", DEFAULT_ROOT)).resolve()
PHP_BIN = os.environ.get("PHP_BIN", "/www/server/php/84/bin/php")
STATE_DIR = ROOT / ".git" / "git-agent"       # dentro de .git: nunca se versiona ni ensucia storage/
LOG_FILE = STATE_DIR / "agent.log"
STATUS_FILE = STATE_DIR / "status.json"
LOCK_FILE = STATE_DIR / "lock"
TRAILER = f"Auto-Commit: git-agent v{VERSION}"

DEFAULT_CONFIG = {
    "branch": "main",
    "quiet_minutes": 8,
    "max_wait_minutes": 90,        # tope: si lleva tanto pendiente, se commitea con solo `hard_quiet_seconds` de calma
    "hard_quiet_seconds": 120,
    "max_file_mb": 2,
    "deny_globs": [
        "storage/*", "vendor/*", "node_modules/*", "*/node_modules/*", "bootstrap/cache/*",
        ".env", ".env.*", "*.sql", "*.sql.gz", "*.dump", "*.zip", "*.tar", "*.tar.gz", "*.tgz",
        "*.pem", "*.key", "*.log", ".well-known/acme-challenge/*", "*.bak", "*.swp", ".DS_Store",
    ],
    "secret_patterns": [
        r"sk-[A-Za-z0-9]{20,}", r"AKIA[0-9A-Z]{16}", r"BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY",
        r"(?i)(password|passwd|secret|token|api[_-]?key)[\"']?\s*(=>|=|:)\s*[\"'][A-Za-z0-9+/_\-]{20,}[\"']",
        r"Bearer [A-Za-z0-9._\-]{30,}",
    ],
    "ignore_secret_files": ["*.md", "*/lang/*", "*.min.css", "*.min.js", "*.lock"],
    "push": {
        "auto": False,                      # `run` solo empuja si esto es true
        "check_health": True,
        "check_db_versions": True,
        "check_credits_ledger": True,
        "health_urls": [
            ["panel.market.com.bo", "/backend", [200, 302]],
            ["market.com.bo", "/", [200]],
        ],
    },
    "cron": {"commit_every_minutes": 10},
    "web_user": "www",
}


# ─────────────────────────── utilidades ───────────────────────────
class GitError(Exception):
    pass


def load_config():
    cfg = json.loads(json.dumps(DEFAULT_CONFIG))
    path = Path(os.environ.get("GIT_AGENT_CONFIG", SCRIPT_DIR / "config.json"))
    if path.exists():
        user = json.loads(path.read_text())
        for k, v in user.items():
            if isinstance(v, dict) and isinstance(cfg.get(k), dict):
                cfg[k].update(v)
            else:
                cfg[k] = v
    return cfg


CFG = load_config()
QUIET = False


def log(msg, level="INFO"):
    line = f"{dt.datetime.now():%Y-%m-%d %H:%M:%S} [{level}] {msg}"
    if not QUIET or level in ("WARN", "ERROR"):
        print(line, flush=True)
    try:
        STATE_DIR.mkdir(parents=True, exist_ok=True)
        if LOG_FILE.exists() and LOG_FILE.stat().st_size > 5 * 1024 * 1024:
            LOG_FILE.replace(LOG_FILE.with_suffix(".log.1"))
        with LOG_FILE.open("a") as fh:
            fh.write(line + "\n")
    except OSError:
        pass


def run(cmd, cwd=None, check=True, timeout=180, env=None, stdin=None):
    p = subprocess.run(cmd, cwd=cwd, capture_output=True, text=True, timeout=timeout, env=env, input=stdin)
    if check and p.returncode != 0:
        raise GitError(f"{' '.join(cmd)[:160]} → {p.stderr.strip() or p.stdout.strip()}"[:600])
    return p


def git(repo, *args, check=True, hooks=False, stdin=None):
    base = ["git", "-C", str(repo), "-c", "core.quotepath=off"]
    if not hooks:
        base += ["-c", "core.hooksPath=/dev/null"]     # no disparar hooks (p. ej. docs-sync)
    return run(base + list(args), check=check, stdin=stdin)


def submodule_paths():
    gm = ROOT / ".gitmodules"
    if not gm.exists():
        return []
    return re.findall(r"^\s*path\s*=\s*(.+)$", gm.read_text(), re.M)


def denied(path):
    return any(fnmatch.fnmatch(path, g) or fnmatch.fnmatch(os.path.basename(path), g) for g in CFG["deny_globs"])


def human_age(seconds):
    m = int(seconds // 60)
    return f"{m} min" if m < 120 else f"{m // 60} h"


# ─────────────────────────── salud del repositorio ───────────────────────────
def other_git_running():
    out = run(["pgrep", "-a", "-x", "git"], check=False).stdout
    mine = str(os.getpid())
    return [l for l in out.splitlines() if l.split(" ", 1)[0] != mine]


def repo_blocked_reason(repo):
    gd = Path(git(repo, "rev-parse", "--absolute-git-dir").stdout.strip())
    for marker in ("MERGE_HEAD", "rebase-merge", "rebase-apply", "CHERRY_PICK_HEAD", "REVERT_HEAD"):
        if (gd / marker).exists():
            return f"operación de git en curso ({marker})"
    lock = gd / "index.lock"
    if lock.exists():
        age = time.time() - lock.stat().st_mtime
        return f"index.lock presente (hace {human_age(age)})"
    branch = git(repo, "symbolic-ref", "--short", "-q", "HEAD", check=False).stdout.strip()
    if repo == ROOT and branch != CFG["branch"]:
        return f"rama '{branch or 'HEAD desacoplado'}' (se esperaba {CFG['branch']})"
    return None


def broken_index_entries(repo):
    bad = []
    out = git(repo, "ls-files", "-s", "-z").stdout
    entries = [e for e in out.split("\0") if e]
    if not entries:
        return bad
    shas = {}
    for e in entries:
        meta, path = e.split("\t", 1)
        mode, sha, _stage = meta.split()
        if mode != "160000":
            shas[sha] = path
    inp = "\n".join(shas) + "\n"
    p = run(["git", "-C", str(repo), "cat-file", "--batch-check"], check=False, stdin=inp)
    for line in p.stdout.splitlines():
        if line.endswith(" missing"):
            bad.append(shas[line.split()[0]])
    return bad


def heal_index(repo, apply=True):
    bad = broken_index_entries(repo)
    if not bad:
        return 0
    log(f"Índice con {len(bad)} entradas a objetos inexistentes (ej. {bad[0]})", "WARN")
    if not apply:
        return len(bad)
    if other_git_running():
        log("Hay otro proceso git activo: no reparo el índice ahora", "WARN")
        return len(bad)
    gd = Path(git(repo, "rev-parse", "--absolute-git-dir").stdout.strip())
    STATE_DIR.mkdir(parents=True, exist_ok=True)
    backup = STATE_DIR / f"index.roto.{int(time.time())}"
    backup.write_bytes((gd / "index").read_bytes())
    git(repo, "read-tree", "HEAD")           # reconstruye el índice; NO toca el árbol de trabajo
    log(f"Índice reparado con read-tree HEAD (copia en {backup.name}); el árbol de trabajo no se tocó")
    return len(bad)


# ─────────────────────────── detección de cambios ───────────────────────────
def collect_changes(repo, skip_prefixes=()):
    """Devuelve {ruta: 'M'|'D'|'A'} de lo pendiente (modificado, borrado, sin rastrear)."""
    out = git(repo, "status", "--porcelain=v1", "-z", "-uall", "--no-renames").stdout
    changes = {}
    for e in (x for x in out.split("\0") if x):
        xy, path = e[:2], e[3:]
        if any(path == p or path.startswith(p + "/") for p in skip_prefixes):
            continue
        if xy == "??":
            state = "A"
        elif "D" in xy:
            state = "D"
        else:
            state = "M"
        changes[path] = state
    return changes


def area_of(path, in_submodule=False):
    parts = path.split("/")
    if in_submodule:
        return "."
    if parts[0] == "plugins" and len(parts) >= 3:
        return "/".join(parts[:3])
    if parts[0] == "themes" and len(parts) >= 2:
        return "/".join(parts[:2])
    if len(parts) == 1:
        return "(raíz)"
    return parts[0]


def scope_of(area, sub_name=None):
    if sub_name:
        return sub_name
    if area.startswith("plugins/"):
        return area.split("/")[2]
    if area.startswith("themes/"):
        return area.split("/")[1]
    return area.strip("()").replace("raíz", "root") or "root"


# ─────────────────────────── validaciones ───────────────────────────
def scan_secrets(repo, files):
    hits = []
    pats = [re.compile(p) for p in CFG["secret_patterns"]]
    for f in files:
        if any(fnmatch.fnmatch(f, g) for g in CFG["ignore_secret_files"]):
            continue
        p = Path(repo) / f
        if not p.is_file() or p.stat().st_size > CFG["max_file_mb"] * 1024 * 1024:
            continue
        try:
            text = p.read_text(errors="ignore")
        except OSError:
            continue
        for pat in pats:
            if pat.search(text):
                hits.append(f)
                break
    return hits


def validate_area(repo, files, area_root):
    """Devuelve lista de problemas que BLOQUEAN el commit del área."""
    problems = []
    big = [f for f in files if (Path(repo) / f).is_file() and (Path(repo) / f).stat().st_size > CFG["max_file_mb"] * 1048576]
    if big:
        problems.append(f"archivo(s) mayor(es) a {CFG['max_file_mb']} MB: {', '.join(big[:3])}")
    for f in files:
        p = Path(repo) / f
        if not p.is_file():
            continue
        if f.endswith(".php"):
            r = run([PHP_BIN, "-l", str(p)], check=False)
            if r.returncode != 0:
                problems.append(f"error de sintaxis PHP en {f}: {(r.stdout + r.stderr).strip().splitlines()[0][:110]}")
        elif f.endswith(".json"):
            try:
                json.loads(p.read_text())
            except ValueError as e:
                problems.append(f"JSON inválido en {f}: {str(e)[:80]}")
        elif f.endswith("version.yaml"):
            try:
                import yaml
                yaml.safe_load(p.read_text())
            except Exception as e:  # noqa: BLE001
                problems.append(f"version.yaml inválido en {f}: {str(e)[:80]}")
    # version.yaml ↔ migraciones (evita commitear una versión sin su migración)
    vy = Path(repo) / area_root / "updates" / "version.yaml" if area_root != "." else Path(repo) / "updates" / "version.yaml"
    if vy.exists() and any(f.endswith("version.yaml") or "/updates/" in f or f.startswith("updates/") for f in files):
        updates = vy.parent
        referenced = set(re.findall(r"([A-Za-z0-9_]+\.php)", vy.read_text()))
        missing = sorted(n for n in referenced if not (updates / n).exists())
        if missing:
            problems.append(f"version.yaml referencia migraciones que no existen: {', '.join(missing[:4])}")
    secrets = scan_secrets(repo, [f for f in files if (Path(repo) / f).is_file()])
    if secrets:
        problems.append(f"posible secreto/credencial en: {', '.join(secrets[:3])}")
    return problems


# ─────────────────────────── mensajes ───────────────────────────
FIX_WORDS = re.compile(r"^(corrige|arregla|repara|soluciona|fix|evita|no falla|ya no)", re.I)


def version_notes(repo, area_root):
    rel = (f"{area_root}/updates/version.yaml" if area_root != "." else "updates/version.yaml")
    p = Path(repo) / rel
    if not p.exists():
        return []
    tracked = git(repo, "ls-files", "--error-unmatch", rel, check=False).returncode == 0
    if tracked:
        lines = [l[1:] for l in git(repo, "diff", "HEAD", "--", rel, check=False).stdout.splitlines()
                 if l.startswith("+") and not l.startswith("+++")]
    else:
        lines = p.read_text().splitlines()
    notes, current = [], None
    for l in lines:
        m = re.match(r"^(\d+\.\d+\.\d+):\s*(?:\"(.*)\")?\s*$", l)
        if m:
            current = m.group(1)
            if m.group(2):
                notes.append((current, m.group(2)))
                current = None
            continue
        m2 = re.match(r'^\s+-\s+"(.*)"\s*$', l)
        if m2 and current:
            notes.append((current, m2.group(1)))
            current = None
            continue
        m3 = re.match(r'^\s+-\s+(?!")(.+?)\s*$', l)          # nota sin comillas (versiones antiguas)
        if m3 and current and not m3.group(1).endswith(".php"):
            notes.append((current, m3.group(1)))
            current = None
    return notes


def build_message(repo, area, files, states, sub_name=None):
    root = "." if sub_name else area
    scope = scope_of(area, sub_name)
    notes = version_notes(repo, root)
    added = sum(1 for f in files if states[f] == "A")
    deleted = sum(1 for f in files if states[f] == "D")
    is_new_plugin = any(f.endswith("/Plugin.php") or f == "Plugin.php" for f in files if states[f] == "A")
    if notes:
        first = notes[0][1]
        typ = "fix" if FIX_WORDS.match(first) else "feat"
        if is_new_plugin:
            typ = "feat"
        subject = first
    else:
        cats = {os.path.splitext(f)[1] for f in files}
        if is_new_plugin:
            typ, subject = "feat", f"nuevo plugin {scope}"
        elif area.startswith("themes/"):
            typ, subject = "style", f"ajustes del tema {scope} ({len(files)} archivos)"
        elif cats <= {".md", ".txt"} or area in ("docs",):
            typ, subject = "docs", f"actualizar documentación ({len(files)} archivos)"
        elif deleted and deleted == len(files):
            typ, subject = "refactor", f"retirar {len(files)} archivos"
        elif area.startswith("plugins/"):
            typ, subject = "chore", f"actualización de {len(files)} archivos"
        else:
            typ, subject = "chore", f"actualización de {len(files)} archivos"
    subject = subject.replace("\n", " ").strip()
    extra = f" (+{len(notes) - 1} versiones)" if len(notes) > 1 else ""
    head = f"{typ}({scope}): {subject[:96]}{extra}"
    body = []
    if len(notes) > 1:
        body.append("Versiones:")
        for v, n in notes[:8]:
            body.append(f"- {v}: {n[:150]}")
        body.append("")
    body.append(f"Archivos: {len(files)} (+{added} nuevos, −{deleted} borrados)")
    for f in files[:12]:
        body.append(f"  {states[f]} {f}")
    if len(files) > 12:
        body.append(f"  … y {len(files) - 12} más")
    return head + "\n\n" + "\n".join(body) + "\n\n" + TRAILER + "\n"


# ─────────────────────────── commit ───────────────────────────
def mtimes(repo, files):
    """(más antigua, más reciente) fecha de modificación de los archivos que existen."""
    ts = [(Path(repo) / f).stat().st_mtime for f in files if (Path(repo) / f).exists()]
    return (min(ts), max(ts)) if ts else (0, 0)


def plan_repo(repo, sub_name=None, only=None, ignore_quiet=False, skip_prefixes=()):
    """Agrupa lo pendiente por área y decide el estado de cada una (sin escribir nada)."""
    changes = collect_changes(repo, skip_prefixes)
    groups = {}
    for path, st in changes.items():
        groups.setdefault(area_of(path, in_submodule=bool(sub_name)), {})[path] = st
    plan = []
    now = time.time()
    for area, states in sorted(groups.items()):
        if only and only not in area and only != sub_name:
            continue
        usable = {f: s for f, s in states.items() if not denied(f)}
        skipped = [f for f in states if denied(f)]
        item = {"repo": str(repo), "area": area, "sub": sub_name, "files": sorted(usable), "states": usable,
                "denied": skipped, "state": "listo", "detail": ""}
        if not usable:
            item.update(state="ignorado", detail=f"solo archivos prohibidos ({len(skipped)})")
        else:
            oldest, newest = mtimes(repo, usable)
            age_new, age_old = now - newest, now - oldest
            cold = age_new >= CFG["quiet_minutes"] * 60
            overdue = age_old >= CFG["max_wait_minutes"] * 60 and age_new >= CFG["hard_quiet_seconds"]
            if not ignore_quiet and not (cold or overdue):
                item.update(state="espera", detail=f"última edición hace {human_age(age_new)} (mín. {CFG['quiet_minutes']} min; "
                                                   f"pendiente hace {human_age(age_old)}, tope {CFG['max_wait_minutes']} min)")
            else:
                if overdue and not cold:
                    log(f"{area}: pendiente hace {human_age(age_old)} (tope {CFG['max_wait_minutes']} min): commiteo con {int(age_new)} s de calma")
                root = "." if sub_name else area
                problems = validate_area(repo, sorted(usable), root)
                if problems:
                    item.update(state="bloqueado", detail="; ".join(problems))
        plan.append(item)
    return plan


def commit_item(item, dry):
    repo, files, states = item["repo"], item["files"], item["states"]
    msg = build_message(repo, item["area"], files, states, item["sub"])
    item["subject"] = msg.splitlines()[0]
    if dry:
        return None
    git(repo, "add", "-A", "--", *files)
    p = git(repo, "commit", "-o", "-q", "-F", "-", "--", *files, stdin=msg, check=False)
    if p.returncode != 0:
        raise GitError(f"commit falló: {(p.stderr + p.stdout).strip()[:300]}")
    return git(repo, "rev-parse", "--short", "HEAD").stdout.strip()


def do_commit(only=None, dry=False, ignore_quiet=False):
    report = {"committed": [], "waiting": [], "blocked": [], "ignored": []}
    reason = repo_blocked_reason(ROOT)
    if reason:
        log(f"Repo no apto para commitear: {reason}", "WARN")
        report["blocked"].append({"area": "(repo)", "reason": reason})
        return report
    if other_git_running():
        log("Hay otro proceso git en ejecución; reintento en la próxima corrida", "WARN")
        report["blocked"].append({"area": "(repo)", "reason": "otro proceso git activo"})
        return report
    heal_index(ROOT, apply=not dry)

    subs = submodule_paths()
    plan = []
    for sp in subs:
        sub = ROOT / sp
        if (sub / ".git").exists():
            if repo_blocked_reason(sub):
                continue
            plan += plan_repo(sub, sub_name=sp.rsplit("/", 1)[-1], only=only, ignore_quiet=ignore_quiet)
    parent_plan = plan_repo(ROOT, only=only, ignore_quiet=ignore_quiet, skip_prefixes=subs)
    # plugins antes que temas, y el resto al final (los temas dependen de los plugins)
    order = lambda it: (0 if it["area"].startswith("plugins/") else 1 if it["area"].startswith("themes/") else 2, it["area"])
    plan += sorted(parent_plan, key=order)

    for item in plan:
        label = item["area"] if not item["sub"] else f"submódulo {item['sub']}"
        if item["state"] == "listo":
            try:
                sha = commit_item(item, dry)
                tag = "[simulado] " if dry else ""
                log(f"{tag}commit {sha or ''} {label}: {item['subject']}")
                report["committed"].append({"area": label, "sha": sha, "files": len(item["files"]), "subject": item["subject"]})
            except (GitError, subprocess.TimeoutExpired) as e:
                log(f"{label}: {e}", "ERROR")
                report["blocked"].append({"area": label, "reason": str(e)[:200]})
        elif item["state"] == "espera":
            report["waiting"].append({"area": label, "files": len(item["files"]), "detail": item["detail"]})
        elif item["state"] == "bloqueado":
            log(f"{label} NO se commitea: {item['detail']}", "WARN")
            report["blocked"].append({"area": label, "reason": item["detail"]})
        else:
            report["ignored"].append({"area": label, "detail": item["detail"]})
        if item["denied"]:
            log(f"{label}: {len(item['denied'])} archivo(s) prohibido(s) omitidos (ej. {item['denied'][0]})", "WARN")

    # punteros de submódulos: un único commit en el padre
    if not dry:
        moved = [sp for sp in subs if (ROOT / sp / ".git").exists()
                 and sp in [e[3:] for e in git(ROOT, "status", "--porcelain=v1").stdout.splitlines()]
                 and not [i for i in plan if i["sub"] == sp.rsplit("/", 1)[-1] and i["state"] in ("espera", "bloqueado")]]
        if moved and not only:
            names = ", ".join(m.rsplit("/", 1)[-1] for m in moved)
            msg = (f"chore: actualizar puntero de submódulos ({names})\n\n"
                   "Los submódulos deben subirse ANTES que este commit.\n\n" + TRAILER + "\n")
            git(ROOT, "add", "--", *moved)
            git(ROOT, "commit", "-o", "-q", "-F", "-", "--", *moved, stdin=msg)
            sha = git(ROOT, "rev-parse", "--short", "HEAD").stdout.strip()
            log(f"commit {sha} punteros de submódulos: {names}")
            report["committed"].append({"area": "punteros", "sha": sha, "files": len(moved), "subject": f"punteros ({names})"})
    return report


# ─────────────────────────── push con compuertas ───────────────────────────
def unpushed(repo):
    r = git(repo, "rev-list", "--count", "@{u}..HEAD", check=False)
    return int(r.stdout.strip() or 0) if r.returncode == 0 else -1


def gates_for_push():
    problems = []
    cfg = CFG["push"]
    reason = repo_blocked_reason(ROOT)
    if reason:
        problems.append(reason)
    if other_git_running():
        problems.append("otro proceso git activo")
    # pendiente sin commitear
    pend = [p for p in collect_changes(ROOT) if not denied(p)]
    if pend:
        problems.append(f"hay {len(pend)} cambios sin commitear en el padre")
    for sp in submodule_paths():
        sub = ROOT / sp
        if (sub / ".git").exists() and git(sub, "status", "--porcelain").stdout.strip():
            problems.append(f"submódulo {sp} tiene cambios sin commitear")
    repos = [(ROOT, "padre")] + [(ROOT / sp, sp.rsplit("/", 1)[-1]) for sp in submodule_paths() if (ROOT / sp / ".git").exists()]
    for repo, name in repos:
        f = git(repo, "fetch", "origin", check=False)
        if f.returncode != 0:
            problems.append(f"{name}: no se pudo consultar el remoto")
            continue
        if git(repo, "merge-base", "--is-ancestor", "@{u}", "HEAD", check=False).returncode != 0:
            problems.append(f"{name}: el remoto tiene commits que no están aquí (no es avance rápido)")
        rng = git(repo, "diff", "@{u}..HEAD", "--name-only", check=False).stdout.split()
        hits = scan_secrets(repo, rng)
        if hits:
            problems.append(f"{name}: posible secreto en lo que se subiría ({', '.join(hits[:2])})")
    if cfg["check_health"]:
        for host, path, ok in cfg["health_urls"]:
            r = run(["curl", "-sk", "-o", "/dev/null", "-m", "15", "-w", "%{http_code}", "-H", f"Host: {host}", f"https://127.0.0.1{path}"], check=False)
            if not r.stdout.strip().isdigit() or int(r.stdout.strip()) not in ok:
                problems.append(f"salud: https://{host}{path} respondió {r.stdout.strip() or 'nada'}")
    artisan = ["sudo", "-u", CFG["web_user"], PHP_BIN, "artisan"]
    if cfg["check_db_versions"] and (ROOT / "artisan").exists():
        code = ('$bad=[];foreach(glob(base_path("plugins/*/*/updates/version.yaml")) as $f){$v=basename(dirname(dirname(dirname($f))));'
                '$p=basename(dirname(dirname($f)));preg_match_all("/^(\\d+\\.\\d+\\.\\d+):/m",file_get_contents($f),$m);if(!$m[1])continue;'
                'usort($m[1],"version_compare");$db=DB::table("system_plugin_versions")->whereRaw("lower(code)=?",[strtolower("$v.$p")])->value("version");'
                'if($db!==null&&$db!==end($m[1]))$bad[]="$v.$p yaml=".end($m[1])." bd=$db";}echo $bad?"DESFASE:".implode(";",$bad):"OK";')
        r = run(artisan + ["tinker", "--execute=" + code], check=False, timeout=90, cwd=ROOT)
        last = r.stdout.strip().splitlines()[-1] if r.stdout.strip() else ""
        if not last.startswith("OK"):
            problems.append(f"versiones código↔BD: {last or r.stderr.strip()[:100] or 'sin respuesta'}")
    if cfg["check_credits_ledger"] and (ROOT / "plugins/aero/credits").exists():
        r = run(artisan + ["credits:verify"], check=False, timeout=90, cwd=ROOT)
        if r.returncode != 0:
            problems.append("libro de créditos: credits:verify reporta problemas")
    return problems


def do_push(dry=False):
    problems = gates_for_push()
    if problems:
        for p in problems:
            log(f"PUSH bloqueado: {p}", "WARN")
        return {"pushed": [], "blocked": problems}
    pushed = []
    repos = [(ROOT / sp, sp.rsplit("/", 1)[-1]) for sp in submodule_paths() if (ROOT / sp / ".git").exists()] + [(ROOT, "padre")]
    for repo, name in repos:                     # submódulos primero, padre al final
        n = unpushed(repo)
        if n <= 0:
            continue
        if dry:
            log(f"[simulado] push {name}: {n} commit(s)")
        else:
            git(repo, "push", "origin", CFG["branch"])
            log(f"push {name}: {n} commit(s) subidos")
        pushed.append({"repo": name, "commits": n})
    return {"pushed": pushed, "blocked": []}


# ─────────────────────────── comandos ───────────────────────────
def write_status(data):
    try:
        STATE_DIR.mkdir(parents=True, exist_ok=True)
        data["ts"] = dt.datetime.now().isoformat(timespec="seconds")
        data["version"] = VERSION
        STATUS_FILE.write_text(json.dumps(data, ensure_ascii=False, indent=2))
    except OSError:
        pass


def cmd_status(args):
    reason = repo_blocked_reason(ROOT)
    print(f"git-agent v{VERSION} · repo {ROOT}")
    if reason:
        print(f"⚠ repo no apto: {reason}")
    bad = broken_index_entries(ROOT)
    print(f"índice: {'ROTO (' + str(len(bad)) + ' entradas a objetos inexistentes; `git-agent doctor --fix`)' if bad else 'sano'}")
    subs = submodule_paths()
    plan = []
    for sp in subs:
        if (ROOT / sp / ".git").exists():
            plan += plan_repo(ROOT / sp, sub_name=sp.rsplit("/", 1)[-1], ignore_quiet=args.now)
    plan += plan_repo(ROOT, ignore_quiet=args.now, skip_prefixes=subs)
    if not plan:
        print("✔ sin cambios pendientes")
    else:
        print(f"\n{'ÁREA':34}{'ESTADO':12}{'ARCHIVOS':10}DETALLE")
        for it in plan:
            label = it["area"] if not it["sub"] else f"submódulo {it['sub']}"
            print(f"{label[:33]:34}{it['state']:12}{len(it['files']):<10}{it['detail'][:90]}")
    for label, repo in [("padre", ROOT)] + [(sp.rsplit('/', 1)[-1], ROOT / sp) for sp in subs if (ROOT / sp / '.git').exists()]:
        n = unpushed(repo)
        if n > 0:
            print(f"↑ {label}: {n} commit(s) sin subir")
    if STATUS_FILE.exists():
        last = json.loads(STATUS_FILE.read_text())
        print(f"\núltima corrida: {last.get('ts')} · commits={len(last.get('committed', []))} espera={len(last.get('waiting', []))} bloqueados={len(last.get('blocked', []))}")


def cmd_commit(args):
    rep = do_commit(only=args.area, dry=args.dry_run, ignore_quiet=args.now)
    if not args.dry_run:
        write_status(rep)
    if not (rep["committed"] or rep["waiting"] or rep["blocked"]):
        log("Sin cambios que commitear")
    return rep


def cmd_push(args):
    res = do_push(dry=args.dry_run)
    return res


def cmd_run(args):
    rep = do_commit(dry=False, ignore_quiet=args.now)
    out = dict(rep)
    if CFG["push"]["auto"]:
        out["push"] = do_push()
    write_status(out)
    if not (rep["committed"] or rep["waiting"] or rep["blocked"]):
        log("Sin cambios que commitear")


def cmd_doctor(args):
    print(f"git-agent v{VERSION} · doctor")
    ok = True
    reason = repo_blocked_reason(ROOT)
    print(("✗ " + reason) if reason else "✓ repo apto (rama, sin merge/rebase, sin index.lock)")
    n = broken_index_entries(ROOT)
    print(f"{'✗' if n else '✓'} índice: {len(n)} entradas rotas")
    if n and args.fix:
        heal_index(ROOT)
    others = other_git_running()
    print(f"{'✗' if others else '✓'} procesos git activos: {len(others)}")
    hook = ROOT / ".git" / "hooks" / "post-commit"
    print(f"{'⚠' if hook.exists() else '✓'} hook post-commit: {'INSTALADO (puede lanzar agentes al commitear; el agente lo ignora con core.hooksPath)' if hook.exists() else 'no instalado'}")
    sess = run(["pgrep", "-a", "claude"], check=False).stdout.strip().splitlines()
    print(f"ℹ sesiones de claude vivas: {len(sess)}")
    cron = run(["crontab", "-l"], check=False).stdout
    print(f"{'✓' if 'git-agent (auto)' in cron else '✗'} cron: {'programado' if 'git-agent (auto)' in cron else 'NO programado (git-agent install-cron)'}")
    print(f"ℹ config: quiet_minutes={CFG['quiet_minutes']} push.auto={CFG['push']['auto']} submódulos={len(submodule_paths())}")
    return 0 if not (reason or n) else 1


CRON_MARK = "# git-agent (auto)"


def cmd_install_cron(args):
    cur = run(["crontab", "-l"], check=False).stdout
    if CRON_MARK in cur:
        print("Ya está programado.")
        return
    every = int(CFG["cron"]["commit_every_minutes"])
    script = "/usr/local/bin/git-agent" if Path("/usr/local/bin/git-agent").exists() else str(SCRIPT_DIR / "git-agent.sh")
    line = f"*/{every} * * * * {script} run --quiet >/dev/null 2>&1 {CRON_MARK}"
    new = cur.rstrip("\n") + "\n" + line + "\n"
    run(["crontab", "-"], stdin=new)
    print(f"Programado cada {every} min:\n  {line}")


def cmd_uninstall_cron(args):
    cur = run(["crontab", "-l"], check=False).stdout
    new = "\n".join(l for l in cur.splitlines() if CRON_MARK not in l) + "\n"
    run(["crontab", "-"], stdin=new)
    print("Cron de git-agent retirado.")


def main():
    global QUIET
    ap = argparse.ArgumentParser(prog="git-agent", description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--quiet", action="store_true", help="solo avisos y errores (para cron)")
    sub = ap.add_subparsers(dest="cmd", required=True)
    for name in ("status", "commit", "push", "run", "doctor", "install-cron", "uninstall-cron"):
        sp = sub.add_parser(name)
        sp.add_argument("--quiet", action="store_true")
        if name in ("status", "commit", "run"):
            sp.add_argument("--now", action="store_true", help="ignorar la espera de quiet_minutes")
        if name in ("commit", "push"):
            sp.add_argument("--dry-run", action="store_true")
        if name == "commit":
            sp.add_argument("--area", help="solo áreas cuyo nombre contenga este texto")
        if name == "doctor":
            sp.add_argument("--fix", action="store_true", help="reparar el índice si está roto")
    args = ap.parse_args()
    QUIET = args.quiet
    STATE_DIR.mkdir(parents=True, exist_ok=True)
    if args.cmd in ("commit", "push", "run"):
        lock = open(LOCK_FILE, "w")
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except OSError:
            log("Otra instancia de git-agent está corriendo; salgo", "INFO")
            return 0
    handler = {"status": cmd_status, "commit": cmd_commit, "push": cmd_push, "run": cmd_run, "doctor": cmd_doctor,
               "install-cron": cmd_install_cron, "uninstall-cron": cmd_uninstall_cron}[args.cmd]
    try:
        res = handler(args)
    except (GitError, subprocess.TimeoutExpired) as e:
        log(str(e), "ERROR")
        return 2
    return res if isinstance(res, int) else 0


if __name__ == "__main__":
    sys.exit(main())
