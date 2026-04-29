<?php
// ============================================================
//  api/maquinas.php  —  CRUD completo para Máquinas
// ============================================================
require_once '../config/helpers.php';
require_once '../config/db_config.php';

$conn = conectar();
$metodo = $_SERVER['REQUEST_METHOD'];

// Autocrear tabla si no existe
/**
 * Crea la tabla maquinas si no existe en la base de datos.
 * Define la estructura básica para almacenar información sobre las máquinas.
 */
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
        /**
         * Obtiene los datos de una máquina específica filtrando por su id_maquina.
         */
        $stmt = $conn->prepare("SELECT * FROM maquinas WHERE id_maquina = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $maquina = $res->fetch_assoc();
        $stmt->close();
        if ($maquina) respuestaOk(['maquina' => $maquina]);
        else respuestaError('Máquina no encontrada.', 404);
    } else {
        /**
         * Obtiene una lista de todas las máquinas registradas, ordenadas de la más reciente a la más antigua.
         */
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

    // Verificar si ya existe una máquina con el mismo nombre
    $chk = $conn->prepare("SELECT id_maquina FROM maquinas WHERE nombre=?");
    $chk->bind_param("s", $nombre); $chk->execute(); $chk->store_result();
    if ($chk->num_rows > 0) { $chk->close(); $conn->close(); respuestaError('Ya existe una máquina con ese nombre.'); }
    $chk->close();

    /**
     * Inserta un nuevo registro de máquina en la base de datos con los datos proporcionados.
     */
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

    // Verificar si ya existe otra máquina con el mismo nombre (excluyendo la actual)
    $chk = $conn->prepare("SELECT id_maquina FROM maquinas WHERE nombre=? AND id_maquina!=?");
    $chk->bind_param("si", $nombre, $id); $chk->execute(); $chk->store_result();
    if ($chk->num_rows > 0) { $chk->close(); $conn->close(); respuestaError('Ya existe otra máquina con ese nombre.'); }
    $chk->close();

    /**
     * Actualiza la información de una máquina existente según su id_maquina.
     */
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

    // Regla 1: No se puede eliminar una máquina que está "En Mantenimiento"
    $chk = $conn->prepare("SELECT estado FROM maquinas WHERE id_maquina=?");
    $chk->bind_param("i", $id); $chk->execute();
    $res_chk = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$res_chk) { $conn->close(); respuestaError('Máquina no encontrada.', 404); }
    if ($res_chk['estado'] === 'En Mantenimiento') {
        $conn->close();
        respuestaError('No se puede eliminar una máquina que está en mantenimiento. Cambie su estado primero.');
    }

    // Regla 2: No se puede eliminar si tiene piezas asignadas
    $chk2 = $conn->prepare("SELECT COUNT(*) as total FROM maquinas_piezas WHERE id_maquina=?");
    $chk2->bind_param("i", $id); $chk2->execute();
    $total_piezas = $chk2->get_result()->fetch_assoc()['total'];
    $chk2->close();
    if ($total_piezas > 0) {
        $conn->close();
        respuestaError("No se puede eliminar: la máquina tiene $total_piezas pieza(s) asignada(s). Desasígnelas primero.");
    }

    // Regla 3: No se puede eliminar si tiene archivos vinculados
    $chk3 = $conn->prepare("SELECT COUNT(*) as total FROM archivos_adjuntos WHERE id_referencia=? AND tipo_referencia='maquina'");
    $chk3->bind_param("i", $id); $chk3->execute();
    $total_archivos = $chk3->get_result()->fetch_assoc()['total'];
    $chk3->close();
    if ($total_archivos > 0) {
        $conn->close();
        respuestaError("No se puede eliminar: la máquina tiene $total_archivos archivo(s) vinculado(s). Elimínelos primero.");
    }

    // Regla 4: No se puede eliminar si tiene incidencias abiertas
    $chk4 = $conn->prepare("SELECT COUNT(*) as total FROM incidencias WHERE id_maquina=? AND estado IN ('Abierta', 'En Proceso')");
    $chk4->bind_param("i", $id); $chk4->execute();
    $total_inc = $chk4->get_result()->fetch_assoc()['total'];
    $chk4->close();
    if ($total_inc > 0) {
        $conn->close();
        respuestaError("No se puede eliminar: la máquina tiene $total_inc incidencia(s) abierta(s). Resuélvalas primero.");
    }

    /**
     * Elimina el registro de una máquina de la base de datos basándose en su id_maquina.
     */
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
