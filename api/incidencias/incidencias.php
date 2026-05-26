<?php
/**
 * ============================================================================
 * API Endpoint: /api/incidencias/incidencias.php
 * ============================================================================
 * Propósito:
 *   Proporciona un servicio RESTful para la gestión (CRUD) de incidencias
 *   reportadas sobre las máquinas del taller. Permite abrir nuevas incidencias,
 *   consultar su estado actual, modificarlas (ej. para registrar soluciones o cerrarlas),
 *   y eliminarlas del sistema.
 *
 * Funciones Principales:
 *   - GET: Obtener una incidencia específica por ID o listar todas las incidencias
 *          ordenadas de forma descendente por fecha de reporte.
 *   - POST: Reportar una nueva incidencia asociada a una máquina (se inicializa
 *           en estado 'abierta' y con fecha actual).
 *   - PUT: Actualizar una incidencia (estado y solución aplicada). Si el nuevo
 *          estado es 'cerrada', establece automáticamente la fecha de resolución a NOW().
 *   - DELETE: Eliminar una incidencia de la base de datos por su ID.
 *
 * Dependencias:
 *   - ../config/helpers.php: Respuestas estandarizadas REST JSON (respuestaOk, respuestaError).
 *   - ../config/db_config.php: Conector centralizado mysqli.
 * ============================================================================
 */

require_once '../config/helpers.php';
require_once '../config/db_config.php';

// Conectar a la base de datos
$conn = conectar();

// Obtener el método HTTP de la petición (GET, POST, PUT, DELETE)
$metodo = $_SERVER['REQUEST_METHOD'];

/**
 * ----------------------------------------------------------------------------
 * MÉTODO GET - Lectura / Consulta de Incidencias
 * ----------------------------------------------------------------------------
 * Permite:
 *   1. Si se proporciona un 'id' por parámetro (?id=X):
 *      Obtiene la información detallada de una incidencia en específico.
 *   2. Si NO se proporciona un 'id':
 *      Obtiene todas las incidencias ordenadas descendentemente por su fecha de reporte.
 *
 * En ambos casos se realiza un LEFT JOIN con la tabla 'maquinas' para incluir
 * el nombre de la máquina asociada en la respuesta (alias 'maquina').
 */
if ($metodo === 'GET') {
    // Caso 1: Obtener una incidencia específica por ID
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        
        $stmt = $conn->prepare("SELECT i.*, m.nombre as maquina FROM incidencias i LEFT JOIN maquinas m ON i.id_maquina = m.id_maquina WHERE i.id_incidencia = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $inc = $res->fetch_assoc();
        $stmt->close();
        
        if ($inc) {
            respuestaOk(['incidencia' => $inc]);
        } else {
            respuestaError('Incidencia no encontrada.', 404);
        }
    } 
    // Caso 2: Obtener el listado de todas las incidencias
    else {
        $query = "SELECT i.*, m.nombre as maquina FROM incidencias i LEFT JOIN maquinas m ON i.id_maquina = m.id_maquina ORDER BY i.fecha_reporte DESC";
        $res = $conn->query($query);
        $incidencias = [];
        
        while ($fila = $res->fetch_assoc()) {
            $incidencias[] = $fila;
        }
        respuestaOk(['incidencias' => $incidencias]);
    }
}

/**
 * ----------------------------------------------------------------------------
 * MÉTODO POST - Registro / Reporte de Nueva Incidencia
 * ----------------------------------------------------------------------------
 * Recibe un payload JSON para abrir una incidencia en una máquina:
 *   - id_maquina (int, obligatorio): ID de la máquina que presenta la avería.
 *   - descripcion (string, obligatorio): Explicación detallada del problema.
 *   - prioridad (string, opcional, por defecto 'Media'): Nivel de urgencia (ej. Baja, Media, Alta).
 *   - tipo (string, opcional, por defecto 'Avería'): Tipo de incidencia (ej. Avería, Mantenimiento preventivo).
 *
 * Lógica de Negocio:
 *   - Se inicializa el estado automáticamente como 'abierta'.
 *   - Se registra la fecha actual utilizando la función NOW() de la base de datos.
 *
 * Validaciones:
 *   - id_maquina debe ser mayor que cero.
 *   - descripcion no debe estar vacía.
 */
if ($metodo === 'POST') {
    // Decodificar el JSON recibido en la petición
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // Asignar parámetros sanitizados
    $id_maquina = (int)($b['id_maquina'] ?? 0);
    $descripcion = trim($b['descripcion_problema'] ?? ($b['descripcion'] ?? ''));
    $tipo = trim($b['tipo'] ?? 'Avería');
    $prioridad = trim($b['prioridad'] ?? 'Media');
    $tipo = trim($b['tipo'] ?? 'Avería');
    
    // Validación obligatoria
    if ($id_maquina <= 0 || !$descripcion) {
        respuestaError('Máquina y descripción son obligatorios.');
    }

    // Insertar la nueva incidencia en la base de datos
    $stmt = $conn->prepare("INSERT INTO incidencias (id_maquina, descripcion, prioridad, tipo, estado, fecha_reporte) VALUES (?, ?, ?, ?, 'abierta', NOW())");
    $stmt->bind_param("isss", $id_maquina, $descripcion, $prioridad, $tipo);
    
    if ($stmt->execute()) {
        respuestaOk(['msg' => 'Incidencia reportada.', 'id' => $stmt->insert_id]);
    } else {
        respuestaError('Error al crear incidencia.');
    }
    $stmt->close();
}

/**
 * ----------------------------------------------------------------------------
 * MÉTODO PUT - Actualización / Cierre de Incidencia
 * ----------------------------------------------------------------------------
 * Recibe un payload JSON con las modificaciones a realizar:
 *   - id_incidencia (int, obligatorio): ID del registro que se va a editar.
 *   - estado (string, obligatorio): Nuevo estado de la incidencia (ej. abierta, en_proceso, cerrada).
 *   - solucion_aplicada (string, opcional): Detalles técnicos del arreglo o solución.
 *
 * Lógica de Negocio:
 *   - Si el estado de la incidencia se actualiza a 'cerrada', la consulta SQL establece
 *     automáticamente el campo `fecha_resolucion` al tiempo actual (NOW()). En caso contrario,
 *     se conserva el valor actual de `fecha_resolucion`.
 *
 * Validaciones:
 *   - El ID de la incidencia debe ser válido y mayor que cero.
 *   - El nuevo estado no puede estar vacío.
 */
if ($metodo === 'PUT') {
    // Decodificar cuerpo de la petición
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // Asignar variables
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

    // Validación obligatoria
    if ($id <= 0 || !$estado) {
        respuestaError('ID y estado obligatorios.');
    }

    // Preparar y ejecutar la consulta de actualización con la lógica condicional para fecha_resolucion
    $stmt = $conn->prepare("UPDATE incidencias SET estado=?, solucion_aplicada=?, fecha_resolucion = IF(? = 'cerrada', NOW(), fecha_resolucion) WHERE id_incidencia=?");
    $stmt->bind_param("sssi", $estado, $solucion, $estado, $id);
    
    if ($stmt->execute()) {
        respuestaOk(['msg' => 'Incidencia actualizada.']);
    } else {
        respuestaError('Error al actualizar.');
    }
    $stmt->close();
}

/**
 * ----------------------------------------------------------------------------
 * MÉTODO DELETE - Eliminación de Incidencia
 * ----------------------------------------------------------------------------
 * Recibe un payload JSON con el identificador de la incidencia:
 *   - id_incidencia (int, obligatorio): ID de la incidencia que se desea borrar.
 *
 * Validaciones:
 *   - El ID debe ser válido y mayor que cero.
 */
if ($metodo === 'DELETE') {
    // Decodificar JSON
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id_incidencia'] ?? 0);
    
    // Validar ID
    if ($id <= 0) {
        respuestaError('ID no válido.');
    }

    // Eliminar físicamente el registro
    $stmt = $conn->prepare("DELETE FROM incidencias WHERE id_incidencia=?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        respuestaOk(['msg' => 'Incidencia eliminada.']);
    } else {
        respuestaError('Error al eliminar.');
    }
    $stmt->close();
    $conn->close();

    if ($ok) {
        respuestaOk(['msg' => 'Incidencia eliminada correctamente']);
    } else {
        respuestaError('Error al eliminar.', 500);
    }
}

// Cerrar conexión
$conn->close();

// Método no permitido
respuestaError('Método no permitido', 405);
