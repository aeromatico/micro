#!/bin/bash
# Fachada de compatibilidad: /usr/local/bin/git-agent apunta acá. El agente real es git-agent.py (v2).
exec /usr/bin/python3 "$(dirname "$(readlink -f "$0")")/git-agent.py" "$@"
