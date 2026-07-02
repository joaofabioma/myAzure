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
| Conta Azure DevOps | — | Gerar o Personal Access Token (PAT) |

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

### 4.1 Gerar o Personal Access Token (PAT)

1. Acesse `https://dev.azure.com/{SuaOrganizacao}/_usersSettings/tokens`;
2. Clique em **+ New Token**;
3. Dê permissão de **leitura em Work Items** e defina a validade;
4. Copie o token gerado (ele só é exibido uma vez).

### 4.2 Editar o `.env`

Abra o `.env` na raiz do projeto e preencha:

| Variável | Descrição | Exemplo |
|---|---|---|
| `ORGANIZATION` | Nome da organização no Azure DevOps | `Loglab` |
| `PERSONAL_ACESS_TOKEN` | PAT gerado no passo anterior | `abc123...` |
| `PROJECT_DEFAULT` | Projeto padrão | `CUIABA-MT-BRASIL` |
| `PROJECTS` | Lista de projetos, separados por vírgula | `SITE-NOVO,CONTROLE-FINANCEIRO` |
| `EMAIL_DEV` | E-mail do desenvolvedor logado no Azure (filtra as tarefas) | `nome.sobrenome@dominio.com.br` |
| `TEMPO_RECARREGAR_PAGINA_MINUTOS` | Intervalo de auto-refresh do dashboard | `30` |
| `REQUEST_ONLINE` | `TRUE` = busca dados na API; `FALSE` = usa o cache local em `data/*.json` | `TRUE` |
| `APP_DEBUG` | Exibe informações de depuração | `FALSE` |
| `URL_TASKS`, `URL_LIST_TASKS`, `URL_ACCESS` | URLs da API — **manter os valores do `.env-example`** | — |

---

## 5. Rodar a aplicação

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
| Página vazia / sem dados | PAT inválido/expirado, `EMAIL_DEV` errado ou projetos incorretos no `.env` | Revise o `.env` e gere um novo PAT se necessário |
| `Failed to open stream: vendor/autoload.php` | `composer install` não foi executado | Rode `composer setup` na raiz do projeto |

---

## 7. Estrutura do projeto (resumo)

| Caminho | Papel |
|---|---|
| `index.php` | Dashboard principal (tabela + gráfico por dia) |
| `dashboard.php` | Análise mensal: gráficos e auditoria de inconsistências |
| `functions.php` | Biblioteca compartilhada: helpers, fetch da API, filtros, agrupamentos |
| `gerarmesatual.php` | Gera o arquivo PHP do mês atual (ex.: `mar2026.php`) |
| `inc/prepend.php` | Carrega o `.env` e define as constantes (ORG, PAT, PROJ, EDEV, ONLINE) |
| `inc/const.php` | Constante `VERSION` (incrementada a cada alteração) |
| `src/Class/Security.php` | Bloqueia acesso direto a arquivos internos |
| `data/*.json` | Cache bruto da API (pré-filtro) |
| `logs/` | Logs de performance |
