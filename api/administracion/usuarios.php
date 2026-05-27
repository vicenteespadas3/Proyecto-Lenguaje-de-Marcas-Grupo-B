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
// Referencia en la web:
//   - Pantalla "Gestión de Usuarios del Sistema" (HTML/administracionMenu/usuarios.html)
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
    $email     = trim($b['email'] ?? '');
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

    // HASH de la contraseña antes de guardar
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Insertar con password_hash
    $s = $conn->prepare("INSERT INTO usuarios (email, password_hash, nombre, apellidos, rol, activo, fecha_creacion) VALUES (?, ?, ?, ?, ?, 1, NOW())");
    $s->bind_param("sssss", $email, $hash, $nombre, $apellidos, $rol);
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

    // 1. Verificar duplicados (excluyendo al actual)
    $chk = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email=? AND id_usuario != ?");
    $chk->bind_param("si", $email, $id); $chk->execute(); $chk->store_result();
    if ($chk->num_rows > 0) { $chk->close(); $conn->close(); respuestaError('El email ya está en uso por otro usuario.'); }
    $chk->close();

    // 2. Regla: No se puede quitar el rol de admin si es el último admin
    if ($rol !== 'admin' && $rol !== '') {
        $chk_role = $conn->query("SELECT rol FROM usuarios WHERE id_usuario = $id");
        $old_role = $chk_role->fetch_assoc()['rol'];
        if ($old_role === 'admin') {
            $res_admins = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE rol='admin'");
            if ($res_admins->fetch_assoc()['total'] <= 1) {
                $conn->close();
                respuestaError('No puedes quitarle el rol de administrador al único admin del sistema.');
            }
        }
    }

    if ($newpass !== '') {
        $hash = password_hash($newpass, PASSWORD_DEFAULT);
        $s = $conn->prepare("UPDATE usuarios SET email=?, password_hash=?, nombre=?, apellidos=?, rol=? WHERE id_usuario=?");
        $s->bind_param("sssssi", $email, $hash, $nombre, $apellidos, $rol, $id);
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

    $chk = $conn->prepare("SELECT email, rol, nombre FROM usuarios WHERE id_usuario=?");
    $chk->bind_param("i", $id); $chk->execute();
    $f = $chk->get_result()->fetch_assoc(); $chk->close();

    if (!$f) { $conn->close(); respuestaError('Usuario no encontrado.', 404); }

    // Regla 1: No se puede eliminar el último usuario administrador
    $rol_admin_str = ($f['rol'] === 'admin' || $f['rol'] === 'administrador') ? $f['rol'] : 'admin';
    if ($f['rol'] === 'admin' || $f['rol'] === 'administrador') {
        $res_admins = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE rol='admin' OR rol='administrador'");
        if ($res_admins->fetch_assoc()['total'] <= 1) {
            $conn->close();
            respuestaError('No se puede eliminar el último usuario administrador del sistema.');
        }
    }

    // Regla 2: No se puede eliminar si tiene incidencias asignadas (Verificado: id_tecnico_asignado)
    $chk_inc = $conn->prepare("SELECT COUNT(*) as total FROM incidencias WHERE id_tecnico_asignado = ?");
    $chk_inc->bind_param("i", $id); $chk_inc->execute();
    $total_inc = $chk_inc->get_result()->fetch_assoc()['total'];
    $chk_inc->close();

    if ($total_inc > 0) {
        $conn->close();
        respuestaError("No se puede eliminar: el usuario tiene $total_inc incidencia(s) asignada(s) como técnico.");
    }

    // Regla 3: No se puede eliminar si tiene ejecuciones de mantenimiento (Verificado: id_tecnico en ejecuciones_mantenimiento)
    $chk_ejec = $conn->prepare("SELECT COUNT(*) as total FROM ejecuciones_mantenimiento WHERE id_tecnico = ?");
    $chk_ejec->bind_param("i", $id); $chk_ejec->execute();
    $total_ejec = $chk_ejec->get_result()->fetch_assoc()['total'];
    $chk_ejec->close();

    if ($total_ejec > 0) {
        $conn->close();
        respuestaError("No se puede eliminar: el usuario tiene $total_ejec ejecución(es) de mantenimiento registradas.");
    }

    // Regla 4: No se puede eliminar si es responsable de proyectos o tareas Gantt
    $chk_proy = $conn->prepare("SELECT COUNT(*) as total FROM proyectos_gantt WHERE id_responsable = ?");
    $chk_proy->bind_param("i", $id); $chk_proy->execute();
    $total_proy = $chk_proy->get_result()->fetch_assoc()['total'];
    $chk_proy->close();

    if ($total_proy > 0) {
        $conn->close();
        respuestaError("No se puede eliminar: el usuario es responsable de $total_proy proyecto(s) Gantt.");
    }

    $s = $conn->prepare("DELETE FROM usuarios WHERE id_usuario=?");
    $s->bind_param("i", $id); $ok = $s->execute(); $s->close(); $conn->close();
    
    if (!$ok) respuestaError('Error al eliminar.', 500);
    respuestaOk(['msg' => "Usuario '{$f['email']}' eliminado."]);
}

respuestaError('Método no soportado.', 405);