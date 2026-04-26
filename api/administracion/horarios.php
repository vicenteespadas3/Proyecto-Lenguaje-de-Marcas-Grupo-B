<?php
require_once '../config/helpers.php';
require_once '../config/db_config.php';

$conn = conectar();
$m = $_SERVER['REQUEST_METHOD'];

// Solo lectura para no complicarnos hoy
if ($m === 'GET') {
    $r = $conn->query("SELECT * FROM asistencia ORDER BY fecha DESC");
    $lista = [];
    while ($f = $r->fetch_assoc()) { $lista[] = $f; }
    $conn->close();
    respuestaOk(['registros' => $lista]);
} else {
    respuestaError('Método en desarrollo.', 405);
}