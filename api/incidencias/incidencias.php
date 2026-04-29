<?php
require_once '../config/helpers.php';
require_once '../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respuestaError('Método no permitido', 405);
}

$conn = conectar();
/**
 * Obtiene todas las incidencias de la tabla 'incidencias'.
 * Los resultados se ordenan por 'fecha_apertura' de forma descendente, mostrando primero las más recientes.
 */
$query = "SELECT * FROM incidencias ORDER BY fecha_apertura DESC";
$res = $conn->query($query);

if (!$res) {
    respuestaError('Error en BD: ' . $conn->error, 500);
}

$incidencias = [];
while ($fila = $res->fetch_assoc()) {
    $incidencias[] = $fila;
}

$conn->close();
respuestaOk(['incidencias' => $incidencias]);