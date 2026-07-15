#!/bin/bash
# Login Azure CLI dentro do container (credenciais persistem em ../data/.azure)
exit 1
set -euo pipefail
cd "$(dirname "$0")"

if ! docker compose ps --status running --services 2>/dev/null | grep -qx azure-monitor; then
    echo "Container não está rodando. Execute ./docker/up.sh primeiro." >&2
    exit 1
fi

TENANT_ID=$(grep -E '^TENANT_ID=' ../.env 2>/dev/null | head -1 | cut -d= -f2- | tr -d ' "\r' || true)
TENANT_ID=${TENANT_ID:-organizations}

if [ -z "$TENANT_ID" ] || [ "$(printf '%s' "$TENANT_ID" | tr '[:upper:]' '[:lower:]')" = "00000000-0000-0000-0000-000000000000" ]; then
    echo "ERRO: TENANT_ID não configurado no .env." >&2
    echo "      Defina o GUID do tenant (portal.azure.com › Microsoft Entra ID › Visão geral)" >&2
    echo "      ou use TENANT_ID=organizations para contas corporativas." >&2
    exit 1
fi

echo ""
echo "=== Passo a passo ==="
echo "1. Copie o código que aparecer abaixo"
echo "2. Abra https://login.microsoft.com/device no navegador"
echo "3. Cole o código e autentique com sua conta corporativa"
echo ""
echo "A mensagem 'No subscriptions found' é normal — acesso só ao DevOps, sem assinatura Azure."
echo ""

# -iT: stdin para Enter automático, sem TTY (evita "stdin is not a terminal")
printf '\n' | docker compose exec -iT -u www-data -e AZURE_CONFIG_DIR=/var/www/html/data/.azure azure-monitor \
    az login --use-device-code --allow-no-subscriptions --tenant "${TENANT_ID}"

echo ""
echo "Verificando token do Azure DevOps..."
if docker compose exec -u www-data -e AZURE_CONFIG_DIR=/var/www/html/data/.azure azure-monitor \
    az account get-access-token --resource 499b84ac-1321-427f-aa17-267ca6975798 --output none 2>/dev/null; then
    echo "Token OK. Credenciais salvas em data/.azure (persistem entre reinícios do container)."
    echo "Acesse http://localhost:8888"
else
    echo "Login concluído, mas o token do Azure DevOps não foi obtido." >&2
    echo "Confirme que sua conta tem acesso à organização no Azure DevOps e execute o script novamente." >&2
    exit 1
fi
