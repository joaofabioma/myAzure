#!/bin/bash
# Sobe o Azure DevOps Monitor em Docker (JSON em ./data persiste no host)
set -euo pipefail
cd "$(dirname "$0")"
docker compose up -d --build "$@"
