# Rodar com Docker (sem PHP na máquina)

Para quem **não tem PHP** instalado e **não tem acesso ao Azure Portal** para criar App Registration.

## Stack

O container usa **Nginx + PHP-FPM** (Alpine). Configuração em `docker/nginx/default.conf`.
A variável `AZURE_CONFIG_DIR` é definida no `docker-compose.yml` (não há Apache).

## Pré-requisitos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- Conta de **desenvolvedor** no Azure DevOps

## Passo a passo

### 1. Copiar configuração

**Windows (PowerShell):**
```powershell
copy .env-example .env
```

**Linux/macOS:**
```bash
cp .env-example .env
```

### 2. Editar o `.env`

| Variável | O que colocar |
|---|---|
| `AUTH_MODE` | `device` (login no navegador, sem Portal Azure) |
| `EMAIL_DEV` | Seu e-mail no Azure DevOps |
| `ORGANIZATION` | Nome da organização (ex.: `Empresa`) |
| `TENANT_ID` | Tenant da empresa (ou `organizations`) |
| `APP_KEY` | 64 caracteres hex (gere uma chave ou copie do exemplo de um colega) |
| `PROJECTS` | Projetos que você usa (opcional) |

> Use comentários com `#` no `.env` (não use `;` — o Docker Compose não interpreta comentários INI).

### 3. Subir

```bash
docker compose up -d --build
```

### 4. Login no navegador (uma vez)

1. Abra **http://localhost:8080**
2. Siga as instruções em `/login.php` (código + [microsoft.com/devicelogin](https://microsoft.com/devicelogin))
3. Entre com a **conta corporativa**
4. Pronto — token salvo em `data/.auth_blob` (criptografado)

## Repassar para um colega

1. Enviar o projeto (git ou ZIP)
2. `copy .env-example .env` → editar `EMAIL_DEV`, `ORGANIZATION`, `AUTH_MODE=device`
3. `docker compose up -d --build`
4. Abrir http://localhost:8080 e fazer login uma vez

## Comandos

```bash
docker compose logs -f
docker compose down
docker compose up -d --build
```

## Problemas comuns

| Sintoma | Solução |
|---|---|
| `failed to read .env` no compose | Troque comentários `;` por `#` no `.env` |
| `.env` não montado | Criar `.env` antes do `docker compose up` |
| Código expirou | Recarregar `/login.php` |
| HTTP 403 | Permissões no projeto Azure DevOps |
| Erro de tenant | `TENANT_ID=organizations` no `.env` |

## Modo `device`

Device Code Flow com client ID público da Microsoft — não exige App Registration. Refresh token automático; blob em `data/.auth_blob`.
