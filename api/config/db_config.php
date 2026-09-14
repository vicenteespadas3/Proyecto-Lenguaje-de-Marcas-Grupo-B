<?php
// ============================================================
//  api/db_config.php  —  Configuración de la base de datos
//  Modifica DB_USER y DB_PASS según tu servidor
// ============================================================
define('DB_HOST', '172.20.10.4');//172.20.10.2 para conectar con servidor de Grupo 5
define('DB_USER', 'admin');//admin para conectar con servidor de Grupo 5
define('DB_PASS', '123456');//123456 para conectar con servidor de Grupo 5
define('DB_NAME', 'GestionTaller');//GestionTaller es el servidor
define('DB_CHARSET', 'utf8mb4');

function conectar(): mysqli
{
    // Apagamos el reporte automático de excepciones de MySQLi para manejar los errores manualmente
    // Esto evita que PHP imprima advertencias en HTML que rompan la respuesta JSON esperada por app.js
    mysqli_report(MYSQLI_REPORT_OFF);

    // Conectamos a la base de datos (se usa @ para suprimir warnings por si el servidor Ubuntu está apagado)
    $c = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($c->connect_error) {
        // Si hay error (ej. base de datos inaccesible), devolvemos un JSON válido con código HTTP 500
        // Esto permite que el frontend (fetch en app.js) capture el error limpiamente sin colgarse
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Error BD: ' . $c->connect_error]);
        exit;
    }

    $c->set_charset(DB_CHARSET);
    return $c;
}
