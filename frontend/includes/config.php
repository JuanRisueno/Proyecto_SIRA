<?php
// includes/config.php
// ──────────────────────────────────────────────────────────────────────────────
// Configuración central de SIRA.
// Define la URL base de la API de forma automática según el entorno de ejecución:
//
//   • Dentro de Docker  → el contenedor 'api' es alcanzable por su hostname interno.
//   • XAMPP / host      → la API está expuesta en el puerto 8000 del anfitrión.
//
// Detección: si el hostname del servidor PHP es 'sira_frontend' (nombre del
// contenedor Docker), usamos el hostname interno; en caso contrario usamos localhost.
// ──────────────────────────────────────────────────────────────────────────────

if (!defined('SIRA_API_BASE')) {
    // /.dockerenv es un archivo que Docker crea automáticamente dentro de TODOS
    // sus contenedores. Nunca existe en un host normal (Windows/Linux/Mac).
    // Es el método estándar y fiable para detectar si estamos dentro de Docker.
    $en_docker = file_exists('/.dockerenv');
    define('SIRA_API_BASE', $en_docker ? 'http://api:8000' : 'http://localhost:8000');
}
