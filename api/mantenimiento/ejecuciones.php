<?php
require_once '../config/helpers.php';
require_once '../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respuestaError('Método no permitido', 405);
}

$conn = conectar();
$query = "SELECT * FROM ejecuciones ORDER BY id_ejecucion DESC";
$res = $conn->query($query);

$ejecuciones = [];
while ($fila = $res->fetch_assoc()) {
    $ejecuciones[] = $fila;
}

$conn->close();
respuestaOk(['ejecuciones' => $ejecuciones]);