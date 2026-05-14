<?php
session_start();
if (!isset($_SESSION['jwt_token'])) { header("Location: ../index.php"); exit(); }

require_once '../includes/config.php';

$token = $_SESSION['jwt_token'];
$user_rol = $_SESSION['user_rol'] ?? '';

// Solo admin, root y cliente
if (!in_array($user_rol, ['admin', 'root', 'cliente'])) {
    header("Location: ../dashboard.php");
    exit();
}

$id_a_gestionar = isset($_GET['id']) ? (int)$_GET['id'] : null;
$is_edit = ($id_a_gestionar !== null);

$error_msg = "";
$success_msg = "";
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
    }
}

// Inicialización de variables para el formulario
$nombre = $parcela_data['nombre'] ?? '';
$ref_catastral = $parcela_data['ref_catastral'] ?? '';
$direccion = $parcela_data['direccion'] ?? '';
$cp = $parcela_data['codigo_postal'] ?? ($_GET['localidad_cp'] ?? '');
$municipio = $parcela_data['localidad']['municipio'] ?? '';
$provincia = $parcela_data['localidad']['provincia'] ?? '';

// Sobrescribir con POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'] ?? $nombre;
    $ref_catastral = $_POST['ref_catastral'] ?? $ref_catastral;
    $direccion = $_POST['direccion'] ?? $direccion;
    $cp = $_POST['cp'] ?? $cp;
    $municipio = $_POST['municipio'] ?? $municipio;
    $provincia = $_POST['provincia'] ?? $provincia;
}

// Determinar cliente_id objetivo
$cliente_id_obj = $is_edit 
    ? ($parcela_data['cliente_id'] ?? null)
    : (isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : (($user_rol === 'cliente') ? ($_SESSION['cliente_id'] ?? null) : null));

// Variables de retorno
$from = $_GET['from'] ?? '';
$url_retorno = !empty($from) ? "../dashboard.php?seccion=" . urlencode($from) . "&cliente_id=$cliente_id_obj" : "../dashboard.php?cliente_id=$cliente_id_obj";

// 2. Procesar Acción de Guardar
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_guardar'])) {
    // Registro silencioso de localidad
    if (!empty($cp) && !empty($municipio)) {
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
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Content-Type: application/json"]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (($is_edit && $http_code == 200) || (!$is_edit && $http_code == 201)) {
        $success_msg = "Parcela gestionada con éxito.";
        $auto_redirect = $url_retorno;
    } else {
        $res_data = json_decode($response, true);
        $error_msg = $res_data['detail'] ?? "Error en la operación.";
    }
}

require_once '../includes/header.php';
?>

<div class="container" style="margin-top: 1rem;">
    <div class="breadcrumbs">
        <span>📍 Tú estás aquí:</span>
        <a href="../dashboard.php">Panel</a>
        <span>/</span>
        <a href="../dashboard.php?cliente_id=<?= $cliente_id_obj ?>">Fincas</a>
        <span>/</span>
        <a href="#"><?= ($is_edit ? "Editar" : "Añadir") . " Parcela" ?></a>
    </div>

    <div class="user-form-container">
        
        <div style="margin-bottom: 2rem;">
            <h1 class="dashboard-title"><?= $is_edit ? "✏️ Editar Parcela" : "🌳 Añadir Nueva Parcela" ?></h1>
            <p class="dashboard-subtitle"><?= $is_edit ? "Ajuste de parámetros para la parcela #$id_a_gestionar" : "Define la ubicación y los datos legales de la nueva zona de cultivo." ?></p>
        </div>

        <?php if ($error_msg): ?>
            <div style="background: var(--color-error-bg); border-left: 4px solid var(--color-error); color: var(--color-error-text); padding: 1rem; margin-bottom: 1.5rem; border-radius: var(--radius-sm);">
                <strong>⚠️ Error:</strong> <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <?php 
        if ($success_msg) {
            $conf_icon  = '🌳';
            $conf_title = $is_edit ? "Cambios Aplicados" : "Registro Completado";
            $conf_msg   = $success_msg;
            $conf_redir = $url_retorno;
            include '../includes/confirmaciones.php';
        }
        ?>

        <form method="POST" class="sira-form">
            <p style="color: var(--color-primary); font-size: 0.85rem; margin-bottom: 2rem;">(*) Campos obligatorios</p>
            
            <div class="form-premium-grid">
                
                <div class="form-col-2">
                    <div class="input-group-premium">
                        <label>Nombre / Alias de la Parcela (*)</label>
                        <input type="text" name="nombre" value="<?= htmlspecialchars($nombre) ?>" placeholder="Ej. Olivar del Norte - Fase 1" required>
                    </div>
                </div>

                <div class="form-col-2">
                    <div class="input-group-premium">
                        <label>Referencia Catastral (*)</label>
                        <input type="text" name="ref_catastral" required maxlength="14" minlength="14" value="<?= htmlspecialchars($ref_catastral) ?>" placeholder="Ej. 1234567AB1234C">
                    </div>
                </div>

                <div class="form-col-2">
                    <div class="input-group-premium">
                        <label>Dirección Postal (*)</label>
                        <input type="text" name="direccion" required value="<?= htmlspecialchars($direccion) ?>" placeholder="Polígono, Parcela, Vía...">
                    </div>
                </div>

                <div class="input-group-premium">
                    <label>Código Postal (*)</label>
                    <input type="text" name="cp" id="input_cp" maxlength="5" required value="<?= htmlspecialchars($cp) ?>" placeholder="28001" onkeyup="autoSuggestGeo(this.value)">
                </div>

                <div class="input-group-premium">
                    <label>Municipio (*)</label>
                    <input type="text" name="municipio" id="input_mun" required value="<?= htmlspecialchars($municipio) ?>" placeholder="Ciudad">
                </div>

                <div class="form-col-2">
                    <div class="input-group-premium">
                        <label>Provincia (*)</label>
                        <input type="text" name="provincia" id="input_prov" required value="<?= htmlspecialchars($provincia) ?>" placeholder="Provincia">
                    </div>
                </div>

            </div>

            <div class="form-footer-actions">
                <?= sira_btn($is_edit ? "Guardar Cambios" : "REGISTRAR PARCELA", 'primary', 'save', ['type' => 'submit', 'name' => 'btn_guardar']) ?>
                <?= sira_btn('CANCELAR', 'secondary', 'cancel', ['href' => $url_retorno]) ?>
            </div>
        </form>

    </div>
</div>

<script>
async function autoSuggestGeo(cp) {
    if (cp.length === 5) {
        try {
            const response = await fetch('<?= SIRA_API_BASE ?>/api/v1/geo/check-cp/' + cp, {
                headers: { 'Authorization': 'Bearer <?= $token ?>' }
            });
            if (response.ok) {
                const data = await response.json();
                document.getElementById('input_mun').value = data.municipio;
                document.getElementById('input_prov').value = data.provincia;
            }
        } catch (e) {}
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
