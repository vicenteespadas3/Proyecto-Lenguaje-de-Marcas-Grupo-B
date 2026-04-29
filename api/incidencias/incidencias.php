<?php
require_once '../config/helpers.php';
require_once '../config/db_config.php';

$conn = conectar();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $conn->prepare("SELECT i.*, m.nombre as maquina FROM incidencias i LEFT JOIN maquinas m ON i.id_maquina = m.id_maquina WHERE i.id_incidencia = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $inc = $res->fetch_assoc();
        $stmt->close();
        if ($inc) respuestaOk(['incidencia' => $inc]);
        else respuestaError('Incidencia no encontrada.', 404);
    } else {
        $query = "SELECT i.*, m.nombre as maquina FROM incidencias i LEFT JOIN maquinas m ON i.id_maquina = m.id_maquina ORDER BY i.fecha_reporte DESC";
        $res = $conn->query($query);
        $incidencias = [];
        while ($fila = $res->fetch_assoc()) $incidencias[] = $fila;
        respuestaOk(['incidencias' => $incidencias]);
    }
}

if ($metodo === 'POST') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $id_maquina = (int)($b['id_maquina'] ?? 0);
    $descripcion = trim($b['descripcion'] ?? '');
    $prioridad = trim($b['prioridad'] ?? 'Media');
    $tipo = trim($b['tipo'] ?? 'Avería');
    
    if ($id_maquina <= 0 || !$descripcion) respuestaError('Máquina y descripción son obligatorios.');

    $stmt = $conn->prepare("INSERT INTO incidencias (id_maquina, descripcion, prioridad, tipo, estado, fecha_reporte) VALUES (?, ?, ?, ?, 'abierta', NOW())");
    $stmt->bind_param("isss", $id_maquina, $descripcion, $prioridad, $tipo);
    if ($stmt->execute()) {
        respuestaOk(['msg' => 'Incidencia reportada.', 'id' => $stmt->insert_id]);
    } else {
        respuestaError('Error al crear incidencia.');
    }
    $stmt->close();
}

if ($metodo === 'PUT') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id_incidencia'] ?? 0);
    $estado = trim($b['estado'] ?? '');
    $solucion = trim($b['solucion_aplicada'] ?? '');

    if ($id <= 0 || !$estado) respuestaError('ID y estado obligatorios.');

    $stmt = $conn->prepare("UPDATE incidencias SET estado=?, solucion_aplicada=?, fecha_resolucion = IF(? = 'cerrada', NOW(), fecha_resolucion) WHERE id_incidencia=?");
    $stmt->bind_param("sssi", $estado, $solucion, $estado, $id);
    if ($stmt->execute()) respuestaOk(['msg' => 'Incidencia actualizada.']);
    else respuestaError('Error al actualizar.');
    $stmt->close();
}

if ($metodo === 'DELETE') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id_incidencia'] ?? 0);
    if ($id <= 0) respuestaError('ID no válido.');

    $stmt = $conn->prepare("DELETE FROM incidencias WHERE id_incidencia=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) respuestaOk(['msg' => 'Incidencia eliminada.']);
    else respuestaError('Error al eliminar.');
    $stmt->close();
}

$conn->close();
respuestaError('Método no permitido', 405);