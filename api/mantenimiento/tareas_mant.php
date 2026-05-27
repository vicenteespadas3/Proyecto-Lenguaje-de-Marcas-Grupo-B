<?php
// ============================================================
//  api/mantenimiento/tareas_mant.php  —  CRUD para Tareas de Mantenimiento
//
//  Estructura real de la tabla tareas_mantenimiento:
//    id_tarea, id_plan (FK), descripcion, orden,
//    instrucciones_detalladas, requiere_epi (bool),
//    epis_necesarios, reglas_seguridad, reglas_medio_ambiente
// ============================================================
require_once '../config/helpers.php';
require_once '../config/db_config.php';

$conn = conectar();
$m    = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────
if ($m === 'GET') {

    // Petición especial para cargar el selector de planes del formulario
    // Uso: ?planes=1  → devuelve id_plan + nombre_maquina + nombre_gama
    if (isset($_GET['planes'])) {
        $sql = "SELECT p.id_plan,
                       m.nombre AS nombre_maquina,
                       g.nombre AS nombre_gama
                FROM planes_mantenimiento p
                LEFT JOIN maquinas            m ON m.id_maquina = p.id_maquina
                LEFT JOIN gamas_mantenimiento g ON g.id_gama    = p.id_gama
                WHERE p.activo = 1
                ORDER BY p.id_plan ASC";
        $r = $conn->query($sql);
        $planes = [];
        if ($r) {
            while ($f = $r->fetch_assoc()) $planes[] = $f;
        }
        $conn->close();
        respuestaOk(['planes' => $planes]);
    }

    // Filtrado por id_plan concreto (para el panel expandible en planes.php)
    // Uso: ?id_plan=5 → devuelve solo las tareas de ese plan
    if (isset($_GET['id_plan'])) {
        $id_plan = (int)$_GET['id_plan'];
        $sql  = "SELECT t.*,
                        m.nombre AS nombre_maquina,
                        g.nombre AS nombre_gama
                 FROM tareas_mantenimiento t
                 LEFT JOIN planes_mantenimiento p ON p.id_plan    = t.id_plan
                 LEFT JOIN maquinas             m ON m.id_maquina = p.id_maquina
                 LEFT JOIN gamas_mantenimiento  g ON g.id_gama    = p.id_gama
                 WHERE t.id_plan = ?
                 ORDER BY t.orden ASC";
        $s = $conn->prepare($sql);
        $s->bind_param("i", $id_plan);
        $s->execute();
        $r = $s->get_result();
        $lista = [];
        while ($f = $r->fetch_assoc()) $lista[] = $f;
        $s->close();
        $conn->close();
        respuestaOk(['tareas' => $lista, 'total' => count($lista)]);
    }


    // GET por ID concreto (ej: ?id=5)
    if (isset($_GET['id'])) {
        $id  = (int)$_GET['id'];
        $sql = "SELECT t.*,
                       m.nombre AS nombre_maquina,
                       g.nombre AS nombre_gama
                FROM tareas_mantenimiento t
                LEFT JOIN planes_mantenimiento p ON p.id_plan    = t.id_plan
                LEFT JOIN maquinas             m ON m.id_maquina = p.id_maquina
                LEFT JOIN gamas_mantenimiento  g ON g.id_gama    = p.id_gama
                WHERE t.id_tarea = ?";
        $s   = $conn->prepare($sql);
        $s->bind_param("i", $id);
        $s->execute();
        $f   = $s->get_result()->fetch_assoc();
        $s->close();
        $conn->close();
        if (!$f) respuestaError('Tarea no encontrada.', 404);
        respuestaOk(['tarea' => $f]);
    }

    // GET listado (con búsqueda opcional: ?buscar=texto)
    $buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

    $base_sql = "SELECT t.*,
                        m.nombre AS nombre_maquina,
                        g.nombre AS nombre_gama
                 FROM tareas_mantenimiento t
                 LEFT JOIN planes_mantenimiento p ON p.id_plan    = t.id_plan
                 LEFT JOIN maquinas             m ON m.id_maquina = p.id_maquina
                 LEFT JOIN gamas_mantenimiento  g ON g.id_gama    = p.id_gama";

    if ($buscar !== '') {
        // Búsqueda por ID tarea, ID plan, descripción, máquina o gama
        $sql  = $base_sql . " WHERE t.id_tarea LIKE ? OR t.id_plan LIKE ? OR t.descripcion LIKE ? OR m.nombre LIKE ? OR g.nombre LIKE ? ORDER BY t.id_plan ASC, t.orden ASC";
        $like = "%" . $buscar . "%";
        $s    = $conn->prepare($sql);
        $s->bind_param("sssss", $like, $like, $like, $like, $like);
        $s->execute();
        $r = $s->get_result();
        $s->close();
    } else {
        $r = $conn->query($base_sql . " ORDER BY t.id_plan ASC, t.orden ASC");
    }

    $lista = [];
    if ($r) {
        while ($f = $r->fetch_assoc()) $lista[] = $f;
    }
    $conn->close();
    respuestaOk(['tareas' => $lista, 'total' => count($lista)]);
}

// ── POST (Alta) ───────────────────────────────────────────────
if ($m === 'POST') {
    $b                       = json_decode(file_get_contents('php://input'), true) ?? [];
    $id_plan                 = (int)($b['id_plan']  ?? 0);
    $descripcion             = trim($b['descripcion'] ?? '');
    $orden                   = (int)($b['orden']    ?? 0);
    $instrucciones           = isset($b['instrucciones_detalladas']) ? trim($b['instrucciones_detalladas']) : null;
    $requiere_epi            = isset($b['requiere_epi']) ? (int)$b['requiere_epi'] : 0;
    $epis_necesarios         = isset($b['epis_necesarios'])       ? trim($b['epis_necesarios'])       : null;
    $reglas_seguridad        = isset($b['reglas_seguridad'])       ? trim($b['reglas_seguridad'])       : null;
    $reglas_medio_ambiente   = isset($b['reglas_medio_ambiente'])  ? trim($b['reglas_medio_ambiente'])  : null;

    // Validaciones
    if ($id_plan <= 0)   respuestaError('Debes indicar un plan de mantenimiento válido.');
    if (!$descripcion)   respuestaError('La descripción de la tarea es obligatoria.');
    if ($orden < 1)      respuestaError('El orden de ejecución debe ser al menos 1.');

    // Verificar que el plan existe
    $chkP = $conn->prepare("SELECT id_plan FROM planes_mantenimiento WHERE id_plan=?");
    $chkP->bind_param("i", $id_plan); $chkP->execute(); $chkP->store_result();
    if ($chkP->num_rows === 0) { $chkP->close(); $conn->close(); respuestaError('El plan indicado no existe.', 404); }
    $chkP->close();

    // Insertar
    $s = $conn->prepare(
        "INSERT INTO tareas_mantenimiento
            (id_plan, descripcion, orden, instrucciones_detalladas, requiere_epi, epis_necesarios, reglas_seguridad, reglas_medio_ambiente)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $s->bind_param("isssisss",
        $id_plan, $descripcion, $orden,
        $instrucciones, $requiere_epi, $epis_necesarios,
        $reglas_seguridad, $reglas_medio_ambiente
    );
    $ok       = $s->execute();
    $db_error = $s->error;
    $nid      = $conn->insert_id;
    $s->close();
    $conn->close();

    if (!$ok) respuestaError('Error al crear la tarea: ' . $db_error, 500);
    respuestaOk(['msg' => 'Tarea creada correctamente.', 'id_tarea' => $nid]);
}

// ── PUT (Modificación) ────────────────────────────────────────
if ($m === 'PUT') {
    $b                       = json_decode(file_get_contents('php://input'), true) ?? [];
    $id_tarea                = (int)($b['id_tarea']  ?? 0);
    $id_plan                 = (int)($b['id_plan']   ?? 0);
    $descripcion             = trim($b['descripcion'] ?? '');
    $orden                   = (int)($b['orden']     ?? 0);
    $instrucciones           = isset($b['instrucciones_detalladas']) ? trim($b['instrucciones_detalladas']) : null;
    $requiere_epi            = isset($b['requiere_epi']) ? (int)$b['requiere_epi'] : 0;
    $epis_necesarios         = isset($b['epis_necesarios'])       ? trim($b['epis_necesarios'])       : null;
    $reglas_seguridad        = isset($b['reglas_seguridad'])       ? trim($b['reglas_seguridad'])       : null;
    $reglas_medio_ambiente   = isset($b['reglas_medio_ambiente'])  ? trim($b['reglas_medio_ambiente'])  : null;

    // Validaciones
    if ($id_tarea <= 0)  respuestaError('ID de tarea no válido.');
    if ($id_plan  <= 0)  respuestaError('Debes indicar un plan de mantenimiento válido.');
    if (!$descripcion)   respuestaError('La descripción de la tarea es obligatoria.');
    if ($orden < 1)      respuestaError('El orden de ejecución debe ser al menos 1.');

    // Verificar que la tarea existe
    $chkT = $conn->prepare("SELECT id_tarea FROM tareas_mantenimiento WHERE id_tarea=?");
    $chkT->bind_param("i", $id_tarea); $chkT->execute(); $chkT->store_result();
    if ($chkT->num_rows === 0) { $chkT->close(); $conn->close(); respuestaError('Tarea no encontrada.', 404); }
    $chkT->close();

    // Verificar que el plan existe
    $chkP = $conn->prepare("SELECT id_plan FROM planes_mantenimiento WHERE id_plan=?");
    $chkP->bind_param("i", $id_plan); $chkP->execute(); $chkP->store_result();
    if ($chkP->num_rows === 0) { $chkP->close(); $conn->close(); respuestaError('El plan indicado no existe.', 404); }
    $chkP->close();

    // Actualizar
    $s = $conn->prepare(
        "UPDATE tareas_mantenimiento
         SET id_plan=?, descripcion=?, orden=?, instrucciones_detalladas=?,
             requiere_epi=?, epis_necesarios=?, reglas_seguridad=?, reglas_medio_ambiente=?
         WHERE id_tarea=?"
    );
    $s->bind_param("isssisssi",
        $id_plan, $descripcion, $orden,
        $instrucciones, $requiere_epi, $epis_necesarios,
        $reglas_seguridad, $reglas_medio_ambiente,
        $id_tarea
    );
    $ok       = $s->execute();
    $db_error = $s->error;
    $s->close();
    $conn->close();

    if (!$ok) respuestaError('Error al actualizar la tarea: ' . $db_error, 500);
    respuestaOk(['msg' => 'Tarea actualizada correctamente.']);
}

// ── DELETE (Baja) ─────────────────────────────────────────────
if ($m === 'DELETE') {
    $b        = json_decode(file_get_contents('php://input'), true) ?? [];
    $id_tarea = (int)($b['id_tarea'] ?? 0);

    if ($id_tarea <= 0) respuestaError('ID de tarea no válido.');

    // Verificar que la tarea existe
    $chk = $conn->prepare("SELECT id_tarea FROM tareas_mantenimiento WHERE id_tarea=?");
    $chk->bind_param("i", $id_tarea); $chk->execute(); $chk->store_result();
    if ($chk->num_rows === 0) { $chk->close(); $conn->close(); respuestaError('Tarea no encontrada.', 404); }
    $chk->close();

    // Verificar dependencias: registros en tareas_ejecutadas
    $chkTE = $conn->prepare("SELECT COUNT(*) AS t FROM tareas_ejecutadas WHERE id_tarea=?");
    $chkTE->bind_param("i", $id_tarea); $chkTE->execute();
    $total = $chkTE->get_result()->fetch_assoc()['t'];
    $chkTE->close();
    if ($total > 0) {
        $conn->close();
        respuestaError("No se puede eliminar: la tarea tiene $total ejecución(es) registrada(s) en el historial.");
    }

    // Verificar dependencias: herramientas asignadas a la tarea
    $chkTH = $conn->prepare("SELECT COUNT(*) AS t FROM tareas_herramientas WHERE id_tarea=?");
    $chkTH->bind_param("i", $id_tarea); $chkTH->execute();
    $totalH = $chkTH->get_result()->fetch_assoc()['t'];
    $chkTH->close();
    if ($totalH > 0) {
        // Eliminamos primero las herramientas asignadas (tabla pivote sin datos de negocio)
        $delH = $conn->prepare("DELETE FROM tareas_herramientas WHERE id_tarea=?");
        $delH->bind_param("i", $id_tarea); $delH->execute(); $delH->close();
    }

    // Eliminar la tarea
    $s = $conn->prepare("DELETE FROM tareas_mantenimiento WHERE id_tarea=?");
    $s->bind_param("i", $id_tarea);
    $ok       = $s->execute();
    $db_error = $s->error;
    $s->close();
    $conn->close();

    if (!$ok) respuestaError('Error al eliminar la tarea: ' . $db_error, 500);
    respuestaOk(['msg' => 'Tarea de mantenimiento eliminada correctamente.']);
}

// Método no soportado
respuestaError('Método no soportado.', 405);
