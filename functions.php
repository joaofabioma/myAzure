<?php
header('X-Robots-Tag: noindex, nofollow, noarchive');
date_default_timezone_set('America/Cuiaba');
setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'pt_BR.utf-8', 'portuguese');
require __DIR__ . '/inc/prepend.php';

if (basename($_SERVER["REQUEST_URI"]) == basename(__FILE__)) {
    header('Location: /', true, 301);
    exit();
}
$ini_app = perf_start('App', __FILE__, __LINE__);

$ini_prepend = perf_start('Inicio Prepend', __FILE__, __LINE__);

perf_end('Inclusao Prepend', $ini_prepend, __FILE__, __LINE__);
if (empty($_SERVER['HTTP_USER_AGENT'])) {
    http_response_code(403);
    exit;
}

function performanceTimestamp(): string
{
    $dt = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', microtime(true)));
    $ms = substr($dt->format('u'), 0, 3);
    return $dt->format('Y-m-d H:i:s') . ':' . $ms;
}

function perf_log(string $acao, string $arquivo, int $linha): void
{
    if (empty($acao)) {
        throw new InvalidArgumentException('Ação não pode ser vazia');
    }

    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $linhaLog = performanceTimestamp() . " - {$arquivo} - {$linha} - {$acao}" . PHP_EOL;
    file_put_contents($logDir . '/performance.log', $linhaLog, FILE_APPEND | LOCK_EX);
}

function perf_start(string $acao, string $arquivo, int $linha): float
{
    if (empty($acao)) {
        throw new InvalidArgumentException('Ação não pode ser vazia');
    }
    if (defined('DEBUG') && DEBUG === false)
        return 0;
    perf_log("INI - $acao", $arquivo ?? '-', $linha);
    return microtime(true);
}

function perf_end(string $acao, float $inicio, string $arquivo, int $linha): void
{
    if (defined('DEBUG') && DEBUG === false)
        return;
    $elapsedMs = (microtime(true) - $inicio) * 1000;
    perf_log("FIM- $acao" . ' | ' . number_format($elapsedMs, 3, '.', '') . 'ms', $arquivo ?? '-', $linha);
}

function _request_get(string $pUrl)
{
    $curl = curl_init($pUrl);
    if (strpos($pUrl, 'WorkItems') !== false) {
        $pUrl = urlencode($pUrl);
    }
    $curl_opt = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: " . 'Basic ' . base64_encode(":" . (defined('PAT') ? PAT : '')),
            'Accept: application/json',
            'Content-Type: application/json'
        ]
    ];
    //die(print('Basic ' . base64_encode(":" . PAT))); // para usar no Postman, se nao souber usar scripts
    curl_setopt_array($curl, $curl_opt);
    $response = curl_exec($curl);
    if ($response === false) {
        if (defined('DEBUG') && DEBUG === true) {
            $info = curl_getinfo($curl);
            var_dump($info);
            die();
        }
        die('Erro CURL na API Azure (Timeout ou Conexão): ' . curl_error($curl) . ' - Linha ' . __LINE__);
    } else {
        $curl = null;
        return $response;
    }
}

function _request_post(string $pUrl, $pPayload)
{
    $curl = curl_init($pUrl);
    //if (strpos($pUrl, 'WorkItems') !== false) {
    //    $pUrl = urlencode($pUrl);
    //}
    $curl_opt = [
        CURLOPT_RETURNTRANSFER => true,
        // CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: " . 'Basic ' . base64_encode(":" . (defined('PAT') ? PAT : '')),
            'Accept: application/json',
            'Content-Type: application/json'
        ],
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $pPayload,
    ];

    curl_setopt_array($curl, $curl_opt);
    $response = curl_exec($curl);
    // var_dump([], urlencode($pUrl), $curl_opt, $response);
    if ($response === false) {
        if (defined('DEBUG') && DEBUG === true) {
            $info = curl_getinfo($curl);
            var_dump($info);
            die();
        }
        die('Erro CURL na API Azure (Timeout ou Conexão): ' . curl_error($curl) . ' - Linha ' . __LINE__);
    } else {
        $curl = null;
        return $response;
    }
}


function tableHead(string $vTittle = ''): string
{
    return $vTittle !== '' ? "<th>$vTittle</th>" : '';
}

function tabela(array $pCols = []): string
{
    if (count($pCols) > 0) {
        $html = '<tr>';
        foreach ($pCols as $val) {
            $html .= tableHead($val);
        }
        $html .= "</tr>";
        return $html;
    }
    return '';
}

function meses(): array
{
    return [
        'jan' => '01',
        'fev' => '02',
        'mar' => '03',
        'abr' => '04',
        'mai' => '05',
        'jun' => '06',
        'jul' => '07',
        'ago' => '08',
        'set' => '09',
        'out' => '10',
        'nov' => '11',
        'dez' => '12'
    ];
}

function intervalo(string $mesAno): array
{
    $mesStr = strtolower(substr($mesAno, 0, 3));
    $ano = substr($mesAno, 3);
    $meses = meses();

    if (isset($meses[$mesStr]) && is_numeric($ano)) {
        $mesNum = $meses[$mesStr];

        // Data de início: primeiro dia do mês
        $dataInicio = "$ano-$mesNum-01T00:00:00Z";

        // Data de fim: último dia do mês
        $ultimoDia = date('t', strtotime("$ano-$mesNum-01"));
        $dataFim = "$ano-$mesNum-" . $ultimoDia . "T23:59:59Z";
        $dataInicioQry = "{$ano}-{$mesNum}-01T00:00:00.0000000";
        $dataFimQry = "{$ano}-{$mesNum}-{$ultimoDia}T00:00:00.0000000";
    } else {
        // Fallback para valores padrão
        $dataInicio = date('Y-m-01T00:00:00Z');
        $dataFim = date('Y-m-tT23:59:59Z');
        $dataInicioQry = date('Y-m-01T00:00:00.0000000');
        $dataFimQry = date('Y-m-tT23:59:59.0000000');
    }

    return [
        'ini' => $dataInicio,
        'fim' => $dataFim,
        'mes' => (int)$mesNum,
        'ano' => $ano,
        'qini' => $dataInicioQry,
        'qfim' => $dataFimQry,
    ];
}

function _urlTasks($projeto, $mes, $ano, $pEmail)
{
    $filter = "month(Custom_Prazoss) eq %s and year(Custom_Prazoss) eq %s and AssignedTo/UserEmail eq '%s'";
    $filter = sprintf($filter, $mes, $ano, $pEmail);
    $filter = urlencode($filter);
    $url = sprintf(UTASK, ORG, $projeto);
    $url .= '&$filter=' . $filter;
    perf_log('URL_REQUEST: ' . $url, __FILE__, __LINE__);
    return $url;
}

function allProjects(string $pOrganization = ORG): void
{
    if (defined('ONLINE') && ONLINE === FALSE) {
        return;
    }

    $url = "https://dev.azure.com/$pOrganization/_apis/projects";
    $response = _request_get($url);
    // var_dump($response);
    $res = json_decode($response, true);
    // var_dump($res);
    if (!empty($res) && is_array($res)) {
        file_put_contents(__DIR__ . '/data/projetos.json', $response); // emergencia ou para DEV
    }
    $res = $response = null;
}

function allUsers_OLD(string $pOrganization = ORG): void
{
    if (defined('ONLINE') && ONLINE === FALSE) {
        return;
    }
    // $url = "https://analytics.dev.azure.com/$pOrganization/_odata/v4.0-preview/Users";
    $url = "https://vssps.dev.azure.com/$pOrganization/_apis/graph/users?api-version=7.0-preview";
    $response = _request_get($url);
    $res = json_decode($response, true);
    if (!empty($res) && is_array($res)) {
        file_put_contents(__DIR__ . '/data/users.json', $response); // emergencia ou para DEV
    }
    $response = null;
}

function allUsers(string $pOrganization = ORG): void
{
    if (defined('ONLINE') && ONLINE === FALSE) {
        return;
    }

    $allUsers = [];
    $continuationToken = null;

    do {
        $url = "https://vsaex.dev.azure.com/Loglab/_apis/MemberEntitlements?skip=0";

        if ($continuationToken) {
            $url .= "&continuationToken=" . urlencode($continuationToken);
        }

        $response = _request_get($url);

        $headers = [];
        $body = null;

        // ⚠️ importante: sua função _request_get precisa retornar headers também
        // Exemplo esperado:
        // ['headers' => [...], 'body' => 'json...']
        if (is_array($response)) {
            $headers = $response['headers'] ?? [];
            $body = $response['body'] ?? null;
        } else {
            $body = $response;
        }

        $res = json_decode($body, true);

        if (!empty($res['continuationToken'])) {
            $allUsers = array_merge($allUsers, $res['items']);
        }

        // 👇 aqui está o segredo
        $continuationToken = $headers['x-ms-continuationtoken'] ?? null;
    } while ($continuationToken);

    // salva tudo
    file_put_contents(
        __DIR__ . '/data/users.json',
        json_encode($allUsers, JSON_PRETTY_PRINT)
    );
}

/**
 * Busca usuario logado no Azure DevOps, precisa ser informado no env
 * EMAIL_DEV=nome.sobrenome@dominio.com.br
 * @var string $pOrganization ORG = EmpresaSoftware
 * @return void
 */
function _me(string $pOrganization = ORG): void
{
    if (defined('ONLINE') && ONLINE === FALSE) {
        return;
    }

    $me = [];
    allUsers($pOrganization);
    $all = file_get_contents(__DIR__ . '/data/users.json');
    $all = json_decode($all, true);
    $users = !empty($all) && is_array($all) && key_exists('value', $all) ? $all['value'] : [];
    $all = null;
    if (!empty($users) && is_array($users)) {
        $me = array_filter($users, fn($u) => $u['UserEmail'] == (defined('EDEV') ? EDEV : ''));
        $me = !empty($me) && is_array($me) ? reset($me) : [];
    }
    if (is_array($me) && count($me) > 1) {
        file_put_contents(__DIR__ . '/data/me.json', json_encode($me)); // emergencia ou para DEV
    }
}

/**
 * Retorna dados do usuario logado no Azure, Dados ja carregados antes
 * @param string $key = 'all', 'UserSK','UserId', 'UserName' ou 'UserEmail'
 * @return array|string|null
 * @example $user = me('UserSK');
 */
function me($key = 'all'): array |string|null
{
    /*
     "UserSK": "UUID",
     "UserId": "UUID",
     "UserName": "Nome Completo",
     "UserEmail": "E-mail",
     "AnalyticsUpdatedDate": "TIMESTAMP",
     "GitHubUserId": null,
     "UserType": null
     */
    $all = file_get_contents(__DIR__ . '/data/me.json');
    $all = json_decode($all, true);
    if (!empty($all)) {
        if (is_array($all)) {
            if (key_exists($key, $all)) {
                return $all[$key];
            } else {
                return $all;
            }
        }
        return $all;
    } else {
        return null;
    }
}

function __naoAutorizado__($httpCode = 401)
{
    header("HTTP/1.1 $httpCode");
    die('Nao Autorizado!');
}

/*function request_content(string $pProjeto = PROJETO) {
 $url = _urlTasks($pProjeto);
 $response = _request_get($url);
 return $response; }*/

function dados_agrupados(array $dados = []): array
{
    $grouped = [];
    foreach ($dados as $r) {
        $rawDate = $r['Custom_Prazoss'] ?? ($r['date'] ?? null);
        if (empty($rawDate))
            continue;
        $key = date('Y-m-d', strtotime($rawDate));

        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'hours' => 0.0,
                'total' => 0,
                'tarefas' => [],
                'correcoes' => 0,
                'corrigir' => [],
            ];
        }

        // horas
        $hours = 0.0;
        if (isset($r['CompletedWork']) && is_numeric($r['CompletedWork'])) {
            $hours = floatval($r['CompletedWork']);
        } else {
            foreach ($r as $v) {
                if (is_numeric($v)) {
                    $hours = floatval($v);
                    break;
                }
            }
        }
        $grouped[$key]['hours'] += $hours;

        $completedWork = floatval($r['CompletedWork'] ?? 0);
        $OriginalEstimate = floatval($r['OriginalEstimate'] ?? 0);
        if ($completedWork == $OriginalEstimate && $completedWork > 0) {
            $grouped[$key]['correcoes'] += 1;
            if (!in_array($r['WorkItemId'], $grouped[$key]['corrigir'], true)) {
                $grouped[$key]['corrigir'][] = $r['WorkItemId'];
            }
        }



        // identificar id e título da tarefa (tentativa por chaves comuns)
        $id = null;
        $title = null;
        foreach ($r as $k => $v) {
            $lk = strtolower($k);
            if ($id === null && (stripos($lk, 'WorkItemId') !== false || stripos($lk, 'WorkItemId') !== false || $lk === 'id' || stripos($lk, 'id') !== false) && (is_numeric($v) || is_string($v))) {
                $id = (string)$v;
            }
        }

        $label = null;
        if ($title && $id)
            $label = $title . ' (' . $id . ')';
        elseif ($title)
            $label = $title;
        elseif ($id)
            $label = $id;

        if ($label) {
            // evitar duplicatas
            if (!in_array($label, $grouped[$key]['tarefas'], true)) {
                $grouped[$key]['tarefas'][] = $label;
            }
        }

        $grouped[$key]['total']++;
    }
    return $grouped;
}

function hasAnalyticsAccess(string $pOrganization = ORG): bool
{
    $url = "https://analytics.dev.azure.com/$pOrganization/_odata/v4.0-preview/";
    $response = _request_get($url);
    $data = json_decode($response, true);

    if (!empty($data) && is_array($data)) {
        file_put_contents(__DIR__ . '/data/hasAnalyticsAccess.json', $response); // emergencia ou para DEV
    }

    return !isset($data['error']);
}

function _url_lista_tarefas_proj(string $pProject, string $pOrganization = ORG)
{
    //
    $url = sprintf(LISTTASKS, $pOrganization, $pProject);
    perf_log('URL_REQUEST: ' . $url, __FILE__, __LINE__);
    return $url;
}

function _get_query_mes(string $pCustomPrazoIni, string $pCustomPrazoFim): string
{
    //[Custom.Prazoss] >= '{$dataIni}T00:00:00.0000000'
    //[Custom.Prazoss] <= '{$dataFim}T00:00:00.0000000'


    $query = [
        'query' => <<<WIQL
SELECT
    [System.Id]
FROM workitems
WHERE
    [Custom.Prazoss] >= '$pCustomPrazoIni'
    AND [Custom.Prazoss] <= '$pCustomPrazoFim'
    AND [System.WorkItemType] <> ''
    AND [System.AssignedTo] = @me
WIQL
    ];

    $encoded = json_encode($query, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    perf_log('Query WIQL gerada: ' . $encoded, __FILE__, __LINE__);

    return $encoded;
}

function _get_WorkItemsId(string $pUrl, string $pDataIni, string $pDataFim): array|false
{
    perf_log('Request Work Items: ' . $pUrl, __FILE__, __LINE__);

    $vPayload = _get_query_mes($pDataIni, $pDataFim);

    perf_log('Post Request List WorkItems: ' . $pUrl, __FILE__, __LINE__);
    $response = _request_post($pUrl, $vPayload);

    $data = json_decode($response, true);
    $workItemsId = [];

    // Extrair os IDs dos WorkItems
    if (isset($data['workItems']) && is_array($data['workItems'])) {
        foreach ($data['workItems'] as $item) {
            if (isset($item['id'])) {
                $workItemsId[] = $item['id'];
            }
        }
    }

    return $workItemsId;
}

function tarefas_mes(array $pWorkItemsId = [], string $pOrganization = ORG)
{
    if (empty($pWorkItemsId)) {
        return;
    }
    $ids = implode(',', $pWorkItemsId);
    $url = sprintf(UTASK, $pOrganization, $ids);
    perf_log('Request Tarefas Mes: ' . $url, __FILE__, __LINE__);

    $response = _request_get($url);
    $data = json_decode($response, true);
    $count = is_array($data) && key_exists('count', $data) ? (int) $data['count'] : 0;
    $newData = [];

    if ($count > 0) {
        $d = $data['value'];

        foreach ($d as $k => $v) {
            $newData[] = _parseWorkItems($v);
        }
    }

    return $newData;
}

function _parseWorkItems(array $pWorkItem = []): array
{
    // parse array para o modelo anterior
    if (!empty($pWorkItem) && is_array($pWorkItem) && key_exists('fields', $pWorkItem) && key_exists('id', $pWorkItem)) {
        $fields = $pWorkItem['fields'];
        $value = [
            'WorkItemId' => $fields['System.Id'] ?? 0,
            'Title' => $fields['System.Title'] ?? 'Ne',
            'WorkItemType' => $fields['System.WorkItemType'] ?? 'Ne',
            'State' => $fields['System.State'] ?? 'Ne',
            'Activity' => $fields['Microsoft.VSTS.Common.Activity'] ?? 'Ne',
            'CompletedWork' => $fields['Microsoft.VSTS.Scheduling.CompletedWork'] ?? 0,
            'OriginalEstimate' => $fields['Microsoft.VSTS.Scheduling.OriginalEstimate'] ?? 0,
            'ParentWorkItemId' => $fields['System.Parent'] ?? 'Ne',
            'Rev' => $pWorkItem['rev'],
            'CommentCount' => $fields['System.CommentCount'] ?? 0,
            'Custom_Prazoss' => $fields['Custom.Prazoss'] ?? '2020-02-20T20:02:22Z',
            'Project' => ['ProjectName' => $fields['System.TeamProject']],
            'AssignedTo' => [
                'UserName' => $fields['System.AssignedTo']['displayName'],
                'UserEmail' => $fields['System.AssignedTo']['uniqueName'],
            ]
        ];
    } else {
        $value = [];
    }

    return $value;
}

$data = hasAnalyticsAccess();

if (isset($qual)) {
    $ini_principal = perf_start('Processamento Principal', __FILE__, __LINE__);

    $ini_request_projects = perf_start('Processamento Request - AllProjects', __FILE__, __LINE__);
    allProjects();
    perf_end('Processamento Request - AllProjects', $ini_request_projects, __FILE__, __LINE__);

    $ini_request = perf_start('Processamento Request - _me', __FILE__, __LINE__);
    _me();
    perf_end('Processamento Request - _me', $ini_request_projects, __FILE__, __LINE__);

    $ini_var = perf_start('Carregamento Variaveis', __FILE__, __LINE__);
    $dados = [];
    $json = [];
    $usuario = me('UserSK');
    $umail = me('UserEmail');
    $projects = defined('PROJ') ? PROJ : [];
    $project = defined('PROJETO') ? PROJETO : '';
    $intervalo = isset($qual) ? intervalo($qual) : ["ini" => null, "fim" => null];
    $dataInicio = $intervalo["ini"];
    $dataFim = $intervalo["fim"];
    $dataInicioQ = $intervalo["qini"];
    $dataFimQ = $intervalo["qfim"];
    $mes = $intervalo["mes"];
    $ano = $intervalo["ano"];
    perf_end('Carregamento Variaveis', $ini_var, __FILE__, __LINE__);


    if (file_exists(__DIR__ . '/data/' . "$qual.json") && defined('ONLINE') && ONLINE === false) {
        perf_log('carregar dados do arquivo cache', __FILE__, __LINE__);
        echo PHP_EOL . '<p>carregando do arquivo</p>' . PHP_EOL;
        $arrayjson = file_get_contents(__DIR__ . '/data/' . "$qual.json");
        $dados = json_decode($arrayjson, true);
    }

    if (is_array($dados) && count($dados) === 0) {
        $perfFetch = perf_start('Busca dados API', __FILE__, __LINE__);
        //allprojects
        //foreach ($projects as $projeto) {

        $url = _url_lista_tarefas_proj($project);
        $ids = _get_WorkItemsId($url, $dataInicioQ, $dataFimQ);

        $perf_request_projeto = perf_start("Request Projeto: $project", __FILE__, __LINE__);
        $jd = tarefas_mes($ids);
        perf_end("Request Projeto: $project", $perf_request_projeto, __FILE__, __LINE__);

        //            $response = _request_post($url, '');
        //            perf_end("Request Projeto: $projeto", $perf_request_projeto, __FILE__, __LINE__);


        //preciso garantir que exista, nao seja vazio e seja array
        //if ((!empty($jd)) && (is_array($jd))) {
        //    $dados = array_merge($dados, $jd);
        //}
        $dados = $jd;
        // }
        file_put_contents(__DIR__ . '/data/' . "$qual.json", json_encode($dados)); // emergencia ou para DEV
        perf_end('Busca dados API', $perfFetch, __FILE__, __LINE__);
    }

    $startTs = strtotime($dataInicio);
    $endTs = strtotime($dataFim);

    $perf_array_filter = perf_start('Filtragem dados retornados da API', __FILE__, __LINE__);
    if(is_null($dados)) die('Dados nulos, em desenvolvimento, verificar resposta da API e estrutura dos dados');
    $dados = array_values(array_filter($dados, function ($val) use ($startTs, $endTs, $umail) {
        if (!isset($val['AssignedTo']['UserEmail']) || $val['AssignedTo']['UserEmail'] !== $umail)
            return false;
        $rawDate = $val['Custom_Prazoss']; // ?? ($val['date'] ?? null);
        if (empty($rawDate))
            return false;

        $ts = strtotime($rawDate);
        if ($ts === false)
            return false;

        return $ts >= $startTs && $ts <= $endTs;
    }));
    perf_end('Filtragem dados retornados da API', $perf_array_filter, __FILE__, __LINE__);


    $perf_agrupamento_dados = perf_start('Agrupamento dados Filtrados', __FILE__, __LINE__);
    $grouped = dados_agrupados($dados);
    perf_end('Agrupamento dados Filtrados', $perf_agrupamento_dados, __FILE__, __LINE__);

    // Ordena por data e monta novo array de saída com campos: date, hours, total, tarefas
    ksort($grouped);
    $dados = [];
    $foot = [];


    $perf_html = perf_start('Preparação Dados HTML', __FILE__, __LINE__);
    $foot = ['date' => 0, 'hours' => 0, 'tarefas' => 0, 'total' => 0, 'correcoes' => 0];
    foreach ($grouped as $d => $info) {
        $dados[] = [
            'date' => $d,
            'hours' => $info['hours'], //  7.63
            'total' => $info['total'], // 12
            'tarefas' => $info['tarefas'], // []
            'correcoes' => $info['correcoes'], // 0
            'corrigir' => $info['corrigir'],
        ];

        $foot['date'] += 1;
        $foot['hours'] += $info['hours'] ?? 0;
        $foot['tarefas'] += count($info['tarefas']) ?? 0;
        $foot['total'] += $info['total'] ?? 0;
        $foot['correcoes'] += $info['correcoes'] ?? 0;
    }

    perf_end('Preparação Dados HTML', $perf_html, __FILE__, __LINE__);
    perf_end('Processamento Principal', $ini_request_projects, __FILE__, __LINE__);
}
perf_end('App', $ini_app, __FILE__, __LINE__);
