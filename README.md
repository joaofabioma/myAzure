# Azure DevOps Monitor

Aplicação PHP que consulta a API do Azure DevOps e exibe as horas lançadas por mês, com dashboard de análise mensal e auditoria de inconsistências(parcial).

Este guia é voltado para quem **não tem PHP instalado** e precisa colocar a aplicação para rodar do zero usando **PHP 8.5**.

---

## 1. Requisitos

| Item | Versão mínima | Para quê |
|---|---|---|
| PHP | **8.5.1** | Executar a aplicação (exigido pelo `composer.json`) |
| Composer | 2.x | Gerar o autoload (`vendor/`), o arquivo `VERSION` e o `.env` |
| Extensões PHP | `curl`, `mbstring`, `openssl` | Chamadas HTTPS à API do Azure DevOps e tratamento de strings |
| Conta corporativa (Entra ID) | — | Login OAuth no Azure DevOps (**sem PAT**) |
| Azure CLI (modo `cli`) | 2.x | Emitir o Access Token via `az login` |

> A aplicação **não roda** sem o `composer install`: as pastas `vendor/` e o arquivo `VERSION` não estão no repositório (estão no `.gitignore`).

---

## 2. Instalar o PHP 8.5

### Windows

**Opção A — winget (recomendado):**

```powershell
winget install PHP.PHP.8.5
```

**Opção B — manual:**

1. Baixe o ZIP **PHP 8.5 (x64, Non Thread Safe)** em [windows.php.net/download](https://windows.php.net/download/).
2. Extraia para `C:\php85`.
3. Adicione `C:\php85` à variável de ambiente `Path` (Configurações → Sistema → Variáveis de ambiente).

**Configurar o `php.ini` (obrigatório no Windows):**

1. Na pasta do PHP, copie `php.ini-development` para `php.ini`.
2. Abra o `php.ini` e descomente (remova o `;` do início) as linhas:

```ini
extension_dir = "ext"
extension=curl
extension=mbstring
extension=openssl
```

3. Para que o `curl` valide certificados HTTPS (necessário para acessar a API do Azure), baixe o [cacert.pem](https://curl.se/ca/cacert.pem), salve em `C:\php85\extras\cacert.pem` e configure no `php.ini`:

```ini
curl.cainfo = "C:\php85\extras\cacert.pem"
openssl.cafile = "C:\php85\extras\cacert.pem"
```

### macOS

```bash
brew install php@8.5
brew link php@8.5 --force
```

*(No macOS as extensões `curl`, `mbstring` e `openssl` já vêm habilitadas.)*

### Linux (Ubuntu/Debian)

```bash
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install php8.5-cli php8.5-curl php8.5-mbstring
```

### Verificar a instalação

```bash
php -v          # deve exibir PHP 8.5.x
php -m          # deve listar: curl, mbstring, openssl
```

---

## 3. Instalar o Composer

- **Windows:** baixe e execute o [Composer-Setup.exe](https://getcomposer.org/Composer-Setup.exe) (ele detecta o PHP instalado automaticamente), ou:

```powershell
winget install Composer.Composer
```

- **macOS/Linux:**

```bash
brew install composer        # macOS
sudo apt install composer    # Ubuntu/Debian
```

Verifique com `composer -V`.

---

## 4. Baixar e configurar o projeto

```bash
git clone https://github.com/joaofabioma/myAzure.git
cd myAzure
composer setup
```

O comando `composer setup` faz três coisas:

1. Roda o `composer install` (gera a pasta `vendor/` com o autoload PSR-4);
2. Grava o arquivo `VERSION` na raiz (sem ele a aplicação responde **503 — Versão incompatível**);
3. Copia `.env-example` para `.env`, caso ainda não exista.

### 4.1 Configurar a autenticação (Microsoft Entra ID — sem PAT)

A aplicação **não usa PAT**. Os tokens OAuth são emitidos pelo Microsoft Entra ID.
Escolha um dos modos no `.env` (variável `AUTH_MODE`):

- **`cli`** (padrão): instale a Azure CLI (`winget install Microsoft.AzureCLI`) e rode `az login` com a conta corporativa. Nada mais é necessário.
- **`oauth`**: requer um App Registration no Entra ID (fluxo Authorization Code + PKCE). Siga o guia completo em [`docs/AUTENTICACAO-ENTRA-ID.md`](docs/AUTENTICACAO-ENTRA-ID.md).
- **`device`**: login Microsoft no navegador (Device Code Flow), **sem App Registration** — ideal para Docker. Veja [`docs/DOCKER.md`](docs/DOCKER.md).

### 4.2 Editar o `.env`

Abra o `.env` na raiz do projeto e preencha:

| Variável | Descrição | Exemplo |
|---|---|---|
| `ORGANIZATION` | Nome da organização no Azure DevOps | `Empresa` |
| `AUTH_MODE` | `cli`, `oauth` ou `device` (Docker, sem Portal) | `cli` |
| `TENANT_ID` | Directory (tenant) ID do Entra ID | `f47ac10b-...` |
| `CLIENT_ID` | Application (client) ID do App Registration (modo `oauth`) | `a1b2c3d4-...` |
| `REDIRECT_URI` | Redirect URI registrada no Entra ID (modo `oauth`) | `http://localhost:8000/callback.php` |
| `APP_KEY` | Chave p/ criptografar o token (modos `oauth` e `device`) | `php -r "echo bin2hex(random_bytes(32));"` |
| `HTTP_TIMEOUT` / `HTTP_RETRIES` | Timeout (s) e retries das chamadas à API | `30` / `3` |
| `PROJECT_DEFAULT` | Projeto padrão | `CUIABA-MT-BRASIL` |
| `PROJECTS` | Lista de projetos, separados por vírgula | `SITE-NOVO,CONTROLE-FINANCEIRO` |
| `EMAIL_DEV` | E-mail do desenvolvedor logado no Azure (filtra as tarefas) | `nome.sobrenome@dominio.com.br` |
| `TEMPO_RECARREGAR_PAGINA_MINUTOS` | Intervalo de auto-refresh do dashboard | `30` |
| `REQUEST_ONLINE` | `TRUE` = busca dados na API; `FALSE` = usa o cache local em `data/*.json` | `TRUE` |
| `APP_DEBUG` | Exibe informações de depuração | `FALSE` |
| `URL_TASKS`, `URL_LIST_TASKS`, `URL_ACCESS` | URLs da API — **manter os valores do `.env-example`** | — |

---

## 5. Rodar a aplicação

### Com Docker (sem PHP instalado)

```bash
copy .env-example .env    # edite EMAIL_DEV e AUTH_MODE=device
docker compose up -d --build
```

Guia completo: [`docs/DOCKER.md`](docs/DOCKER.md) — abra **http://localhost:8080**.

### Com PHP local

Use o servidor embutido do próprio PHP (não precisa de Apache/Nginx):

```bash
php -S localhost:8000
```

Depois abra no navegador: **<http://localhost:8000>**

| Página | URL |
|---|---|
| Dashboard principal (horas por dia) | `http://localhost:8000/index.php` |
| Análise mensal (gráficos e auditoria) | `http://localhost:8000/dashboard.php` |

Na primeira execução com `REQUEST_ONLINE=TRUE`, a aplicação consulta a API do Azure DevOps e salva o cache em `data/*.json`. Nas execuções seguintes, se quiser trabalhar offline, basta trocar para `REQUEST_ONLINE=FALSE`.

---

## 6. Problemas comuns

| Sintoma | Causa | Solução |
|---|---|---|
| `Versão incompatível ... Aplicação encerrada.` (HTTP 503) | Arquivo `VERSION` ausente ou desatualizado em relação a `inc/const.php` | Rode `composer install` (o script pós-instalação regrava o `VERSION`) |
| `Call to undefined function curl_init()` | Extensão `curl` desabilitada | Descomente `extension=curl` no `php.ini` e reinicie o servidor |
| `Call to undefined function mb_...()` | Extensão `mbstring` desabilitada | Descomente `extension=mbstring` no `php.ini` |
| Erro de certificado SSL nas chamadas à API (Windows) | `curl` sem CA bundle | Configure `curl.cainfo` no `php.ini` (ver seção 2) |
| Página vazia / sem dados | Sem login no Entra ID, `EMAIL_DEV` errado ou projetos incorretos no `.env` | Rode `az login` (modo `cli`) ou acesse `/login.php` (modo `oauth`); revise o `.env` |
| `Autenticação Azure DevOps necessária` (HTTP 401) | Token ausente/expirado | Modo `cli`: `az login`; modo `oauth`: acessar `/login.php` |
| `Failed to open stream: vendor/autoload.php` | `composer install` não foi executado | Rode `composer setup` na raiz do projeto |

---

## 7. Estrutura do projeto (resumo)

| Caminho | Papel |
|---|---|
| `index.php` | Dashboard principal (tabela + gráfico por dia) |
| `dashboard.php` | Análise mensal: gráficos e auditoria de inconsistências |
| `functions.php` | Biblioteca compartilhada: helpers, fetch da API, filtros, agrupamentos |
| `gerarmesatual.php` | Gera o arquivo PHP do mês atual (ex.: `mar2026.php`) |
| `inc/prepend.php` | Carrega o `.env`, inicializa os serviços Azure e define as constantes (ORG, PROJ, EDEV, ONLINE) |
| `inc/const.php` | Constante `VERSION` (incrementada a cada alteração) |
| `src/Class/Security.php` | Bloqueia acesso direto a arquivos internos |
| `src/Azure/` | Autenticação Entra ID + cliente REST (ver [`docs/AUTENTICACAO-ENTRA-ID.md`](docs/AUTENTICACAO-ENTRA-ID.md)) |
| `login.php` / `callback.php` / `logout.php` | Fluxo OAuth 2.0 (Authorization Code + PKCE) |
| `atividades.php` | Endpoint JSON/HTML com as atividades do mês (WIQL + Work Items) |
| `data/*.json` | Cache bruto da API (pré-filtro) |
| `logs/` | Logs de performance |
