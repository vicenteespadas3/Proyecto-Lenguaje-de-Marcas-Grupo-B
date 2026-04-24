<?php
// ============================================================
//  api/piezas.php — GET /api/piezas.php
//  Obtiene el inventario de piezas desde la base de datos
// ============================================================
require_once '../config/helpers.php';
require_once '../config/db_config.php';

// Validamos que la petición sea correcta
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respuestaError('Método no permitido. Usa GET.', 405);
}

$conn = conectar(); // Conectamos a la BD usando la configuración central

// Seleccionamos todas las piezas de la tabla
$query = "SELECT * FROM piezas_repuesto ORDER BY id_pieza ASC";
$res = $conn->query($query);

if (!$res) {
    // Si la consulta falla (tabla mal nombrada, etc.)
    $conn->close();
    respuestaError('Error en la consulta de BD: ' . $conn->error, 500);
}

// Convertimos el resultado en un array asociativo
$piezas = [];
while ($fila = $res->fetch_assoc()) {
    $piezas[] = $fila;
}

$conn->close();

// Devolvemos la respuesta al frontend en formato JSON
respuestaOk(['piezas' => $piezas]);