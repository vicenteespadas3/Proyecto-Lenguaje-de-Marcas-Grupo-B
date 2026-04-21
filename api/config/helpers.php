<?php
// ============================================================
//  api/helpers.php  —  Cabeceras JSON, sesión, log
//  Incluido en TODOS los endpoints de la API
// ============================================================

// Cabeceras REST obligatorias
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Responder a preflight CORS y salir
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Respuestas JSON estándar ───────────────────────────────────
function respuestaOk(array $d = []): void {
    echo json_encode(array_merge(['ok'=>true], $d));
    exit;
}
function respuestaError(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok'=>false,'error'=>$msg]);
    exit;
}

// ── Sesión ─────────────────────────────────────────────────────
function iniciarSesion(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
}
function requireAuth(): string {
    iniciarSesion();
    if (empty($_SESSION['usuario'])) respuestaError('No autorizado.', 401);
    return $_SESSION['usuario'];
}
function requireAdmin(): string {
    $u = requireAuth();
    if (empty($_SESSION['es_admin'])) respuestaError('Solo administradores.', 403);
    return $u;
}

// ── Log de accesos ─────────────────────────────────────────────
function registrarLog(mysqli $conn, string $usuario, string $pagina): void {
    iniciarSesion();
    $sid  = session_id();
    $stmt = $conn->prepare(
        "INSERT INTO log_accesos (usuario, pagina, session_id) VALUES (?,?,?)"
    );
    if ($stmt) {
        $stmt->bind_param("sss", $usuario, $pagina, $sid);
        $stmt->execute();
        $stmt->close();
    }
}
