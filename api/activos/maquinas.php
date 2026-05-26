<?php
/**
 * ============================================================================
 * API Endpoint: /api/activos/maquinas.php
 * ============================================================================
 * Propósito:
 *   Proporciona un servicio RESTful para la gestión integral (CRUD) de las
 *   máquinas del taller. Permite registrar nuevas máquinas, listar las existentes,
 *   actualizar sus estados u otros campos, y eliminarlas controlando la integridad
 *   referencial en la base de datos.
 *
 * Referencia en la web:
 *   - Pantalla "Máquinas" (HTML/activosMenu/maquinas.html).
 *   - Funciones llamadas mediante fetch() en Js/maquinas.js (si existe) o en la propia vista.
 * Funciones Principales:
 *   - GET: Obtener una máquina por ID o listar todas las máquinas de forma descendente.
 *   - POST: Registrar una nueva máquina con validación de número de serie único.
 *   - PUT: Actualizar todos los detalles de una máquina existente.
 *   - DELETE: Dar de baja/eliminar una máquina, validando que no esté en mantenimiento
 *             y que no tenga incidencias activas asociadas.
 *
 * Dependencias:
 *   - ../config/helpers.php: Cabeceras CORS, respuestas estructuradas (respuestaOk, respuestaError), etc.
 *   - ../config/db_config.php: Conectividad y juego de caracteres UTF-8 con la base de datos.
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
 * MÉTODO GET - Lectura / Consulta de Máquinas
 * ----------------------------------------------------------------------------
 * Permite:
 *   1. Si se provee un 'id' (?id=X):
 *      Retorna los detalles de la máquina correspondiente a ese ID.
 *   2. Si NO se provee un 'id':
 *      Retorna un array con todas las máquinas de la tabla ordenadas por
 *      id_maquina descendentemente.
 */
if ($metodo === 'GET') {
    // Caso 1: Obtener una máquina específica por ID
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        
        $stmt = $conn->prepare("SELECT * FROM maquinas WHERE id_maquina = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $maquina = $res->fetch_assoc();
        $stmt->close();
        
        if ($maquina) {
            respuestaOk(['maquina' => $maquina]);
        } else {
            respuestaError('Máquina no encontrada.', 404);
        }
    } 
    // Caso 2: Obtener la lista de todas las máquinas
    else {
        $res = $conn->query("SELECT * FROM maquinas ORDER BY id_maquina DESC");
        if (!$res) {
            respuestaError('Error BD: ' . $conn->error, 500);
        }
        
        $maquinas = [];
        while ($f = $res->fetch_assoc()) {
            $maquinas[] = $f;
        }
        respuestaOk(['maquinas' => $maquinas]);
    }
}

/**
 * ----------------------------------------------------------------------------
 * MÉTODO POST - Registro / Alta de una Máquina
 * ----------------------------------------------------------------------------
 * Recibe un payload JSON con las propiedades de la nueva máquina:
 *   - nombre (string, obligatorio): Nombre identificativo de la máquina.
 *   - tipo (string, opcional): Categoría o tipo de máquina (p. ej., Elevador, Torno).
 *   - numero_serie (string, opcional): Número de serie único del fabricante.
 *   - ubicacion (string, opcional): Ubicación física dentro del taller.
 *   - fabricante (string, opcional): Marca o fabricante.
 *   - estado (string, opcional, por defecto 'disponible'): Estado operativo (disponible, en_mantenimiento, averiada, etc.).
 *   - proveedor_id (int/null, opcional): ID del proveedor asociado de la tabla 'proveedores_maquinas'.
 *
 * Validaciones:
 *   - El nombre de la máquina es obligatorio y no puede estar vacío.
 *   - Si se especifica un número de serie, este no debe estar duplicado en la BD
 *     (se excluyen cadenas vacías en la verificación de unicidad).
 */
if ($metodo === 'POST') {
    // Decodificar el JSON de la petición
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // Obtener y limpiar los parámetros
    $nombre = trim($b['nombre'] ?? '');
    $tipo = trim($b['tipo'] ?? '');
    $numero_serie = trim($b['numero_serie'] ?? '');
    $ubicacion = trim($b['ubicacion'] ?? '');
    $fabricante = trim($b['fabricante'] ?? '');
    $estado = trim($b['estado'] ?? 'disponible');
    $proveedor_id = !empty($b['proveedor_id']) ? (int)$b['proveedor_id'] : null;

    // Validación obligatoria
    if (!$nombre) {
        respuestaError('El nombre de la máquina es obligatorio.');
    }

    // Validación de unicidad de número de serie (solo si no está vacío)
    $chk = $conn->prepare("SELECT id_maquina FROM maquinas WHERE numero_serie=? AND numero_serie != ''");
    $chk->bind_param("s", $numero_serie); 
    $chk->execute(); 
    $chk->store_result();
    
    if ($chk->num_rows > 0) { 
        $chk->close(); 
        $conn->close(); 
        respuestaError('Ya existe una máquina con ese número de serie.'); 
    }
    $chk->close();

    // Insertar la máquina. Se inicializa el campo 'activa' en 1 y 'fecha_registro' al momento actual (NOW())
    $stmt = $conn->prepare("INSERT INTO maquinas (nombre, tipo, numero_serie, ubicacion, fabricante, estado, proveedor_id, activa, fecha_registro) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
    $stmt->bind_param("ssssssi", $nombre, $tipo, $numero_serie, $ubicacion, $fabricante, $estado, $proveedor_id);
    
    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        $stmt->close();
        respuestaOk(['msg' => 'Máquina creada con éxito.', 'id_maquina' => $id]);
    } else {
        respuestaError('Error al crear: ' . $stmt->error, 500);
    }
}

/**
 * ----------------------------------------------------------------------------
 * MÉTODO PUT - Edición / Modificación de una Máquina Existente
 * ----------------------------------------------------------------------------
 * Recibe un payload JSON para actualizar una máquina específica:
 *   - id_maquina (int, obligatorio): ID de la máquina a modificar.
 *   - nombre (string, obligatorio): Nuevo nombre.
 *   - tipo, numero_serie, ubicacion, fabricante, estado, proveedor_id (opcionales).
 *
 * Validaciones:
 *   - ID de la máquina válido y mayor que cero.
 *   - Nombre no vacío.
 */
if ($metodo === 'PUT') {
    // Decodificar el JSON de la petición
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // Obtener campos de la máquina
    $id = (int)($b['id_maquina'] ?? 0);
    $nombre = trim($b['nombre'] ?? '');
    $tipo = trim($b['tipo'] ?? '');
    $numero_serie = trim($b['numero_serie'] ?? '');
    $ubicacion = trim($b['ubicacion'] ?? '');
    $fabricante = trim($b['fabricante'] ?? '');
    $estado = trim($b['estado'] ?? 'disponible');
    $proveedor_id = !empty($b['proveedor_id']) ? (int)$b['proveedor_id'] : null;

    // Validar parámetros mínimos obligatorios
    if ($id <= 0) {
        respuestaError('ID de máquina no válido.');
    }
    if (!$nombre) {
        respuestaError('El nombre de la máquina es obligatorio.');
    }

    // Actualizar la tupla en la base de datos
    $stmt = $conn->prepare("UPDATE maquinas SET nombre=?, tipo=?, numero_serie=?, ubicacion=?, fabricante=?, estado=?, proveedor_id=? WHERE id_maquina=?");
    $stmt->bind_param("ssssssii", $nombre, $tipo, $numero_serie, $ubicacion, $fabricante, $estado, $proveedor_id, $id);
    
    if ($stmt->execute()) {
        $stmt->close();
        respuestaOk(['msg' => 'Máquina actualizada correctamente.']);
    } else {
        respuestaError('Error al actualizar: ' . $stmt->error, 500);
    }
}

/**
 * ----------------------------------------------------------------------------
 * MÉTODO DELETE - Baja / Eliminación de una Máquina
 * ----------------------------------------------------------------------------
 * Recibe un payload JSON con el ID de la máquina a borrar:
 *   - id_maquina (int, obligatorio): ID del registro a eliminar.
 *
 * Control de Integridad de Negocio (Crítico):
 *   1. No se puede eliminar una máquina si está en estado 'en_mantenimiento'.
 *   2. No se puede eliminar una máquina si tiene alguna incidencia activa
 *      (cuyo estado en la tabla 'incidencias' sea diferente a 'cerrada').
 */
if ($metodo === 'DELETE') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id_maquina'] ?? 0);

    if ($id <= 0) {
        respuestaError('ID de máquina no válido.');
    }

    // Control de Integridad 1: Verificar que la máquina no esté actualmente bajo un mantenimiento activo
    $chk = $conn->prepare("SELECT estado FROM maquinas WHERE id_maquina=?");
    $chk->bind_param("i", $id); 
    $chk->execute();
    $m_data = $chk->get_result()->fetch_assoc(); 
    $chk->close();
    
    if ($m_data && $m_data['estado'] === 'en_mantenimiento') {
        respuestaError('No se puede eliminar una máquina en mantenimiento.');
    }

    // Control de Integridad 2: Garantizar que no haya incidencias pendientes (no cerradas) asociadas a esta máquina
    $chk2 = $conn->prepare("SELECT COUNT(*) as t FROM incidencias WHERE id_maquina=? AND estado != 'cerrada'");
    $chk2->bind_param("i", $id); 
    $chk2->execute();
    $t_incidencias = $chk2->get_result()->fetch_assoc()['t'];
    $chk2->close();
    
    if ($t_incidencias > 0) {
        respuestaError('No se puede eliminar: la máquina tiene incidencias activas.');
    }

    // Si supera los controles, se procede al DELETE físico
    $stmt = $conn->prepare("DELETE FROM maquinas WHERE id_maquina=?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $stmt->close();
        respuestaOk(['msg' => 'Máquina eliminada correctamente.']);
    } else {
        respuestaError('Error al eliminar: ' . $stmt->error, 500);
    }
}

// Cerrar la conexión
$conn->close();

// Si se utiliza un método no permitido
respuestaError('Método no soportado.', 405);
