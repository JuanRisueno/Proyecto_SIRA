<?php
session_start();
if (!isset($_SESSION['jwt_token'])) { header("Location: ../index.php"); exit(); }

require_once '../includes/config.php';

$token = $_SESSION['jwt_token'];
$user_rol = $_SESSION['user_rol'] ?? '';
$cliente_id_session = $_SESSION['cliente_id'] ?? null;

$id_a_gestionar = $_GET['id'] ?? null;
$is_edit = ($id_a_gestionar !== null);

$error_msg = "";
$success_msg = "";
$cult_data = null;

// 1. Obtener datos si es edición
if ($is_edit) {
    $api_get_url = SIRA_API_BASE . "/api/v1/cultivos/$id_a_gestionar";
    $ch = curl_init($api_get_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Accept: application/json"]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $cult_data = json_decode($response, true);
        // Permiso: Dueño o Admin
        $es_dueno = ($cult_data['cliente_id'] == $cliente_id_session);
        $es_admin = in_array($user_rol, ['admin', 'root']);
        if (!$es_dueno && !$es_admin) {
            header("Location: ../dashboard.php?seccion=cultivos&error=sin_permiso");
            exit();
        }
    } else {
        header("Location: ../dashboard.php?seccion=cultivos&error=no_existe");
        exit();
    }
}

// Valores por defecto para parámetros de salud
$p = ($is_edit && isset($cult_data['parametros'])) ? $cult_data['parametros'] : [
    "temp_optima_min" => 15.0, "temp_optima_max" => 30.0,
    "humedad_optima_min" => 60.0, "humedad_optima_max" => 80.0,
    "necesidad_hidrica" => 4.50, "ph_ideal" => 6.5
];

// 2. Lógica de Retorno Dinámica (Backflow) - Necesaria antes del POST
$cliente_id_seleccionado = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : $cliente_id_session;
$from = $_GET['from'] ?? '';
if (!empty($from)) {
    $url_retorno = "../dashboard.php?seccion=" . urlencode($from) . ($cliente_id_seleccionado ? "&cliente_id=$cliente_id_seleccionado" : "");
} else {
    $url_retorno = "../dashboard.php?seccion=cultivos" . ($cliente_id_seleccionado ? "&cliente_id=$cliente_id_seleccionado" : "");
}

// 3. Procesar POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre_cultivo'] ?? '');
    
    if (empty($nombre)) {
        $error_msg = "El nombre del cultivo es obligatorio.";
    } else {
        $api_url = $is_edit ? SIRA_API_BASE . "/api/v1/cultivos/$id_a_gestionar" : SIRA_API_BASE . "/api/v1/cultivos/";
        $method = $is_edit ? "PUT" : "POST";

        $data = [
            "nombre_cultivo" => $nombre, 
            "parametros" => [
                "temp_optima_min" => (float)($_POST['temp_min'] ?? 0),
                "temp_optima_max" => (float)($_POST['temp_max'] ?? 0),
                "humedad_optima_min" => (float)($_POST['hum_min'] ?? 0),
                "humedad_optima_max" => (float)($_POST['hum_max'] ?? 0),
                "necesidad_hidrica" => (float)($_POST['riego'] ?? 0),
                "ph_ideal" => ($_POST['ph'] !== '') ? (float)$_POST['ph'] : null
            ]
        ];

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Content-Type: application/json"]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200 || $http_code == 201) {
            $success_msg = $is_edit ? "Cultivo actualizado correctamente." : "Cultivo registrado correctamente.";
            $auto_redirect = $url_retorno;
            if ($is_edit) $cult_data = json_decode($response, true);
        } else {
            $res_data = json_decode($response, true);
            $error_msg = $res_data['detail'] ?? "Error en la operación del cultivo.";
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
        <a href="../dashboard.php?seccion=cultivos">Catálogo de Cultivos</a>
        <span>/</span>
        <a href="#"><?= $is_edit ? "Editar" : "Nuevo Cultivo" ?></a>
    </div>

    <div class="user-form-container card">
        
        <div style="margin-bottom: 2rem;">
            <h1 class="dashboard-title"><?= $is_edit ? "✏️ Editar Cultivo" : "🌱 Nuevo Cultivo" ?></h1>
            <p class="dashboard-subtitle"><?= $is_edit ? "Modificando ficha técnica de <strong>".htmlspecialchars($cult_data['nombre_cultivo'])."</strong>" : "Define el nombre y los parámetros óptimos de salud." ?></p>
        </div>

        <?php if ($error_msg): ?>
            <div style="background: rgba(231, 76, 60, 0.1); border-left: 4px solid var(--color-error); color: var(--color-error); padding: 1rem; margin-bottom: 1.5rem;">
                <strong>⚠️ Error:</strong> <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <?php 
        if ($success_msg) {
            $conf_icon  = '🍃';
            $conf_title = $is_edit ? "Cambios Guardados" : "Cultivo Registrado";
            $conf_msg   = $success_msg;
            $conf_redir = $url_retorno;
            include '../includes/confirmaciones.php';
        }
        ?>

        <form method="POST" class="sira-form">
            <div class="form-premium-grid">
                <div class="form-col-2">
                    <div class="input-group-premium">
                        <label>Nombre del Cultivo (*)</label>
                        <input type="text" name="nombre_cultivo" placeholder="Ej. Tomate Cherry" required value="<?= $is_edit ? htmlspecialchars($cult_data['nombre_cultivo']) : '' ?>">
                    </div>
                </div>

                <?php if ($is_edit): ?>
                <div class="form-col-2">
                    <div class="input-group-premium">
                        <label>Propietario / Origen</label>
                        <input type="text" value="<?= htmlspecialchars($cult_data['nombre_cliente'] ?? 'Sistema') ?>" disabled class="input-readonly">
                    </div>
                </div>
                <?php endif; ?>

                <div class="input-group-premium">
                    <label>🌡️ Temp. Mín (ºC)</label>
                    <input type="number" step="0.1" name="temp_min" value="<?= $p['temp_optima_min'] ?>" required>
                </div>
                <div class="input-group-premium">
                    <label>🔥 Temp. Máx (ºC)</label>
                    <input type="number" step="0.1" name="temp_max" value="<?= $p['temp_optima_max'] ?>" required>
                </div>
                <div class="input-group-premium">
                    <label>💧 Humedad Mín (%)</label>
                    <input type="number" step="0.1" name="hum_min" value="<?= $p['humedad_optima_min'] ?>" required>
                </div>
                <div class="input-group-premium">
                    <label>💧 Humedad Máx (%)</label>
                    <input type="number" step="0.1" name="hum_max" value="<?= $p['humedad_optima_max'] ?>" required>
                </div>
                <div class="input-group-premium">
                    <label>🚿 Riego Diario (L/m²)</label>
                    <input type="number" step="0.01" name="riego" value="<?= $p['necesidad_hidrica'] ?>" required>
                </div>
                <div class="input-group-premium">
                    <label>🧪 pH Suelo Ideal</label>
                    <input type="number" step="0.1" name="ph" value="<?= $p['ph_ideal'] ?>">
                </div>
            </div>

            <div class="form-footer-actions">
                <?= sira_btn($is_edit ? 'Guardar Cambios' : 'Registrar Cultivo Completo', 'primary', 'save', ['type' => 'submit']) ?>
                <?= sira_btn('Cancelar', 'secondary', 'cancel', ['href' => $url_retorno]) ?>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
