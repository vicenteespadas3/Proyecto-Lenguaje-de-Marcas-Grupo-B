<?php
/**
 * ============================================================================
 * API Endpoint: /api/activos/proveedores.php
 * ============================================================================
 * Propósito:
 *   Proporciona un servicio RESTful para la gestión (CRUD) de los proveedores
 *   de maquinaria del taller. Permite listar proveedores, dar de alta nuevos
 *   proveedores de empresas externas, actualizar su información de contacto,
 *   o eliminarlos del sistema.
 *
 * Mapeo de Campos / Alias en Consultas (Crítico para compatibilidad frontend):
 *   - 'nombre' en la base de datos se expone como 'empresa' en la API.
 *   - 'direccion' en la base de datos se expone como 'equipos' en la API.
 *   Los campos 'contacto', 'telefono' y 'email' se mapean directamente.
 *
 * Funciones Principales:
 *   - GET: Obtener un proveedor específico por su ID o listar todos los proveedores.
 *   - POST: Registrar un nuevo proveedor con validación de nombre de empresa único.
 *   - PUT: Actualizar los datos de un proveedor con validación de unicidad de nombre.
 *   - DELETE: Eliminar un proveedor de la base de datos por su ID.
 *
 * Dependencias:
 *   - ../config/helpers.php: Respuestas estructuradas JSON (respuestaOk, respuestaError) e interceptador de CORS.
 *   - ../config/db_config.php: Configuración y función de conexión a la base de datos centralizada.
 * ============================================================================
 */

require_once '../config/helpers.php';
require_once '../config/db_config.php';

// Conectar a la base de datos
$conn = conectar();

// Obtener el método de la solicitud (GET, POST, PUT, DELETE)
$metodo = $_SERVER['REQUEST_METHOD'];

/**
 * ----------------------------------------------------------------------------
 * MÉTODO GET - Lectura / Consulta de Proveedores
 * ----------------------------------------------------------------------------
 * Permite:
 *   1. Si se provee un 'id' (?id=X):
 *      Retorna la información del proveedor correspondiente.
 *   2. Si NO se provee un 'id':
 *      Retorna un listado de todos los proveedores ordenados descendentemente por ID.
 *
 * Nota: En las consultas SELECT se aplican los alias obligatorios:
 *   `nombre` AS `empresa` e `direccion` AS `equipos` para concordar con la API del frontend.
 */
if ($metodo === 'GET') {
    // Caso 1: Obtener un proveedor específico por ID
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        /**
         * Obtiene los datos de un proveedor específico filtrado por id_proveedor.
         * Mapea algunos campos con alias ('nombre' como 'empresa', 'direccion' como 'equipos').
         */
        $stmt = $conn->prepare("SELECT id_proveedor, nombre as empresa, contacto, telefono, email, direccion as equipos FROM proveedores_maquinas WHERE id_proveedor = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $proveedor = $res->fetch_assoc();
        $stmt->close();
        
        if ($proveedor) {
            respuestaOk(['proveedor' => $proveedor]);
        } else {
            respuestaError('Proveedor no encontrado.', 404);
        }
    } 
    // Caso 2: Listar todos los proveedores
    else {
        /**
         * Obtiene todos los proveedores registrados, ordenados de forma descendente por id_proveedor.
         * Mapea algunos campos con alias ('nombre' como 'empresa', 'direccion' como 'equipos').
         */
        $res = $conn->query("SELECT id_proveedor, nombre as empresa, contacto, telefono, email, direccion as equipos FROM proveedores_maquinas ORDER BY id_proveedor DESC");
        if (!$res) {
            respuestaError('Error BD: ' . $conn->error, 500);
        }
        
        $proveedores = [];
        while ($f = $res->fetch_assoc()) {
            $proveedores[] = $f;
        }
        respuestaOk(['proveedores' => $proveedores]);
    }
}

/**
 * ----------------------------------------------------------------------------
 * MÉTODO POST - Alta / Registro de un Proveedor
 * ----------------------------------------------------------------------------
 * Recibe un payload JSON con la información del proveedor:
 *   - empresa (string, obligatorio): Nombre de la empresa proveedora (mapea a 'nombre' en BD).
 *   - contacto (string, opcional): Nombre de la persona de contacto.
 *   - telefono (string, opcional): Número telefónico.
 *   - email (string, opcional): Dirección de correo electrónico.
 *   - equipos (string, opcional): Descripción de los equipos suministrados (mapea a 'direccion' en BD).
 *
 * Validaciones:
 *   - El nombre de la empresa ('empresa') es requerido y no puede estar en blanco.
 *   - El nombre de la empresa debe ser único; no puede haber dos proveedores con el mismo nombre.
 */
if ($metodo === 'POST') {
    // Decodificar payload JSON
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // Mapear campos de JSON a variables del script
    $empresa = trim($b['empresa'] ?? '');
    $contacto = trim($b['contacto'] ?? '');
    $telefono = trim($b['telefono'] ?? '');
    $email = trim($b['email'] ?? '');
    $equipos = trim($b['equipos'] ?? '');

    // Validación obligatoria
    if (!$empresa) {
        respuestaError('El nombre de la empresa es obligatorio.');
    }

    // Validación de unicidad de nombre de empresa
    $chk = $conn->prepare("SELECT id_proveedor FROM proveedores_maquinas WHERE nombre=?");
    $chk->bind_param("s", $empresa); 
    $chk->execute(); 
    $chk->store_result();
    
    if ($chk->num_rows > 0) { 
        $chk->close(); 
        $conn->close(); 
        respuestaError('Ya existe un proveedor con ese nombre de empresa.'); 
    }
    $chk->close();

    /**
     * Inserta un nuevo proveedor en la base de datos (proveedores_maquinas) con los datos recibidos.
     */
    $stmt = $conn->prepare("INSERT INTO proveedores_maquinas (nombre, contacto, telefono, email, direccion) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $empresa, $contacto, $telefono, $email, $equipos);
    
    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        $stmt->close();
        respuestaOk(['msg' => 'Proveedor creado con éxito.', 'id_proveedor' => $id]);
    } else {
        respuestaError('Error al crear: ' . $stmt->error, 500);
    }
}

/**
 * ----------------------------------------------------------------------------
 * MÉTODO PUT - Edición / Actualización de Proveedor
 * ----------------------------------------------------------------------------
 * Recibe un payload JSON para actualizar un proveedor existente:
 *   - id_proveedor (int, obligatorio): ID del proveedor.
 *   - empresa (string, obligatorio): Nuevo nombre de la empresa (mapea a 'nombre' en BD).
 *   - contacto, telefono, email, equipos (opcionales).
 *
 * Validaciones:
 *   - ID del proveedor válido y mayor que cero.
 *   - Nombre de empresa no vacío.
 *   - El nuevo nombre no debe estar ocupado por otro proveedor (excluyendo el actual).
 */
if ($metodo === 'PUT') {
    // Decodificar payload JSON
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // Obtener parámetros
    $id = (int)($b['id_proveedor'] ?? 0);
    $empresa = trim($b['empresa'] ?? '');
    $contacto = trim($b['contacto'] ?? '');
    $telefono = trim($b['telefono'] ?? '');
    $email = trim($b['email'] ?? '');
    $equipos = trim($b['equipos'] ?? '');

    // Validaciones de entrada
    if ($id <= 0) {
        respuestaError('ID de proveedor no válido.');
    }
    if (!$empresa) {
        respuestaError('El nombre de la empresa es obligatorio.');
    }

    // Verificar si ya existe otro proveedor con el nuevo nombre (excluyendo el actual)
    $chk = $conn->prepare("SELECT id_proveedor FROM proveedores_maquinas WHERE nombre=? AND id_proveedor!=?");
    $chk->bind_param("si", $empresa, $id); 
    $chk->execute(); 
    $chk->store_result();
    
    if ($chk->num_rows > 0) { 
        $chk->close(); 
        $conn->close(); 
        respuestaError('Ya existe otro proveedor con ese nombre de empresa.'); 
    }
    $chk->close();

    /**
     * Actualiza la información de un proveedor existente identificado por id_proveedor.
     */
    $stmt = $conn->prepare("UPDATE proveedores_maquinas SET nombre=?, contacto=?, telefono=?, email=?, direccion=? WHERE id_proveedor=?");
    $stmt->bind_param("sssssi", $empresa, $contacto, $telefono, $email, $equipos, $id);
    
    if ($stmt->execute()) {
        $stmt->close();
        respuestaOk(['msg' => 'Proveedor actualizado correctamente.']);
    } else {
        respuestaError('Error al actualizar: ' . $stmt->error, 500);
    }
}

/**
 * ----------------------------------------------------------------------------
 * MÉTODO DELETE - Baja / Eliminación de Proveedor
 * ----------------------------------------------------------------------------
 * Recibe un payload JSON con el ID del proveedor a eliminar:
 *   - id_proveedor (int, obligatorio): ID del proveedor a borrar físicamente de la BD.
 *
 * Validaciones:
 *   - El ID debe ser válido y mayor que cero.
 */
if ($metodo === 'DELETE') {
    // Decodificar cuerpo JSON
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id_proveedor'] ?? 0);

    if ($id <= 0) {
        respuestaError('ID de proveedor no válido.');
    }

    /**
     * Elimina el registro de un proveedor de la base de datos de acuerdo a su id_proveedor.
     */
    $stmt = $conn->prepare("DELETE FROM proveedores_maquinas WHERE id_proveedor=?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $stmt->close();
        respuestaOk(['msg' => 'Proveedor eliminado correctamente.']);
    } else {
        respuestaError('Error al eliminar: ' . $stmt->error, 500);
    }
}

// Cerrar conexión
$conn->close();

// Método no permitido
respuestaError('Método no soportado.', 405);
