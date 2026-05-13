<?php
session_start();
if (!isset($_SESSION['jwt_token'])) { header("Location: ../index.php"); exit(); }

require_once '../includes/config.php';

$token = $_SESSION['jwt_token'];
$user_rol = $_SESSION['user_rol'] ?? '';

// Solo admin y root
if (!in_array($user_rol, ['admin', 'root'])) {
    header("Location: ../dashboard.php");
    exit();
}

$error_msg = "";
$success_msg = "";
$geo_status_msg = "";
$candidatos = [];

// Variables de estado del formulario (priorizan POST)
$cp = $_POST['cp'] ?? '';
$municipio = $_POST['municipio'] ?? '';
$provincia = $_POST['provincia'] ?? '';
$cp_confirmado = $_POST['cp_confirmado'] ?? '';

// [V14.1] Lógica de Retorno Dinámica (Backflow)
$from = $_GET['from'] ?? '';
if (!empty($from)) {
    $url_retorno = "../dashboard.php?seccion=" . urlencode($from);
} else {
    $url_retorno = "../dashboard.php?seccion=localidades";
}

// 2. Procesar Acciones (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // CASO A: VALIDAR POR CP
    if (isset($_POST['btn_validar_cp'])) {
        if (strlen($cp) === 5) {
            $api_url = SIRA_API_BASE . "/api/v1/geo/check-cp/" . urlencode($cp);
            $ch = curl_init($api_url);
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
                $geo_status_msg = ($data['origen'] === 'local') ? "✅ Localidad ya registrada." : "🌍 Datos de API externa.";
            } else {
                $error_msg = "Código Postal no reconocido.";
                $cp_confirmado = "";
            }
        } else {
            $error_msg = "El CP debe tener 5 dígitos.";
        }
    }

    // CASO B: BUSCAR POR NOMBRE
    elseif (isset($_POST['btn_buscar_nombre'])) {
        if (strlen($municipio) >= 3) {
            $api_url = SIRA_API_BASE . "/api/v1/geo/search-municipio/" . urlencode($municipio);
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
                $error_msg = $res_data['detail'] ?? "No se encontraron coincidencias para ese municipio.";
            }
        }
    }

    // CASO C: SELECCIONAR CANDIDATO
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
            $provincia = $data['provincia']; // Forzamos la nueva provincia
            $cp_confirmado = $cp;
        }
    }

    // CASO D: REGISTRAR
    elseif (isset($_POST['btn_registrar'])) {
        if ($cp !== $cp_confirmado || empty($cp_confirmado)) {
            $error_msg = "⚠️ ERROR: CP no validado.";
        } else {
            $api_url = SIRA_API_BASE . "/api/v1/localidades/";
            $data = [
                "codigo_postal" => $cp,
                "municipio" => $municipio,
                "provincia" => $provincia
            ];

            $ch = curl_init($api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Content-Type: application/json"]);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code == 201 || $http_code == 200) {
                $success_msg = "Localidad registrada correctamente.";
                $auto_redirect = $url_retorno;
            } else {
                $res_data = json_decode($response, true);
                $error_msg = $res_data['detail'] ?? "Error en la operación.";
            }
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
        <a href="../dashboard.php?seccion=localidades">Gestión Localidades</a>
        <span>/</span>
        <a href="#">Añadir Inteligente</a>
    </div>

    <div class="user-form-container">
        
        <div style="margin-bottom: 2rem;">
            <h1 class="dashboard-title">📍 Registro de Localidad</h1>
            <p class="dashboard-subtitle">Gating System (Validación Forzosa SIRA GEO).</p>
        </div>

        <?php if ($error_msg): ?>
            <div style="background: var(--color-error-bg); border-left: 4px solid var(--color-error); color: var(--color-error-text); padding: 1rem; margin-bottom: 1.5rem; border-radius: var(--radius-sm);">
                <strong>⚠️ Alerta:</strong> <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <?php 
        if ($success_msg) {
            $conf_icon  = '📍';
            $conf_title = "Localidad Añadida";
            $conf_msg   = $success_msg;
            $conf_redir = $url_retorno;
            include '../includes/confirmaciones.php';
        }
        ?>

        <form method="POST" class="sira-form">
            <p style="color: var(--color-primary); font-size: 0.85rem; margin-bottom: 2rem;">(*) Campos obligatorios</p>
            <input type="hidden" name="cp_confirmado" value="<?= htmlspecialchars($cp_confirmado) ?>">
            
            <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--color-primary); border-radius: 10px; padding: 1rem; margin-bottom: 2rem; font-size: 0.85rem; color: var(--color-text-main);">
                🔒 <strong>SIRA Gating System:</strong> Es obligatorio validar el CP o buscar el municipio antes de registrar.
            </div>


            <div class="form-footer-actions">
                <?php if (!empty($cp_confirmado) && $cp === $cp_confirmado): ?>
                    <button type="submit" name="btn_registrar" value="1" class="btn-sira btn-primary">
                        Registrar Localidad
                    </button>
                <?php else: ?>
                    <div style="flex: 2; background: rgba(255,255,255,0.05); color: var(--color-text-muted); padding: 0.8rem; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.82rem; font-weight: 600; cursor: not-allowed; border: 1px dashed rgba(255,255,255,0.2); text-align: center; line-height: 1.4;">
                        🔒 Rellene los campos obligatorios (*) <br>y valide el CP para continuar
                    </div>
                <?php endif; ?>
                
                <a href="<?= $url_retorno ?>" class="btn-sira btn-secondary">
                    Cancelar
                </a>
            </div>
        </form>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
