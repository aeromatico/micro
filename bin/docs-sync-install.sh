#!/usr/bin/env bash
#
# Programa el vigilante de documentación (docs-sync-watch) — la forma robusta de mantener
# al día la documentación del tenant. El hook post-commit es un acelerador OPCIONAL.
#
#   bin/docs-sync-install.sh               # cron cada 30 min (sin tocar el hook)
#   bin/docs-sync-install.sh --with-hook   # además instala el hook acelerador (seguro)
#   bin/docs-sync-install.sh --remove      # quita el cron y el hook
#
# El vigilante arranca en SIMULACIÓN (dry_run=true en .opencode/docs-sync.json): registra qué
# documentaría sin llamar al modelo. Para activarlo de verdad: "dry_run": false.
#
set -euo pipefail

ROOT="$(git -C "$(dirname "$0")/.." rev-parse --show-toplevel)"
WATCH="$ROOT/.opencode/docs-sync-watch.py"
SRC="$ROOT/.opencode/hooks/post-commit"
DEST="$ROOT/.git/hooks/post-commit"

[ -x "$WATCH" ] || { echo "No se encontró $WATCH" >&2; exit 1; }

case "${1:-}" in
  --remove)
    "$WATCH" uninstall-cron
    if [ -f "$DEST" ] && grep -q "Docs Sync" "$DEST" 2>/dev/null; then rm -f "$DEST"; echo "Hook post-commit eliminado."; fi
    exit 0 ;;
  --with-hook)
    if [ -f "$DEST" ] && ! grep -q "Docs Sync" "$DEST" 2>/dev/null; then
      echo "Ya existe un post-commit distinto en $DEST; revísalo a mano." >&2; exit 1
    fi
    cp "$SRC" "$DEST"; chmod +x "$DEST"; echo "Hook acelerador instalado en $DEST" ;;
esac

"$WATCH" install-cron
echo
"$WATCH" status
