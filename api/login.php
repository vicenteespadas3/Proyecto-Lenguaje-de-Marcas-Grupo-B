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
require_once 'helpers.php';
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respuestaError('Usa POST.',405);

$b        = json_decode(file_get_contents('php://input'), true) ?? [];
$usuario  = trim($b['usuario']  ?? '');
$password = trim($b['password'] ?? '');
$metodo   = trim($b['metodo']   ?? 'v1');

if (!$usuario || !$password) respuestaError('Usuario y contraseña obligatorios.');

$conn = conectar();
$fila = null;

if ($metodo === 'v1') {
    // ── V1: prepare + bind_param + execute ────────────────────
    $stmt = $conn->prepare(
        "SELECT id,usuario,nombre,apellidos,es_admin FROM usuarios WHERE usuario=? AND password=?"
    );
    $stmt->bind_param("ss", $usuario, $password);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    // ── V2: query + num_rows ──────────────────────────────────
    $ue  = $conn->real_escape_string($usuario);
    $pe  = $conn->real_escape_string($password);
    $res = $conn->query(
        "SELECT id,usuario,nombre,apellidos,es_admin FROM usuarios WHERE usuario='$ue' AND password='$pe'"
    );
    if ($res && $res->num_rows > 0) $fila = $res->fetch_assoc();
}

if ($fila) {
    iniciarSesion();
    $_SESSION['usuario']  = $fila['usuario'];
    $_SESSION['nombre']   = $fila['nombre'];
    $_SESSION['apellidos']= $fila['apellidos'];
    $_SESSION['es_admin'] = (int)$fila['es_admin'];
    registrarLog($conn, $fila['usuario'], 'login');
    $conn->close();
    setcookie("ultimoUsuario", $fila['usuario'], time()+2592000, "/");
    respuestaOk([
        'usuario'  => $fila['usuario'],
        'nombre'   => $fila['nombre'],
        'apellidos'=> $fila['apellidos'],
        'es_admin' => (int)$fila['es_admin']
    ]);
} else {
    $conn->close();
    respuestaError('Usuario o contraseña incorrectos.', 401);
}
