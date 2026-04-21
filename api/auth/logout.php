<?php
// api/logout.php  —  POST /api/logout.php
// cURL: curl -b cookies.txt -X POST http://localhost/api/logout.php
require_once '../config/helpers.php';
require_once '../config/db_config.php';
iniciarSesion();

// Si existe una sesión activa, dejamos constancia de la salida en el registro (log) de la BD
if (!empty($_SESSION['usuario'])) {
    $conn = conectar();
    registrarLog($conn, $_SESSION['usuario'], 'logout');
    $conn->close();
}

// Destruimos la sesión en el servidor PHP, eliminando cualquier rastro del usuario logueado
session_destroy();

// Devolvemos una confirmación al frontend
respuestaOk(['msg'=>'Sesión cerrada.']);
