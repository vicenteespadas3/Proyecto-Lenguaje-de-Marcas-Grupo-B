<?php
/**
 * ============================================================================
 * API Endpoint: /api/activos/archivos.php
 * ============================================================================
 * Propósito:
 *   Proporciona un servicio RESTful para la gestión (CRUD) de los archivos
 *   adjuntos de la aplicación. Estos archivos suelen ser manuales, fichas técnicas,
 *   garantías u otros documentos asociados a las máquinas del taller.
 *
 * Funciones Principales:
 *   - GET: Obtener un archivo por ID o listar todos los archivos ordenados de forma descendente.
 *   - POST: Registrar un nuevo archivo y asociarlo a una máquina.
 *   - PUT: Actualizar los datos de un archivo existente.
 *   - DELETE: Eliminar un archivo de la base de datos.
 *
 * Dependencias:
 *   - ../config/helpers.php: Proporciona funciones de respuesta HTTP (respuestaOk, respuestaError),
 *     cabeceras CORS obligatorias y utilidades de sesión.
 *   - ../config/db_config.php: Permite la conexión a la base de datos MariaDB/MySQL.
 * ============================================================================
 */

require_once '../config/helpers.php';
require_once '../config/db_config.php';

// Establecer la conexión con la base de datos
$conn = conectar();

// Obtener el método HTTP de la petición (GET, POST, PUT o DELETE)
$metodo = $_SERVER['REQUEST_METHOD'];

/**
 * ----------------------------------------------------------------------------
 * MÉTODO GET - Lectura / Consulta de Archivos
 * ----------------------------------------------------------------------------
 * Permite dos comportamientos:
 *   1. Si se proporciona un 'id' por parámetro en la URL (?id=X):
 *      Obtiene los datos detallados de un archivo específico.
 *   2. Si NO se proporciona un 'id':
 *      Obtiene la lista completa de todos los archivos adjuntos ordenados por
 *      id_archivo de forma descendente (los más recientes primero).
 *
 * Ambos casos realizan un LEFT JOIN con la tabla 'maquinas' para obtener el
 * nombre de la máquina a la que está asociado el archivo.
 */
if ($metodo === 'GET') {
    // Caso 1: Obtener un archivo específico por ID
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        
        // Preparar la consulta para evitar inyecciones SQL
        $stmt = $conn->prepare("SELECT a.id_archivo, a.nombre_archivo, a.ruta_archivo as tipo, a.id_referencia as id_maquina, a.fecha_subida, m.nombre as maquina FROM archivos_adjuntos a LEFT JOIN maquinas m ON a.id_referencia = m.id_maquina WHERE a.id_archivo = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $archivo = $res->fetch_assoc();
        $stmt->close();
        
        // Devolver la respuesta según el resultado
        if ($archivo) {
            respuestaOk(['archivo' => $archivo]);
        } else {
            respuestaError('Archivo no encontrado.', 404);
        }
    } 
    // Caso 2: Obtener todos los archivos registrados
    else {
        /**
         * Obtiene todos los archivos adjuntos ordenados por ID de forma descendente.
         * Realiza un LEFT JOIN con la tabla maquinas para incluir el nombre de la máquina asociada a cada archivo.
         */
        $res = $conn->query("SELECT a.id_archivo, a.nombre_archivo, a.ruta_archivo as tipo, a.id_referencia as id_maquina, a.fecha_subida, m.nombre as maquina FROM archivos_adjuntos a LEFT JOIN maquinas m ON a.id_referencia = m.id_maquina ORDER BY a.id_archivo DESC");
        
        if (!$res) {
            respuestaError('Error BD: ' . $conn->error, 500);
        }
        
        $archivos = [];
        // Recorrer los registros devueltos y almacenarlos en un array
        while ($f = $res->fetch_assoc()) {
            $archivos[] = $f;
        }
        
        // Devolver la lista completa en formato JSON
        respuestaOk(['archivos' => $archivos]);
    }
}

/**
 * ----------------------------------------------------------------------------
 * MÉTODO POST - Registro / Alta de un Nuevo Archivo
 * ----------------------------------------------------------------------------
 * Recibe un payload JSON con la información básica para registrar un archivo:
 *   - nombre_archivo (string, obligatorio): Nombre identificativo del archivo.
 *   - tipo (string, opcional, por defecto 'PDF'): El tipo o formato del archivo (p. ej., PDF, JPG, PNG).
 *   - id_maquina (int/null, opcional): ID de la máquina de la tabla 'maquinas' a la cual se asocia.
 *   - fecha_subida (string, opcional, por defecto la fecha actual): Fecha en la que se registra.
 *
 * Validaciones:
 *   - El nombre del archivo no puede estar vacío.
 *   - No debe existir ya un archivo con el mismo nombre en la base de datos.
 */
if ($metodo === 'POST') {
    // Decodificar el cuerpo de la petición en formato JSON
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // Sanitizar y asignar parámetros de entrada
    $nombre = trim($b['nombre_archivo'] ?? '');
    $tipo = trim($b['tipo'] ?? 'PDF');
    $id_maquina = !empty($b['id_maquina']) ? (int)$b['id_maquina'] : null;
    $fecha = trim($b['fecha_subida'] ?? date('Y-m-d'));

    // Validación: Nombre de archivo obligatorio
    if (!$nombre) {
        respuestaError('El nombre del archivo es obligatorio.');
    }

    // Validación: Verificar si ya existe un archivo con el mismo nombre para evitar duplicados
    $chk = $conn->prepare("SELECT id_archivo FROM archivos_adjuntos WHERE nombre_archivo=?");
    $chk->bind_param("s", $nombre); 
    $chk->execute(); 
    $chk->store_result();
    
    if ($chk->num_rows > 0) { 
        $chk->close(); 
        $conn->close(); 
        respuestaError('Ya existe un archivo con ese nombre.'); 
    }
    $chk->close();

    /**
     * Inserta un nuevo registro de archivo en la base de datos.
     * Se asume que el tipo de referencia siempre es 'maquina'.
     */
    $stmt = $conn->prepare("INSERT INTO archivos_adjuntos (nombre_archivo, ruta_archivo, id_referencia, tipo_referencia, fecha_subida) VALUES (?, ?, ?, 'maquina', ?)");
    $stmt->bind_param("ssis", $nombre, $tipo, $id_maquina, $fecha);
    
    // Ejecutar la inserción y devolver respuesta con el ID del nuevo archivo
    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        $stmt->close();
        respuestaOk(['msg' => 'Archivo registrado con éxito.', 'id_archivo' => $id]);
    } else {
        respuestaError('Error al crear: ' . $stmt->error, 500);
    }
}

/**
 * ----------------------------------------------------------------------------
 * MÉTODO PUT - Edición / Modificación de un Archivo Existente
 * ----------------------------------------------------------------------------
 * Recibe un payload JSON con los campos actualizados de un archivo:
 *   - id_archivo (int, obligatorio): ID del archivo que se desea modificar.
 *   - nombre_archivo (string, obligatorio): Nuevo nombre identificativo del archivo.
 *   - tipo (string, opcional, por defecto 'PDF'): Nuevo formato o tipo.
 *   - id_maquina (int/null, opcional): Nuevo ID de máquina asociada.
 *
 * Validaciones:
 *   - ID de archivo válido y mayor que cero.
 *   - Nombre del archivo no vacío.
 *   - No debe existir otro archivo con el mismo nombre en la BD (excluyendo el archivo que se está modificando).
 */
if ($metodo === 'PUT') {
    // Decodificar el cuerpo de la petición JSON
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // Obtener y asignar campos recibidos
    $id = (int)($b['id_archivo'] ?? 0);
    $nombre = trim($b['nombre_archivo'] ?? '');
    $tipo = trim($b['tipo'] ?? 'PDF');
    $id_maquina = !empty($b['id_maquina']) ? (int)$b['id_maquina'] : null;

    // Validación de integridad básica de la petición
    if ($id <= 0) {
        respuestaError('ID no válido.');
    }
    if (!$nombre) {
        respuestaError('El nombre del archivo es obligatorio.');
    }

    // Validación: Verificar si existe otro archivo diferente que ya use ese mismo nombre
    $chk = $conn->prepare("SELECT id_archivo FROM archivos_adjuntos WHERE nombre_archivo=? AND id_archivo!=?");
    $chk->bind_param("si", $nombre, $id); 
    $chk->execute(); 
    $chk->store_result();
    
    if ($chk->num_rows > 0) { 
        $chk->close(); 
        $conn->close(); 
        respuestaError('Ya existe otro archivo con ese nombre.'); 
    }
    $chk->close();

    /**
     * Actualiza los datos de un archivo existente identificado por su id_archivo.
     * Modifica el nombre, la ruta (tipo) y la referencia a la máquina.
     */
    $stmt = $conn->prepare("UPDATE archivos_adjuntos SET nombre_archivo=?, ruta_archivo=?, id_referencia=? WHERE id_archivo=?");
    $stmt->bind_param("ssii", $nombre, $tipo, $id_maquina, $id);
    
    // Ejecutar la actualización e informar del éxito
    if ($stmt->execute()) {
        $stmt->close();
        respuestaOk(['msg' => 'Archivo actualizado.']);
    } else {
        respuestaError('Error al actualizar: ' . $stmt->error, 500);
    }
}

/**
 * ----------------------------------------------------------------------------
 * MÉTODO DELETE - Eliminación de un Archivo
 * ----------------------------------------------------------------------------
 * Recibe un payload JSON con el ID del archivo a eliminar:
 *   - id_archivo (int, obligatorio): ID del archivo que se va a borrar.
 *
 * Validaciones:
 *   - El ID de archivo debe ser válido y mayor que cero.
 */
if ($metodo === 'DELETE') {
    // Decodificar el cuerpo de la petición JSON
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id_archivo'] ?? 0);

    // Validación del ID
    if ($id <= 0) {
        respuestaError('ID no válido.');
    }

    /**
     * Elimina un archivo de la base de datos basándose en su id_archivo.
     */
    $stmt = $conn->prepare("DELETE FROM archivos_adjuntos WHERE id_archivo=?");
    $stmt->bind_param("i", $id);
    
    // Ejecutar borrado y responder
    if ($stmt->execute()) {
        $stmt->close();
        respuestaOk(['msg' => 'Archivo eliminado.']);
    } else {
        respuestaError('Error al eliminar: ' . $stmt->error, 500);
    }
}

// Cerrar conexión a la base de datos al finalizar el script
$conn->close();

// Si se recibe un método HTTP diferente a GET, POST, PUT o DELETE, lanzar un error 405 (Método no soportado)
respuestaError('Método no soportado.', 405);
