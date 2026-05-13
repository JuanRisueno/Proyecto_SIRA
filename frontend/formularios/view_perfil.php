<?php
session_start();
if (!isset($_SESSION['jwt_token'])) { header("Location: ../index.php"); exit(); }

require_once '../includes/config.php';

$token = $_SESSION['jwt_token'];
$user_rol = $_SESSION['user_rol'] ?? '';
$id_view = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$id_view) { header("Location: ../dashboard.php"); exit(); }

// 1. Obtener datos del usuario
$api_get_url = SIRA_API_BASE . "/api/v1/clientes/$id_view";
$ch = curl_init($api_get_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $token",
    "Accept: application/json"
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code != 200) {
    header("Location: ../dashboard.php?error=usuario_no_encontrado");
    exit();
}
$user_data = json_decode($response, true);

// 2. Control de Acceso
// Solo Root puede ver a otros Admins. Admins pueden verse a sí mismos.
$es_mi_perfil = ($id_view == ($_SESSION['cliente_id'] ?? 0));
if ($user_rol === 'admin' && !$es_mi_perfil && in_array($user_data['rol'], ['admin', 'root'])) {
    header("Location: ../dashboard.php?error=sin_permiso_perfil_gestion");
    exit();
}

// 3. Lógica de Edición
$puede_editar = ($user_rol === 'root') || ($es_mi_perfil && in_array($user_rol, ['admin', 'root']));

$page_title = "SIRA — Perfil de Usuario";
$page_css   = "dashboard";
require_once '../includes/header.php';
?>

<div class="container" style="margin-top: 1rem;">
    <div class="breadcrumbs">
        <span>📍 Tú estás aquí:</span>
        <a href="../dashboard.php">Panel</a>
        <span>/</span>
        <a href="#">Ficha de Usuario</a>
    </div>

    <div class="user-form-container" style="max-width: 800px; margin: 0 auto;">
        
        <!-- CABECERA PREMIUM DE FICHA -->
        <div style="margin-bottom: 2.5rem; text-align: center;">
            <div style="font-size: 4rem; margin-bottom: 1rem; filter: drop-shadow(0 10px 20px rgba(0,0,0,0.3));">
                <?= ($user_data['rol'] === 'cliente') ? '👨‍🌾' : '🛡️' ?>
            </div>
            <h1 class="dashboard-title" style="margin-bottom: 0.5rem;"><?= htmlspecialchars($user_data['nombre_empresa']) ?></h1>
            <div style="display: flex; justify-content: center; gap: 10px; align-items: center;">
                <span class="list-badge-tech <?= ($user_data['rol'] === 'root') ? 'highlight-glow' : '' ?>" style="padding: 6px 15px; font-size: 0.85rem;">
                    <?= strtoupper($user_data['rol']) ?>
                </span>
                <?php if ($user_data['activa']): ?>
                    <span style="color: var(--color-primary); font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">● ACTIVO</span>
                <?php else: ?>
                    <span style="color: var(--color-warning); font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">● ARCHIVADO</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- CUADRÍCULA DE DATOS (LECTURA) -->
        <div class="form-premium-grid" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); padding: 2rem; border-radius: 15px; margin-bottom: 2.5rem;">
            
            <div class="form-col-1">
                <div class="input-group-premium">
                    <label>Identificador (CIF/DNI)</label>
                    <div style="padding: 1rem; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); border-radius: 8px; color: var(--color-text-main); font-family: monospace; font-size: 1.1rem; font-weight: 700;">
                        <?= htmlspecialchars($user_data['cif']) ?>
                    </div>
                </div>
            </div>

            <div class="form-col-1">
                <div class="input-group-premium">
                    <label>Persona de Contacto / Cargo</label>
                    <div style="padding: 1rem; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); border-radius: 8px; color: var(--color-text-main); font-size: 1.1rem;">
                        <?= htmlspecialchars($user_data['persona_contacto']) ?>
                    </div>
                </div>
            </div>

            <div class="form-col-1">
                <div class="input-group-premium">
                    <label>Correo Electrónico</label>
                    <div style="padding: 1rem; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); border-radius: 8px; color: var(--color-text-main); font-size: 1.1rem;">
                        <?= htmlspecialchars($user_data['email_admin']) ?>
                    </div>
                </div>
            </div>

            <div class="form-col-1">
                <div class="input-group-premium">
                    <label>Teléfono de Contacto</label>
                    <div style="padding: 1rem; background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); border-radius: 8px; color: var(--color-text-main); font-size: 1.1rem;">
                        <?= htmlspecialchars($user_data['telefono']) ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($user_data['ultima_actividad'])): ?>
            <div class="form-col-2">
                <div class="input-group-premium" style="margin-top: 1rem; text-align: center;">
                    <label>Última Actividad en el Sistema</label>
                    <div style="color: var(--color-text-muted); font-size: 0.9rem;">
                        📅 <?= date("d/m/Y H:i", strtotime($user_data['ultima_actividad'])) ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <div class="form-footer-actions" style="justify-content: center; gap: 1.5rem;">
            <?php if ($puede_editar): ?>
                <?= sira_btn('Editar Información', 'primary', 'gear', ['href' => "formulario_usuario.php?id=$id_view"]) ?>
            <?php endif; ?>
            
            <?= sira_btn('Volver al Listado', 'secondary', 'cancel', ['href' => "../dashboard.php"]) ?>
        </div>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
