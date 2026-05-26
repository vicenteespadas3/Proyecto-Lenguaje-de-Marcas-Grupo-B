<?php
/**
 * ============================================================================
 * Archivo: api/config/helpers.php
 * ============================================================================
 * Propósito:
 *   Proveer funciones globales de apoyo (utilidades) que se usan a lo largo
 *   de todos los endpoints de la API. Se encarga de:
 *     - Establecer las cabeceras HTTP necesarias (CORS, JSON).
 *     - Estandarizar las respuestas JSON (éxito o error).
 *     - Gestionar y verificar la sesión del usuario.
 *     - Registrar logs de auditoría (quién hace qué).
 *
 * Referencia en la web:
 *   - Utilizado internamente por TODOS los scripts PHP bajo la carpeta /api/.
 * ============================================================================
 */

// ----------------------------------------------------------------------------
// CABECERAS REST OBLIGATORIAS (Headers HTTP)
// ----------------------------------------------------------------------------
// Indica al navegador que el contenido devuelto es siempre JSON
header('Content-Type: application/json; charset=utf-8');
// Permite que la API sea consumida desde cualquier origen (CORS)
header('Access-Control-Allow-Origin: *');
// Métodos HTTP permitidos
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
// Cabeceras permitidas en la petición
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Manejar la petición 'preflight' (OPTIONS) que hacen los navegadores
// antes de enviar un POST, PUT o DELETE por seguridad CORS.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // Responde con 'No Content' y detiene la ejecución
    exit;
}

// ----------------------------------------------------------------------------
// RESPUESTAS JSON ESTÁNDAR
// ----------------------------------------------------------------------------

/**
 * Devuelve una respuesta JSON exitosa (código HTTP 200).
 * Termina la ejecución del script (exit).
 * 
 * @param array $d Datos adicionales a incluir en el JSON.
 */
function respuestaOk(array $d = []): void {
    // Une el array ['ok'=>true] con los datos recibidos y los convierte a JSON
    echo json_encode(array_merge(['ok'=>true], $d));
    exit;
}

/**
 * Devuelve una respuesta JSON indicando un error.
 * Termina la ejecución del script (exit).
 * 
 * @param string $msg Mensaje de error a mostrar.
 * @param int $code Código HTTP a devolver (por defecto 400 - Bad Request).
 */
function respuestaError(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok'=>false, 'error'=>$msg]);
    exit;
}

// ----------------------------------------------------------------------------
// GESTIÓN DE SESIONES (Seguridad)
// ----------------------------------------------------------------------------

/**
 * Inicia la sesión de PHP de forma segura, comprobando que no esté ya iniciada.
 */
function iniciarSesion(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
}

/**
 * Verifica si hay un usuario logueado. Si no lo hay, aborta la petición con error 401.
 * Se usa al principio de cualquier endpoint que requiera estar logueado.
 * 
 * @return string Email/Identificador del usuario logueado.
 */
function requireAuth(): string {
    iniciarSesion();
    // Si no existe 'usuario' en $_SESSION, significa que no se ha hecho login
    if (empty($_SESSION['usuario'])) respuestaError('No autorizado.', 401);
    return $_SESSION['usuario'];
}

/**
 * Verifica si el usuario logueado es Administrador. Si no lo es, aborta con error 403.
 * Se usa en endpoints sensibles (ej. crear usuarios, borrar datos críticos).
 * 
 * @return string Email/Identificador del usuario logueado (que es administrador).
 */
function requireAdmin(): string {
    // Primero comprueba que esté logueado
    $u = requireAuth();
    // Luego comprueba si tiene la bandera de admin
    if (empty($_SESSION['es_admin'])) respuestaError('Solo administradores.', 403);
    return $u;
}

// ----------------------------------------------------------------------------
// LOG DE ACCESOS (Auditoría)
// ----------------------------------------------------------------------------

/**
 * Registra una acción de un usuario en la tabla 'log_accesos' de la BD.
 * Sirve para auditar qué páginas visita o qué acciones realiza cada usuario.
 * 
 * @param mysqli $conn Objeto de conexión a la base de datos.
 * @param string $usuario Email/Identificador del usuario.
 * @param string $pagina Nombre de la página o acción realizada (ej. 'login', 'activos').
 */
function registrarLog(mysqli $conn, string $usuario, string $pagina): void {
    iniciarSesion();
    $sid  = session_id(); // Obtiene el identificador único de la sesión PHP
    
    // Preparar la consulta SQL (evita inyecciones SQL)
    $stmt = $conn->prepare(
        "INSERT INTO log_accesos (usuario, pagina, session_id) VALUES (?,?,?)"
    );
    if ($stmt) {
        // Enlazar los 3 strings (sss) a las interrogaciones
        $stmt->bind_param("sss", $usuario, $pagina, $sid);
        $stmt->execute();
        $stmt->close();
    }
}
