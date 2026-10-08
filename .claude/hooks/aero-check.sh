#!/bin/bash
# PostToolUse (Edit|Write|MultiEdit): atrapa en el acto los fallos silenciosos de October.
#  - *.php                          → php -l
#  - version.yaml / controllers/*.yaml de un plugin Aero → bin/verify-plugin (solo ERRORES, sin tests)
# Exit 2 = el mensaje vuelve a Claude para que lo corrija. Exit 0 = todo bien o no aplica.
f=$(python3 -c 'import sys,json;d=json.load(sys.stdin);print((d.get("tool_input") or {}).get("file_path",""))' 2>/dev/null)
[ -z "$f" ] && exit 0
ROOT=/www/wwwroot/micro.clouds.com.bo
case "$f" in "$ROOT"/*) ;; *) exit 0;; esac

if [[ "$f" == *.php && -f "$f" ]]; then
  out=$(/www/server/php/84/bin/php -l "$f" 2>&1) || { echo "Error de sintaxis PHP en $f:" >&2; echo "$out" >&2; exit 2; }
fi

if [[ "$f" =~ plugins/aero/([a-z0-9_]+)/(updates/version\.yaml|controllers/.*\.yaml|models/.*\.yaml)$ ]]; then
  p="${BASH_REMATCH[1]}"
  out=$("$ROOT/bin/verify-plugin" "$p" --no-tests 2>&1 | grep 'ERROR')
  if [ -n "$out" ]; then echo "verify-plugin aero/$p encontró errores tras editar $f:" >&2; echo "$out" >&2; exit 2; fi
fi
exit 0
