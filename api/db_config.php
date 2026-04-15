<?php
// ============================================================
//  api/db_config.php  —  Configuración de la base de datos
//  Modifica DB_USER y DB_PASS según tu servidor
// ============================================================
define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_NAME',    'instituto');
define('DB_CHARSET', 'utf8mb4');

function conectar(): mysqli {
    $c = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($c->connect_error) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>'Error BD: '.$c->connect_error]);
        exit;
    }
    $c->set_charset(DB_CHARSET);
    return $c;
}
