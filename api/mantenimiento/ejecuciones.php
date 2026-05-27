<?php
// ==============================================================================
// ARCHIVO: api/mantenimiento/ejecuciones.php
// DESCRIPCIÓN: API REST para gestionar ejecuciones de mantenimiento (CRUD)
// MÉTODOS HTTP SOPORTADOS:
//   - GET: Obtener ejecuciones o tareas ejecutadas
//   - POST: Crear nueva ejecución o actualizar estado de tareas
//   - PUT: Modificar ejecución existente (no implementado actualmente)
//   - DELETE: Eliminar ejecución
// CLIENTES:
//   - Js/ejecuciones.js (controlador frontend)
//   - HTML/mantenimientoMenu/ejecuciones.html (interfaz web)
// BASE DE DATOS:
//   - Tabla: ejecuciones_mantenimiento
//   - Tabla: tareas_ejecutadas
// FUNCIONALIDADES:
//   1. Listar ejecuciones con filtros (ID, resultado, orden)
//   2. Crear nuevas ejecuciones de mantenimiento
//   3. Obtener tareas ejecutadas de una ejecución
//   4. Actualizar estado de tareas (completada/no completada)
//   5. Eliminar ejecuciones
// ==============================================================================

// Cargar archivo de configuración con funciones auxiliares y conexión BD
require_once '../config/helpers.php';
require_once '../config/db_config.php';

// Obtener el método HTTP de la petición (GET, POST, PUT, DELETE, etc.)
$m = $_SERVER['REQUEST_METHOD'];
// Obtener el parámetro 'action' de la URL para determinar subrutás
$action = $_GET['action'] ?? '';

// ==============================================================================
// ENDPOINT: GET /ejecuciones.php?action=get_tareas
// DESCRIPCIÓN: Obtiene las tareas ejecutadas de una ejecución específica
//               o todas las tareas ejecutadas si no se proporciona ID
// ==============================================================================
    // Obtener el ID de ejecución de los parámetros (si se proporciona)
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

// ==============================================================================
// ENDPOINT: POST /ejecuciones.php?action=update_tarea
// DESCRIPCIÓN: Actualiza el estado de una tarea ejecutada (completada o no)
// ==============================================================================
    // Decodificar el JSON del cuerpo de la petición
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // Extraer parámetros necesarios para actualizar la tarea
    $idTareaEjecutada = (int)($body['id'] ?? 0);
    $idEjecucion = (int)($body['id_ejecucion'] ?? 0);
    $idTarea = (int)($body['id_tarea'] ?? 0);
    $estadoTarea = trim((string)($body['estado_tarea'] ?? ''));

    // Validar que el estado sea uno de los valores permitidos
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

// ==============================================================================
// ENDPOINT: GET /ejecuciones.php
// DESCRIPCIÓN: Obtiene lista de ejecuciones con filtros opcionales
//               o detalles de una ejecución específica si se proporciona ID
// ==============================================================================
    if (isset($_GET['id'])) {
        // Obtener ID de la ejecución a buscar
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
        // Si no hay ID específico, listar todas las ejecuciones con filtros opcionales
        // Obtener parámetros de filtro de la URL
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

// ==============================================================================
// ENDPOINT: POST /ejecuciones.php
// DESCRIPCIÓN: Crea una nueva ejecución de mantenimiento
// PARÁMETROS (JSON):
//   - id_orden (requerido): ID de la orden de trabajo
//   - fecha_ejecucion: Fecha de la ejecución (default: hoy)
//   - hora_inicio: Hora de inicio (opcional)
//   - hora_fin: Hora de finalización (opcional)
//   - observaciones: Notas sobre la intervención (opcional)
//   - resultado: Resultado (exitoso o con_incidencias)
// ==============================================================================
if ($m === 'POST') {
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

// ==============================================================================
// ENDPOINT: DELETE /ejecuciones.php
// DESCRIPCIÓN: Elimina una ejecución de mantenimiento
// PARÁMETROS (JSON):
//   - id_ejecucion (requerido): ID de la ejecución a eliminar
// ==============================================================================
if ($m === 'DELETE') {
    // Decodificar el JSON del cuerpo de la petición
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    // Obtener ID de ejecución a eliminar
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

// ==============================================================================
// FUNCIONES AUXILIARES
// ==============================================================================

/**
 * Crea una conexión a la base de datos usando PDO
 * PDO proporciona una interfaz independiente del gestor de BD
 * @return PDO Objeto de conexión PDO a MySQL
 */
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

/**
 * Obtiene las tareas ejecutadas de una ejecución específica
 * Normaliza los datos para que sean consistentes sin importar la estructura BD
 * @param PDO $pdo Conexión a base de datos PDO
 * @param int $idEjecucion ID de la ejecución
 * @return array Lista de tareas ejecutadas normalizadas
 */
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

/**
 * Obtiene todas las tareas ejecutadas de todas las ejecuciones
 * Ordena de más reciente a más antigua
 * @param PDO $pdo Conexión a base de datos PDO
 * @return array Lista de todas las tareas ejecutadas normalizadas
 */
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

/**
 * Actualiza el estado de una tarea ejecutada (completada o no)
 * Intenta usar varios métodos para ubicar y actualizar la tarea
 * @param PDO $pdo Conexión a base de datos PDO
 * @param int $idTareaEjecutada ID de la tarea ejecutada
 * @param int $idEjecucion ID de la ejecución padre
 * @param int $idTarea ID de la tarea
 * @param string $estadoTarea Estado a asignar ('Completada' o 'No Completada')
 */
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

/**
 * Normaliza un registro de tarea ejecutada desde BD a un formato estándar
 * Convierte diferentes estructuras de tabla a un formato consistente
 * @param array $tarea Datos de tarea desde la BD
 * @return array Tarea normalizada con estructura uniforme
 */
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

/**
 * Verifica si una tarea está completada
 * Maneja múltiples formatos: boolean, int (0/1), string ('Completada')
 * @param mixed $estado Estado de la tarea
 * @return bool true si la tarea está completada
 */
    if ($estado === true || $estado === 1 || $estado === '1') {
        return true;
    }

    return strtolower((string)$estado) === 'completada';
}

/**
 * Obtiene la lista de nombres de columnas de una tabla
 * Usa information_schema para ser independiente del gestor BD
 * @param PDO $pdo Conexión a base de datos PDO
 * @param string $tabla Nombre de la tabla
 * @return array Lista de nombres de columnas
 */
    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME
         FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = :tabla'
    );
    $stmt->execute([':tabla' => $tabla]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Verifica si una tabla existe en la base de datos
 * @param PDO $pdo Conexión a base de datos PDO
 * @param string $tabla Nombre de la tabla
 * @return bool true si la tabla existe
 */
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :tabla'
    );
    $stmt->execute([':tabla' => $tabla]);

    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Verifica si una columna es de tipo numérico
 * @param PDO $pdo Conexión a base de datos PDO
 * @param string $tabla Nombre de la tabla
 * @param string $columna Nombre de la columna
 * @return bool true si la columna es numérica
 */
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
