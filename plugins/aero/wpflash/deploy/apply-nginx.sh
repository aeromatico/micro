#!/bin/bash
#
# Aero.WpFlash — aplica el enrutamiento nginx generado por
# Classes\DomainRouter::regenerateNginxConfig() (o el comando de respaldo
# `php artisan wpflash:nginx-sync`).
#
# Por qué es un script aparte en vez de que la app lo haga sola: los archivos
# de vhost de Baota son de root y recargar nginx requiere privilegios que el
# proceso de October (usuario www) no tiene ni debería tener. October solo
# escribe su archivo "deseado" en su propio storage/ (sin privilegios
# especiales); este script — instalado como cron de ROOT — es el único punto
# que toca /www/server/panel/vhost y recarga nginx.
#
# Instalación (una sola vez, como root):
#   crontab -e
#   * * * * * /www/wwwroot/micro.clouds.com.bo/plugins/aero/wpflash/deploy/apply-nginx.sh >> /var/log/aero-wpflash-nginx.log 2>&1
#
set -euo pipefail

SRC="/www/wwwroot/micro.clouds.com.bo/storage/app/wpflash/tenants.conf"
DEST="/www/server/panel/vhost/nginx/aero-wpflash.conf"

if [ ! -f "$SRC" ]; then
    echo "$(date -Iseconds) — no existe $SRC todavía, nada que aplicar."
    exit 0
fi

if [ -f "$DEST" ] && cmp -s "$SRC" "$DEST"; then
    exit 0
fi

cp "$SRC" "$DEST"

if nginx -t; then
    nginx -s reload
    echo "$(date -Iseconds) — aero-wpflash.conf actualizado y nginx recargado."
else
    echo "$(date -Iseconds) — nginx -t falló con el nuevo aero-wpflash.conf, se revierte." >&2
    rm -f "$DEST"
    exit 1
fi
