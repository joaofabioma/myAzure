<?php

declare(strict_types=1);

namespace App\Azure;

/**
 * Cliente REST do Azure DevOps autenticado com Bearer token (Entra ID).
 *
 * - Reutiliza o token do AuthService (renovado automaticamente).
 * - Retry exponencial para falhas transitórias (rede, 429, 5xx).
 * - Em 401 tenta renovar o token uma única vez e repete a chamada.
 * - Logs estruturados sem nunca expor o token.
 */
final class AzureDevOpsService
{
    private const API_VERSION = '7.1';

    public function __construct(
        private readonly Config $config,
        private readonly AuthService $auth,
        private readonly Logger $logger,
    ) {
    }

    // ------------------------------------------------------------------
    // Operações de alto nível
    // ------------------------------------------------------------------

    /**
     * Lista os projetos da organização.
     * GET /_apis/projects?api-version=7.1
     */
    public function getProjects(): array
    {
        $data = $this->get($this->config->baseUrl() . '/_apis/projects?api-version=' . self::API_VERSION);
        return $data['value'] ?? [];
    }

    /**
     * Executa uma consulta WIQL e retorna a resposta bruta (workItems => [id, url]).
     * POST /{project}/_apis/wit/wiql?api-version=7.1
     */
    public function executeWIQL(string $wiql, ?string $project = null): array
    {
        $project = $project ?? $this->config->project;
        $url = $this->config->baseUrl() . '/' . rawurlencode($project)
            . '/_apis/wit/wiql?api-version=' . self::API_VERSION;

        return $this->post($url, ['query' => $wiql]);
    }

    /**
     * Busca Work Items por IDs (em lotes de 200 — limite da API).
     * GET /_apis/wit/workitems?ids=...&api-version=7.1
     *
     * @param int[]         $ids
     * @param string[]|null $fields Campos a retornar (null = todos)
     */
    public function getWorkItems(array $ids, ?array $fields = null): array
    {
        if ($ids === []) {
            return [];
        }

        $items = [];
        foreach (array_chunk($ids, 200) as $chunk) {
            $query = [
                'ids'           => implode(',', array_map('intval', $chunk)),
                'api-version'   => self::API_VERSION,
            ];
            if ($fields !== null) {
                $query['fields'] = implode(',', $fields);
            }
            $url = $this->config->baseUrl() . '/_apis/wit/workitems?' . http_build_query($query);
            $data = $this->get($url);
            $items = array_merge($items, $data['value'] ?? []);
        }

        return $items;
    }

    /**
     * FUNCIONALIDADE PRINCIPAL: consulta as atividades lançadas no período,
     * sem PAT — WIQL para obter os IDs e depois detalhes dos Work Items.
     *
     * @param string      $dateFrom  ex: 2026-07-01T00:00:00.0000000
     * @param string      $dateTo    ex: 2026-07-31T00:00:00.0000000
     * @param string|null $project   null = projeto padrão da configuração
     *
     * @return array<int, array{id:int,title:string,assignedTo:string,assignedToEmail:string,
     *                          state:string,createdDate:string,changedDate:string,
     *                          iterationPath:string,areaPath:string,completedWork:float,
     *                          originalEstimate:float,prazo:string}>
     */
    public function getActivities(string $dateFrom, string $dateTo, ?string $project = null): array
    {
        $wiql = <<<WIQL
        SELECT [System.Id]
        FROM workitems
        WHERE [Custom.Prazoss] >= '{$dateFrom}'
          AND [Custom.Prazoss] <= '{$dateTo}'
          AND [System.WorkItemType] <> ''
          AND [System.AssignedTo] = @me
        ORDER BY [Custom.Prazoss]
        WIQL;

        $result = $this->executeWIQL($wiql, $project);
        $ids = array_map(static fn(array $wi): int => (int)$wi['id'], $result['workItems'] ?? []);

        $this->logger->info('AzureDevOpsService: WIQL executada.', [
            'project' => $project ?? $this->config->project,
            'total'   => count($ids),
        ]);

        if ($ids === []) {
            return [];
        }

        $items = $this->getWorkItems($ids, [
            'System.Id',
            'System.Title',
            'System.AssignedTo',
            'System.State',
            'System.CreatedDate',
            'System.ChangedDate',
            'System.IterationPath',
            'System.AreaPath',
            'System.WorkItemType',
            'System.TeamProject',
            'System.Parent',
            'System.CommentCount',
            'Microsoft.VSTS.Common.Activity',
            'Microsoft.VSTS.Scheduling.CompletedWork',
            'Microsoft.VSTS.Scheduling.OriginalEstimate',
            'Custom.Prazoss',
        ]);

        return array_map(static function (array $wi): array {
            $f = $wi['fields'] ?? [];
            return [
                'id'               => (int)($wi['id'] ?? 0),
                'title'            => (string)($f['System.Title'] ?? ''),
                'assignedTo'       => (string)($f['System.AssignedTo']['displayName'] ?? ''),
                'assignedToEmail'  => (string)($f['System.AssignedTo']['uniqueName'] ?? ''),
                'state'            => (string)($f['System.State'] ?? ''),
                'createdDate'      => (string)($f['System.CreatedDate'] ?? ''),
                'changedDate'      => (string)($f['System.ChangedDate'] ?? ''),
                'iterationPath'    => (string)($f['System.IterationPath'] ?? ''),
                'areaPath'         => (string)($f['System.AreaPath'] ?? ''),
                'completedWork'    => (float)($f['Microsoft.VSTS.Scheduling.CompletedWork'] ?? 0),
                'originalEstimate' => (float)($f['Microsoft.VSTS.Scheduling.OriginalEstimate'] ?? 0),
                'prazo'            => (string)($f['Custom.Prazoss'] ?? ''),
            ];
        }, $items);
    }

    // ------------------------------------------------------------------
    // HTTP genérico (Bearer + retry + tratamento 401/403)
    // ------------------------------------------------------------------

    public function get(string $url): array
    {
        return $this->request('GET', $url);
    }

    public function post(string $url, array $payload): array
    {
        return $this->request('POST', $url, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** Igual ao get(), mas retorna o corpo bruto (para quem precisa do JSON original). */
    public function getRaw(string $url): string
    {
        return $this->requestRaw('GET', $url)['body'];
    }

    /** Igual ao post(), mas com payload/retorno brutos. */
    public function postRaw(string $url, string $payload): string
    {
        return $this->requestRaw('POST', $url, $payload)['body'];
    }

    private function request(string $method, string $url, ?string $payload = null): array
    {
        $body = $this->requestRaw($method, $url, $payload)['body'];
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new ApiException('Resposta não-JSON da API Azure DevOps.', 0, substr($body, 0, 500));
        }
        return $data;
    }

    /**
     * @return array{status:int, body:string}
     */
    private function requestRaw(string $method, string $url, ?string $payload = null): array
    {
        $attempts = $this->config->httpRetries + 1;
        $refreshed = false;
        $lastError = '';

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $token = $this->auth->getAccessToken();

            $curl = curl_init($url);
            $opts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_TIMEOUT        => $this->config->httpTimeout,
                CURLOPT_CONNECTTIMEOUT => min(10, $this->config->httpTimeout),
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $token,
                    'Accept: application/json',
                    'Content-Type: application/json',
                ],
            ];
            if ($payload !== null) {
                $opts[CURLOPT_POSTFIELDS] = $payload;
            }
            curl_setopt_array($curl, $opts);

            $body = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $curlErr = curl_error($curl);
            $curl = null;

            // Falha de rede/timeout → transitória, tenta de novo
            if ($body === false) {
                $lastError = $curlErr;
                $this->logger->warning('AzureDevOpsService: falha de rede, tentando novamente.', [
                    'method' => $method, 'url' => $url, 'attempt' => $attempt, 'error' => $curlErr,
                ]);
                $this->backoff($attempt);
                continue;
            }

            if ($status >= 200 && $status < 300) {
                return ['status' => $status, 'body' => (string)$body];
            }

            // 401: token expirado/inválido → força renovação e repete UMA vez
            if ($status === 401 && !$refreshed) {
                $refreshed = true;
                $this->logger->warning('AzureDevOpsService: HTTP 401, renovando token e repetindo chamada.', [
                    'method' => $method, 'url' => $url,
                ]);
                try {
                    if ($this->config->authMode === Config::AUTH_MODE_OAUTH) {
                        $this->auth->refreshToken();
                    } else {
                        // modo CLI: limpa cache em memória; getAccessToken pedirá novo token à CLI
                        $this->auth->logout();
                    }
                } catch (AuthException $e) {
                    throw new ApiException('Não autorizado (401) e renovação falhou: ' . $e->getMessage(), 401, (string)$body);
                }
                continue;
            }

            if ($status === 401) {
                $this->logger->error('AzureDevOpsService: HTTP 401 persistente.', ['method' => $method, 'url' => $url]);
                throw new ApiException('Não autorizado (401): token recusado pelo Azure DevOps. Efetue login novamente.', 401, (string)$body);
            }

            if ($status === 403) {
                $this->logger->error('AzureDevOpsService: HTTP 403 — sem permissão.', ['method' => $method, 'url' => $url]);
                throw new ApiException(
                    'Acesso negado (403): o usuário autenticado não tem permissão neste recurso do Azure DevOps.',
                    403,
                    (string)$body
                );
            }

            // 429 / 5xx → transitório, retry com backoff
            if ($status === 429 || $status >= 500) {
                $lastError = "HTTP $status";
                $this->logger->warning('AzureDevOpsService: resposta transitória, tentando novamente.', [
                    'method' => $method, 'url' => $url, 'status' => $status, 'attempt' => $attempt,
                ]);
                $this->backoff($attempt);
                continue;
            }

            // Demais 4xx → erro definitivo
            $this->logger->error('AzureDevOpsService: erro na API.', [
                'method' => $method, 'url' => $url, 'status' => $status,
                'body' => substr((string)$body, 0, 300),
            ]);
            throw new ApiException("Erro HTTP $status na API Azure DevOps.", $status, (string)$body);
        }

        throw new ApiException("Falha após {$attempts} tentativas: {$lastError}", 0);
    }

    private function backoff(int $attempt): void
    {
        // 0.5s, 1s, 2s... com teto de 8s
        usleep(min(8_000_000, (int)(500_000 * (2 ** ($attempt - 1)))));
    }
}
