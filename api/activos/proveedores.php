<?php
require_once '../config/helpers.php';
require_once '../config/db_config.php';

$conn = conectar();
$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET (Listar o Buscar) ───────────────────────────────────
if ($metodo === 'GET') {
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
        if ($proveedor) respuestaOk(['proveedor' => $proveedor]);
        else respuestaError('Proveedor no encontrado.', 404);
    } else {
        /**
         * Obtiene todos los proveedores registrados, ordenados de forma descendente por id_proveedor.
         * Mapea algunos campos con alias ('nombre' como 'empresa', 'direccion' como 'equipos').
         */
        $res = $conn->query("SELECT id_proveedor, nombre as empresa, contacto, telefono, email, direccion as equipos FROM proveedores_maquinas ORDER BY id_proveedor DESC");
        if (!$res) respuestaError('Error BD: ' . $conn->error, 500);
        $proveedores = [];
        while ($f = $res->fetch_assoc()) $proveedores[] = $f;
        respuestaOk(['proveedores' => $proveedores]);
    }
}

// ── POST (Alta) ──────────────────────────────────────────────
if ($metodo === 'POST') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $empresa = trim($b['empresa'] ?? '');
    $contacto = trim($b['contacto'] ?? '');
    $telefono = trim($b['telefono'] ?? '');
    $email = trim($b['email'] ?? '');
    $equipos = trim($b['equipos'] ?? '');

    if (!$empresa) respuestaError('El nombre de la empresa es obligatorio.');

    // Verificar si ya existe un proveedor con el mismo nombre de empresa
    $chk = $conn->prepare("SELECT id_proveedor FROM proveedores_maquinas WHERE nombre=?");
    $chk->bind_param("s", $empresa); $chk->execute(); $chk->store_result();
    if ($chk->num_rows > 0) { $chk->close(); $conn->close(); respuestaError('Ya existe un proveedor con ese nombre de empresa.'); }
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

// ── PUT (Modificación) ───────────────────────────────────────
if ($metodo === 'PUT') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id_proveedor'] ?? 0);
    $empresa = trim($b['empresa'] ?? '');
    $contacto = trim($b['contacto'] ?? '');
    $telefono = trim($b['telefono'] ?? '');
    $email = trim($b['email'] ?? '');
    $equipos = trim($b['equipos'] ?? '');

    if ($id <= 0) respuestaError('ID de proveedor no válido.');
    if (!$empresa) respuestaError('El nombre de la empresa es obligatorio.');

    // Verificar si ya existe otro proveedor con el mismo nombre de empresa (excluyendo el actual)
    $chk = $conn->prepare("SELECT id_proveedor FROM proveedores_maquinas WHERE nombre=? AND id_proveedor!=?");
    $chk->bind_param("si", $empresa, $id); $chk->execute(); $chk->store_result();
    if ($chk->num_rows > 0) { $chk->close(); $conn->close(); respuestaError('Ya existe otro proveedor con ese nombre de empresa.'); }
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

// ── DELETE (Baja) ────────────────────────────────────────────
if ($metodo === 'DELETE') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id_proveedor'] ?? 0);

    if ($id <= 0) respuestaError('ID de proveedor no válido.');

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

$conn->close();
respuestaError('Método no soportado.', 405);
