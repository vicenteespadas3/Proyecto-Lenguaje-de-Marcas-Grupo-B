<?php
require_once '../config/helpers.php';
require_once '../config/db_config.php';

iniciarSesion();

if (empty($_SESSION['usuario'])) {
    respuestaError('Sin sesión.', 401);
}

$conn = conectar();
$metodo = $_SERVER['REQUEST_METHOD'];
$email_sesion = $_SESSION['usuario'];

// Obtener datos del usuario actual
if ($metodo === 'GET') {
    $stmt = $conn->prepare("SELECT id_usuario, email, nombre, apellidos, rol, activo, fecha_creacion, ultimo_acceso FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email_sesion);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();

    if ($user) {
        respuestaOk(['usuario' => $user]);
    } else {
        respuestaError('Usuario no encontrado.', 404);
    }
}

// Actualizar datos del usuario actual
if ($metodo === 'PUT') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $nombre = trim($b['nombre'] ?? '');
    $apellidos = trim($b['apellidos'] ?? '');
    $email = trim($b['email'] ?? '');
    $password = trim($b['password'] ?? '');

    if (!$email || !$nombre) {
        respuestaError('Nombre y email son obligatorios.');
    }

    // 1. Verificar duplicados (si cambia el email)
    $chk = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ? AND email != ?");
    $chk->bind_param("ss", $email, $email_sesion);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) {
        $chk->close();
        respuestaError('El nuevo email ya está en uso por otro usuario.');
    }
    $chk->close();

    // 2. Actualizar
    if (!empty($password)) {
        // Si cambia password, lo guardamos (en texto plano o hash según el proyecto, 
        // pero usuarios.php parece usar texto plano o hash dependiendo de la versión. 
        // Usaremos password_hash para ser consistentes con login.php)
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, apellidos=?, email=?, password_hash=? WHERE email=?");
        $stmt->bind_param("sssss", $nombre, $apellidos, $email, $hash, $email_sesion);
    } else {
        $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, apellidos=?, email=? WHERE email=?");
        $stmt->bind_param("ssss", $nombre, $apellidos, $email, $email_sesion);
    }

    if ($stmt->execute()) {
        // Actualizar sesión si el email cambió
        $_SESSION['usuario'] = $email;
        $_SESSION['nombre'] = $nombre;
        $_SESSION['apellidos'] = $apellidos;
        respuestaOk(['msg' => 'Perfil actualizado con éxito.']);
    } else {
        respuestaError('Error al actualizar el perfil.');
    }
    $stmt->close();
}

$conn->close();
respuestaError('Método no permitido.', 405);
