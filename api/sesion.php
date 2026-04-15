<?php
// api/sesion.php  —  GET /api/sesion.php
// cURL: curl -b cookies.txt http://localhost/api/sesion.php
require_once 'helpers.php';
iniciarSesion();
if (!empty($_SESSION['usuario'])) {
    respuestaOk([
        'usuario'   => $_SESSION['usuario'],
        'nombre'    => $_SESSION['nombre']    ?? '',
        'apellidos' => $_SESSION['apellidos'] ?? '',
        'es_admin'  => (int)($_SESSION['es_admin'] ?? 0),
        'session_id'=> session_id()
    ]);
} else {
    respuestaError('Sin sesión.', 401);
}
