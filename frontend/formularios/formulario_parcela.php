<?php
session_start();
if (!isset($_SESSION['jwt_token'])) { header("Location: ../index.php"); exit(); }

require_once '../includes/config.php';

$token = $_SESSION['jwt_token'];
$user_rol = $_SESSION['user_rol'] ?? '';

// Solo admin, root y cliente pueden entrar aquí
if (!in_array($user_rol, ['admin', 'root', 'cliente'])) {
    header("Location: ../dashboard.php");
    exit();
}

$id_a_gestionar = isset($_GET['id']) ? (int)$_GET['id'] : null;
$is_edit = ($id_a_gestionar !== null);

$error_msg = "";
$success_msg = "";
$geo_status_msg = "";
$candidatos = [];
$parcela_data = null;

// 1. Obtener datos si es edición
if ($is_edit) {
    $api_get_url = SIRA_API_BASE . "/api/v1/parcelas/$id_a_gestionar";
    $ch = curl_init($api_get_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Accept: application/json"]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $parcela_data = json_decode($response, true);
        // Seguridad: Un cliente solo puede editar sus propias parcelas
        if ($user_rol === 'cliente' && $parcela_data['cliente_id'] !== (int)$_SESSION['cliente_id']) {
            header("Location: ../dashboard.php?error=acceso_denegado");
            exit();
        }
    } else {
        header("Location: ../dashboard.php?error=parcela_no_encontrada");
        exit();
    }
}

// Determinar cliente_id objetivo
$cliente_id_obj = $is_edit 
    ? $parcela_data['cliente_id'] 
    : (isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : (($user_rol === 'cliente') ? ($_SESSION['cliente_id'] ?? null) : null));

if (!$cliente_id_obj) {
    header("Location: ../dashboard.php?error=cliente_no_especificado");
    exit();
}

// Variables de estado (priorizan POST)
$nombre = $_POST['nombre'] ?? ($is_edit ? ($parcela_data['nombre'] ?? '') : '');
$ref_catastral = $_POST['ref_catastral'] ?? ($is_edit ? ($parcela_data['ref_catastral'] ?? '') : '');
$direccion = $_POST['direccion'] ?? ($is_edit ? ($parcela_data['direccion'] ?? '') : '');
$cp = $_POST['cp'] ?? ($is_edit ? ($parcela_data['codigo_postal'] ?? '') : ($_GET['localidad_cp'] ?? ''));
$municipio = $_POST['municipio'] ?? ($is_edit ? ($parcela_data['localidad']['municipio'] ?? '') : '');
$provincia = $_POST['provincia'] ?? ($is_edit ? ($parcela_data['localidad']['provincia'] ?? '') : '');
$nombre_busqueda = $_POST['nombre_busqueda'] ?? '';
$es_nuevo_cp = $_POST['es_nuevo_cp'] ?? '0';
$cp_confirmado = $_POST['cp_confirmado'] ?? ($is_edit ? $cp : '');

// [NUEVO V14.5] Inyección Contextual desde el Dashboard
if (!$is_edit && $_SERVER["REQUEST_METHOD"] !== "POST" && isset($_GET['localidad_cp'])) {
    $cp = $_GET['localidad_cp'];
    if (strlen($cp) === 5) {
        $api_geo_url = SIRA_API_BASE . "/api/v1/geo/check-cp/" . urlencode($cp);
        $ch = curl_init($api_geo_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Accept: application/json"]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code == 200) {
            $data = json_decode($res, true);
            $municipio = $data['municipio'];
            $provincia = $data['provincia'];
            $cp_confirmado = $cp;
            $geo_status_msg = "✅ Ubicación de la zona cargada automáticamente.";
        }
    }
}

// [V14.2] Lógica de Retorno Inteligente (SIRA Backflow Engine)
$from = $_GET['from'] ?? '';
if (!empty($from) && $from !== 'parcelas') {
    $url_retorno = "../dashboard.php?seccion=" . urlencode($from) . "&cliente_id=$cliente_id_obj";
} else {
    // Retorno por defecto a la vista de la Localidad (Parcelas)
    $url_retorno = "../dashboard.php?cliente_id=$cliente_id_obj&localidad_cp=" . urlencode($cp);
}

$es_cliente = ($user_rol === 'cliente');
$es_admin   = in_array($user_rol, ['admin', 'root']);

// 2. Procesar Acciones
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // CASO A: VALIDAR CP
    if (isset($_POST['btn_validar_cp']) && (!$es_cliente || !$is_edit)) {
        if (strlen($cp) === 5) {
            $api_geo_url = SIRA_API_BASE . "/api/v1/geo/check-cp/" . urlencode($cp);
            $ch = curl_init($api_geo_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Accept: application/json"]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code == 200) {
                $data = json_decode($res, true);
                $municipio = $data['municipio'];
                $provincia = $data['provincia'];
                $es_nuevo_cp = ($data['origen'] === 'local') ? '0' : '1';
                $cp_confirmado = $cp; // MEMORIA: Guardamos que este CP es válido
                $geo_status_msg = ($es_nuevo_cp === '0') ? "✅ Localización validada." : "🌍 CP Externo validado.";
            } else {
                $error_msg = "Código Postal no reconocido.";
                $cp_confirmado = "";
            }
        }
    }

    // [NUEVO] CASO B: BUSCAR POR NOMBRE (Híbrido)
    elseif (isset($_POST['btn_buscar_nombre'])) {
        if (strlen($nombre_busqueda) >= 3) {
            $api_url = SIRA_API_BASE . "/api/v1/geo/search-municipio/" . urlencode($nombre_busqueda);
            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Accept: application/json"]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code == 200) {
                $candidatos = json_decode($res, true) ?: [];
            } else {
                $candidatos = [];
                $res_data = json_decode($res, true);
                $error_msg = $res_data['detail'] ?? "No se encontraron resultados.";
            }
        } else {
            $error_msg = "Mínimo 3 caracteres para buscar.";
        }
    }

    // [NUEVO] CASO C: SELECCIONAR CANDIDATO
    elseif (isset($_POST['btn_seleccionar_cp'])) {
        $sel_cp = $_POST['sel_cp'] ?? '';
        $api_url = SIRA_API_BASE . "/api/v1/geo/check-cp/" . urlencode($sel_cp);
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Accept: application/json"]);
        $res = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($res, true);
        if ($data) {
            $cp = $data['codigo_postal'];
            $municipio = $data['municipio'];
            $provincia = $data['provincia'];
            $es_nuevo_cp = ($data['origen'] === 'local') ? '0' : '1';
            $cp_confirmado = $cp;
        }
    }

    // CASO E: GUARDAR / ACTUALIZAR
    elseif (isset($_POST['btn_guardar'])) {
        // El Gating System solo bloquea si NO eres admin y el CP no coincide con el validado
        if (!$es_admin && ($cp !== $cp_confirmado || empty($cp_confirmado))) {
            $error_msg = "⚠️ Debe validar el Código Postal antes de registrar la finca.";
        } else {
            // Si el CP es nuevo, registrar localidad (disponible para todos los roles validados)
            if ($es_nuevo_cp === '1') {
                $loc_api = SIRA_API_BASE . "/api/v1/localidades/";
                $loc_data = ["codigo_postal" => $cp, "municipio" => $municipio, "provincia" => $provincia];
                $ch = curl_init($loc_api);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loc_data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Content-Type: application/json"]);
                curl_exec($ch);
                curl_close($ch);
            }

            $api_url = $is_edit ? SIRA_API_BASE . "/api/v1/parcelas/$id_a_gestionar" : SIRA_API_BASE . "/api/v1/parcelas/";
            $method = $is_edit ? "PUT" : "POST";
            
            $data = [
                "nombre" => $nombre ?: null,
                "codigo_postal" => $cp,
                "ref_catastral" => $ref_catastral,
                "direccion" => $direccion
            ];
            if (!$is_edit) $data["cliente_id"] = $cliente_id_obj;

            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Content-Type: application/json", "Accept: application/json"]);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (($is_edit && $http_code == 200) || (!$is_edit && $http_code == 201)) {
                $success_msg = $is_edit ? "Parcela actualizada correctamente." : "Parcela registrada correctamente.";
                $auto_redirect = $url_retorno;
                if ($is_edit) $parcela_data = json_decode($response, true);
            } else {
                $res_data = json_decode($response, true);
                $detalle_api = $res_data['detail'] ?? 'Error desconocido';
                // Si el detalle es un array (validación de FastAPI), lo aplanamos
                if (is_array($detalle_api)) {
                    $detalle_api = json_encode($detalle_api);
                }
                $error_msg = "Error de la API ($http_code): " . $detalle_api;
            }
        }
    }
}

$page_title = "SIRA - " . ($is_edit ? "Editar Parcela" : "Añadir Parcela");
$page_css   = "dashboard";

require_once '../includes/header.php';
?>

<div class="container" style="margin-top: 1rem;">
    <div class="breadcrumbs">
        <span>📍 Tú estás aquí:</span>
        <a href="../dashboard.php">Panel</a>
        <span>/</span>
        <a href="../dashboard.php?cliente_id=<?= $cliente_id_obj ?>">Entorno Cliente</a>
        <span>/</span>
        <a href="#"><?= $is_edit ? "Editar Parcela" : "Añadir Parcela" ?></a>
    </div>

    <div class="user-form-container">
        
        <div style="margin-bottom: 2rem;">
            <h1 class="dashboard-title"><?= $is_edit ? "✏️ Editar Parcela" : "➕ Añadir Nueva Parcela" ?></h1>
            <p class="dashboard-subtitle"><?= $is_edit ? "Ajustes de infraestructura para ID #$id_a_gestionar" : "Registro de infraestructuras (SIRA Gating System)." ?></p>
        </div>

        <?php if ($error_msg): ?>
            <script>window.scrollTo(0, 0);</script>
            <div style="background: var(--color-error-bg); border-left: 4px solid var(--color-error); color: var(--color-error-text); padding: 1rem; margin-bottom: 1.5rem; border-radius: var(--radius-sm);">
                <strong>⚠️ Alerta:</strong> <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <?php 
        if ($success_msg) {
            $conf_icon  = '🍃';
            $conf_title = $is_edit ? "Cambios Guardados" : "Parcela Registrada";
            $conf_msg   = $success_msg;
            $conf_redir = $url_retorno;
            include '../includes/confirmaciones.php';
        }
        ?>

        <form method="POST" class="sira-form">
            <?php if ($es_cliente && $is_edit): ?>
                <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--color-primary); color: var(--color-text-main); padding: 1.2rem; margin-bottom: 2rem; border-radius: 10px; font-size: 0.9rem; line-height: 1.5;">
                    💡 <strong>Nota:</strong> Como cliente, puedes personalizar el nombre (alias). La ubicación legal está bloqueada.
                </div>
            <?php endif; ?>

            <p class="form-required-label">(*) Campos obligatorios</p>
            <input type="hidden" name="es_nuevo_cp" value="<?= htmlspecialchars($es_nuevo_cp) ?>">
            <input type="hidden" name="cp_confirmado" value="<?= htmlspecialchars($cp_confirmado) ?>">
            
            <?php if (!$es_cliente): ?>
            <div class="sira-gating-box">
                🔒 <strong>SIRA Gating System:</strong> Es obligatorio validar el Código Postal antes de guardar.
            </div>
            <?php endif; ?>

            <div class="form-premium-grid">
                
                <?php if ($is_edit): ?>
                <div class="form-col-2">
                    <div class="input-group-premium">
                        <label style="color: var(--color-text-muted);">Propietario (No editable)</label>
                        <input type="text" value="<?= htmlspecialchars($parcela_data['cliente']['nombre_empresa'] ?? 'Cliente #'.$cliente_id_obj) ?>" disabled class="input-readonly">
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-col-2">
                    <div class="input-group-premium">
                        <label>Nombre de la Parcela (Alias)</label>
                        <input type="text" name="nombre" value="<?= htmlspecialchars($nombre) ?>" placeholder="Ej. Finca de los Olivos...">
                    </div>
                </div>

                <div class="form-col-2">
                    <div class="input-group-premium">
                        <label>Referencia Catastral (*)</label>
                        <input type="text" name="ref_catastral" required maxlength="14" minlength="14" 
                               value="<?= htmlspecialchars($ref_catastral) ?>" placeholder="Ej. 1234567AB1234C" 
                               autocomplete="off"
                               <?= ($es_cliente && $is_edit) ? 'readonly class="input-readonly"' : '' ?>>
                    </div>
                </div>

                <div class="form-col-2">
                    <div class="input-group-premium">
                        <label>Dirección de la Parcela (*)</label>
                        <input type="text" name="direccion" required value="<?= htmlspecialchars($direccion) ?>" placeholder="Ej. Polígono 4, Parcela 12..." <?= ($es_cliente && $is_edit) ? 'readonly class="input-readonly"' : '' ?>>
                    </div>
                </div>

                <?php if (!$is_edit && !isset($_GET['localidad_cp']) && empty($cp_confirmado)): ?>
                <div class="form-col-2">
                    <div class="input-group-premium">
                        <label>Municipio (Buscador) (*)</label>
                        <div class="input-group-inline">
                            <input type="text" name="nombre_busqueda" value="<?= htmlspecialchars($nombre_busqueda) ?>" placeholder="Ej. Linares">
                            <?= sira_btn('Buscar CPs', 'helper', 'search', ['type' => 'submit', 'formnovalidate' => true, 'name' => 'btn_buscar_nombre']) ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($candidatos)): ?>
                <div class="form-col-2">
                    <div class="input-group-premium" style="border: 1px solid var(--color-primary); background: rgba(16, 185, 129, 0.05); padding: 0.8rem; border-radius: 10px;">
                        <label>📦 SELECCIÓN DE CÓDIGO POSTAL:</label>
                        <div class="input-group-inline">
                            <select name="sel_cp" style="flex: 1;">
                                <?php foreach ($candidatos as $c): ?>
                                    <option value="<?= htmlspecialchars($c['codigo_postal']) ?>">
                                        <?= ($c['origen'] === 'local' ? '✅' : '🌍') ?> 
                                        <?= htmlspecialchars($c['codigo_postal']) ?> — <?= htmlspecialchars($c['provincia']) ?> (<?= htmlspecialchars($c['municipio']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="btn_seleccionar_cp" class="btn-sira btn-primary" style="padding: 0 0.8rem; font-size: 0.75rem;" formnovalidate>✅ Seleccionar y Autocompletar</button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-col-1">
                    <div class="input-group-premium">
                        <label>Código Postal (*)</label>
                        <div class="input-group-inline">
                            <input type="text" name="cp" maxlength="5" value="<?= htmlspecialchars($cp) ?>" placeholder="Ej. 28001" <?= ($es_cliente && $is_edit) || !empty($cp_confirmado) ? 'readonly class="input-readonly"' : '' ?>>
                            <?php if ((!$es_cliente || !$is_edit) && empty($cp_confirmado)): ?>
                                <?= sira_btn('Validar CP', 'helper', 'shield', ['type' => 'submit', 'formnovalidate' => true, 'name' => 'btn_validar_cp']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-col-1">
                    <div class="input-group-premium">
                        <label>Municipio</label>
                        <input type="text" name="municipio" value="<?= htmlspecialchars($municipio) ?>" readonly class="input-readonly">
                    </div>
                </div>

                <div class="form-col-2">
                    <div class="input-group-premium">
                        <label>Provincia</label>
                        <input type="text" name="provincia" value="<?= htmlspecialchars($provincia) ?>" readonly class="input-readonly">
                    </div>
                </div>
            </div>

            <?php if ($geo_status_msg): ?>
            <div class="geo-status-msg" style="margin-top: 1rem; font-size: 0.85rem; color: #34d399;">
                <?= htmlspecialchars($geo_status_msg) ?>
            </div>
            <?php endif; ?>

            <div class="form-footer-actions">
                <?php if ($es_cliente || ($is_edit && $cp === $cp_confirmado) || (!empty($cp_confirmado) && $cp === $cp_confirmado)): ?>
                    <?= sira_btn($is_edit ? 'Guardar Cambios' : 'Registrar Finca', 'primary', 'save', ['type' => 'submit', 'name' => 'btn_guardar', 'value' => '1']) ?>
                <?php else: ?>
                    <div class="gating-lock-msg" style="flex: 2; background: rgba(255,255,255,0.05); color: var(--color-text-muted); padding: 0.8rem; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.82rem; font-weight: 600; cursor: not-allowed; border: 1px dashed rgba(255,255,255,0.2); text-align: center;">
                        🔒 Valide el CP para continuar
                    </div>
                <?php endif; ?>
                
                <?= sira_btn('Cancelar', 'secondary', 'cancel', ['href' => $url_retorno]) ?>
            </div>
        </form>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
