<?php
// ============================================================
//  api/herramientas.php — GET /api/herramientas.php
//  Obtiene la lista de herramientas desde la base de datos
// ============================================================
require_once '../config/helpers.php';
require_once '../config/db_config.php';

// Solo aceptamos peticiones GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respuestaError('Método no permitido. Usa GET.', 405);
}

$conn = conectar();

// Consulta para obtener las herramientas
// Cambia 'herramientas' por el nombre real de tu tabla en la BBDD
$query = "SELECT * FROM herramientas ORDER BY id_herramienta ASC";
$res = $conn->query($query);

if (!$res) {
    $conn->close();
    respuestaError('Error en la consulta de BD: ' . $conn->error, 500);
}

$herramientas = [];
while ($fila = $res->fetch_assoc()) {
    $herramientas[] = $fila;
}

$conn->close();

// Devolvemos el array en formato JSON al frontend
respuestaOk(['herramientas' => $herramientas]);