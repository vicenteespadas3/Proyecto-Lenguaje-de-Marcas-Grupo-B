<?php
// ============================================================
//  api/mantenimiento/ejecuciones.php  —  CRUD para Ejecuciones
// ============================================================
// Referencia en la web:
//   - Pantalla "Historial de Ejecuciones" (HTML/mantenimientoMenu/ejecuciones.html)
require_once '../config/helpers.php';
require_once '../config/db_config.php';

$m = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Endpoints añadidos para tareas ejecutadas. Usan PDO como API interna.
if ($m === 'GET' && $action === 'get_tareas') {
    $idEjecucion = isset($_GET['id_ejecucion']) ? (int)$_GET['id_ejecucion'] : 0;

    try {
        $pdo = conectarPDOEjecuciones();
        if ($idEjecucion > 0) {
            respuestaOk([
                'tabla' => 'tareas_ejecutadas',
                'columnas' => obtenerColumnasPDO($pdo, 'tareas_ejecutadas'),
                'tareas' => obtenerTareasEjecutadas($pdo, $idEjecucion)
            ]);
        } else {
            respuestaOk([
                'tabla' => 'tareas_ejecutadas',
                'columnas' => obtenerColumnasPDO($pdo, 'tareas_ejecutadas'),
                'tareas' => obtenerTodasTareasEjecutadas($pdo)
            ]);
        }
    } catch (PDOException $e) {
        respuestaError('Error de base de datos: ' . $e->getMessage(), 500);
    }
}

if ($m === 'POST' && $action === 'update_tarea') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $idTareaEjecutada = (int)($body['id'] ?? 0);
    $idEjecucion = (int)($body['id_ejecucion'] ?? 0);
    $idTarea = (int)($body['id_tarea'] ?? 0);
    $estadoTarea = trim((string)($body['estado_tarea'] ?? ''));

    if ($estadoTarea !== 'Completada' && $estadoTarea !== 'No Completada') {
        respuestaError('Estado de tarea no válido.');
    }

    try {
        $pdo = conectarPDOEjecuciones();
        actualizarTareaEjecutada($pdo, $idTareaEjecutada, $idEjecucion, $idTarea, $estadoTarea);
        respuestaOk(['msg' => 'Estado de tarea actualizado.']);
    } catch (PDOException $e) {
        respuestaError('Error de base de datos: ' . $e->getMessage(), 500);
    }
}

$conn = conectar();

// ── GET ─────────────────────────────────────────────────────
if ($m === 'GET') {
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];

        // 1. Obtener datos de la ejecución
        $s = $conn->prepare("SELECT e.*, d.descripcion as tipo_nombre, m.nombre as maquina_nombre
                             FROM ejecuciones_mantenimiento e
                             JOIN ordenes_trabajo ot ON e.id_orden = ot.id_orden
                             JOIN maquinas m ON ot.id_maquina = m.id_maquina
                             JOIN definiciones d ON ot.id_tipo = d.id_definicion
                             WHERE e.id_ejecucion = ?");
        $s->bind_param("i", $id);
        $s->execute();
        $ejec = $s->get_result()->fetch_assoc();
        $s->close();

        if (!$ejec) {
            $conn->close();
            respuestaError('Ejecución no encontrada.', 404);
        }

        $conn->close();

        try {
            $pdo = conectarPDOEjecuciones();
            respuestaOk([
                'ejecucion' => $ejec,
                'tabla' => 'tareas_ejecutadas',
                'columnas' => obtenerColumnasPDO($pdo, 'tareas_ejecutadas'),
                'tareas' => obtenerTareasEjecutadas($pdo, $id)
            ]);
        } catch (PDOException $e) {
            respuestaError('Error de base de datos: ' . $e->getMessage(), 500);
        }
    } else {
        $idEjecucionFiltro = isset($_GET['id_ejecucion']) ? trim($_GET['id_ejecucion']) : '';
        $resultadoFiltro = isset($_GET['resultado']) ? trim($_GET['resultado']) : '';
        $ordenFiltro = isset($_GET['orden']) ? trim($_GET['orden']) : 'recientes';

        $query = "SELECT
                    id_ejecucion,
                    id_orden,
                    fecha_ejecucion,
                    hora_inicio,
                    hora_fin,
                    id_tecnico,
                    observaciones,
                    resultado
                  FROM ejecuciones_mantenimiento";

        $where = [];
        $types = '';
        $params = [];

        if ($idEjecucionFiltro !== '') {
            if (!ctype_digit($idEjecucionFiltro)) {
                $conn->close();
                respuestaOk(['ejecuciones' => []]);
            }

            $where[] = 'id_ejecucion = ?';
            $types .= 'i';
            $params[] = (int)$idEjecucionFiltro;
        }

        if (in_array($resultadoFiltro, ['exitoso', 'con_incidencias'], true)) {
            $where[] = 'resultado = ?';
            $types .= 's';
            $params[] = $resultadoFiltro;
        }

        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }

        $orderBy = match ($ordenFiltro) {
            'antiguas' => ' ORDER BY fecha_ejecucion ASC, hora_inicio ASC, id_ejecucion ASC',
            'id_asc' => ' ORDER BY id_ejecucion ASC',
            'id_desc' => ' ORDER BY id_ejecucion DESC',
            default => ' ORDER BY fecha_ejecucion DESC, hora_inicio DESC, id_ejecucion DESC',
        };

        if (!empty($params)) {
            $s = $conn->prepare($query . $orderBy);
            $s->bind_param($types, ...$params);
            $s->execute();
            $r = $s->get_result();
            $lista = [];
            while ($f = $r->fetch_assoc()) $lista[] = $f;
            $s->close();
        } else {
            $res = $conn->query($query . $orderBy);
            $lista = [];
            if ($res) {
                while ($f = $res->fetch_assoc()) $lista[] = $f;
            }
        }
        $conn->close();
        respuestaOk(['ejecuciones' => $lista]);
    }
}

// ── POST ────────────────────────────────────────────────────
if ($m === 'POST') {
    iniciarSesion();
    $b = json_decode(file_get_contents('php://input'), true) ?? [];

    $id_orden = (int)($b['id_orden'] ?? 0);
    $fecha = $b['fecha_ejecucion'] ?? date('Y-m-d');
    $h_inicio = $b['hora_inicio'] ?? null;
    $h_fin = $b['hora_fin'] ?? null;
    $id_tecnico = $_SESSION['id_usuario'] ?? 1;
    $obs = $b['observaciones'] ?? '';
    $res = $b['resultado'] ?? 'exitoso';

    if ($id_orden <= 0) {
        $conn->close();
        respuestaError('El ID de orden es obligatorio.');
    }

    $stmt = $conn->prepare("INSERT INTO ejecuciones_mantenimiento (id_orden, fecha_ejecucion, hora_inicio, hora_fin, id_tecnico, observaciones, resultado) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssiss", $id_orden, $fecha, $h_inicio, $h_fin, $id_tecnico, $obs, $res);

    $ok = $stmt->execute();
    $nid = $conn->insert_id;
    $stmt->close();
    $conn->close();

    if ($ok) {
        respuestaOk(['msg' => 'Ejecución registrada con éxito', 'id' => $nid]);
    } else {
        respuestaError('Error al crear la ejecución.', 500);
    }
}

// ── DELETE ──────────────────────────────────────────────────
if ($m === 'DELETE') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id_ejecucion'] ?? 0);

    if ($id <= 0) {
        $conn->close();
        respuestaError('ID de ejecución no válido.');
    }

    $stmt = $conn->prepare("DELETE FROM ejecuciones_mantenimiento WHERE id_ejecucion=?");
    $stmt->bind_param("i", $id);

    $ok = $stmt->execute();
    $stmt->close();
    $conn->close();

    if ($ok) {
        respuestaOk(['msg' => 'Ejecución eliminada con éxito']);
    } else {
        respuestaError('Error al eliminar.', 500);
    }
}

$conn->close();
respuestaError('Método no permitido', 405);

function conectarPDOEjecuciones(): PDO
{
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function obtenerTareasEjecutadas(PDO $pdo, int $idEjecucion): array
{
    $columnas = obtenerColumnasPDO($pdo, 'tareas_ejecutadas');

    if (empty($columnas)) {
        respuestaError('No se ha encontrado la tabla tareas_ejecutadas.', 500);
    }

    $orderBy = in_array('id_tarea_ejecutada', $columnas, true)
        ? 'id_tarea_ejecutada'
        : (in_array('id', $columnas, true) ? 'id' : 'id_ejecucion');

    $stmt = $pdo->prepare("SELECT * FROM tareas_ejecutadas WHERE id_ejecucion = :id_ejecucion ORDER BY $orderBy ASC");
    $stmt->execute([':id_ejecucion' => $idEjecucion]);
    $tareas = $stmt->fetchAll();

    return array_map('normalizarTareaEjecutada', $tareas);
}

function obtenerTodasTareasEjecutadas(PDO $pdo): array
{
    $columnas = obtenerColumnasPDO($pdo, 'tareas_ejecutadas');

    if (empty($columnas)) {
        respuestaError('No se ha encontrado la tabla tareas_ejecutadas.', 500);
    }

    $orderBy = in_array('id_tarea_ejecutada', $columnas, true)
        ? 'id_tarea_ejecutada'
        : (in_array('id', $columnas, true) ? 'id' : 'id_ejecucion');

    $stmt = $pdo->prepare("SELECT * FROM tareas_ejecutadas ORDER BY $orderBy DESC");
    $stmt->execute();
    $tareas = $stmt->fetchAll();

    return array_map('normalizarTareaEjecutada', $tareas);
}

function actualizarTareaEjecutada(PDO $pdo, int $idTareaEjecutada, int $idEjecucion, int $idTarea, string $estadoTarea): void
{
    $columnas = obtenerColumnasPDO($pdo, 'tareas_ejecutadas');

    if (empty($columnas)) {
        respuestaError('No se ha encontrado la tabla tareas_ejecutadas.', 500);
    }

    $columnaEstado = in_array('estado_tarea', $columnas, true)
        ? 'estado_tarea'
        : (in_array('completada', $columnas, true) ? 'completada' : '');

    if ($columnaEstado === '') {
        respuestaError('No existe una columna de estado para actualizar la tarea.', 500);
    }

    $valorEstado = $estadoTarea;
    if ($columnaEstado === 'completada' || columnaEsNumericaPDO($pdo, 'tareas_ejecutadas', $columnaEstado)) {
        $valorEstado = $estadoTarea === 'Completada' ? 1 : 0;
    }

    if (in_array('id', $columnas, true) && $idTareaEjecutada > 0) {
        $stmt = $pdo->prepare("UPDATE tareas_ejecutadas SET $columnaEstado = :estado WHERE id = :id");
        $stmt->execute([
            ':estado' => $valorEstado,
            ':id' => $idTareaEjecutada,
        ]);
        return;
    }

    if (in_array('id_tarea_ejecutada', $columnas, true) && $idTareaEjecutada > 0) {
        $stmt = $pdo->prepare("UPDATE tareas_ejecutadas SET $columnaEstado = :estado WHERE id_tarea_ejecutada = :id");
        $stmt->execute([
            ':estado' => $valorEstado,
            ':id' => $idTareaEjecutada,
        ]);
        return;
    }

    if (in_array('id_tarea', $columnas, true) && $idEjecucion > 0 && $idTarea > 0) {
        $stmt = $pdo->prepare("UPDATE tareas_ejecutadas SET $columnaEstado = :estado WHERE id_ejecucion = :id_ejecucion AND id_tarea = :id_tarea");
        $stmt->execute([
            ':estado' => $valorEstado,
            ':id_ejecucion' => $idEjecucion,
            ':id_tarea' => $idTarea,
        ]);
        return;
    }

    respuestaError('No se pudo identificar la tarea a actualizar.');
}

function normalizarTareaEjecutada(array $tarea): array
{
    $estado = $tarea['estado_tarea'] ?? ($tarea['completada'] ?? 0);

    return [
        'id' => $tarea['id'] ?? ($tarea['id_tarea_ejecutada'] ?? ''),
        'id_tarea_ejecutada' => $tarea['id_tarea_ejecutada'] ?? ($tarea['id'] ?? ''),
        'id_tarea' => $tarea['id_tarea'] ?? '',
        'id_ejecucion' => $tarea['id_ejecucion'] ?? '',
        'observaciones' => $tarea['observaciones'] ?? '',
        'tiempo_empleado' => $tarea['tiempo_empleado'] ?? '',
        'descripcion_tarea' => 'Tarea #' . ($tarea['id_tarea'] ?? ''),
        'estado_tarea' => tareaEstaCompletada($estado) ? 'Completada' : 'No Completada',
    ];
}

function tareaEstaCompletada($estado): bool
{
    if ($estado === true || $estado === 1 || $estado === '1') {
        return true;
    }

    return strtolower((string)$estado) === 'completada';
}

function obtenerColumnasPDO(PDO $pdo, string $tabla): array
{
    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME
         FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = :tabla'
    );
    $stmt->execute([':tabla' => $tabla]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function existeTablaPDO(PDO $pdo, string $tabla): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :tabla'
    );
    $stmt->execute([':tabla' => $tabla]);

    return (int)$stmt->fetchColumn() > 0;
}

function columnaEsNumericaPDO(PDO $pdo, string $tabla, string $columna): bool
{
    $stmt = $pdo->prepare(
        'SELECT DATA_TYPE
         FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = :tabla AND column_name = :columna'
    );
    $stmt->execute([
        ':tabla' => $tabla,
        ':columna' => $columna,
    ]);

    $tipo = $stmt->fetchColumn();
    return in_array($tipo, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'bit', 'boolean'], true);
}
