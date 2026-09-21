#!/usr/bin/env bash
#
# Instala el hook post-commit que dispara el agente docs-sync.
#
#   bin/docs-sync-install.sh          # instala
#   bin/docs-sync-install.sh --remove # desinstala
#
set -euo pipefail

ROOT="$(git -C "$(dirname "$0")/.." rev-parse --show-toplevel)"
SRC="$ROOT/.opencode/hooks/post-commit"
DEST="$ROOT/.git/hooks/post-commit"

if [ "${1:-}" = "--remove" ]; then
    if [ -f "$DEST" ] && grep -q "Docs Sync" "$DEST" 2>/dev/null; then
        rm -f "$DEST"
        echo "Hook post-commit eliminado."
    else
        echo "No hay hook de Docs Sync instalado."
    fi
    exit 0
fi

if [ ! -f "$SRC" ]; then
    echo "No se encontró $SRC" >&2
    exit 1
fi

if [ -f "$DEST" ] && ! grep -q "Docs Sync" "$DEST" 2>/dev/null; then
    echo "Ya existe un post-commit distinto en $DEST." >&2
    echo "Revísalo a mano y combínalo con $SRC." >&2
    exit 1
fi

cp "$SRC" "$DEST"
chmod +x "$DEST"
echo "Hook post-commit instalado en $DEST"
echo "Log: $ROOT/.opencode/docs-sync.log"
echo
echo "Cada commit que toque plugins de Aero (menos docs) disparará el agente docs-sync."
echo "Requiere que 'opencode' esté en el PATH (o exportá OPENCODE_BIN=/ruta/opencode)."
