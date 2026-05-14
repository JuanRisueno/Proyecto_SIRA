<?php
session_start();
if (!isset($_SESSION['jwt_token'])) { header("Location: ../index.php"); exit(); }

require_once '../includes/config.php';

$token = $_SESSION['jwt_token'];
$user_rol = $_SESSION['user_rol'] ?? '';

// Solo admin y root pueden gestionar geografía
if (!in_array($user_rol, ['admin', 'root'])) {
    header("Location: ../dashboard.php");
    exit();
}

$error_msg = "";
$success_msg = "";

// Variables de retorno
$from = $_GET['from'] ?? '';
$url_retorno = !empty($from) ? "../dashboard.php?seccion=" . urlencode($from) : "../dashboard.php?seccion=localidades";

// Procesar Registro Directo
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_registrar'])) {
    $cp = $_POST['cp'] ?? '';
    $municipio = $_POST['municipio'] ?? '';
    $provincia = $_POST['provincia'] ?? '';

    if (empty($cp) || empty($municipio) || empty($provincia)) {
        $error_msg = "Todos los campos marcados con (*) son obligatorios.";
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
            $error_msg = $res_data['detail'] ?? "Error al registrar la localidad. Verifique que el CP no exista.";
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container" style="margin-top: 2rem;">
    <div class="breadcrumbs">
        <span>📍 SIRA GEO</span>
        <span>/</span>
        <a href="../dashboard.php?seccion=localidades">Gestión</a>
        <span>/</span>
        <a href="#">Nuevo Registro</a>
    </div>

    <div class="glass-form-container" style="max-width: 600px; margin: 2rem auto; padding: 2.5rem; border: 1px solid rgba(255,255,255,0.1);">
        <div class="form-header-premium">
            <h2 class="main-title">📍 Registro de Localidad</h2>
            <p class="subtitle">Complete los datos geográficos de la nueva zona</p>
        </div>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.5rem;"><?= $error_msg ?></div>
        <?php endif; ?>

        <?php if ($success_msg): ?>
            <div class="alert alert-success" style="margin-bottom: 1.5rem;"><?= $success_msg ?></div>
            <script>setTimeout(() => { window.location.href = '<?= $auto_redirect ?>'; }, 2000);</script>
        <?php endif; ?>

        <form method="POST" action="" class="sira-premium-form">
            <div class="input-group-premium">
                <label>Código Postal (*)</label>
                <input type="text" name="cp" id="input_cp" maxlength="5" placeholder="Ej. 28001" required 
                       onkeyup="autoSuggestGeo(this.value)">
                <span class="input-bar"></span>
            </div>

            <div class="input-group-premium">
                <label>Municipio (*)</label>
                <input type="text" name="municipio" id="input_mun" placeholder="Nombre de la ciudad" required>
                <span class="input-bar"></span>
            </div>

            <div class="input-group-premium">
                <label>Provincia (*)</label>
                <input type="text" name="provincia" id="input_prov" placeholder="Nombre de la provincia" required>
                <span class="input-bar"></span>
            </div>

            <div class="form-actions" style="margin-top: 3rem; display: flex; gap: 1rem;">
                <a href="<?= $url_retorno ?>" class="btn-sira btn-secondary" style="flex: 1; text-align: center; text-decoration: none; display: flex; align-items: center; justify-content: center;">
                    CANCELAR
                </a>
                <button type="submit" name="btn_registrar" class="btn-sira btn-primary" style="flex: 2; font-weight: bold; letter-spacing: 1px;">
                    ✅ REGISTRAR LOCALIDAD
                </button>
            </div>
        </form>
    </div>
</div>

<script>
/**
 * Ayuda al usuario a rellenar el formulario pero NO bloquea.
 * Si el CP es reconocido, autorrellena. Si no, deja al usuario escribir.
 */
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
                // Efecto visual de éxito
                document.getElementById('input_cp').style.borderColor = 'var(--color-primary)';
            }
        } catch (e) {
            console.log("Sugerencia no disponible");
        }
    }
}
</script>

<style>
    .glass-form-container {
        background: var(--glass-bg);
        backdrop-filter: var(--glass-blur);
        border-radius: 20px;
        box-shadow: var(--shadow-premium);
    }
    .input-group-premium {
        margin-bottom: 1.5rem;
        position: relative;
    }
    .input-group-premium label {
        display: block;
        font-size: 0.85rem;
        color: var(--color-primary);
        margin-bottom: 0.5rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .input-group-premium input {
        width: 100%;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.1);
        padding: 1rem;
        border-radius: 10px;
        color: white;
        font-size: 1rem;
        transition: all 0.3s ease;
    }
    .input-group-premium input:focus {
        background: rgba(255,255,255,0.1);
        border-color: var(--color-primary);
        outline: none;
        box-shadow: 0 0 15px rgba(16, 185, 129, 0.2);
    }
    .main-title {
        color: white;
        font-size: 1.8rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }
    .subtitle {
        color: rgba(255,255,255,0.6);
        font-size: 0.9rem;
        margin-bottom: 2rem;
    }
</style>