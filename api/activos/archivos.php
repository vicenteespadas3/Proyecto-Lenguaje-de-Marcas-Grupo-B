<?php
require_once '../config/helpers.php';
require_once '../config/db_config.php';

$conn = conectar();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $conn->prepare("SELECT a.id_archivo, a.nombre_archivo, a.ruta_archivo as tipo, a.id_referencia as id_maquina, a.fecha_subida, m.nombre as maquina FROM archivos_adjuntos a LEFT JOIN maquinas m ON a.id_referencia = m.id_maquina WHERE a.id_archivo = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $archivo = $res->fetch_assoc();
        $stmt->close();
        if ($archivo) respuestaOk(['archivo' => $archivo]);
        else respuestaError('Archivo no encontrado.', 404);
    } else {
        $res = $conn->query("SELECT a.id_archivo, a.nombre_archivo, a.ruta_archivo as tipo, a.id_referencia as id_maquina, a.fecha_subida, m.nombre as maquina FROM archivos_adjuntos a LEFT JOIN maquinas m ON a.id_referencia = m.id_maquina ORDER BY a.id_archivo DESC");
        if (!$res) respuestaError('Error BD: ' . $conn->error, 500);
        $archivos = [];
        while ($f = $res->fetch_assoc()) $archivos[] = $f;
        respuestaOk(['archivos' => $archivos]);
    }
}

if ($metodo === 'POST') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $nombre = trim($b['nombre_archivo'] ?? '');
    $tipo = trim($b['tipo'] ?? 'PDF');
    $id_maquina = !empty($b['id_maquina']) ? (int)$b['id_maquina'] : null;
    $fecha = trim($b['fecha_subida'] ?? date('Y-m-d'));

    if (!$nombre) respuestaError('El nombre del archivo es obligatorio.');

    $stmt = $conn->prepare("INSERT INTO archivos_adjuntos (nombre_archivo, ruta_archivo, id_referencia, tipo_referencia, fecha_subida) VALUES (?, ?, ?, 'maquina', ?)");
    $stmt->bind_param("ssis", $nombre, $tipo, $id_maquina, $fecha);
    
    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        $stmt->close();
        respuestaOk(['msg' => 'Archivo registrado con éxito.', 'id_archivo' => $id]);
    } else {
        respuestaError('Error al crear: ' . $stmt->error, 500);
    }
}

if ($metodo === 'PUT') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id_archivo'] ?? 0);
    $nombre = trim($b['nombre_archivo'] ?? '');
    $tipo = trim($b['tipo'] ?? 'PDF');
    $id_maquina = !empty($b['id_maquina']) ? (int)$b['id_maquina'] : null;

    if ($id <= 0) respuestaError('ID no válido.');
    if (!$nombre) respuestaError('El nombre del archivo es obligatorio.');

    $stmt = $conn->prepare("UPDATE archivos_adjuntos SET nombre_archivo=?, ruta_archivo=?, id_referencia=? WHERE id_archivo=?");
    $stmt->bind_param("ssii", $nombre, $tipo, $id_maquina, $id);
    
    if ($stmt->execute()) {
        $stmt->close();
        respuestaOk(['msg' => 'Archivo actualizado.']);
    } else {
        respuestaError('Error al actualizar: ' . $stmt->error, 500);
    }
}

if ($metodo === 'DELETE') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id_archivo'] ?? 0);

    if ($id <= 0) respuestaError('ID no válido.');

    $stmt = $conn->prepare("DELETE FROM archivos_adjuntos WHERE id_archivo=?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $stmt->close();
        respuestaOk(['msg' => 'Archivo eliminado.']);
    } else {
        respuestaError('Error al eliminar: ' . $stmt->error, 500);
    }
}

$conn->close();
respuestaError('Método no soportado.', 405);
