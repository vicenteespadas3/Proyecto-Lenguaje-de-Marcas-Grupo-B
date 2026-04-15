<?php
// api/logout.php  —  POST /api/logout.php
// cURL: curl -b cookies.txt -X POST http://localhost/api/logout.php
require_once 'helpers.php';
require_once 'db_config.php';
iniciarSesion();
if (!empty($_SESSION['usuario'])) {
    $conn = conectar();
    registrarLog($conn, $_SESSION['usuario'], 'logout');
    $conn->close();
}
session_destroy();
respuestaOk(['msg'=>'Sesión cerrada.']);
