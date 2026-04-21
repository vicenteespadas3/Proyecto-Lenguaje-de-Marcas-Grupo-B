<?php
// ============================================================
//  api/maquinas.php  —  GET /api/maquinas.php
//  Obtiene la lista de máquinas desde la base de datos
// ============================================================
require_once '../config/helpers.php';
require_once '../config/db_config.php';

// Solo aceptamos peticiones GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respuestaError('Método no permitido. Usa GET.', 405);
}

$conn = conectar(); // Si la BD está apagada, esto devuelve 500 automáticamente gracias a db_config.php

// Seleccionamos las máquinas (ordenadas por la más reciente primero)
// Ajustamos la consulta para ser tolerante a errores si la tabla aún no existe
$query = "SELECT * FROM maquinas ORDER BY id_maquina DESC";
$res = $conn->query($query);

if (!$res) {
    // Si falla la consulta (ej. tabla no existe)
    $conn->close();
    respuestaError('Error en la consulta de BD: ' . $conn->error, 500);
}

$maquinas = [];
while ($fila = $res->fetch_assoc()) {
    $maquinas[] = $fila;
}

$conn->close();

// Devolvemos el array en formato JSON al frontend
respuestaOk(['maquinas' => $maquinas]);
