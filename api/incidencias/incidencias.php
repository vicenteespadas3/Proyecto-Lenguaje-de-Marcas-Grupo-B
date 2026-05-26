<?php
// ============================================================
//  api/incidencias/incidencias.php  —  CRUD para Incidencias
// ============================================================
require_once '../config/helpers.php';
require_once '../config/db_config.php';

$conn = conectar();
$m = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────
if ($m === 'GET') {
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $s = $conn->prepare("SELECT i.*, m.nombre as maquina 
                             FROM incidencias i 
                             LEFT JOIN maquinas m ON i.id_maquina = m.id_maquina 
                             WHERE i.id_incidencia = ?");
        $s->bind_param("i", $id);
        $s->execute();
        $f = $s->get_result()->fetch_assoc();
        $s->close();
        $conn->close();
        if (!$f) respuestaError('Incidencia no encontrada.', 404);
        respuestaOk(['incidencia' => $f]);
    } else {
        $buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
        
        if ($buscar !== '') {
            $s = $conn->prepare("SELECT i.*, m.nombre as maquina 
                                 FROM incidencias i 
                                 LEFT JOIN maquinas m ON i.id_maquina = m.id_maquina 
                                 WHERE i.descripcion LIKE ? OR m.nombre LIKE ? 
                                 ORDER BY i.fecha_reporte DESC, i.id_incidencia DESC");
            $like = "%" . $buscar . "%";
            $s->bind_param("ss", $like, $like);
            $s->execute();
            $r = $s->get_result();
            $lista = [];
            while ($f = $r->fetch_assoc()) $lista[] = $f;
            $s->close();
        } else {
            $query = "SELECT i.*, m.nombre as maquina 
                      FROM incidencias i 
                      LEFT JOIN maquinas m ON i.id_maquina = m.id_maquina 
                      ORDER BY i.fecha_reporte DESC, i.id_incidencia DESC";
            $res = $conn->query($query);
            $lista = [];
            if ($res) {
                while ($f = $res->fetch_assoc()) $lista[] = $f;
            }
        }
        $conn->close();
        respuestaOk(['incidencias' => $lista]);
    }
}

// ── POST ──────────────────────────────────────────────────────
if ($m === 'POST') {
    iniciarSesion();
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $id_maquina = (int)($b['id_maquina'] ?? 0);
    $descripcion = trim($b['descripcion_problema'] ?? ($b['descripcion'] ?? ''));
    $tipo = trim($b['tipo'] ?? 'Avería');
    $prioridad = trim($b['prioridad'] ?? 'Media');
    $estado = trim($b['estado'] ?? 'abierta');
    $id_usuario = $_SESSION['id_usuario'] ?? 1; // Fallback al admin si no hay sesión

    if ($id_maquina <= 0 || !$descripcion) {
        $conn->close();
        respuestaError('La máquina y la descripción son obligatorias.');
    }

    $stmt = $conn->prepare("INSERT INTO incidencias (id_maquina, id_usuario_reporta, descripcion, tipo, prioridad, estado, fecha_reporte) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iissss", $id_maquina, $id_usuario, $descripcion, $tipo, $prioridad, $estado);

    $ok = $stmt->execute();
    $nid = $conn->insert_id;
    $err = $stmt->error;
    $stmt->close();
    $conn->close();

    if ($ok) {
        respuestaOk(['msg' => 'Incidencia reportada con éxito', 'id' => $nid, 'id_incidencia' => $nid]);
    } else {
        respuestaError('Error al crear la incidencia: ' . $err, 500);
    }
}

// ── PUT ───────────────────────────────────────────────────────
if ($m === 'PUT') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id_incidencia'] ?? 0);

    if ($id <= 0) {
        $conn->close();
        respuestaError('ID de incidencia no válido.');
    }

    if (isset($b['id_maquina']) || isset($b['descripcion']) || isset($b['descripcion_problema'])) {
        // Modificación general de la incidencia
        $id_maquina = (int)($b['id_maquina'] ?? 0);
        $descripcion = trim($b['descripcion_problema'] ?? ($b['descripcion'] ?? ''));
        $prioridad = trim($b['prioridad'] ?? 'Media');
        $estado = trim($b['estado'] ?? 'abierta');
        $tipo = trim($b['tipo'] ?? 'Avería');

        $stmt = $conn->prepare("UPDATE incidencias SET id_maquina=?, descripcion=?, prioridad=?, estado=?, tipo=? WHERE id_incidencia=?");
        $stmt->bind_param("issssi", $id_maquina, $descripcion, $prioridad, $estado, $tipo, $id);
    } else {
        // Resolución de la incidencia (procedente del commit remoto)
        $estado = trim($b['estado'] ?? '');
        $solucion = trim($b['solucion_aplicada'] ?? '');

        if (!$estado) {
            $conn->close();
            respuestaError('El estado es obligatorio.');
        }

        $stmt = $conn->prepare("UPDATE incidencias SET estado=?, solucion_aplicada=?, fecha_resolucion = IF(? = 'cerrada', NOW(), fecha_resolucion) WHERE id_incidencia=?");
        $stmt->bind_param("sssi", $estado, $solucion, $estado, $id);
    }

    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();

    if ($ok) {
        respuestaOk(['msg' => 'Incidencia actualizada correctamente']);
    } else {
        respuestaError('Error al actualizar la incidencia.', 500);
    }
}

// ── DELETE ────────────────────────────────────────────────────
if ($m === 'DELETE') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id_incidencia'] ?? 0);

    if ($id <= 0) {
        $conn->close();
        respuestaError('ID de incidencia no válido.');
    }

    $stmt = $conn->prepare("DELETE FROM incidencias WHERE id_incidencia=?");
    $stmt->bind_param("i", $id);

    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();

    if ($ok) {
        respuestaOk(['msg' => 'Incidencia eliminada correctamente']);
    } else {
        respuestaError('Error al eliminar.', 500);
    }
}

$conn->close();
respuestaError('Método no permitido', 405);
