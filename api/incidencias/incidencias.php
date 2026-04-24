<?php
// ============================================================
//  api/incidencias/incidencias.php
//  Gestiona el CRUD de incidencias del taller
// ============================================================
require_once '../config/helpers.php';
require_once '../config/db_config.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$conn = conectar();

// Autocrear tabla si no existe
$conn->query("CREATE TABLE IF NOT EXISTS incidencias (
    id_incidencia INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT NOT NULL,
    prioridad ENUM('Baja', 'Media', 'Alta', 'Crítica') DEFAULT 'Media',
    estado ENUM('Abierta', 'En Proceso', 'Resuelta', 'Cerrada') DEFAULT 'Abierta',
    id_maquina INT NULL,
    usuario_reporte VARCHAR(100) DEFAULT 'Sistema',
    fecha_reporte TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion TIMESTAMP NULL
)");

switch ($metodo) {
    case 'GET':
        // Obtener todas las incidencias
        $query = "SELECT i.*, m.nombre as nombre_maquina 
                  FROM incidencias i 
                  LEFT JOIN maquinas m ON i.id_maquina = m.id_maquina 
                  ORDER BY i.fecha_reporte DESC";
        $res = $conn->query($query);
        
        if (!$res) {
            // Si la tabla no existe, devolvemos un array vacío para no romper el front
            respuestaOk(['incidencias' => []]);
        }

        $incidencias = [];
        while ($fila = $res->fetch_assoc()) {
            $incidencias[] = $fila;
        }
        respuestaOk(['incidencias' => $incidencias]);
        break;

    case 'POST':
        // Crear una nueva incidencia
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['titulo']) || !isset($data['descripcion'])) {
            respuestaError('Faltan datos obligatorios (título, descripción)', 400);
        }

        $titulo = $conn->real_escape_string($data['titulo']);
        $descripcion = $conn->real_escape_string($data['descripcion']);
        $prioridad = $conn->real_escape_string($data['prioridad'] ?? 'Media');
        $id_maquina = isset($data['id_maquina']) ? (int)$data['id_maquina'] : null;
        $usuario_reporte = $conn->real_escape_string($data['usuario'] ?? 'Anónimo');

        $query = "INSERT INTO incidencias (titulo, descripcion, prioridad, id_maquina, usuario_reporte, estado) 
                  VALUES ('$titulo', '$descripcion', '$prioridad', " . ($id_maquina ? $id_maquina : "NULL") . ", '$usuario_reporte', 'Abierta')";
        
        if ($conn->query($query)) {
            respuestaOk(['mensaje' => 'Incidencia reportada correctamente', 'id' => $conn->insert_id]);
        } else {
            respuestaError('Error al guardar la incidencia: ' . $conn->error, 500);
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['id_incidencia'])) respuestaError('Falta ID de incidencia', 400);
        
        $id = (int)$data['id_incidencia'];
        $estado = $conn->real_escape_string($data['estado'] ?? 'Resuelta');
        
        $query = "UPDATE incidencias SET estado = '$estado'";
        if ($estado == 'Resuelta' || $estado == 'Cerrada') {
            $query .= ", fecha_resolucion = CURRENT_TIMESTAMP";
        }
        $query .= " WHERE id_incidencia = $id";

        if ($conn->query($query)) {
            respuestaOk(['mensaje' => 'Incidencia actualizada']);
        } else {
            respuestaError('Error al actualizar: ' . $conn->error, 500);
        }
        break;

    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['id_incidencia'])) respuestaError('Falta ID de incidencia', 400);
        
        $id = (int)$data['id_incidencia'];
        if ($conn->query("DELETE FROM incidencias WHERE id_incidencia = $id")) {
            respuestaOk(['mensaje' => 'Incidencia eliminada']);
        } else {
            respuestaError('Error al eliminar: ' . $conn->error, 500);
        }
        break;

    default:
        respuestaError('Método no permitido', 405);
        break;
}

$conn->close();
