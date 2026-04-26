<?php
// ============================================================
//  api/herramientas.php — CRUD completo para Herramientas
// ============================================================
require_once '../config/helpers.php';
require_once '../config/db_config.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$conn = conectar();

// La tabla herramientas ya existe en la base de datos con una estructura específica.

switch ($metodo) {
    case 'GET':
        // Obtener la lista de herramientas activas
        $query = "SELECT * FROM herramientas WHERE activa = 1 ORDER BY id_herramienta DESC";
        $res = $conn->query($query);

        if (!$res) {
            respuestaError('Error en la consulta de BD: ' . $conn->error, 500);
        }

        $herramientas = [];
        while ($fila = $res->fetch_assoc()) {
            $herramientas[] = $fila;
        }
        respuestaOk(['herramientas' => $herramientas]);
        break;

    case 'POST':
        // Añadir nueva herramienta
        $b = json_decode(file_get_contents('php://input'), true) ?? [];
        $nombre = trim($b['nombre'] ?? '');
        $tipo = trim($b['tipo'] ?? 'General');
        $cod_int = trim($b['codigo_interno'] ?? '');
        $estado = trim($b['estado'] ?? 'disponible');
        $ubicacion = trim($b['ubicacion'] ?? '');

        if (!$nombre) respuestaError('El nombre de la herramienta es obligatorio.');

        $stmt = $conn->prepare("INSERT INTO herramientas (nombre, tipo, codigo_interno, estado, ubicacion, activa) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("sssss", $nombre, $tipo, $cod_int, $estado, $ubicacion);

        if ($stmt->execute()) {
            respuestaOk(['msg' => 'Herramienta creada con éxito.', 'id_herramienta' => $stmt->insert_id]);
        } else {
            respuestaError('Error al crear: ' . $stmt->error, 500);
        }
        break;

    case 'PUT':
        // Actualizar herramienta
        $b = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int)($b['id_herramienta'] ?? 0);
        $nombre = trim($b['nombre'] ?? '');
        $tipo = trim($b['tipo'] ?? 'General');
        $cod_int = trim($b['codigo_interno'] ?? '');
        $estado = trim($b['estado'] ?? 'disponible');
        $ubicacion = trim($b['ubicacion'] ?? '');

        if ($id <= 0) respuestaError('ID no válido.');
        if (!$nombre) respuestaError('El nombre es obligatorio.');

        $stmt = $conn->prepare("UPDATE herramientas SET nombre=?, tipo=?, codigo_interno=?, estado=?, ubicacion=? WHERE id_herramienta=?");
        $stmt->bind_param("sssssi", $nombre, $tipo, $cod_int, $estado, $ubicacion, $id);

        if ($stmt->execute()) respuestaOk(['msg' => 'Herramienta actualizada.']);
        else respuestaError('Error al actualizar: ' . $stmt->error, 500);
        break;

    case 'DELETE':
        // Borrado físico de la herramienta
        $b = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int)($b['id_herramienta'] ?? 0);

        if ($id <= 0) respuestaError('ID no válido.');

        $stmt = $conn->prepare("DELETE FROM herramientas WHERE id_herramienta=?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) respuestaOk(['msg' => 'Herramienta eliminada.']);
        else respuestaError('Error al eliminar: ' . $stmt->error, 500);
        break;

    default:
        respuestaError('Método no permitido.', 405);
        break;
}

$conn->close();