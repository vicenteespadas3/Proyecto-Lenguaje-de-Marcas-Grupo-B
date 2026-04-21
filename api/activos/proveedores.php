<?php
require_once '../config/helpers.php';
require_once '../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') respuestaError('Método no permitido. Usa GET.', 405);

$conn = conectar();
$res = $conn->query("SELECT * FROM proveedores_maquinas");

if (!$res) {
    $conn->close();
    respuestaError('Error en la consulta de BD: ' . $conn->error, 500);
}

$proveedores = [];
while ($fila = $res->fetch_assoc()) {
    $proveedores[] = $fila;
}
$conn->close();

respuestaOk(['proveedores' => $proveedores]);
?>
