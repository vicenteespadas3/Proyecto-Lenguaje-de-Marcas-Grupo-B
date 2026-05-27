<?php
/**
 * PROYECTO: ERP Robots - Sistema de Gestión
 * AUTORA: Guadalupe MGM
 * DESCRIPCIÓN: Gestión de cuadrantes y turnos de trabajo semanales.
 * * USO TÉCNICO:
 * - Maneja la disponibilidad de lunes a domingo y rangos de horas (inicio/fin).
 */

// ============================================================
//  api/horarioTrabajo.php  —  Gestión de turnos y cuadrantes
// ============================================================
// Referencia en la web:
//   - Pantalla "Gestión de Cuadrantes y Turnos" (HTML/administracionMenu/horarioTrabajo.html)
require_once '../config/helpers.php';
require_once '../config/db_config.php';

$conn = conectar();
$m = $_SERVER['REQUEST_METHOD'];

// ── GET: Listar los turnos configurados ───────────────────────
if ($m === 'GET') {
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $s = $conn->prepare("SELECT * FROM horarios_trabajo WHERE id_horario = ?");
        $s->bind_param("i", $id);
        $s->execute();
        $f = $s->get_result()->fetch_assoc();
        $s->close();
        if (!$f) respuestaError('Horario no encontrado.', 404);
        respuestaOk(['horario' => $f]);
    } else {
        $r = $conn->query("SELECT * FROM horarios_trabajo ORDER BY nombre ASC");
        $lista = [];
        while ($f = $r->fetch_assoc()) { 
            // Convertimos los 0 y 1 de los días a booleanos para el JS
            $f['lunes'] = (bool)$f['lunes'];
            $f['martes'] = (bool)$f['martes'];
            $f['miercoles'] = (bool)$f['miercoles'];
            $f['jueves'] = (bool)$f['jueves'];
            $f['viernes'] = (bool)$f['viernes'];
            $f['sabado'] = (bool)$f['sabado'];
            $f['domingo'] = (bool)$f['domingo'];
            $lista[] = $f; 
        }
        $conn->close();
        respuestaOk(['horarios' => $lista]);
    }
}

// ── POST: Crear un nuevo turno ────────────────────────────────
if ($m === 'POST') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $nombre = trim($b['nombre'] ?? '');
    $inicio = $b['hora_inicio'] ?? '08:00:00';
    $fin    = $b['hora_fin']    ?? '16:00:00';
    // Días de la semana (0 o 1)
    $l = (int)($b['lunes'] ?? 0);
    $m_val = (int)($b['martes'] ?? 0);
    $x = (int)($b['miercoles'] ?? 0);
    $j = (int)($b['jueves'] ?? 0);
    $v = (int)($b['viernes'] ?? 0);
    $s_val = (int)($b['sabado'] ?? 0);
    $d = (int)($b['domingo'] ?? 0);

    if (!$nombre) respuestaError('El nombre del horario es obligatorio.');

    // Verificar si ya existe un horario con el mismo nombre
    $chk = $conn->prepare("SELECT id_horario FROM horarios_trabajo WHERE nombre=?");
    $chk->bind_param("s", $nombre); $chk->execute(); $chk->store_result();
    if ($chk->num_rows > 0) { $chk->close(); $conn->close(); respuestaError('Ya existe un horario con ese nombre.'); }
    $chk->close();

    $s = $conn->prepare("INSERT INTO horarios_trabajo (nombre, hora_inicio, hora_fin, lunes, martes, miercoles, jueves, viernes, sabado, domingo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $s->bind_param("sssiiiiiii", $nombre, $inicio, $fin, $l, $m_val, $x, $j, $v, $s_val, $d);
    
    $ok = $s->execute();
    $nid = $conn->insert_id;
    $s->close(); $conn->close();

    if (!$ok) respuestaError('Error al guardar el horario.', 500);
    respuestaOk(['msg' => "Horario '$nombre' creado correctamente.", 'id' => $nid]);
}

// ── DELETE: Borrar un turno ───────────────────────────────────
if ($m === 'DELETE') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id_horario'] ?? 0);

    if ($id <= 0) respuestaError('ID no válido.');

    $s = $conn->prepare("DELETE FROM horarios_trabajo WHERE id_horario = ?");
    $s->bind_param("i", $id);
    $ok = $s->execute();
    $s->close(); $conn->close();

    if (!$ok) respuestaError('Error al eliminar el horario.', 500);
    respuestaOk(['msg' => 'Horario eliminado.']);
}

respuestaError('Método no soportado.', 405);