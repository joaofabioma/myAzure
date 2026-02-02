<?php
require __DIR__ . '/inc/prepend.php';

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

function allProjects(string $pOrganization = ORG)
{
    $url = "https://dev.azure.com/$pOrganization/_apis/projects";
    $_pat = PAT;
    $auth = 'Basic ' . base64_encode(":$_pat");
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: $auth ",
            'Accept: application/json'
        ]
    ]);
    $response = curl_exec($curl);
    if ($response === false) {
        die('Erro CURL: ' . curl_error($curl) . __LINE__);
    } else {
        $res = json_decode($response, true);
        if (is_array($res)) {
            file_put_contents(__DIR__ . '/data/projetos.json', $response); // emergencia ou para DEV
        }
        $res = null;
    }
    $curl = null;
}

function allUsers(string $pOrganization = ORG)
{
    $url = "https://analytics.dev.azure.com/$pOrganization/_odata/v4.0-preview/Users";
    $_pat = PAT;
    $auth = 'Basic ' . base64_encode(":$_pat");
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: $auth ",
            'Accept: application/json'
        ]
    ]);
    $response = curl_exec($curl);
    if ($response === false) {
        die('Erro CURL: ' . curl_error($curl) . __LINE__);
    } else {
        $res = json_decode($response, true);
        if (is_array($res)) {
            file_put_contents(__DIR__ . '/data/users.json', $response); // emergencia ou para DEV
        }
        $res = null;
    }
    $curl = null;
}

function _me(string $pOrganization = ORG)
{
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

function request_content(string $pProjeto = PROJETO)
{
    $_pat = PAT; // Personal Access Token - _ODATA
    $url = _urlTasks($pProjeto);
    $auth = 'Basic ' . base64_encode(":$_pat");
    //die(print($auth)); // para usar no Postman, se nao souber usar scripts

    $ch = null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: $auth",
            'Accept: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        die('Erro CURL: ' . curl_error($ch) . __LINE__);
    }
    $ch = null;
    return $response;
}

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
                'tarefas' => []
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

allProjects();
_me();

if (isset($qual)) {
    $dados = [];
    $json = [];
    $usuario = me('UserSK');
    $projects     = PROJ;
    $intervalo = isset($qual) ? intervalo($qual) : ["ini" => null, "fim" => null];
    $dataInicio = $intervalo["ini"];
    $dataFim    = $intervalo["fim"];


    if (file_exists(__DIR__ . '/data/' . "$qual.json") && false) {
        echo PHP_EOL . '<p>carregando do arquivo</p>' . PHP_EOL;
        $arrayjson = file_get_contents(__DIR__ . '/data/' . "$qual.json");
        $dados = json_decode($arrayjson, true);
    }

    if (count($dados) === 0) {
        //multiprojeto
        foreach ($projects as $value) {
            $response = request_content($value);
            $jd = json_decode($response, true);

            //preciso garantir que exista, nao seja vazio e seja array
            if (isset($jd['value']) && (!empty($jd['value'])) && (is_array($jd['value']))) {
                $values = $jd['value'];
                $json = array_merge($json, $values);
            }
        }

        //está assim apenas por causa da variavel, remover $dados da problema ao buscar os dados
        $dados = $json; //$json['value']; //unico projeto
        file_put_contents(__DIR__ . '/data/' ."$qual.json", json_encode($dados)); // emergencia ou para DEV
        $json = null; //libera memoria
    }

    $startTs = strtotime($dataInicio);
    $endTs = strtotime($dataFim);

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
            'tarefas' => $info['tarefas']
        ];
    }
}
