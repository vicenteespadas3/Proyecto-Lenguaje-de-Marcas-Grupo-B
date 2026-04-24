<?php
// ============================================================
//  api/login.php  —  POST /api/login.php
// ============================================================
//  Body JSON: { "usuario":"admin", "password":"1234", "metodo":"v1" }
//
//  cURL:
//  curl -c cookies.txt -X POST http://localhost/api/login.php \
//       -H "Content-Type: application/json" \
//       -d '{"usuario":"admin","password":"1234","metodo":"v1"}'
//
//  MÉTODOS:
//   v1 → prepare() + bind_param() + execute()  [RECOMENDADO]
//   v2 → query() + num_rows                    [Para comparar]
// ============================================================
require_once '../config/helpers.php';
require_once '../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respuestaError('Usa POST.',405);

$b        = json_decode(file_get_contents('php://input'), true) ?? [];
$usuario  = trim($b['usuario']  ?? '');
$password = trim($b['password'] ?? '');
$metodo   = trim($b['metodo']   ?? 'v1');

if (!$usuario || !$password) respuestaError('Usuario y contraseña obligatorios.');

$conn = conectar();
$fila = null;

if ($metodo === 'v1') {
    // ── V1: prepare + bind_param + execute (Seguro contra SQL Injection) ──
    // Permite loguearse tanto con el ID numérico como con el correo
    if (is_numeric($usuario)) {
        $stmt = $conn->prepare("SELECT id_usuario AS id, email AS usuario, nombre, apellidos, rol, password_hash, activo FROM usuarios WHERE id_usuario=?");
        $stmt->bind_param("i", $usuario);
    } else {
        $stmt = $conn->prepare("SELECT id_usuario AS id, email AS usuario, nombre, apellidos, rol, password_hash, activo FROM usuarios WHERE email=? OR nombre=?");
        $stmt->bind_param("ss", $usuario, $usuario);
    }
    $stmt->execute();
    $fila_bd = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Verificamos: 1. Que el usuario exista, 2. Que la cuenta esté activa (activo == 1)
    // 3. Que la contraseña introducida coincida con el hash seguro guardado en la BD usando password_verify()
    if ($fila_bd && $fila_bd['activo'] == 1 && password_verify($password, $fila_bd['password_hash'])) {
        $fila = $fila_bd;
        // Determinamos automáticamente si el usuario tiene privilegios de administrador
        $fila['es_admin'] = ($fila_bd['rol'] === 'administrador') ? 1 : 0;
    }
} else {
    // ── V2: query + num_rows ──────────────────────────────────
    $ue  = $conn->real_escape_string($usuario);
    if (is_numeric($usuario)) {
        $idNum = (int)$usuario;
        $res = $conn->query("SELECT id_usuario AS id, email AS usuario, nombre, apellidos, rol, password_hash, activo FROM usuarios WHERE id_usuario=$idNum");
    } else {
        $res = $conn->query("SELECT id_usuario AS id, email AS usuario, nombre, apellidos, rol, password_hash, activo FROM usuarios WHERE email='$ue' OR nombre='$ue'");
    }
    if ($res && $res->num_rows > 0) {
        $fila_bd = $res->fetch_assoc();
        if ($fila_bd['activo'] == 1 && password_verify($password, $fila_bd['password_hash'])) {
            $fila = $fila_bd;
            $fila['es_admin'] = ($fila_bd['rol'] === 'administrador') ? 1 : 0;
        }
    }
}

if ($fila) {
    // Si la validación fue un éxito, iniciamos la sesión en el servidor PHP
    iniciarSesion();
    
    // Guardamos los datos críticos del usuario en las variables de sesión
    $_SESSION['usuario']  = $fila['usuario'];
    $_SESSION['nombre']   = $fila['nombre'];
    $_SESSION['apellidos']= $fila['apellidos'];
    $_SESSION['es_admin'] = (int)$fila['es_admin'];
    
    // Dejamos un registro del acceso en la base de datos para auditoría
    registrarLog($conn, $fila['usuario'], 'login');
    $conn->close();
    
    // Creamos una cookie que recordará el correo del usuario durante 30 días para autocompletar futuros logins
    setcookie("ultimoUsuario", $fila['usuario'], time()+2592000, "/");
    
    // Devolvemos los datos al frontend (app.js) para que sepa quién inició sesión
    respuestaOk([
        'usuario'  => $fila['usuario'],
        'nombre'   => $fila['nombre'],
        'apellidos'=> $fila['apellidos'],
        'es_admin' => (int)$fila['es_admin']
    ]);
} else {
    // Si falla (credenciales incorrectas o cuenta inactiva), denegamos el acceso
    $conn->close();
    respuestaError('Usuario o contraseña incorrectos.', 401);
}
