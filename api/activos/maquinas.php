<?php
// ============================================================
//  api/maquinas.php  —  CRUD completo para Máquinas
// ============================================================
require_once '../config/helpers.php';
require_once '../config/db_config.php';

$conn = conectar();
$metodo = $_SERVER['REQUEST_METHOD'];

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
    $tipo = trim($b['tipo'] ?? '');
    $numero_serie = trim($b['numero_serie'] ?? '');
    $ubicacion = trim($b['ubicacion'] ?? '');
    $fabricante = trim($b['fabricante'] ?? '');
    $estado = trim($b['estado'] ?? 'disponible');
    $proveedor_id = !empty($b['proveedor_id']) ? (int)$b['proveedor_id'] : null;

    if (!$nombre) respuestaError('El nombre de la máquina es obligatorio.');

    $chk = $conn->prepare("SELECT id_maquina FROM maquinas WHERE numero_serie=? AND numero_serie != ''");
    $chk->bind_param("s", $numero_serie); $chk->execute(); $chk->store_result();
    if ($chk->num_rows > 0) { $chk->close(); $conn->close(); respuestaError('Ya existe una máquina con ese número de serie.'); }
    $chk->close();

    $stmt = $conn->prepare("INSERT INTO maquinas (nombre, tipo, numero_serie, ubicacion, fabricante, estado, proveedor_id, activa, fecha_registro) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
    $stmt->bind_param("ssssssi", $nombre, $tipo, $numero_serie, $ubicacion, $fabricante, $estado, $proveedor_id);
    
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
    $tipo = trim($b['tipo'] ?? '');
    $numero_serie = trim($b['numero_serie'] ?? '');
    $ubicacion = trim($b['ubicacion'] ?? '');
    $fabricante = trim($b['fabricante'] ?? '');
    $estado = trim($b['estado'] ?? 'disponible');
    $proveedor_id = !empty($b['proveedor_id']) ? (int)$b['proveedor_id'] : null;

    if ($id <= 0) respuestaError('ID de máquina no válido.');
    if (!$nombre) respuestaError('El nombre de la máquina es obligatorio.');

    $stmt = $conn->prepare("UPDATE maquinas SET nombre=?, tipo=?, numero_serie=?, ubicacion=?, fabricante=?, estado=?, proveedor_id=? WHERE id_maquina=?");
    $stmt->bind_param("ssssssii", $nombre, $tipo, $numero_serie, $ubicacion, $fabricante, $estado, $proveedor_id, $id);
    
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

    // Verificaciones de integridad (Garantizar que no haya dependencias activas)
    // 1. Mantenimiento
    $chk = $conn->prepare("SELECT estado FROM maquinas WHERE id_maquina=?");
    $chk->bind_param("i", $id); $chk->execute();
    $m_data = $chk->get_result()->fetch_assoc(); $chk->close();
    if ($m_data && $m_data['estado'] === 'en_mantenimiento') {
        respuestaError('No se puede eliminar una máquina en mantenimiento.');
    }

    // 2. Incidencias
    $chk2 = $conn->prepare("SELECT COUNT(*) as t FROM incidencias WHERE id_maquina=? AND estado != 'cerrada'");
    $chk2->bind_param("i", $id); $chk2->execute();
    if ($chk2->get_result()->fetch_assoc()['t'] > 0) {
        respuestaError('No se puede eliminar: la máquina tiene incidencias activas.');
    }
    $chk2->close();

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
