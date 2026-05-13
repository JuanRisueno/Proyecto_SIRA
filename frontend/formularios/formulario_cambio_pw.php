<?php
session_start();
if (!isset($_SESSION['jwt_token'])) { header("Location: ../index.php"); exit(); }
require_once '../includes/config.php';

$error_msg = "";
$success_msg = "";

$user_rol = $_SESSION['user_rol'] ?? 'cliente';
$min_len = ($user_rol === 'root' || $user_rol === 'admin') ? 10 : 8;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_pw = $_POST['new_password'];
    $confirm_pw = $_POST['confirm_password'];

    if ($new_pw !== $confirm_pw) {
        $error_msg = "Las contraseñas no coinciden.";
    } else {
        $cliente_id = $_SESSION['cliente_id'];
        $token = $_SESSION['jwt_token'];
        $api_url = SIRA_API_BASE . "/api/v1/clientes/$cliente_id";
        $data = ["password" => $new_pw];

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200) {
            unset($_SESSION['debe_cambiar_pw']);
            $success_msg = "Contraseña actualizada correctamente. Bienvenido a SIRA.";
        } else {
            $json = json_decode($response, true);
            $detail = $json['detail'] ?? "Error desconocido en el servidor de seguridad.";
            if (is_array($detail)) $detail = implode(", ", $detail);
            $error_msg = $detail;
        }
    }
}

$page_title = "SIRA — Seguridad de Cuenta";
$page_css = "dashboard"; 
require_once '../includes/header.php';
?>

<style>
    .sira-btn:disabled { cursor: not-allowed !important; }
</style>

<style>
    /* Forzar que el footer no se pegue al fondo y respete el flujo natural */
    body { min-height: auto !important; height: auto !important; display: block !important; }
    .sira-footer { margin-top: 0.5rem !important; }
</style>

<div class="container" style="max-width: 650px; margin-top: 0.5rem; margin-bottom: 0.5rem;">
    <div class="glass-form-container" style="padding: 1.2rem; margin-bottom: 0;">
        
        <?php if ($success_msg): ?>
            <div style="min-height: 40vh; display: flex; align-items: center; justify-content: center;">
                <?php 
                    $conf_icon = '🔒';
                    $conf_title = "Seguridad Actualizada";
                    $conf_msg = $success_msg;
                    $conf_redir = "../dashboard.php";
                    include '../includes/confirmaciones.php';
                ?>
            </div>
        <?php else: ?>
            
            <div class="form-header-premium" style="flex-direction: row; align-items: center; text-align: left; gap: 1.5rem; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05);">
                <div class="icon-badge" style="font-size: 2rem; width: 55px; height: 55px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">🛡️</div>
                <div>
                    <h1 class="main-title" style="font-size: 1.5rem; margin: 0;">Protege tu cuenta</h1>
                    <p class="sub-title" style="margin: 0; font-size: 0.85rem; opacity: 0.7;">Establece una contraseña segura propia para continuar.</p>
                </div>
            </div>

            <?php if ($error_msg): ?>
                <div class="alert alert-error" style="background: rgba(248, 113, 113, 0.1); border: 1px solid #f87171; padding: 0.75rem; border-radius: 8px; color: #f87171; margin-bottom: 1rem; font-size: 0.9rem; text-align: center;">
                   ⚠️ <?= htmlspecialchars($error_msg) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="sira-form" autocomplete="off">
                <!-- Dummy inputs para engañar al gestor de contraseñas del navegador -->
                <input type="text" style="display:none" name="prevent_autofill_user">
                <input type="password" style="display:none" name="prevent_autofill_pass">

                <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 1.2rem; border-radius: 12px; margin-bottom: 1rem;">
                    

                    <div style="margin-bottom: 0.8rem;">
                        <label style="display: block; font-size: 0.65rem; font-weight: 800; color: var(--color-text-main); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.3rem;">NUEVA CONTRASEÑA</label>
                        <div style="position: relative;">
                            <input type="password" name="new_password" id="new_password" required placeholder="••••••••" autocomplete="new-password" style="width: 100%; background: var(--color-bg-input); border: 1px solid var(--border-input); padding: 0.6rem 1rem; padding-right: 3rem; border-radius: 8px; color: var(--color-text-main); font-size: 1rem;" onkeyup="triggerComplexity(this.value)">
                            <button type="button" class="btn-eye" onclick="togglePassword('new_password', this)" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--color-text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; width: 25px; height: 25px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 18px; height: 18px;"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </div>
                    </div>

                    <div style="margin-bottom: 0.8rem;">
                        <label style="display: block; font-size: 0.65rem; font-weight: 800; color: var(--color-text-main); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.3rem;">CONFIRMAR CONTRASEÑA</label>
                        <div style="position: relative;">
                            <input type="password" name="confirm_password" id="confirm_password" required placeholder="••••••••" autocomplete="new-password" style="width: 100%; background: var(--color-bg-input); border: 1px solid var(--border-input); padding: 0.6rem 1rem; padding-right: 3rem; border-radius: 8px; color: var(--color-text-main); font-size: 1rem;" onkeyup="triggerMatch()">
                            <button type="button" class="btn-eye" onclick="togglePassword('confirm_password', this)" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--color-text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; width: 25px; height: 25px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 18px; height: 18px;"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </div>
                    </div>

                    <div class="pw-requirements" style="margin-top: 1.2rem; padding: 1rem; background: rgba(0,0,0,0.2); border-radius: 10px; border: 1px solid rgba(255,255,255,0.05);">
                        <h4 style="font-size: 0.6rem; text-transform: uppercase; color: var(--color-text-muted); margin-bottom: 0.75rem; letter-spacing: 0.1em;">Requisitos SIRA:</h4>
                        <ul style="list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 6px;">
                            <li id="req-len" style="font-size: 0.75rem; opacity: 0.5; display: flex; align-items: center; gap: 6px;">❌ Mínimo <?= $min_len ?> caracteres</li>
                            <li id="req-cap" style="font-size: 0.75rem; opacity: 0.5; display: flex; align-items: center; gap: 6px;">❌ Una mayúscula</li>
                            <li id="req-low" style="font-size: 0.75rem; opacity: 0.5; display: flex; align-items: center; gap: 6px;">❌ Una minúscula</li>
                            <li id="req-num" style="font-size: 0.75rem; opacity: 0.5; display: flex; align-items: center; gap: 6px;">❌ Al menos un número</li>
                            <li id="req-sym" style="font-size: 0.75rem; opacity: 0.5; display: flex; align-items: center; gap: 6px;">❌ Un símbolo (!@#...)</li>
                            <li style="font-size: 0.75rem; opacity: 0.8; color: var(--color-primary); display: flex; align-items: center; gap: 6px; grid-column: span 1;">🔄 Diferente a las últimas 5</li>
                        </ul>
                    </div>
                </div>

                <div class="form-actions-wow" style="border: none; padding: 0; margin: 0;">
                    <button type="submit" id="submit_btn" class="sira-btn" style="width: 100%; height: 50px; font-size: 0.9rem; filter: grayscale(1); opacity: 0.5;" disabled>ACTUALIZAR Y COMENZAR</button>
                </div>
            </form>
        <?php endif; ?>

    </div>
</div>

<script src="../assets/js/sira-security-ui.js"></script>
<script>
    const minLenRequired = <?= $min_len ?>;
    // Wrapper para pasar la constante del servidor al script externo
    function triggerComplexity(val) { validateComplexity(val, minLenRequired); }
    function triggerMatch() { checkMatch(minLenRequired); }
</script>

<?php require_once '../includes/footer.php'; ?>
