<?php
// ==============================================================================
// ARCHIVO: api/mantenimiento/ordenes.php
// DESCRIPCIÓN: API REST para gestionar órdenes de trabajo (CRUD)
// MÉTODOS HTTP SOPORTADOS:
//   - GET: Listar órdenes con filtros opcionales
//   - POST: Crear nueva orden de trabajo
//   - PUT: Modificar orden existente
//   - DELETE: Eliminar orden
// CLIENTES:
//   - Js/ordenes.js (controlador frontend)
//   - HTML/mantenimientoMenu/ordenes.html (interfaz web)
// BASE DE DATOS:
//   - Tabla: ordenes_trabajo
//   - Tabla: maquinas (para referencia)
//   - Tabla: definiciones (para tipos, estados, prioridades)
// FUNCIONALIDADES:
//   1. Listar órdenes con filtros (búsqueda, estado, prioridad)
//   2. Crear nuevas órdenes de trabajo
//   3. Modificar órdenes existentes
//   4. Eliminar órdenes
//   5. Resolver IDs de máquinas, tipos y prioridades automáticamente
// ==============================================================================

// Cargar archivo de configuración con variables de conexión
require_once '../config/db_config.php';

// Configurar headers HTTP para respuestas JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Responder a peticiones OPTIONS (pre-flight de CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    // Crear conexión PDO a la base de datos
    $pdo = conectarPDO();
    // Obtener el método HTTP de la petición (GET, POST, PUT, DELETE)
    $metodo = $_SERVER['REQUEST_METHOD'];

    // Enrutar a la función correspondiente según el método HTTP
    if ($metodo === 'GET') {
        listarOrdenes($pdo);
    }

    if ($metodo === 'POST') {
        crearOrden($pdo);
    }

    if ($metodo === 'PUT') {
        actualizarOrden($pdo);
    }

    if ($metodo === 'DELETE') {
        eliminarOrden($pdo);
    }

    // Si llegamos aquí, el método HTTP no es soportado
    responderError('Metodo no permitido.', 405);
} catch (PDOException $e) {
    // Capturar errores de la base de datos
    responderError('Error de base de datos: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    // Capturar cualquier otro error
    responderError($e->getMessage(), 500);
}

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

// ==============================================================================
// ENDPOINT: GET /ordenes.php
// DESCRIPCIÓN: Lista todas las órdenes de trabajo con filtros opcionales
// PARÁMETROS QUERY (opcionales):
//   - buscar: Texto para buscar en ID, máquina u observaciones
//   - estado: Filtrar por estado de la orden
//   - prioridad: Filtrar por prioridad
// ==============================================================================
function listarOrdenes(PDO $pdo): void
{
    // Obtener y validar parámetros de filtro de la URL
    $buscar = isset($_GET['buscar']) ? trim((string)$_GET['buscar']) : '';
    $estado = isset($_GET['estado']) ? trim((string)$_GET['estado']) : '';
    $prioridad = isset($_GET['prioridad']) ? trim((string)$_GET['prioridad']) : '';

    $whereClauses = [];
    $params = [];

    if ($buscar !== '') {
        if (ctype_digit($buscar)) {
            $whereClauses[] = '(ot.id_orden = :buscar_id OR ot.id_maquina = :buscar_maq OR ot.observaciones LIKE :buscar_text)';
            $params[':buscar_id'] = (int)$buscar;
            $params[':buscar_maq'] = (int)$buscar;
            $params[':buscar_text'] = '%' . $buscar . '%';
        } else {
            $whereClauses[] = '(ot.observaciones LIKE :buscar_text OR m.nombre LIKE :buscar_maq_nombre)';
            $params[':buscar_text'] = '%' . $buscar . '%';
            $params[':buscar_maq_nombre'] = '%' . $buscar . '%';
        }
    }

    if ($estado !== '') {
        $id_estado = resolverIdDefinicion($pdo, $estado, 0);
        if ($id_estado > 0) {
            $whereClauses[] = 'ot.id_estado = :id_estado';
            $params[':id_estado'] = $id_estado;
        }
    }

    if ($prioridad !== '') {
        $id_prioridad = resolverIdDefinicion($pdo, $prioridad, 0);
        if ($id_prioridad > 0) {
            $whereClauses[] = 'ot.id_prioridad = :id_prioridad';
            $params[':id_prioridad'] = $id_prioridad;
        }
    }

    $whereSql = '';
    if (!empty($whereClauses)) {
        $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
    }

    $sql = "SELECT
                ot.id_orden,
                ot.id_maquina,
                ot.id_plan,
                ot.id_tipo,
                ot.id_estado,
                ot.id_prioridad,
                ot.id_rol_asignado,
                ot.fecha_programada,
                ot.fecha_inicio,
                ot.fecha_finalizacion,
                ot.tiempo_estimado,
                ot.tiempo_real,
                ot.observaciones
             FROM ordenes_trabajo ot
             LEFT JOIN maquinas m ON ot.id_maquina = m.id_maquina
             $whereSql
             ORDER BY ot.fecha_programada DESC, ot.id_orden DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ordenes = $stmt->fetchAll();

    responderJson([
        'success' => true,
        'tabla' => 'ordenes_trabajo',
        'columnas' => [
            'id_orden',
            'id_maquina',
            'id_plan',
            'id_tipo',
            'id_estado',
            'id_prioridad',
            'id_rol_asignado',
            'fecha_programada',
            'fecha_inicio',
            'fecha_finalizacion',
            'tiempo_estimado',
            'tiempo_real',
            'observaciones',
        ],
        'ordenes' => $ordenes,
    ]);
}

// ==============================================================================
// ENDPOINT: POST /ordenes.php
// DESCRIPCIÓN: Crea una nueva orden de trabajo
// PARÁMETROS (JSON):
//   - descripcion (requerido): Descripción de la averia o intervención
//   - maquina (requerido): Nombre o ID de la máquina
//   - tipo (requerido): Tipo (Correctivo, Preventivo, Predictivo)
//   - prioridad (requerido): Prioridad (Baja, Media, Alta)
// ==============================================================================
function crearOrden(PDO $pdo): void
{
    $body = json_decode(file_get_contents('php://input'), true);

    if (!is_array($body)) {
        responderError('El cuerpo de la peticion debe ser JSON valido.');
    }

    $descripcion = trim((string)($body['descripcion'] ?? ''));
    $maquina = trim((string)($body['maquina'] ?? ''));
    $tipo = trim((string)($body['tipo'] ?? ''));
    $prioridad = trim((string)($body['prioridad'] ?? ''));

    if ($descripcion === '' || $maquina === '' || $tipo === '' || $prioridad === '') {
        responderError('Descripcion, maquina, tipo y prioridad son obligatorios.');
    }

    $tiposPermitidos = ['Correctivo', 'Preventivo', 'Predictivo'];
    $prioridadesPermitidas = ['Baja', 'Media', 'Alta'];

    if (!in_array($tipo, $tiposPermitidos, true)) {
        responderError('Tipo de mantenimiento no valido.');
    }

    if (!in_array($prioridad, $prioridadesPermitidas, true)) {
        responderError('Prioridad no valida.');
    }

    $pdo->beginTransaction();

    try {
        $columnas = obtenerColumnas($pdo, 'ordenes_trabajo');

        if (empty($columnas)) {
            responderError('No se ha encontrado la tabla ordenes_trabajo en la base de datos configurada.', 500);
        }

        $codigo = in_array('codigo', $columnas, true) ? generarCodigoOrden($pdo) : null;
        $datosInsert = construirDatosInsert($pdo, $columnas, [
            'codigo' => $codigo,
            'descripcion' => $descripcion,
            'maquina' => $maquina,
            'tipo' => $tipo,
            'prioridad' => $prioridad,
            'estado' => 'Pendiente',
        ]);

        if (empty($datosInsert)) {
            responderError('La tabla ordenes_trabajo no tiene columnas compatibles para crear ordenes.', 500);
        }

        $campos = array_keys($datosInsert);
        $placeholders = array_map('crearPlaceholder', $campos);
        $stmt = $pdo->prepare(
            'INSERT INTO ordenes_trabajo (' . implode(', ', $campos) . ')
             VALUES (' . implode(', ', $placeholders) . ')'
        );

        $stmt->execute($datosInsert);

        $id = (int)$pdo->lastInsertId();
        $pdo->commit();

        responderJson([
            'success' => true,
            'id' => $id,
            'codigo' => $codigo ?? ('OT-' . str_pad((string)$id, 3, '0', STR_PAD_LEFT)),
        ], 201);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

/**
 * Genera un código único para la orden con formato: OT-AÑO-NNN
 * @param PDO $pdo Conexión a base de datos
 * @return string Código generado para la nueva orden
 */
    $anio = date('Y');
    $prefijo = 'OT-' . $anio . '-';

    $stmt = $pdo->prepare(
        'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(codigo, "-", -1) AS UNSIGNED)), 0) + 1 AS siguiente
         FROM ordenes_trabajo
         WHERE codigo LIKE :prefijo'
    );
    $stmt->execute([':prefijo' => $prefijo . '%']);

    $siguiente = (int)$stmt->fetchColumn();

    return $prefijo . str_pad((string)$siguiente, 3, '0', STR_PAD_LEFT);
}

/**
 * Crea un placeholder parametrizado para consultas preparadas
 * @param string $campo Nombre del campo
 * @return string Placeholder en formato :campo
 */
    return ':' . $campo;
}

/**
 * Obtiene la lista de nombres de columnas de una tabla
 * @param PDO $pdo Conexión a base de datos
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
 * Construye una consulta SELECT completa con columnas flexibles
 * Maneja diferentes nombres de columnas posibles en la tabla
 * @param array $columnas Lista de columnas disponibles en la tabla
 * @return string Consulta SQL SELECT
 */
    $id = columna($columnas, ['id', 'id_orden'], 'NULL');
    $codigo = in_array('codigo', $columnas, true)
        ? 'codigo'
        : "CONCAT('OT-', LPAD(" . columna($columnas, ['id_orden', 'id'], '0') . ", 3, '0'))";
    $descripcion = columna($columnas, ['descripcion', 'observaciones'], "'Orden de mantenimiento'");
    $maquina = in_array('maquina', $columnas, true)
        ? 'maquina'
        : (in_array('id_maquina', $columnas, true) ? 'CAST(id_maquina AS CHAR)' : "'Sin maquina'");
    $tipo = in_array('tipo', $columnas, true)
        ? 'tipo'
        : (in_array('id_tipo', $columnas, true) ? 'CAST(id_tipo AS CHAR)' : "'Mantenimiento'");
    $prioridad = in_array('prioridad', $columnas, true)
        ? 'prioridad'
        : (in_array('id_prioridad', $columnas, true) ? 'CAST(id_prioridad AS CHAR)' : "'Media'");
    $estado = in_array('estado', $columnas, true)
        ? 'estado'
        : (in_array('id_estado', $columnas, true) ? 'CAST(id_estado AS CHAR)' : "'Pendiente'");
    $fecha = columna($columnas, ['fecha_creacion', 'fecha_programada', 'fecha'], 'NULL');

    return "SELECT
                $id AS id,
                $codigo AS codigo,
                $descripcion AS descripcion,
                $maquina AS maquina,
                $tipo AS tipo,
                $prioridad AS prioridad,
                $estado AS estado,
                $fecha AS fecha_programada
            FROM ordenes_trabajo";
}

/**
 * Obtiene el nombre del campo de fecha apropiado para ordenar
 * @param array $columnas Lista de columnas disponibles
 * @return string Nombre de columna para ordenamiento
 */
    foreach (['fecha_creacion', 'fecha_programada', 'fecha', 'id_orden', 'id'] as $campo) {
        if (in_array($campo, $columnas, true)) {
            return $campo;
        }
    }

    return '1';
}

/**
 * Busca un nombre de columna en una lista de candidatos
 * Útil para manejar diferentes esquemas de tabla
 * @param array $columnas Lista de columnas disponibles
 * @param array $candidatas Nombres candidatos a buscar
 * @param string $fallback Valor por defecto si ninguno se encuentra
 * @return string Nombre de columna encontrado o fallback
 */
    foreach ($candidatas as $candidata) {
        if (in_array($candidata, $columnas, true)) {
            return $candidata;
        }
    }

    return $fallback;
}

/**
 * Construye un array de datos para insertar en la BD
 * Mapea los datos ingresados a las columnas disponibles en la tabla
 * @param PDO $pdo Conexión a base de datos
 * @param array $columnas Nombres de columnas disponibles
 * @param array $datos Datos a insertar
 * @return array Array con datos mapeados a columnas
 */
    $insert = [];

    foreach (['codigo', 'descripcion', 'maquina', 'tipo', 'prioridad', 'estado'] as $campo) {
        if (in_array($campo, $columnas, true)) {
            $insert[$campo] = $datos[$campo];
        }
    }

    if (in_array('observaciones', $columnas, true)) {
        $insert['observaciones'] = $datos['descripcion'] ?? $datos['observaciones'] ?? '';
    }

    if (in_array('fecha_creacion', $columnas, true)) {
        $insert['fecha_creacion'] = date('Y-m-d H:i:s');
    } elseif (in_array('fecha_programada', $columnas, true)) {
        $insert['fecha_programada'] = date('Y-m-d');
    }

    if (in_array('id_maquina', $columnas, true) && !isset($insert['id_maquina'])) {
        $insert['id_maquina'] = resolverIdMaquina($pdo, $datos['maquina']);
    }

    if (in_array('id_tipo', $columnas, true) && !isset($insert['id_tipo'])) {
        $insert['id_tipo'] = resolverIdDefinicion($pdo, $datos['tipo'], 1);
    }

    if (in_array('id_prioridad', $columnas, true) && !isset($insert['id_prioridad'])) {
        $insert['id_prioridad'] = resolverIdDefinicion($pdo, $datos['prioridad'], 2);
    }

    if (in_array('id_estado', $columnas, true) && !isset($insert['id_estado'])) {
        $insert['id_estado'] = resolverIdDefinicion($pdo, $datos['estado'], 1);
    }

    return $insert;
}

/**
 * Resuelve el ID de una máquina a partir de su nombre o ID
 * @param PDO $pdo Conexión a base de datos
 * @param string $maquina Nombre o ID de la máquina
 * @return int ID de la máquina
 */
    if (ctype_digit($maquina)) {
        return (int)$maquina;
    }

    $stmt = $pdo->prepare('SELECT id_maquina FROM maquinas WHERE nombre = :nombre LIMIT 1');
    $stmt->execute([':nombre' => $maquina]);
    $id = $stmt->fetchColumn();

    if ($id !== false) {
        return (int)$id;
    }

    $stmt = $pdo->query('SELECT id_maquina FROM maquinas ORDER BY id_maquina ASC LIMIT 1');
    $id = $stmt->fetchColumn();

    return $id !== false ? (int)$id : 1;
}

/**
 * Resuelve el ID de una definición (tipo, estado, prioridad) a partir de su descripción
 * Busca en la tabla 'definiciones' por descripción o código
 * @param PDO $pdo Conexión a base de datos
 * @param string $texto Descripción o código de la definición
 * @param int $fallback ID por defecto si no se encuentra
 * @return int ID de la definición o fallback
 */
    $stmt = $pdo->prepare(
        'SELECT id_definicion
         FROM definiciones
         WHERE descripcion = :texto1 OR codigo = :texto2
         ORDER BY id_definicion ASC
         LIMIT 1'
    );
    $stmt->execute([':texto1' => $texto, ':texto2' => $texto]);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int)$id : $fallback;
}

/**
 * Responde con JSON exitoso
 * @param array $datos Datos a enviar en la respuesta
 * @param int $codigoHttp Código HTTP de respuesta (default: 200)
 */
    http_response_code($codigoHttp);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Responde con JSON de error
 * @param string $mensaje Mensaje de error
 * @param int $codigoHttp Código HTTP de error (default: 400)
 */
    responderJson([
        'success' => false,
        'error' => $mensaje,
    ], $codigoHttp);
}

// ==============================================================================
// ENDPOINT: PUT /ordenes.php
// DESCRIPCIÓN: Modifica una orden de trabajo existente
// PARÁMETROS (JSON):
//   - id_orden (requerido): ID de la orden a modificar
//   - descripcion (requerido): Nueva descripción
//   - maquina (requerido): Nueva máquina
//   - tipo (requerido): Nuevo tipo
//   - prioridad (requerido): Nueva prioridad
// ==============================================================================
function actualizarOrden(PDO $pdo): void
{
    $body = json_decode(file_get_contents('php://input'), true);

    if (!is_array($body)) {
        responderError('El cuerpo de la peticion debe ser JSON valido.');
    }

    $id_orden = (int)($body['id_orden'] ?? 0);
    if ($id_orden <= 0) {
        responderError('El ID de la orden es obligatorio.');
    }

    $descripcion = trim((string)($body['descripcion'] ?? ''));
    $maquina = trim((string)($body['maquina'] ?? ''));
    $tipo = trim((string)($body['tipo'] ?? ''));
    $prioridad = trim((string)($body['prioridad'] ?? ''));

    if ($descripcion === '' || $maquina === '' || $tipo === '' || $prioridad === '') {
        responderError('Descripcion, maquina, tipo y prioridad son obligatorios.');
    }

    $tiposPermitidos = ['Correctivo', 'Preventivo', 'Predictivo'];
    $prioridadesPermitidas = ['Baja', 'Media', 'Alta'];

    if (!in_array($tipo, $tiposPermitidos, true)) {
        responderError('Tipo de mantenimiento no valido.');
    }

    if (!in_array($prioridad, $prioridadesPermitidas, true)) {
        responderError('Prioridad no valida.');
    }

    // Verificar si existe la orden
    $stmt = $pdo->prepare('SELECT id_orden FROM ordenes_trabajo WHERE id_orden = :id');
    $stmt->execute([':id' => $id_orden]);
    if (!$stmt->fetch()) {
        responderError('La orden de trabajo no existe.', 404);
    }

    $id_maquina = resolverIdMaquina($pdo, $maquina);
    $id_tipo = resolverIdDefinicion($pdo, $tipo, 1);
    $id_prioridad = resolverIdDefinicion($pdo, $prioridad, 2);

    $stmtUpdate = $pdo->prepare(
        'UPDATE ordenes_trabajo 
         SET id_maquina = :id_maquina, 
             id_tipo = :id_tipo, 
             id_prioridad = :id_prioridad, 
             observaciones = :observaciones 
         WHERE id_orden = :id_orden'
    );

    $ok = $stmtUpdate->execute([
        ':id_maquina' => $id_maquina,
        ':id_tipo' => $id_tipo,
        ':id_prioridad' => $id_prioridad,
        ':observaciones' => $descripcion,
        ':id_orden' => $id_orden
    ]);

    if (!$ok) {
        responderError('Error al modificar la orden.', 500);
    }

    responderJson([
        'success' => true,
        'msg' => 'Orden modificada correctamente.'
    ]);
}

// ==============================================================================
// ENDPOINT: DELETE /ordenes.php
// DESCRIPCIÓN: Elimina una orden de trabajo
// PARÁMETROS (JSON):
//   - id_orden (requerido): ID de la orden a eliminar
// ==============================================================================
function eliminarOrden(PDO $pdo): void
{
    $body = json_decode(file_get_contents('php://input'), true);

    if (!is_array($body)) {
        responderError('El cuerpo de la peticion debe ser JSON valido.');
    }

    $id_orden = (int)($body['id_orden'] ?? 0);
    if ($id_orden <= 0) {
        responderError('El ID de la orden es obligatorio.');
    }

    // Verificar si existe la orden
    $stmt = $pdo->prepare('SELECT id_orden FROM ordenes_trabajo WHERE id_orden = :id');
    $stmt->execute([':id' => $id_orden]);
    if (!$stmt->fetch()) {
        responderError('La orden de trabajo no existe.', 404);
    }

    $stmtDelete = $pdo->prepare('DELETE FROM ordenes_trabajo WHERE id_orden = :id');
    $ok = $stmtDelete->execute([':id' => $id_orden]);

    if (!$ok) {
        responderError('Error al eliminar la orden.', 500);
    }

    responderJson([
        'success' => true,
        'msg' => 'Orden eliminada correctamente.'
    ]);
}
