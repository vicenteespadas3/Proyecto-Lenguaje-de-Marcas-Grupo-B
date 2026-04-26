<?php
require_once '../config/helpers.php';
require_once '../config/db_config.php';

$conn = conectar();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $r = $conn->query("SELECT * FROM configuracion");
    $data = [];
    while ($f = $r->fetch_assoc()) { $data[] = $f; }
    $conn->close();
    respuestaOk(['config' => $data]);
} else {
    respuestaError('No implementado.', 405);
}