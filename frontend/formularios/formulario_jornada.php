<?php
session_start();
if (!isset($_SESSION['jwt_token'])) { header("Location: ../index.php"); exit(); }
require_once '../includes/config.php';
$token = $_SESSION['jwt_token'];
$from = $_GET['from'] ?? '';
$inv_id = isset($_GET['inv_id']) ? (int)$_GET['inv_id'] : null;
$type = $_GET['type'] ?? 'invernadero';
$cliente_id_url = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : null;

if ($type === 'global' && !$cliente_id_url) { header("Location: ../dashboard.php"); exit(); }
if ($type === 'invernadero' && !$inv_id) { header("Location: ../dashboard.php"); exit(); }

$success_msg = "";

// [V14.3] Lógica de Retorno Dinámica (SIRA Backflow Engine)
$parcela_id = $_GET['parcela_id'] ?? '';
$loc_cp = $_GET['localidad_cp'] ?? '';

// Construimos el retorno base con todo el contexto geográfico disponible
if ($from === 'sensores') {
    $url_retorno = "../sensores.php?id_inv=$inv_id";
} elseif ($type === 'global' && !$parcela_id && empty($from)) {
    // Si no hay contexto y es global, retorno limpio
    $url_retorno = "../dashboard.php?msg=msg_ok" . ($cliente_id_url ? "&cliente_id=$cliente_id_url" : "");
} else {
    $url_retorno = "../dashboard.php?cliente_id=$cliente_id_url";
    if ($parcela_id) $url_retorno .= "&parcela_id=$parcela_id";
    if ($loc_cp) $url_retorno .= "&localidad_cp=".urlencode($loc_cp);
    if (!empty($from)) $url_retorno .= "&seccion=" . urlencode($from);
}

// 1. Obtener Datos de Identidad
$inv_nombre = "Configuración Maestro";
if ($type === 'invernadero') {
    $api_get_inv_url = SIRA_API_BASE . "/api/v1/invernaderos/$inv_id";
    $ch = curl_init($api_get_inv_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Accept: application/json"]);
    $inv_res = curl_exec($ch);
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200) {
        $inv_data = json_decode($inv_res, true);
        $inv_nombre = $inv_data['nombre'] ?? "Invernadero #$inv_id";
    }
    curl_close($ch);
} else {
    $inv_nombre = "Política Global del Agricultor";
}

// 2. Config Actual
$api_get_jornada_url = ($type === 'global') 
    ? SIRA_API_BASE . "/api/v1/config/jornada/cliente/$cliente_id_url"
    : SIRA_API_BASE . "/api/v1/config/jornada/invernadero/$inv_id";

$ch = curl_init($api_get_jornada_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Accept: application/json"]);
$response = curl_exec($ch);
$current_config = (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200) ? json_decode($response, true) : ["default" => [], "es_laborable" => true, "heredar_de_global" => false];
curl_close($ch);

// 3. Procesar Guardado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $es_laborable = isset($_POST['es_laborable']) && $_POST['es_laborable'] === '1';
    $heredar_de_global = isset($_POST['heredar_de_global']) && $_POST['heredar_de_global'] === '1';
    
    $new_config = [
        "es_laborable" => $es_laborable, 
        "heredar_de_global" => $heredar_de_global,
        "default" => []
    ];

    function prc($data, $p) {
        $t = [];
        for ($i = 0; $i < 2; $i++) {
            $ini = $data[$p."_ini_".$i] ?? ''; $fin = $data[$p."_fin_".$i] ?? '';
            if (!empty($ini) && !empty($fin)) $t[] = ["inicio" => $ini, "fin" => $fin];
        }
        return $t;
    }
    if ($es_laborable && !$heredar_de_global) {
        $new_config["default"] = prc($_POST, "def");
        foreach([1,2,3,4,5,6,0] as $d) {
            $tipo = $_POST["tipo_dia_$d"] ?? 'null';
            if ($tipo === 'null') $new_config[strval($d)] = null;
            elseif ($tipo === 'off') $new_config[strval($d)] = [];
            else $new_config[strval($d)] = prc($_POST, "d$d");
        }
    }
    $api_put_url = ($type === 'global')
        ? SIRA_API_BASE . "/api/v1/config/jornada/cliente/$cliente_id_url"
        : SIRA_API_BASE . "/api/v1/config/jornada/invernadero/$inv_id";

    $ch = curl_init($api_put_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($new_config));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Content-Type: application/json"]);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $success_msg = "Configuración de jornada guardada correctamente.";
    }
}

$page_title = "SIRA - Jornada";
$page_css = "dashboard";
require_once '../includes/header.php';
$semana = [1 => "Lunes", 2 => "Martes", 3 => "Miércoles", 4 => "Jueves", 5 => "Viernes", 6 => "Sábado", 0 => "Domingo"];

// Determinamos si es una edición individual (desde el reloj del invernadero) para simplificar la UI
$is_mini_mode = ($type === 'invernadero');
?>

<div class="container" style="max-width: 1200px; margin-top: 1rem;">
    
    <div class="glass-form-container">
        
        <?php 
        if (isset($success_msg) && $success_msg) {
            $conf_icon  = '🕒';
            $conf_title = "Jornada Actualizada";
            $conf_msg   = $success_msg;
            $conf_redir = $url_retorno;
            include '../includes/confirmaciones.php';
        }
        ?>
        
        <form action="formulario_jornada.php?<?= http_build_query($_GET) ?>" method="POST" class="sira-form <?= $is_mini_mode ? 'sira-mini-jornada' : '' ?>">
            
            <?php if (!$is_mini_mode): ?>
                <div class="form-header-premium">
                    <div class="title-group">
                        <span class="icon-badge">🕒</span>
                        <div>
                            <h1 class="main-title">Configuración de Jornada</h1>
                            <p class="sub-title">Semana laboral para <span class="highlight"><?= htmlspecialchars($inv_nombre) ?></span></p>
                        </div>
                    </div>
                    
                    <div class="status-toggle-box">
                        <span class="toggle-label">MODO PRODUCTIVO</span>
                        <label class="sira-switch-wow">
                            <input type="checkbox" id="check-laborable" name="es_laborable" value="1" <?= ($current_config['es_laborable'] ?? true) ? 'checked' : '' ?> onchange="toggleProductivo(this)">
                            <div class="slider-wow"></div>
                        </label>
                    </div>

                    <?php if ($type === 'invernadero'): ?>
                    <div class="status-toggle-box" style="background: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.3);">
                        <span class="toggle-label" style="color: #60a5fa;">HEREDAR GLOBAL</span>
                        <label class="sira-switch-wow">
                            <input type="checkbox" id="check-heredar" name="heredar_de_global" value="1" <?= ($current_config['heredar_de_global'] ?? false) ? 'checked' : '' ?> onchange="toggleHeredar(this)">
                            <div class="slider-wow" style="background: rgba(59, 130, 246, 0.2); border-color: #60a5fa;"></div>
                        </label>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- HEADER SIMPLIFICADO PARA INVERNADERO INDIVIDUAL -->
                <div class="form-header-premium mini-header-sira" style="padding: 1.5rem; margin-bottom: 2rem;">
                    <div class="title-group">
                        <span class="icon-badge" style="width: 40px; height: 40px; font-size: 1.2rem;">🏠</span>
                        <div>
                            <h2 style="font-size: 1.3rem; font-weight: 800; color: var(--color-primary); margin: 0;"><?= htmlspecialchars($inv_nombre) ?></h2>
                            <p style="font-size: 0.75rem; opacity: 0.5; margin: 0; text-transform: uppercase; letter-spacing: 0.1em;">Gestión de Jornada Específica</p>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 1.5rem;">
                         <div class="status-toggle-box" style="background: transparent; border: none; padding: 0;">
                            <span class="toggle-label" style="font-size: 0.7rem;">MODO PRODUCTIVO</span>
                            <label class="sira-switch-wow">
                                <input type="checkbox" id="check-laborable" name="es_laborable" value="1" <?= ($current_config['es_laborable'] ?? true) ? 'checked' : '' ?> onchange="toggleProductivo(this)">
                                <div class="slider-wow"></div>
                            </label>
                        </div>
                        <!-- Campo oculto para heredar_de_global si estamos en modo individual, para que NO herede por defecto al guardar -->
                        <input type="hidden" name="heredar_de_global" value="0">
                    </div>
                </div>
            <?php endif; ?>

            <div id="j-body" style="transition: var(--transition-smooth); <?php 
                $show_body = ($current_config['es_laborable'] ?? true) && !($current_config['heredar_de_global'] ?? false);
                echo $show_body ? '' : 'opacity: 0.3; pointer-events: none; filter: grayscale(0.8);';
            ?>">
                
                <?php if (!$is_mini_mode): ?>
                <div class="base-jornada-strip">
                    <div class="strip-label">
                        <span class="icon">📅</span>
                        <span>JORNADA BASE</span>
                    </div>
                    <div class="tramos-container">
                        <?php for ($i = 0; $i < 2; $i++): $val = $current_config['default'][$i] ?? null; ?>
                            <div class="tramo-pill-wow">
                                <span class="tramo-id">T<?= $i+1 ?></span>
                                <input type="time" name="def_ini_<?= $i ?>" id="def_ini_<?= $i ?>" value="<?= $val['inicio'] ?? '' ?>" oninput="validateForm()">
                                <span class="arrow">➜</span>
                                <input type="time" name="def_fin_<?= $i ?>" id="def_fin_<?= $i ?>" value="<?= $val['fin'] ?? '' ?>" oninput="validateForm()">
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php else: ?>
                    <!-- Si es mini mode, enviamos la jornada base como oculta para no romper el sistema de sincronización -->
                    <?php for ($i = 0; $i < 2; $i++): $val = $current_config['default'][$i] ?? null; ?>
                        <input type="hidden" name="def_ini_<?= $i ?>" id="def_ini_<?= $i ?>" value="<?= $val['inicio'] ?? '' ?>">
                        <input type="hidden" name="def_fin_<?= $i ?>" id="def_fin_<?= $i ?>" value="<?= $val['fin'] ?? '' ?>">
                    <?php endfor; ?>
                <?php endif; ?>

                <div class="exceptions-grid">
                    <?php foreach ($semana as $idx => $nombre): 
                        $conf = $current_config[strval($idx)] ?? null;
                        $tipo = is_array($conf) ? (empty($conf) ? 'off' : 'spec') : 'null';
                    ?>
                        <div class="day-card-wow">
                            <div class="card-header">
                                <span class="day-name"><?= $nombre ?></span>
                                <select name="tipo_dia_<?= $idx ?>" class="select-wow" onchange="tglDia(<?= $idx ?>, this.value)">
                                    <option value="null" <?= $tipo === 'null' ? 'selected' : '' ?>>J. Base</option>
                                    <option value="off" <?= $tipo === 'off' ? 'selected' : '' ?>>Cerrado</option>
                                    <option value="spec" <?= $tipo === 'spec' ? 'selected' : '' ?>>Específico</option>
                                </select>
                            </div>

                            <div id="inputs_<?= $idx ?>" class="card-body" style="display: <?= $tipo === 'spec' ? 'flex' : 'none' ?>;">
                                <?php for ($i = 0; $i < 2; $i++): $v = $conf[$i] ?? null; ?>
                                    <div class="mini-pill">
                                        <input type="time" name="d<?= $idx ?>_ini_<?= $i ?>" value="<?= $v['inicio'] ?? '' ?>">
                                        <input type="time" name="d<?= $idx ?>_fin_<?= $i ?>" value="<?= $v['fin'] ?? '' ?>">
                                    </div>
                                <?php endfor; ?>
                            </div>
                            
                            <div id="label_<?= $idx ?>" class="card-status-label" style="display: <?= $tipo === 'spec' ? 'none' : 'block' ?>;">
                                <?= ($tipo === 'null') ? 'Sincronizado' : 'Sin Actividad' ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>

            <div class="form-footer-actions">
                <?= sira_btn('GUARDAR CONFIGURACIÓN', 'primary', 'save', ['type' => 'submit', 'id' => 'save-btn']) ?>
                <?= sira_btn('CANCELAR', 'secondary', 'cancel', ['href' => $url_retorno]) ?>
            </div>
        </form>

    </div>
</div>

<script>
function tglDia(idx, v) {
    document.getElementById('inputs_' + idx).style.display = (v === 'spec') ? 'flex' : 'none';
    document.getElementById('label_' + idx).style.display = (v === 'spec') ? 'none' : 'block';
    document.getElementById('label_' + idx).innerText = (v === 'null') ? 'Sincronizado' : 'Sin Actividad';
}

function validateForm() {
    const isLaborable = document.getElementById('check-laborable').checked;
    const heredarCheck = document.getElementById('check-heredar');
    const isHeredar = heredarCheck ? heredarCheck.checked : false;
    const saveBtn = document.getElementById('save-btn');
    
    if (!isLaborable || isHeredar) {
        saveBtn.disabled = false;
        saveBtn.style.opacity = '1';
        saveBtn.style.cursor = 'pointer';
        return;
    }

    let hasOneTramo = false;
    for (let i = 0; i < 2; i++) {
        const ini = document.getElementById('def_ini_' + i).value;
        const fin = document.getElementById('def_fin_' + i).value;
        if (ini && fin) {
            hasOneTramo = true;
            break;
        }
    }
    
    saveBtn.disabled = !hasOneTramo;
    saveBtn.style.opacity = hasOneTramo ? '1' : '0.3';
    saveBtn.style.cursor = hasOneTramo ? 'pointer' : 'not-allowed';
}

function toggleProductivo(cb) {
    const isHeredar = document.getElementById('check-heredar')?.checked || false;
    if (!isHeredar) {
        document.getElementById('j-body').style.opacity = cb.checked ? '1' : '0.15';
        document.getElementById('j-body').style.pointerEvents = cb.checked ? 'auto' : 'none';
        document.getElementById('j-body').style.filter = 'none';
    }
    validateForm();
}

function toggleHeredar(cb) {
    const body = document.getElementById('j-body');
    if (cb.checked) {
        body.style.opacity = '0.3';
        body.style.pointerEvents = 'none';
        body.style.filter = 'grayscale(0.8)';
    } else {
        const isLab = document.getElementById('check-laborable').checked;
        body.style.opacity = isLab ? '1' : '0.15';
        body.style.pointerEvents = isLab ? 'auto' : 'none';
        body.style.filter = 'none';
    }
    validateForm();
}

// Inicializar al cargar
document.addEventListener('DOMContentLoaded', validateForm);
</script>


<?php require_once '../includes/footer.php'; ?>
