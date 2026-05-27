<?php
/**
 * ============================================================================
 * Archivo: api/auth/sesion.php
 * ============================================================================
 * Propósito:
 *   Endpoint ultraligero que verifica si el usuario tiene una sesión activa
 *   en el servidor (cookie PHPSESSID válida). Devuelve sus datos básicos o
 *   un error 401 si la sesión caducó o no existe.
 *
 * Referencia en la web:
 *   - Se invoca automáticamente al principio de Js/app.js (función checkSesion).
 *   - Si devuelve 401, el frontend bloquea la carga y redirige a login.html.
 * ============================================================================
 */
require_once '../config/helpers.php';
iniciarSesion();
// Verificamos si existe la variable 'usuario' en la sesión actual
// Esto actúa como "guardián" de seguridad para las páginas del frontend
if (!empty($_SESSION['usuario'])) {
    // Si está logueado, devolvemos sus datos para que el frontend (app.js) arme el menú y los permisos
    respuestaOk([
        'usuario'   => $_SESSION['usuario'],
        'nombre'    => $_SESSION['nombre']    ?? '',
        'apellidos' => $_SESSION['apellidos'] ?? '',
        'es_admin'  => (int)($_SESSION['es_admin'] ?? 0),
        'session_id'=> session_id() // Ayuda a identificar la sesión actual
    ]);
} else {
    // Si no está logueado, forzamos un error 401 que el frontend interpretará para redirigir al login
    respuestaError('Sin sesión.', 401);
}
