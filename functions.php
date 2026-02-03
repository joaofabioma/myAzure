<?php
header('X-Robots-Tag: noindex, nofollow, noarchive');
require __DIR__ . '/inc/prepend.php';
if (empty($_SERVER['HTTP_USER_AGENT'])) {
    http_response_code(403);
    exit;
}

function _request_get(string $pUrl)
{
    $curl = curl_init($pUrl);
    $curl_opt = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: " . 'Basic ' . base64_encode(":" . PAT),
            'Accept: application/json'
        ]
    ];
    //die(print('Basic ' . base64_encode(":" . PAT))); // para usar no Postman, se nao souber usar scripts
    curl_setopt_array($curl, $curl_opt);
    $response = curl_exec($curl);
    if ($response === false) {
        die('Erro CURL: ' . curl_error($curl) . __LINE__);
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
    } else {
        // Fallback para valores padrão
        $dataInicio = date('Y-m-01T00:00:00Z');
        $dataFim = date('Y-m-tT23:59:59Z');
    }

    return [
        'ini' => $dataInicio,
        'fim' => $dataFim,
    ];
}

function _urlTasks($projeto)
{
    $url = sprintf(UTASK, ORG, $projeto);
    return $url;
}

function allProjects(string $pOrganization = ORG): void
{
    if (ONLINE === FALSE) {
        return;
    }

    $url = "https://dev.azure.com/$pOrganization/_apis/projects";
    $response = _request_get($url);
    $res = json_decode($response, true);
    if (!empty($res) && is_array($res)) {
        file_put_contents(__DIR__ . '/data/projetos.json', $response); // emergencia ou para DEV
    }
    $res = $response = null;
}

function allUsers(string $pOrganization = ORG): void
{
    if (ONLINE === FALSE) {
        return;
    }
    $url = "https://analytics.dev.azure.com/$pOrganization/_odata/v4.0-preview/Users";
    $response = _request_get($url);
    $res = json_decode($response, true);
    if (!empty($res) && is_array($res)) {
        file_put_contents(__DIR__ . '/data/users.json', $response); // emergencia ou para DEV
    }
    $response = null;
}

/**
 * Busca usuario logado no Azure DevOps, precisa ser informado no env
 * EMAIL_DEV=nome.sobrenome@dominio.com.br
 * @var string $pOrganization ORG = EmpresaSoftware
 * @return void
 */
function _me(string $pOrganization = ORG): void
{
    if (ONLINE === FALSE) {
        return;
    }

    $me = [];
    allUsers($pOrganization);
    $all = file_get_contents(__DIR__ . '/data/users.json');
    $all = json_decode($all, true);
    $users = !empty($all) && is_array($all) && key_exists('value', $all) ? $all['value'] : [];
    $all = null;
    if (!empty($users) && is_array($users)) {
        $me = array_filter($users, fn($u) => $u['UserEmail'] == EDEV);
        $me = !empty($me) && is_array($me) ? reset($me) : [];
    }
    file_put_contents(__DIR__ . '/data/me.json', json_encode($me)); // emergencia ou para DEV
}

/**
 * Retorna dados do usuario logado no Azure, Dados ja carregados antes
 * @param string $key = 'all', 'UserSK','UserId', 'UserName' ou 'UserEmail'
 * @return array|string|null
 * @example $user = me('UserSK');
 */
function me($key = 'all'): array|string|null
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

/*function request_content(string $pProjeto = PROJETO)
{
    $url = _urlTasks($pProjeto);
    $response = _request_get($url);
    return $response;
}*/

function dados_agrupados(array $dados = []): array
{
    $grouped = [];
    foreach ($dados as $r) {
        $rawDate = $r['Custom_Prazoss'] ?? ($r['date'] ?? null);
        if (empty($rawDate)) continue;
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

        $completedWork = $val['CompletedWork'] ?? 0;
        $OriginalEstimate = $val['OriginalEstimate'] ?? 0;
        if ((bool)($completedWork == $OriginalEstimate) && $completedWork !== 0) {
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
        if ($title && $id) $label = $title . ' (' . $id . ')';
        elseif ($title) $label = $title;
        elseif ($id) $label = $id;

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

if (isset($qual)) {
    // var_dump($qual);
    allProjects();
    _me();

    $dados = [];
    $json = [];
    $usuario    = me('UserSK');
    $projects   = PROJ;
    $intervalo  = isset($qual) ? intervalo($qual) : ["ini" => null, "fim" => null];
    $dataInicio = $intervalo["ini"];
    $dataFim    = $intervalo["fim"];


    if (file_exists(__DIR__ . '/data/' . "$qual.json") && ONLINE === false) {
        echo PHP_EOL . '<p>carregando do arquivo</p>' . PHP_EOL;
        $arrayjson = file_get_contents(__DIR__ . '/data/' . "$qual.json");
        $dados = json_decode($arrayjson, true);
    }

    if (count($dados) === 0) {
        //multiprojeto
        foreach ($projects as $projeto) {
            $url = _urlTasks($projeto);
            $response = _request_get($url);
            $jd = json_decode($response, true);

            //preciso garantir que exista, nao seja vazio e seja array
            if (isset($jd['value']) && (!empty($jd['value'])) && (is_array($jd['value']))) {
                $values = $jd['value'];
                $dados = array_merge($dados, $values);
            }
        }
        file_put_contents(__DIR__ . '/data/' . "$qual.json", json_encode($dados)); // emergencia ou para DEV
    }

    $startTs = strtotime($dataInicio);
    $endTs = strtotime($dataFim);
    $usuario = me('UserSK');

    $dados = array_values(array_filter($dados, function ($val) use ($startTs, $endTs, $usuario) {
        // filtrar por AssignedTo
        if (!isset($val['AssignedToUserSK']) || $val['AssignedToUserSK'] !== $usuario) return false;

        // obter data do registro
        $rawDate = $val['Custom_Prazoss']; // ?? ($val['date'] ?? null);
        if (empty($rawDate)) return false;

        $ts = strtotime($rawDate);
        if ($ts === false) return false;

        return $ts >= $startTs && $ts <= $endTs;
    }));

    $grouped = dados_agrupados($dados);
    // Ordena por data e monta novo array de saída com campos: date, hours, total, tarefas
    ksort($grouped);
    $dados = [];
    foreach ($grouped as $d => $info) {
        $dados[] = [
            'date' => $d,
            'hours' => $info['hours'],
            'total' => $info['total'],
            'tarefas' => $info['tarefas'],
            'correcoes' => $info['correcoes'],
            'corrigir' => $info['corrigir'],
        ];
    }
}
