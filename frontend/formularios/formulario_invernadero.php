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
$inv_data = null;

// 1. Obtener datos si es edición
if ($is_edit) {
    $api_get_url = SIRA_API_BASE . "/api/v1/invernaderos/$id_a_gestionar";
    $ch = curl_init($api_get_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Accept: application/json"]);
    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $inv_data = json_decode($res, true);
    } else {
        header("Location: ../dashboard.php?error=invernadero_no_encontrado");
        exit();
    }
}

// 2. Obtener catálogos necesarios
// Cultivos
$api_cultivos_url = SIRA_API_BASE . "/api/v1/cultivos/";
$ch_c = curl_init($api_cultivos_url);
curl_setopt($ch_c, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch_c, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Accept: application/json"]);
$res_c = curl_exec($ch_c);
$cultivos_data = (curl_getinfo($ch_c, CURLINFO_HTTP_CODE) == 200) ? json_decode($res_c, true) : [];
curl_close($ch_c);

// Parcelas (Solo si no es edición o si necesitamos elegir)
$cliente_id_seleccionado = $is_edit 
    ? ($inv_data['parcela']['cliente_id'] ?? null) 
    : (isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : (($user_rol === 'cliente') ? ($_SESSION['cliente_id'] ?? null) : null));

$parcelas_data = [];
if (!$is_edit) {
    require_once '../dashboard/api/api_infraestructura.php';
    require_once '../dashboard/api/api_produccion.php';
    $parcelas_data = listarTodasLasParcelasDelCliente($token, $cliente_id_seleccionado);
} else {
    require_once '../dashboard/api/api_produccion.php';
}

$page_title = "SIRA - " . ($is_edit ? "Editar Invernadero" : "Añadir Invernadero");
$page_css   = "dashboard";
$es_admin_full = in_array($user_rol, ['admin', 'root']);
$localidad_cp = $_GET['localidad_cp'] ?? ($is_edit ? ($inv_data['parcela']['codigo_postal'] ?? '') : '');
$from = $_GET['from'] ?? '';
$parcela_id_final = $is_edit ? ($inv_data['parcela_id'] ?? 0) : (int)($_POST['parcela_id'] ?? $_GET['parcela_id'] ?? 0);

// [V14.3] Parche de Robustez: Si nos falta el cliente o el CP (común en navegación root), lo recuperamos de la parcela
if ($parcela_id_final > 0 && (!$cliente_id_seleccionado || empty($localidad_cp))) {
    $p_auto = obtenerDetalleAsset($token, true, $parcela_id_final);
    if ($p_auto) {
        if (!$cliente_id_seleccionado) $cliente_id_seleccionado = $p_auto['cliente_id'];
        if (empty($localidad_cp)) $localidad_cp = $p_auto['codigo_postal'];
    }
}

// [V14.2] Lógica de Retorno Inteligente (SIRA Backflow Engine)
if (!empty($from) && $from !== 'invernaderos') {
    $url_retorno = "../dashboard.php?seccion=" . urlencode($from) . "&cliente_id=$cliente_id_seleccionado";
} else {
    // Retorno a la vista detallada de la Finca (Parcela)
    $url_retorno = "../dashboard.php?parcela_id=$parcela_id_final&cliente_id=$cliente_id_seleccionado&localidad_cp=".urlencode($localidad_cp);
}

// 3. Procesar POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'] ?? '';
    $cultivo_id = $_POST['cultivo_id'] ?? null;
    $largo_m = $_POST['largo_m'] ?? ($is_edit ? $inv_data['largo_m'] : 0);
    $ancho_m = $_POST['ancho_m'] ?? ($is_edit ? $inv_data['ancho_m'] : 0);
    $fecha_plantacion = $_POST['fecha_plantacion'] ?? ($is_edit ? $inv_data['fecha_plantacion'] : null);
    if (!$parcela_id_final) {
        $error_msg = "Debes seleccionar una parcela de destino.";
    } else {
        $api_url = $is_edit ? SIRA_API_BASE . "/api/v1/invernaderos/$id_a_gestionar" : SIRA_API_BASE . "/api/v1/invernaderos/";
        $method = $is_edit ? "PUT" : "POST";

        $data = [
            "nombre" => $nombre,
            "cultivo_id" => !empty($cultivo_id) ? (int)$cultivo_id : null,
        ];

        // Restricción de edición para clientes
        $es_admin_full = in_array($user_rol, ['admin', 'root']);
        if ($es_admin_full || !$is_edit) {
            $data["largo_m"] = (float)$largo_m;
            $data["ancho_m"] = (float)$ancho_m;
            $data["fecha_plantacion"] = !empty($fecha_plantacion) ? $fecha_plantacion : null;
            if (!$is_edit) $data["parcela_id"] = $parcela_id_final;
        }

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Content-Type: application/json", "Accept: application/json"]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (($is_edit && $http_code == 200) || (!$is_edit && $http_code == 201)) {
            $success_msg = $is_edit ? "Invernadero actualizado correctamente." : "Invernadero registrado correctamente.";
            $auto_redirect = $url_retorno;
            if ($is_edit) $inv_data = json_decode($response, true);
        } else {
            $res_data = json_decode($response, true);
            $error_msg = $res_data['detail'] ?? "Error en la operación del invernadero.";
        }
    }
}


require_once '../includes/header.php';
?>

<div class="container" style="margin-top: 1rem;">
    <div class="breadcrumbs">
        <span>📍 Tú estás aquí:</span>
        <a href="../dashboard.php">Panel</a>
        <span>/</span>
        <a href="../dashboard.php?cliente_id=<?= $cliente_id_seleccionado ?>">Fincas</a>
        <span>/</span>
        <a href="#"><?= ($is_edit ? "Editar" : "Añadir") . " Invernadero" ?></a>
    </div>

    <div class="user-form-container">
        
        <div style="margin-bottom: 2rem;">
            <h1 class="dashboard-title"><?= $is_edit ? "✏️ Editar Invernadero" : "🏠 Añadir Nuevo Invernadero" ?></h1>
            <p class="dashboard-subtitle"><?= $is_edit ? "Ajuste de parámetros para canal #$id_a_gestionar" : "Define las dimensiones y características de la nueva zona de cultivo." ?></p>
        </div>

        <?php if ($error_msg): ?>
            <script>window.scrollTo(0, 0);</script>
            <div style="background: var(--color-error-bg); border-left: 4px solid var(--color-error); color: var(--color-error-text); padding: 1rem; margin-bottom: 1.5rem; border-radius: var(--radius-sm);">
                <strong>⚠️ Error:</strong> <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <?php 
        if ($success_msg) {
            $conf_icon  = '🏠';
            $conf_title = $is_edit ? "Cambios Aplicados" : "Registro Completado";
            $conf_msg   = $success_msg;
            $conf_redir = $url_retorno;
            include '../includes/confirmaciones.php';
        }
        ?>

        <form method="POST" class="sira-form">
            <?php if (!$es_admin_full && $is_edit): ?>
                <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid var(--color-primary); color: var(--color-text-main); padding: 1.2rem; margin-bottom: 2rem; border-radius: 10px; font-size: 0.9rem; line-height: 1.5;">
                    💡 <strong>Nota:</strong> Como cliente, puedes modificar el alias y el cultivo. Las dimensiones están protegidas.
                </div>
            <?php endif; ?>
            
            <p style="color: var(--color-primary); font-size: 0.85rem; margin-bottom: 2rem;">(*) Campos obligatorios</p>
            <div class="form-premium-grid">
                
                <div class="form-col-2">
                    <div class="input-group-premium">
                        <label>Alias del Invernadero (*)</label>
                        <input type="text" name="nombre" value="<?= $is_edit ? htmlspecialchars($inv_data['nombre']) : '' ?>" required maxlength="50" placeholder="Ej. Sector Norte - Fase 1">
                    </div>
                </div>

                <div class="form-col-2">
                    <div class="input-group-premium">
                        <label>Parcela de Destino (*)</label>
                        <?php if ($is_edit || isset($_GET['parcela_id'])): ?>
                            <?php 
                                $p_id = $is_edit ? $inv_data['parcela_id'] : (int)$_GET['parcela_id'];
                                $p_nombre = $is_edit ? ($inv_data['parcela']['nombre'] ?: "Finca #$p_id") : "Finca #$p_id";
                            ?>
                            <input type="text" value="<?= htmlspecialchars($p_nombre) ?>" readonly class="input-readonly">
                            <input type="hidden" name="parcela_id" value="<?= $p_id ?>">
                        <?php else: ?>
                            <select name="parcela_id" required>
                                <option value="">-- Selecciona una finca --</option>
                                <?php foreach ($parcelas_data as $p): ?>
                                    <option value="<?= $p['parcela_id'] ?>"><?= htmlspecialchars($p['nombre'] ?: 'Finca #'.$p['parcela_id']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="input-group-premium">
                    <label>Largo (m) (*)</label>
                    <input type="number" step="0.01" name="largo_m" value="<?= $is_edit ? (float)$inv_data['largo_m'] : '' ?>" required placeholder="Ej. 120.50" <?= (!$es_admin_full && $is_edit) ? 'readonly class="input-readonly"' : '' ?>>
                </div>

                <div class="input-group-premium">
                    <label>Ancho (m) (*)</label>
                    <input type="number" step="0.01" name="ancho_m" value="<?= $is_edit ? (float)$inv_data['ancho_m'] : '' ?>" required placeholder="Ej. 45.00" <?= (!$es_admin_full && $is_edit) ? 'readonly class="input-readonly"' : '' ?>>
                </div>

                <div class="form-col-2">
                    <div class="input-group-premium">
                        <label>Cultivo Actual (Opcional)</label>
                        <select name="cultivo_id">
                            <option value="">-- Sin asignar (Barbecho) --</option>
                            <?php foreach ($cultivos_data as $c): ?>
                                <option value="<?= $c['cultivo_id'] ?>" <?= ($is_edit && $inv_data['cultivo_id'] == $c['cultivo_id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre_cultivo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-col-2">
                    <div class="input-group-premium">
                        <label>Fecha de Plantación (Opcional)</label>
                        <input type="date" name="fecha_plantacion" value="<?= ($is_edit ? $inv_data['fecha_plantacion'] : date('Y-m-d')) ?>" <?= (!$es_admin_full && $is_edit) ? 'readonly class="input-readonly"' : '' ?>>
                    </div>
                </div>

            </div>

            <div class="form-footer-actions">
                <?= sira_btn($is_edit ? "Guardar Cambios" : "Registrar Invernadero", 'primary', 'save', ['type' => 'submit']) ?>
                <?= sira_btn('Cancelar', 'secondary', 'cancel', ['href' => $url_retorno]) ?>
            </div>
        </form>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
