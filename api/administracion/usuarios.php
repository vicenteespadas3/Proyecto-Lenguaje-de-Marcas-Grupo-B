<?php
/**
 * PROYECTO: ERP Robots - Sistema de Gestión
 * AUTORA: Guadalupe MGM
 * DESCRIPCIÓN: CRUD completo de usuarios (Altas, Bajas, Modificaciones y Listados).
 * * USO TÉCNICO:
 * - Endpoints: GET (listar), POST (crear), PUT (editar), DELETE (borrar).
 */

// ============================================================
//  api/usuarios.php  —  CRUD adaptado a la BBDD oficial
// ============================================================
require_once '../config/helpers.php';
require_once '../config/db_config.php';

$yo = 'admin'; // Para pruebas, luego se controlaría por sesión
$conn = conectar();
$m = $_SERVER['REQUEST_METHOD'];

// ── GET (Listar o uno solo) ───────────────────────────────────
if ($m === 'GET') {
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        // Adaptado: id_usuario, password_hash, rol
        $s = $conn->prepare("SELECT id_usuario, email, nombre, apellidos, rol, activo FROM usuarios WHERE id_usuario=?");
        $s->bind_param("i", $id); $s->execute();
        $f = $s->get_result()->fetch_assoc(); $s->close(); $conn->close();
        if (!$f) respuestaError('No encontrado.', 404);
        respuestaOk(['usuario' => $f]);
    } else {
        // Listado general
        $r = $conn->query("SELECT id_usuario, email, nombre, apellidos, rol, activo FROM usuarios ORDER BY apellidos, nombre");
        $lista = [];
        while ($f = $r->fetch_assoc()) { $lista[] = $f; }
        $conn->close();
        respuestaOk(['usuarios' => $lista, 'total' => count($lista)]);
    }
}

// ── POST (Alta de usuario) ────────────────────────────────────
if ($m === 'POST') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $email     = trim($b['email'] ?? ''); // Usamos email según tu BBDD
    $password  = trim($b['password'] ?? '');
    $nombre    = trim($b['nombre'] ?? '');
    $apellidos = trim($b['apellidos'] ?? '');
    $rol       = trim($b['rol'] ?? 'tecnico');

    if (!$email || !$password) respuestaError('Email y contraseña obligatorios.');

    // Verificar si el email ya existe
    $chk = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email=?");
    $chk->bind_param("s", $email); $chk->execute(); $chk->store_result();
    if ($chk->num_rows > 0) { $chk->close(); $conn->close(); respuestaError('El email ya está registrado.'); }
    $chk->close();

    // Insertar con password_hash (columna oficial)
    $s = $conn->prepare("INSERT INTO usuarios (email, password_hash, nombre, apellidos, rol, activo, fecha_creacion) VALUES (?, ?, ?, ?, ?, 1, NOW())");
    $s->bind_param("sssss", $email, $password, $nombre, $apellidos, $rol);
    $ok = $s->execute(); $nid = $conn->insert_id; $s->close(); $conn->close();

    if (!$ok) respuestaError('Error al crear el usuario.', 500);
    respuestaOk(['msg' => "Usuario '$email' creado correctamente.", 'id' => $nid]);
}

// ── PUT (Modificación) ────────────────────────────────────────
if ($m === 'PUT') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id_usuario'] ?? 0);
    $email     = trim($b['email'] ?? '');
    $nombre    = trim($b['nombre'] ?? '');
    $apellidos = trim($b['apellidos'] ?? '');
    $rol       = trim($b['rol'] ?? '');
    $newpass   = trim($b['password'] ?? '');

    if ($id <= 0) respuestaError('ID no válido.');

    if ($newpass !== '') {
        $s = $conn->prepare("UPDATE usuarios SET email=?, password_hash=?, nombre=?, apellidos=?, rol=? WHERE id_usuario=?");
        $s->bind_param("sssssi", $email, $newpass, $nombre, $apellidos, $rol, $id);
    } else {
        $s = $conn->prepare("UPDATE usuarios SET email=?, nombre=?, apellidos=?, rol=? WHERE id_usuario=?");
        $s->bind_param("ssssi", $email, $nombre, $apellidos, $rol, $id);
    }
    
    $ok = $s->execute(); $s->close(); $conn->close();
    if (!$ok) respuestaError('Error al actualizar.', 500);
    respuestaOk(['msg' => 'Usuario actualizado correctamente.']);
}

// ── DELETE (Baja) ─────────────────────────────────────────────
if ($m === 'DELETE') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id_usuario'] ?? 0);

    $chk = $conn->prepare("SELECT email FROM usuarios WHERE id_usuario=?");
    $chk->bind_param("i", $id); $chk->execute();
    $f = $chk->get_result()->fetch_assoc(); $chk->close();

    if (!$f) { $conn->close(); respuestaError('Usuario no encontrado.', 404); }

    $s = $conn->prepare("DELETE FROM usuarios WHERE id_usuario=?");
    $s->bind_param("i", $id); $ok = $s->execute(); $s->close(); $conn->close();
    
    if (!$ok) respuestaError('Error al eliminar.', 500);
    respuestaOk(['msg' => "Usuario '{$f['email']}' eliminado."]);
}

respuestaError('Método no soportado.', 405);