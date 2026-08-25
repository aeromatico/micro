#!/usr/bin/env bash
# Actualiza Alpine.js local al ultimo 3.x y sincroniza la version en el layout.
set -euo pipefail
cd "$(dirname "$0")/.."

THEME=themes/microsites
V=$(curl -fsS "https://registry.npmjs.org/alpinejs/latest" | python3 -c "import sys,json;print(json.load(sys.stdin)['version'])")
OLD=$(cat "$THEME/assets/js/.alpine-version" 2>/dev/null || echo "?")

if [ "$V" = "$OLD" ]; then
  echo "Alpine ya esta en $V"
  exit 0
fi

echo "Alpine $OLD -> $V"
curl -fsS -o "$THEME/assets/js/alpine.min.js" "https://cdn.jsdelivr.net/npm/alpinejs@${V}/dist/cdn.min.js"
echo "$V" > "$THEME/assets/js/.alpine-version"
sed -i "s|alpine\.min\.js' | theme }}?v=[0-9.]*|alpine.min.js' | theme }}?v=${V}|" "$THEME/layouts/base.htm"
chown www:www "$THEME/assets/js/alpine.min.js" 2>/dev/null || true
echo "Listo. Revisa el diff y prueba el sitio antes de commitear."
