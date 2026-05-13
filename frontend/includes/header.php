<?php
require_once 'config.php';
require_once 'icons_helper.php';
require_once 'buttons.php';
// Variables que se deben definir ANTES de incluir este archivo:
//   $page_title (string) — Título de la pestaña del navegador
//   $page_css   (string) — Nombre del CSS de página sin extensión ('dashboard', 'sensores', 'login')

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ── Seguridad: Cambio de contraseña obligatorio ─────────────────────────
if (session_status() == PHP_SESSION_NONE) session_start();
$current_page = basename($_SERVER['PHP_SELF']);
if (isset($_SESSION['debe_cambiar_pw']) && $_SESSION['debe_cambiar_pw'] === true) {
    if ($current_page !== 'formulario_cambio_pw.php' && $current_page !== 'logout.php') {
        // Detectar profundidad para redirección correcta
        $is_in_subdir = (strpos($_SERVER['PHP_SELF'], '/formularios/') !== false || strpos($_SERVER['PHP_SELF'], '/dashboard/') !== false);
        $redir_path = $is_in_subdir ? "formulario_cambio_pw.php" : "formularios/formulario_cambio_pw.php";
        
        // Si estamos en un subdirectorio pero NO es /formularios/ (ej: /dashboard/), 
        // necesitamos subir un nivel y entrar en formularios/
        if (strpos($_SERVER['PHP_SELF'], '/dashboard/') !== false) {
             $redir_path = "../formularios/formulario_cambio_pw.php";
        }

        header("Location: " . $redir_path);
        exit();
    }
}

// Configuración Regional de SIRA (España)
date_default_timezone_set('Europe/Madrid');
setlocale(LC_TIME, 'es_ES.UTF-8', 'esp');

// ── Lógica de cambio de tema (Persistencia vía Cookies + Sesión) ──────────
if (isset($_GET['theme'])) {
    $new_theme = ($_GET['theme'] === 'light') ? 'light' : 'dark';
    $_SESSION['sira_theme'] = $new_theme;
    
    // Guardamos en cookie por 30 días para que persista tras el logout
    setcookie('sira_theme_persist', $new_theme, [
        'expires' => time() + (86400 * 30),
        'path' => '/',
        'samesite' => 'Lax'
    ]);
    
    // ── Redirección inteligente: mantenemos todos los parámetros excepto 'theme' ──
    $params = $_GET;
    unset($params['theme']);
    $url_base = strtok($_SERVER['REQUEST_URI'], '?');
    $query_string = http_build_query($params);
    $url_final = $url_base . ($query_string ? '?' . $query_string : '');
    
    header("Location: $url_final");
    exit();
}

// Prioridad de lectura: 1. Sesión (rápida) | 2. Cookie (persistente) | 3. Default (dark)
$tema_actual = $_SESSION['sira_theme'] ?? ($_COOKIE['sira_theme_persist'] ?? 'dark');
$tema_opuesto = ($tema_actual === 'dark') ? 'light' : 'dark';
$tema_icono_svg = ($tema_actual === 'dark') 
    ? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2"></path><path d="M12 20v2"></path><path d="m4.93 4.93 1.41 1.41"></path><path d="m17.66 17.66 1.41 1.41"></path><path d="M2 12h2"></path><path d="M20 12h2"></path><path d="m6.34 17.66-1.41 1.41"></path><path d="m19.07 4.93-1.41 1.41"></path></svg>' 
    : '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path></svg>';

$page_title = $page_title ?? "SIRA";
$page_css   = $page_css   ?? null;

// ── Ruta base dinámica (100% portable: Docker, XAMPP Windows/Linux, subdirectorio, raíz) ──
// En Windows, __DIR__ usa backslashes (\) y DOCUMENT_ROOT puede usar forward slashes (/).
// Normalizamos ambos a forward slashes antes de comparar para que str_replace funcione.
$_doc_root  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
$_front_dir = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$base_url   = str_replace($_doc_root, '', $_front_dir);
// Si está en la raíz del dominio, $base_url queda vacío — correcto (ej: localhost/)
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?= $tema_actual ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Auto-refresh para el Dashboard (30s) -->
    <title><?= htmlspecialchars($page_title) ?></title>
    <!-- Iconos y Favicon -->
    <link rel="icon" type="image/svg+xml" href="<?= $base_url ?>/assets/img/favicon.svg">
    <link rel="icon" type="image/png" href="<?= $base_url ?>/assets/img/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Redirección Automática (0% JS) -->
    <?php if (isset($auto_redirect)): ?>
        <meta http-equiv="refresh" content="3;url=<?= $auto_redirect ?>">
    <?php endif; ?>
    <!-- CSS Base: Variables globales, reset y componentes compartidos -->
    <link rel="stylesheet" href="<?= $base_url ?>/css/style.css?v=1.1">
    <!-- CSS específico de esta página -->
    <?php if ($page_css): ?>
        <link rel="stylesheet" href="<?= $base_url ?>/css/<?= htmlspecialchars($page_css) ?>.css?v=1.1">
    <?php endif; ?>
</head>
<body>

<nav>
    <a href="<?= $base_url ?>/dashboard.php" class="nav-brand">
        <img src="<?= $base_url ?>/assets/img/logo-full.svg" alt="SiRA Logo" class="nav-logo">
        <span class="nav-tagline">Gestión Dinámica</span>
    </a>

    <!-- Reloj Central Premium (PHP Dynamic) -->
    <div class="nav-center-info">
        <div class="nav-clock">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            <span><?= date('H:i') ?></span>
            
            <?php if (isset($_SESSION['user_rol'])): ?>
                <?php if ($_SESSION['user_rol'] === 'root'): ?>
                    <span class="nav-privilege-badge badge-root">ROOT</span>
                <?php elseif ($_SESSION['user_rol'] === 'admin'): ?>
                    <span class="nav-privilege-badge badge-admin">ADMIN</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="nav-date">
            <span><?= date('d/m/Y') ?></span>
        </div>
    </div>
    <div class="nav-actions">
        <!-- Botón de tema: siempre visible, preservando parámetros de la URL -->
        <?php
            $params_tema = $_GET;
            $params_tema['theme'] = $tema_opuesto;
            $toggle_url = '?' . http_build_query($params_tema);
        ?>
        <a href="<?= $toggle_url ?>" class="theme-toggle" title="Cambiar a modo <?= $tema_opuesto ?>">
            <?= $tema_icono_svg ?>
        </a>

        <!-- Botones de Gestión (Solo Admin/Root) -->
        <?php if (isset($_SESSION['user_rol']) && in_array($_SESSION['user_rol'], ['admin', 'root'])): ?>
            <a href="<?= $base_url ?>/dashboard.php?reset_ocultos=1" class="global-btn" title="Volver al Panel de Gestión Global">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                <span>Panel Global</span>
            </a>
        <?php endif; ?>

        <!-- Cerrar sesión: solo si el usuario está logueado -->
        <?php if (isset($_SESSION['jwt_token'])): ?>
            <a href="<?= $base_url ?>/logout.php" class="logout-btn" title="Cerrar Sesión">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                <span>Cerrar Sesión</span>
            </a>
        <?php endif; ?>
    </div>
</nav>
