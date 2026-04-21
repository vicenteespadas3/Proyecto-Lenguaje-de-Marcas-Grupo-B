<?php
require_once '../config/helpers.php';
require_once '../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') respuestaError('Método no permitido. Usa GET.', 405);

$conn = conectar();
$res = $conn->query("SELECT * FROM archivos_adjuntos");

if (!$res) {
    $conn->close();
    respuestaError('Error en la consulta de BD: ' . $conn->error, 500);
}

$archivos = [];
while ($fila = $res->fetch_assoc()) {
    $archivos[] = $fila;
}
$conn->close();

respuestaOk(['archivos' => $archivos]);
?>
