<?php
// ============================================================
//  api/maquinas.php  —  CRUD completo para Máquinas
// ============================================================
require_once '../config/helpers.php';
require_once '../config/db_config.php';

$conn = conectar();
$metodo = $_SERVER['REQUEST_METHOD'];

// Autocrear tabla si no existe
$conn->query("CREATE TABLE IF NOT EXISTS maquinas (
    id_maquina INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    estado VARCHAR(50) DEFAULT 'Activa',
    ubicacion VARCHAR(255) DEFAULT 'Taller Principal',
    ultima_revision DATE NULL
)");

// ── GET (Listar o Buscar) ───────────────────────────────────
if ($metodo === 'GET') {
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM maquinas WHERE id_maquina = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $maquina = $res->fetch_assoc();
        $stmt->close();
        if ($maquina) respuestaOk(['maquina' => $maquina]);
        else respuestaError('Máquina no encontrada.', 404);
    } else {
        $res = $conn->query("SELECT * FROM maquinas ORDER BY id_maquina DESC");
        if (!$res) respuestaError('Error BD: ' . $conn->error, 500);
        $maquinas = [];
        while ($f = $res->fetch_assoc()) $maquinas[] = $f;
        respuestaOk(['maquinas' => $maquinas]);
    }
}

// ── POST (Alta) ──────────────────────────────────────────────
if ($metodo === 'POST') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $nombre = trim($b['nombre'] ?? '');
    $estado = trim($b['estado'] ?? 'Activa');
    $ubicacion = trim($b['ubicacion'] ?? '');
    $ultima_revision = trim($b['ultima_revision'] ?? date('Y-m-d'));

    if (!$nombre) respuestaError('El nombre de la máquina es obligatorio.');

    $stmt = $conn->prepare("INSERT INTO maquinas (nombre, estado, ubicacion, ultima_revision) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $nombre, $estado, $ubicacion, $ultima_revision);
    
    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        $stmt->close();
        respuestaOk(['msg' => 'Máquina creada con éxito.', 'id_maquina' => $id]);
    } else {
        respuestaError('Error al crear: ' . $stmt->error, 500);
    }
}

// ── PUT (Modificación) ───────────────────────────────────────
if ($metodo === 'PUT') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id_maquina'] ?? 0);
    $nombre = trim($b['nombre'] ?? '');
    $estado = trim($b['estado'] ?? 'Activa');
    $ubicacion = trim($b['ubicacion'] ?? '');
    $ultima_revision = trim($b['ultima_revision'] ?? date('Y-m-d'));

    if ($id <= 0) respuestaError('ID de máquina no válido.');
    if (!$nombre) respuestaError('El nombre de la máquina es obligatorio.');

    $stmt = $conn->prepare("UPDATE maquinas SET nombre=?, estado=?, ubicacion=?, ultima_revision=? WHERE id_maquina=?");
    $stmt->bind_param("ssssi", $nombre, $estado, $ubicacion, $ultima_revision, $id);
    
    if ($stmt->execute()) {
        $stmt->close();
        respuestaOk(['msg' => 'Máquina actualizada correctamente.']);
    } else {
        respuestaError('Error al actualizar: ' . $stmt->error, 500);
    }
}

// ── DELETE (Baja) ────────────────────────────────────────────
if ($metodo === 'DELETE') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id_maquina'] ?? 0);

    if ($id <= 0) respuestaError('ID de máquina no válido.');

    $stmt = $conn->prepare("DELETE FROM maquinas WHERE id_maquina=?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $stmt->close();
        respuestaOk(['msg' => 'Máquina eliminada correctamente.']);
    } else {
        respuestaError('Error al eliminar: ' . $stmt->error, 500);
    }
}

$conn->close();
respuestaError('Método no soportado.', 405);
