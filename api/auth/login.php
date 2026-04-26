<?php
/**
 * PROYECTO: ERP Robots - Sistema de Gestión
 * AUTORA: Guadalupe MGM
 * DESCRIPCIÓN: API de Autenticación con verificación de hash y gestión de sesiones.
 * * USO TÉCNICO:
 * - Endpoint: POST /api/login.php
 * - Body JSON: { "usuario": "email@ejemplo.com", "password": "1234" }
 */

require_once '../config/helpers.php';
require_once '../config/db_config.php';

// 1. Solo permitimos peticiones POST (seguridad)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respuestaError('Método no permitido. Usa POST.', 405);
}

// 2. Leer los datos enviados por los robots (Frontend)
$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$usuario  = trim($body['usuario']  ?? ''); // Puede ser el email o el nombre
$password = trim($body['password'] ?? '');

if (!$usuario || !$password) {
    respuestaError('El email y la contraseña son obligatorios.');
}

$conn = conectar();

// 3. BUSQUEDA DEL USUARIO (Protección contra SQL Injection con Prepare)
// Buscamos por email o por nombre, tal cual lo hacía tu versión original
if (is_numeric($usuario)) {
    $stmt = $conn->prepare("SELECT id_usuario AS id, email, nombre, apellidos, rol, password_hash, activo FROM usuarios WHERE id_usuario=?");
    $stmt->bind_param("i", $usuario);
} else {
    $stmt = $conn->prepare("SELECT id_usuario AS id, email, nombre, apellidos, rol, password_hash, activo FROM usuarios WHERE email=? OR nombre=?");
    $stmt->bind_param("ss", $usuario, $usuario);
}

$stmt->execute();
$resultado = $stmt->get_result();
$fila_bd = $resultado->fetch_assoc();
$stmt->close();

// 4. VERIFICACIÓN DE SEGURIDAD
// Comprobamos: existencia, que esté activo y que la clave encaje con el hash
if ($fila_bd && $fila_bd['activo'] == 1 && password_verify($password, $fila_bd['password_hash'])) {
    
    // Iniciar sesión oficial en el servidor
    iniciarSesion();
    
    // Calculamos si es administrador basándonos en el rol de tu BBDD
    $es_admin = ($fila_bd['rol'] === 'administrador') ? 1 : 0;

    // Guardamos datos en la sesión (PHP Session)
    $_SESSION['usuario']   = $fila_bd['email'];
    $_SESSION['nombre']    = $fila_bd['nombre'];
    $_SESSION['es_admin']  = $es_admin;
    
    // Registro de auditoría y actualización de último acceso
    registrarLog($conn, $fila_bd['email'], 'login');
    $id_user = $fila_bd['id'];
    $conn->query("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = $id_user");
    
    $conn->close();

    // Crear cookie de recuerdo (30 días)
    setcookie("ultimoUsuario", $fila_bd['email'], time() + 2592000, "/");

    // Respuesta final para el JavaScript (JS)
    respuestaOk([
        'usuario'  => $fila_bd['email'],
        'nombre'   => $fila_bd['nombre'],
        'es_admin' => $es_admin,
        'msg'      => "¡Bienvenida, " . $fila_bd['nombre'] . "!"
    ]);

} else {
    // Si algo falla, cerramos conexión y avisamos
    if (isset($conn)) $conn->close();
    respuestaError('Credenciales incorrectas o cuenta inactiva.', 401);
}