<?php
/**
 * ============================================================================
 * Archivo: api/auth/logout.php
 * ============================================================================
 * Propósito:
 *   Cierra la sesión actual del usuario tanto en PHP como dejando un registro
 *   en la base de datos de auditoría.
 *
 * Referencia en la web:
 *   - Botón "Cerrar sesión" en el menú superior derecho (perfil del usuario).
 *   - Manejado por la función `cerrarSesion()` en Js/app.js o Js/login.js.
 * ============================================================================
 */
require_once '../config/helpers.php';
require_once '../config/db_config.php';

// Asegurarse de que hay una sesión iniciada antes de intentar destruirla
iniciarSesion();
// Si existe una sesión activa, dejamos constancia de la salida en el registro (log) de la BD
if (!empty($_SESSION['usuario'])) {
    $conn = conectar();
    registrarLog($conn, $_SESSION['usuario'], 'logout');
    $conn->close();
}

// Destruimos la sesión en el servidor PHP, eliminando cualquier rastro del usuario logueado
session_destroy();

// Devolvemos una confirmación al frontend
respuestaOk(['msg'=>'Sesión cerrada.']);
