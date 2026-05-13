<?php
// 1. Iniciamos la sesión para poder acceder a ella
session_start();

// 2. Notificar al backend para que invalide el session_id
if (isset($_SESSION['jwt_token'])) {
    require_once 'includes/config.php';
    require_once 'dashboard/api/api_helper.php';
    sira_api_call($_SESSION['jwt_token'], "/api/auth/logout", "POST");
}

// 3. Limpiamos todas las variables de sesión (como el token JWT)
$_SESSION = array();

// 3. Destruimos la sesión por completo en el servidor
session_destroy();

// 4. Redirigimos al usuario a la página de login (index.php)
header("Location: index.php");
exit();
?>