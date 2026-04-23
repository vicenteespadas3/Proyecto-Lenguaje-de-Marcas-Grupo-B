<?php
require_once '../config/helpers.php';
require_once '../config/db_config.php';

$conn = conectar();
$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET (Listar o Buscar) ───────────────────────────────────
if ($metodo === 'GET') {
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $conn->prepare("SELECT id_proveedor, nombre as empresa, contacto, telefono, email, direccion as equipos FROM proveedores_maquinas WHERE id_proveedor = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $proveedor = $res->fetch_assoc();
        $stmt->close();
        if ($proveedor) respuestaOk(['proveedor' => $proveedor]);
        else respuestaError('Proveedor no encontrado.', 404);
    } else {
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
