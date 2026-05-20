<?php
// ============================================================
//  api/mantenimiento/planes.php  —  CRUD para Planes de Mantenimiento
//
//  Estructura real de la tabla planes_mantenimiento:
//    id_plan, id_maquina, id_gama, id_frecuencia (FK definiciones),
//    horas_intervalo (nullable), id_dificultad (FK definiciones),
//    duracion_estimada (nullable, minutos), activo, fecha_creacion
//
//  La tabla 'definiciones' almacena frecuencias (categoria='planes_frecuencia')
//  y dificultades (categoria='planes_dificultad').
// ============================================================
require_once '../config/helpers.php';
require_once '../config/db_config.php';

$conn = conectar();
$m    = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────
if ($m === 'GET') {

    // Petición especial para cargar los selectores del formulario
    // Uso: ?definiciones=1  → devuelve frecuencias y dificultades
    if (isset($_GET['definiciones'])) {
        $frecuencias = [];
        $dificultades = [];

        $rF = $conn->prepare("SELECT id_definicion, descripcion FROM definiciones WHERE categoria='planes_frecuencia' AND activo=1 ORDER BY orden_visual ASC");
        $rF->execute();
        $resF = $rF->get_result();
        while ($f = $resF->fetch_assoc()) $frecuencias[] = $f;
        $rF->close();

        $rD = $conn->prepare("SELECT id_definicion, descripcion FROM definiciones WHERE categoria='planes_dificultad' AND activo=1 ORDER BY orden_visual ASC");
        $rD->execute();
        $resD = $rD->get_result();
        while ($d = $resD->fetch_assoc()) $dificultades[] = $d;
        $rD->close();

        $conn->close();
        respuestaOk(['frecuencias' => $frecuencias, 'dificultades' => $dificultades]);
    }

    // GET por ID concreto (ej: ?id=5)
    if (isset($_GET['id'])) {
        $id  = (int)$_GET['id'];
        $sql = "SELECT p.*,
                       m.nombre  AS nombre_maquina,
                       g.nombre  AS nombre_gama,
                       df.descripcion AS desc_frecuencia,
                       dd.descripcion AS desc_dificultad
                FROM planes_mantenimiento p
                LEFT JOIN maquinas            m  ON m.id_maquina   = p.id_maquina
                LEFT JOIN gamas_mantenimiento g  ON g.id_gama      = p.id_gama
                LEFT JOIN definiciones        df ON df.id_definicion = p.id_frecuencia
                LEFT JOIN definiciones        dd ON dd.id_definicion = p.id_dificultad
                WHERE p.id_plan = ?";
        $s   = $conn->prepare($sql);
        $s->bind_param("i", $id);
        $s->execute();
        $f   = $s->get_result()->fetch_assoc();
        $s->close();
        $conn->close();
        if (!$f) respuestaError('Plan no encontrado.', 404);
        respuestaOk(['plan' => $f]);
    }

    // GET listado (con búsqueda opcional: ?buscar=texto)
    $buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

    $base_sql = "SELECT p.*,
                        m.nombre  AS nombre_maquina,
                        g.nombre  AS nombre_gama,
                        df.descripcion AS desc_frecuencia,
                        dd.descripcion AS desc_dificultad
                 FROM planes_mantenimiento p
                 LEFT JOIN maquinas            m  ON m.id_maquina    = p.id_maquina
                 LEFT JOIN gamas_mantenimiento g  ON g.id_gama       = p.id_gama
                 LEFT JOIN definiciones        df ON df.id_definicion = p.id_frecuencia
                 LEFT JOIN definiciones        dd ON dd.id_definicion = p.id_dificultad";

    if ($buscar !== '') {
        // Búsqueda por ID del plan, nombre de máquina, nombre de gama o frecuencia
        $sql  = $base_sql . " WHERE p.id_plan LIKE ? OR m.nombre LIKE ? OR g.nombre LIKE ? OR df.descripcion LIKE ? ORDER BY p.id_plan ASC";
        $like = "%" . $buscar . "%";
        $s    = $conn->prepare($sql);
        $s->bind_param("ssss", $like, $like, $like, $like);
        $s->execute();
        $r = $s->get_result();
        $s->close();
    } else {
        $r = $conn->query($base_sql . " ORDER BY p.id_plan ASC");
    }

    $lista = [];
    if ($r) {
        while ($f = $r->fetch_assoc()) $lista[] = $f;
    }
    $conn->close();
    respuestaOk(['planes' => $lista, 'total' => count($lista)]);
}

// ── POST (Alta) ───────────────────────────────────────────────
if ($m === 'POST') {
    $b                 = json_decode(file_get_contents('php://input'), true) ?? [];
    $id_maquina        = (int)($b['id_maquina']        ?? 0);
    $id_gama           = (int)($b['id_gama']           ?? 0);
    $id_frecuencia     = (int)($b['id_frecuencia']     ?? 0);
    $horas_intervalo   = isset($b['horas_intervalo'])  && $b['horas_intervalo'] !== null && $b['horas_intervalo'] !== '' ? (int)$b['horas_intervalo'] : null;
    $id_dificultad     = (int)($b['id_dificultad']     ?? 0);
    $duracion_estimada = isset($b['duracion_estimada']) && $b['duracion_estimada'] !== null && $b['duracion_estimada'] !== '' ? (int)$b['duracion_estimada'] : null;
    $activo            = isset($b['activo']) ? (int)$b['activo'] : 1;

    // Validaciones
    if ($id_maquina    <= 0) respuestaError('Debes indicar una máquina válida.');
    if ($id_gama       <= 0) respuestaError('Debes indicar una gama de mantenimiento válida.');
    if ($id_frecuencia <= 0) respuestaError('Debes indicar una frecuencia válida.');
    if ($id_dificultad <= 0) respuestaError('Debes indicar un nivel de dificultad válido.');

    // Verificar existencia de FK
    $chkM = $conn->prepare("SELECT id_maquina FROM maquinas WHERE id_maquina=?");
    $chkM->bind_param("i", $id_maquina); $chkM->execute(); $chkM->store_result();
    if ($chkM->num_rows === 0) { $chkM->close(); $conn->close(); respuestaError('La máquina indicada no existe.', 404); }
    $chkM->close();

    $chkG = $conn->prepare("SELECT id_gama FROM gamas_mantenimiento WHERE id_gama=?");
    $chkG->bind_param("i", $id_gama); $chkG->execute(); $chkG->store_result();
    if ($chkG->num_rows === 0) { $chkG->close(); $conn->close(); respuestaError('La gama indicada no existe.', 404); }
    $chkG->close();

    $chkF = $conn->prepare("SELECT id_definicion FROM definiciones WHERE id_definicion=? AND categoria='planes_frecuencia'");
    $chkF->bind_param("i", $id_frecuencia); $chkF->execute(); $chkF->store_result();
    if ($chkF->num_rows === 0) { $chkF->close(); $conn->close(); respuestaError('La frecuencia indicada no es válida.', 404); }
    $chkF->close();

    $chkD = $conn->prepare("SELECT id_definicion FROM definiciones WHERE id_definicion=? AND categoria='planes_dificultad'");
    $chkD->bind_param("i", $id_dificultad); $chkD->execute(); $chkD->store_result();
    if ($chkD->num_rows === 0) { $chkD->close(); $conn->close(); respuestaError('El nivel de dificultad indicado no es válido.', 404); }
    $chkD->close();

    // Insertar
    $s = $conn->prepare(
        "INSERT INTO planes_mantenimiento (id_maquina, id_gama, id_frecuencia, horas_intervalo, id_dificultad, duracion_estimada, activo)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $s->bind_param("iiiiiii", $id_maquina, $id_gama, $id_frecuencia, $horas_intervalo, $id_dificultad, $duracion_estimada, $activo);
    $ok       = $s->execute();
    $db_error = $s->error;
    $nid      = $conn->insert_id;
    $s->close();
    $conn->close();

    if (!$ok) respuestaError('Error al crear el plan: ' . $db_error, 500);
    respuestaOk(['msg' => 'Plan de mantenimiento creado correctamente.', 'id_plan' => $nid]);
}

// ── PUT (Modificación) ────────────────────────────────────────
if ($m === 'PUT') {
    $b                 = json_decode(file_get_contents('php://input'), true) ?? [];
    $id_plan           = (int)($b['id_plan']           ?? 0);
    $id_maquina        = (int)($b['id_maquina']        ?? 0);
    $id_gama           = (int)($b['id_gama']           ?? 0);
    $id_frecuencia     = (int)($b['id_frecuencia']     ?? 0);
    $horas_intervalo   = isset($b['horas_intervalo'])  && $b['horas_intervalo'] !== null && $b['horas_intervalo'] !== '' ? (int)$b['horas_intervalo'] : null;
    $id_dificultad     = (int)($b['id_dificultad']     ?? 0);
    $duracion_estimada = isset($b['duracion_estimada']) && $b['duracion_estimada'] !== null && $b['duracion_estimada'] !== '' ? (int)$b['duracion_estimada'] : null;
    $activo            = isset($b['activo']) ? (int)$b['activo'] : 1;

    // Validaciones
    if ($id_plan       <= 0) respuestaError('ID de plan no válido.');
    if ($id_maquina    <= 0) respuestaError('Debes indicar una máquina válida.');
    if ($id_gama       <= 0) respuestaError('Debes indicar una gama de mantenimiento válida.');
    if ($id_frecuencia <= 0) respuestaError('Debes indicar una frecuencia válida.');
    if ($id_dificultad <= 0) respuestaError('Debes indicar un nivel de dificultad válido.');

    // Verificar que el plan existe
    $chkP = $conn->prepare("SELECT id_plan FROM planes_mantenimiento WHERE id_plan=?");
    $chkP->bind_param("i", $id_plan); $chkP->execute(); $chkP->store_result();
    if ($chkP->num_rows === 0) { $chkP->close(); $conn->close(); respuestaError('Plan no encontrado.', 404); }
    $chkP->close();

    // Verificar FK
    $chkM = $conn->prepare("SELECT id_maquina FROM maquinas WHERE id_maquina=?");
    $chkM->bind_param("i", $id_maquina); $chkM->execute(); $chkM->store_result();
    if ($chkM->num_rows === 0) { $chkM->close(); $conn->close(); respuestaError('La máquina indicada no existe.', 404); }
    $chkM->close();

    $chkG = $conn->prepare("SELECT id_gama FROM gamas_mantenimiento WHERE id_gama=?");
    $chkG->bind_param("i", $id_gama); $chkG->execute(); $chkG->store_result();
    if ($chkG->num_rows === 0) { $chkG->close(); $conn->close(); respuestaError('La gama indicada no existe.', 404); }
    $chkG->close();

    $chkF = $conn->prepare("SELECT id_definicion FROM definiciones WHERE id_definicion=? AND categoria='planes_frecuencia'");
    $chkF->bind_param("i", $id_frecuencia); $chkF->execute(); $chkF->store_result();
    if ($chkF->num_rows === 0) { $chkF->close(); $conn->close(); respuestaError('La frecuencia indicada no es válida.', 404); }
    $chkF->close();

    $chkD = $conn->prepare("SELECT id_definicion FROM definiciones WHERE id_definicion=? AND categoria='planes_dificultad'");
    $chkD->bind_param("i", $id_dificultad); $chkD->execute(); $chkD->store_result();
    if ($chkD->num_rows === 0) { $chkD->close(); $conn->close(); respuestaError('El nivel de dificultad indicado no es válido.', 404); }
    $chkD->close();

    // Actualizar
    $s = $conn->prepare(
        "UPDATE planes_mantenimiento
         SET id_maquina=?, id_gama=?, id_frecuencia=?, horas_intervalo=?, id_dificultad=?, duracion_estimada=?, activo=?
         WHERE id_plan=?"
    );
    $s->bind_param("iiiiiiii", $id_maquina, $id_gama, $id_frecuencia, $horas_intervalo, $id_dificultad, $duracion_estimada, $activo, $id_plan);
    $ok       = $s->execute();
    $db_error = $s->error;
    $s->close();
    $conn->close();

    if (!$ok) respuestaError('Error al actualizar el plan: ' . $db_error, 500);
    respuestaOk(['msg' => 'Plan actualizado correctamente.']);
}

// ── DELETE (Baja) ─────────────────────────────────────────────
if ($m === 'DELETE') {
    $b       = json_decode(file_get_contents('php://input'), true) ?? [];
    $id_plan = (int)($b['id_plan'] ?? 0);

    if ($id_plan <= 0) respuestaError('ID de plan no válido.');

    // Verificar que el plan existe
    $chk = $conn->prepare("SELECT id_plan FROM planes_mantenimiento WHERE id_plan=?");
    $chk->bind_param("i", $id_plan); $chk->execute(); $chk->store_result();
    if ($chk->num_rows === 0) { $chk->close(); $conn->close(); respuestaError('Plan no encontrado.', 404); }
    $chk->close();

    // Verificar dependencias: órdenes de trabajo activas con este plan
    $chkOT = $conn->prepare("SELECT COUNT(*) AS t FROM ordenes_trabajo WHERE id_plan=?");
    $chkOT->bind_param("i", $id_plan); $chkOT->execute();
    $total = $chkOT->get_result()->fetch_assoc()['t'];
    $chkOT->close();
    if ($total > 0) {
        $conn->close();
        respuestaError("No se puede eliminar: el plan tiene $total orden(es) de trabajo asociada(s). Elimínalas o reasígnalas primero.");
    }

    // Eliminar
    $s = $conn->prepare("DELETE FROM planes_mantenimiento WHERE id_plan=?");
    $s->bind_param("i", $id_plan);
    $ok       = $s->execute();
    $db_error = $s->error;
    $s->close();
    $conn->close();

    if (!$ok) respuestaError('Error al eliminar el plan: ' . $db_error, 500);
    respuestaOk(['msg' => 'Plan de mantenimiento eliminado correctamente.']);
}

// Método no soportado
respuestaError('Método no soportado.', 405);
