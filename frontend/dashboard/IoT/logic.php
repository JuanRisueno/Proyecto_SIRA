<?php
/**
 * logic.php - Cerebro del ecosistema IoT SIRA
 * Gestiona la simulación, overrides de actuadores y preparación de datos maestros.
 */

if (!isset($_SESSION['jwt_token'])) {
    header("Location: index.php");
    exit();
}

/**
 * Función genérica para API REST (GET/POST)
 */
function callIoTAPI($method, $url, $token, $data = null) {
    $ch = curl_init($url);
    $headers = [
        "Authorization: Bearer $token",
        "Accept: application/json"
    ];
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, 1);
        if ($data) {
            $json = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            $headers[] = "Content-Type: application/json";
        }
    }
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error_msg = curl_error($ch);
    curl_close($ch);
    
    if ($http_code >= 200 && $http_code < 300) {
        return json_decode($response, true) ?: [];
    } else {
        return ["_error" => true, "_code" => $http_code, "_response" => $response, "_curl_err" => $error_msg];
    }
}

/**
 * Helper para renderizar gráfica SVG
 */
function render_svg_chart($sensor_id, $token, $color) {
    $url = SIRA_API_BASE . "/api/v1/iot/mediciones/sensor/{$sensor_id}?limit=15";
    $mediciones = callIoTAPI('GET', $url, $token) ?: [];
    
    if (empty($mediciones)) return '<p class="no-data">Sin datos</p>';
    
    $datos = array_reverse($mediciones);
    $valores = array_column($datos, 'valor');
    
    $max = max($valores);
    $min = min($valores);
    $range = ($max == $min) ? 1 : ($max - $min);
    
    $width = 250; $height = 120; $padding = 10;
    $points = "";
    $step = ($width - (2 * $padding)) / max(1, count($valores) - 1);
    
    foreach ($valores as $i => $v) {
        $x = $padding + ($i * $step);
        $y = ($height - $padding) - (($v - $min) / $range * ($height - 2 * $padding));
        $points .= "$x,$y ";
    }
    
    return "<svg width='100%' height='100%' viewBox='0 0 $width $height' preserveAspectRatio='none' class='mini-chart'><polyline fill='none' stroke='$color' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' points='$points' /></svg>";
}

// 1. Procesamiento de POST (PRG Pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clean_url = strtok($_SERVER['REQUEST_URI'], '#');
    $anchor = "";

    if (isset($_POST['simular_escenario'])) {
        $id_target = intval($_POST['invernadero_id']);
        $escenario = urlencode($_POST['simular_escenario']);
        $resp = callIoTAPI('POST', SIRA_API_BASE . "/api/v1/iot/simular/{$id_target}/{$escenario}", $_SESSION['jwt_token']);
        
        if (!isset($resp['_error'])) {
            if (!isset($_SESSION['simulacion_activa']) || !is_array($_SESSION['simulacion_activa'])) {
                $_SESSION['simulacion_activa'] = [];
            }
            $key = (string)$id_target;
            $_SESSION['simulacion_activa'][$key] = [
                'id_escenario' => $resp['id_escenario'] ?? $_POST['simular_escenario'],
                'nombre' => $resp['escenario_aplicado'] ?? 'Simulación Activa',
                'momento' => $resp['momento'] ?? 'En curso...',
                'hora_virtual' => $resp['hora_virtual'] ?? null,
                'ubicacion_full' => $resp['ubicacion_simulada'] ?? null,
                'desc' => $resp['descripcion'] ?? 'Los sensores están respondiendo al escenario inyectado.'
            ];
            // Anclamos arriba (vacío) para poder leer el banner de SIRA Intelligence
            $anchor = ""; 
        }
    } elseif (isset($_POST['toggle_vfx'])) {
        /* 🔌 Toggle Maestro de Efectos (VFX) */
        if (!isset($_SESSION['vfx_enabled'])) $_SESSION['vfx_enabled'] = false;
        $_SESSION['vfx_enabled'] = !$_SESSION['vfx_enabled'];
        // Anclamos arriba también para mantener consistencia
        $anchor = ""; 
    } elseif (isset($_POST['override_actuador'])) {
        $act_id = intval($_POST['actuador_id']);
        $estado_deseado = $_POST['nuevo_estado'];

        // [V14.7] Persistencia de Sub-Modos Manuales (UI Feedback)
        if (isset($_POST['set_persistence'])) {
            $_SESSION['iot_persistence_' . $act_id] = $_POST['set_persistence'];
        }

        callIoTAPI('POST', SIRA_API_BASE . "/api/v1/iot/override/", $_SESSION['jwt_token'], [
            "actuador_id" => $act_id,
            "nuevo_estado" => $estado_deseado,
            "duracion" => $_SESSION['iot_persistence_' . $act_id] ?? 'perm'
        ]);
        $anchor = "#act-card-" . $act_id;
    }
    
    session_write_close();
    header("Location: " . $clean_url . $anchor);
    exit();
}

// 2. Preparación de Datos de Estado
$sim_key = (string)$id_inv;
$hora_virtual_api = $_SESSION['simulacion_activa'][$sim_key]['hora_virtual'] ?? null;
$ubicacion_ctx = $_SESSION['simulacion_activa'][$sim_key]['ubicacion_full'] ?? null;

$url_estado = SIRA_API_BASE . "/api/v1/iot/estado/{$id_inv}";
$params = [];
if ($hora_virtual_api) $params['hora_virtual'] = $hora_virtual_api;
if (isset($_SESSION['simulacion_activa'][$sim_key]['id_escenario'])) $params['escenario'] = $_SESSION['simulacion_activa'][$sim_key]['id_escenario'];
if ($ubicacion_ctx) {
    if (preg_match('/^(.*) \((.*)\)$/', $ubicacion_ctx, $m)) {
        $params['ubicacion'] = $m[1]; $params['tz'] = $m[2];
    } else { $params['ubicacion'] = $ubicacion_ctx; }
}

if (!empty($params)) $url_estado .= "?" . http_build_query($params);

$estado_iot = callIoTAPI('GET', $url_estado, $token);
$sensores_raw = $estado_iot['sensores'] ?? [];
$actuadores_raw = $estado_iot['actuadores'] ?? [];

// 3. Lógica de Alineación Maestra (Fidelidad MD 5x5)
$priority_sensors = ['radiaci', 'suelo', 'temp', 'lluvia', 'viento'];
$priority_actuators = ['ilumina', 'electrov', 'calefac', 'ventilad', 'motor'];

$sensores_final = [];
$actuadores_final = [];

foreach ($sensores_raw as $s) {
    $matched = false;
    foreach ($priority_sensors as $keyword) {
        if (stripos($s['tipo'], $keyword) !== false) {
            $s['_priority'] = array_search($keyword, $priority_sensors);
            $matched = true;
            break;
        }
    }
    if (!$matched) $s['_priority'] = 99;
    $sensores_final[] = $s;
}

foreach ($actuadores_raw as $a) {
    $matched = false;
    foreach ($priority_actuators as $keyword) {
        if (stripos($a['tipo'], $keyword) !== false) {
            $a['_priority'] = array_search($keyword, $priority_actuators);
            $matched = true;
            break;
        }
    }
    if (!$matched) $a['_priority'] = 99;
    $actuadores_final[] = $a;
}

usort($sensores_final, function($a, $b) { return $a['_priority'] <=> $b['_priority']; });
usort($actuadores_final, function($a, $b) { return $a['_priority'] <=> $b['_priority']; });

// 4. Variables de Vista
$jornada_activa = $estado_iot['jornada_activa'] ?? false;
$jornada_configurada = $estado_iot['jornada_configurada'] ?? true;
$nombre_cultivo = $estado_iot['cultivo_nombre'] ?? 'Sin información de planta';
$parametros_optimos = $estado_iot['parametros_optimos'] ?? null;
$diagnostico_humano = $estado_iot['diagnostico_humano'] ?? 'Esperando lectura de sensores...';

$contexto_api = $estado_iot['contexto_simulacion'] ?? null;
if ($contexto_api) {
    if (!$hora_virtual_api) $hora_virtual_api = $contexto_api['hora'];
    if (!$ubicacion_ctx) $ubicacion_ctx = $contexto_api['ubicacion'] . " (" . $contexto_api['tz'] . ")";
}
