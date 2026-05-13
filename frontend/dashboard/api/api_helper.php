<?php
/**
 * api_helper.php - Funciones base para comunicación con SIRA API
 */

/**
 * Realiza una petición CURL estandarizada a la API
 */
function sira_api_call($token, $endpoint, $method = 'GET', $data = null) {
    $url = SIRA_API_BASE . $endpoint;
    $ch = curl_init($url);
    
    $headers = [
        "Accept: application/json"
    ];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $headers[] = "Content-Type: application/json";
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $http_code,
        'data' => json_decode($response, true) ?? $response
    ];
}
