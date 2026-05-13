<?php
/**
 * api_sistema.php - Funciones para configuración global del sistema SIRA
 */

require_once dirname(__FILE__) . '/api_helper.php';

function guardarConfiguracionSocial($token, $data) {
    return sira_api_call($token, "/api/v1/sistema/social", 'POST', $data);
}

function obtenerConfiguracionSocial($token) {
    $res = sira_api_call($token, "/api/v1/sistema/social", 'GET');
    return ($res['code'] == 200) ? $res['data'] : null;
}
