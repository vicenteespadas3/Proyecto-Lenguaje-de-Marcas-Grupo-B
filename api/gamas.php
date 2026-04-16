<?php
// ============================================================
//  api/gamas.php  —  CRUD para Gamas de Mantenimiento
// ============================================================
require_once 'helpers.php';
require_once 'db_config.php';

// Descomentar la siguiente línea si se requiere autenticación para este endpoint
// $yo = requireAuth();

$conn = conectar();
$m    = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────
if ($m === 'GET') {
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $s  = $conn->prepare("SELECT id_gama, nombre, tipo_mantenimiento, tipo_gama FROM gamas WHERE id_gama=?");
        $s->bind_param("i", $id); 
        $s->execute();
        $f  = $s->get_result()->fetch_assoc(); 
        $s->close(); 
        $conn->close();
        if (!$f) respuestaError('Gama no encontrada.', 404);
        respuestaOk(['gama' => $f]);
    } else {
        $r = $conn->query("SELECT id_gama, nombre, tipo_mantenimiento, tipo_gama FROM gamas ORDER BY nombre");
        $lista = [];
        if ($r) {
            while ($f = $r->fetch_assoc()) { 
                $lista[] = $f; 
            }
        }
        $conn->close();
        respuestaOk(['gamas' => $lista, 'total' => count($lista)]);
    }
}

// ── POST (alta) ───────────────────────────────────────────────
if ($m === 'POST') {
    $b                  = json_decode(file_get_contents('php://input'), true) ?? [];
    $nombre             = trim($b['nombre'] ?? '');
    $tipo_mantenimiento = trim($b['tipo_mantenimiento'] ?? 'preventivo');
    $tipo_gama          = trim($b['tipo_gama'] ?? 'mecanica');

    if (!$nombre) respuestaError('El nombre de la gama es obligatorio.');

    $s = $conn->prepare("INSERT INTO gamas (nombre, tipo_mantenimiento, tipo_gama) VALUES (?, ?, ?)");
    $s->bind_param("sss", $nombre, $tipo_mantenimiento, $tipo_gama);
    $ok = $s->execute(); 
    $nid = $conn->insert_id; 
    $s->close(); 
    $conn->close();
    if (!$ok) respuestaError('Error al crear la gama.', 500);
    respuestaOk(['msg' => "Gama '$nombre' creada.", 'id_gama' => $nid]);
}

// ── PUT (modificación) ────────────────────────────────────────
if ($m === 'PUT') {
    $b                  = json_decode(file_get_contents('php://input'), true) ?? [];
    $id_gama            = (int)($b['id_gama'] ?? 0);
    $nombre             = trim($b['nombre'] ?? '');
    $tipo_mantenimiento = trim($b['tipo_mantenimiento'] ?? 'preventivo');
    $tipo_gama          = trim($b['tipo_gama'] ?? 'mecanica');

    if ($id_gama <= 0) respuestaError('ID de gama no válido.');
    if (!$nombre) respuestaError('El nombre de la gama es obligatorio.');

    $chk = $conn->prepare("SELECT id_gama FROM gamas WHERE id_gama=?");
    $chk->bind_param("i", $id_gama); 
    $chk->execute(); 
    $chk->store_result();
    if ($chk->num_rows === 0) { 
        $chk->close(); 
        $conn->close(); 
        respuestaError('Gama no encontrada.', 404); 
    }
    $chk->close();

    $s = $conn->prepare("UPDATE gamas SET nombre=?, tipo_mantenimiento=?, tipo_gama=? WHERE id_gama=?");
    $s->bind_param("sssi", $nombre, $tipo_mantenimiento, $tipo_gama, $id_gama);
    $ok = $s->execute(); 
    $s->close(); 
    $conn->close();
    if (!$ok) respuestaError('Error al modificar la gama.', 500);
    respuestaOk(['msg' => 'Gama actualizada correctamente.']);
}

// ── DELETE (baja) ─────────────────────────────────────────────
if ($m === 'DELETE') {
    $b       = json_decode(file_get_contents('php://input'), true) ?? [];
    $id_gama = (int)($b['id_gama'] ?? 0);
    if ($id_gama <= 0) respuestaError('ID de gama no válido.');

    $s = $conn->prepare("DELETE FROM gamas WHERE id_gama=?");
    $s->bind_param("i", $id_gama); 
    $ok = $s->execute(); 
    $s->close(); 
    $conn->close();
    if (!$ok) respuestaError('Error al eliminar la gama.', 500);
    respuestaOk(['msg' => "Gama eliminada correctamente."]);
}

respuestaError('Método no soportado.', 405);
