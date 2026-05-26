<?php
/**
 * ============================================================================
 * API Endpoint: /api/incidencias/incidencias.php
 * ============================================================================
 * Propósito:
 *   Servicio RESTful para la gestión (CRUD) de incidencias reportadas sobre
 *   las máquinas del taller.
 *
 * Lógica de negocio adicional:
 *   - POST: Al registrar una incidencia, actualiza automáticamente el estado
 *           de la máquina afectada según el tipo de incidencia:
 *             · 'Avería'        → maquina.estado = 'averia'
 *             · 'Mantenimiento' → maquina.estado = 'en_mantenimiento'
 *             · Otros tipos     → no se toca el estado de la máquina
 *   - PUT:  Al cerrar ('cerrada') o resolver ('resuelta') una incidencia,
 *           devuelve la máquina al estado 'disponible' automáticamente.
 *
 * Métodos:
 *   - GET    : Listar todas las incidencias o una por ?id=X
 *   - POST   : Reportar nueva incidencia (+ actualizar estado de máquina)
 *   - PUT    : Actualizar estado/solución (+ liberar máquina si se cierra)
 *   - DELETE : Eliminar una incidencia
 *
 * Dependencias:
 *   - ../config/helpers.php  : respuestaOk(), respuestaError()
 *   - ../config/db_config.php: conectar()
 * ============================================================================
 */

require_once '../config/helpers.php';
require_once '../config/db_config.php';

// Establecer conexión con la base de datos
$conn = conectar();

// Leer el método HTTP de la petición
$metodo = $_SERVER['REQUEST_METHOD'];

// ============================================================================
// MÉTODO GET — Consulta de incidencias
// ============================================================================
/**
 * Casos soportados:
 *   1. ?id=X  → Devuelve la incidencia con ese ID.
 *   2. Sin id → Devuelve todas las incidencias ordenadas por fecha DESC.
 *
 * En ambos casos el LEFT JOIN con maquinas añade:
 *   - alias 'maquina'        → nombre de la máquina
 *   - alias 'estado_maquina' → estado actual de la máquina
 */
if ($metodo === 'GET') {

    // Caso 1: Una incidencia específica por su ID
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];

        $stmt = $conn->prepare(
            "SELECT i.*, m.nombre AS maquina, m.estado AS estado_maquina
             FROM incidencias i
             LEFT JOIN maquinas m ON i.id_maquina = m.id_maquina
             WHERE i.id_incidencia = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $inc = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($inc) {
            respuestaOk(['incidencia' => $inc]);
        } else {
            respuestaError('Incidencia no encontrada.', 404);
        }

    // Caso 2: Listar todas las incidencias
    } else {
        $res = $conn->query(
            "SELECT i.*, m.nombre AS maquina, m.estado AS estado_maquina
             FROM incidencias i
             LEFT JOIN maquinas m ON i.id_maquina = m.id_maquina
             ORDER BY i.fecha_reporte DESC, i.id_incidencia DESC"
        );

        if (!$res) {
            respuestaError('Error BD: ' . $conn->error, 500);
        }

        $incidencias = [];
        while ($fila = $res->fetch_assoc()) {
            $incidencias[] = $fila;
        }
        respuestaOk(['incidencias' => $incidencias]);
    }
}

// ============================================================================
// MÉTODO POST — Registrar nueva incidencia + actualizar estado de máquina
// ============================================================================
/**
 * Payload JSON esperado:
 *   - id_maquina  (int,    obligatorio)
 *   - descripcion (string, obligatorio)
 *   - tipo        (string, opcional, defecto 'Averia') — Averia | Mantenimiento | Otro
 *   - prioridad   (string, opcional, defecto 'Media')  — Baja | Media | Alta | Critica
 *
 * Lógica de negocio:
 *   1. Inserta la incidencia con estado inicial 'abierta'.
 *   2. Según el campo 'tipo', actualiza el estado de la máquina:
 *        · 'Averia'        → maquinas.estado = 'averia'
 *        · 'Mantenimiento' → maquinas.estado = 'en_mantenimiento'
 *        · Otros tipos     → no se modifica el estado de la máquina
 */
if ($metodo === 'POST') {
    // Decodificar el cuerpo JSON
    $b = json_decode(file_get_contents('php://input'), true) ?? [];

    // Extraer y sanear los campos
    $id_maquina  = (int)($b['id_maquina'] ?? 0);
    $descripcion = trim($b['descripcion'] ?? '');
    $tipo        = trim($b['tipo']        ?? 'Averia');
    $prioridad   = trim($b['prioridad']   ?? 'Media');

    // Validaciones obligatorias
    if ($id_maquina <= 0) {
        respuestaError('El ID de maquina es obligatorio.');
    }
    if (!$descripcion) {
        respuestaError('La descripcion es obligatoria.');
    }

    // Paso 1: Insertar la incidencia con estado 'abierta' y fecha actual
    $stmt = $conn->prepare(
        "INSERT INTO incidencias (id_maquina, descripcion, tipo, prioridad, estado, fecha_reporte)
         VALUES (?, ?, ?, ?, 'abierta', NOW())"
    );
    $stmt->bind_param("isss", $id_maquina, $descripcion, $tipo, $prioridad);

    if (!$stmt->execute()) {
        respuestaError('Error al registrar la incidencia: ' . $stmt->error, 500);
    }
    $nuevo_id = $stmt->insert_id;
    $stmt->close();

    // Paso 2: Actualizar el estado de la máquina según el tipo de incidencia.
    // Usamos stripos para comparación sin distinción de tildes/mayúsculas.
    $nuevo_estado_maquina = null;
    $tipo_lower = strtolower($tipo);

    if (strpos($tipo_lower, 'averia') !== false || strpos($tipo_lower, 'avería') !== false) {
        // Tipo Avería → la máquina pasa a estado 'averia'
        $nuevo_estado_maquina = 'averia';
    } elseif (strpos($tipo_lower, 'mantenimiento') !== false) {
        // Tipo Mantenimiento → la máquina pasa a estado 'en_mantenimiento'
        $nuevo_estado_maquina = 'en_mantenimiento';
    }
    // Para cualquier otro tipo (ej. 'Otro', 'Revisión', etc.) no se cambia el estado

    if ($nuevo_estado_maquina !== null) {
        $stmt2 = $conn->prepare("UPDATE maquinas SET estado = ? WHERE id_maquina = ?");
        $stmt2->bind_param("si", $nuevo_estado_maquina, $id_maquina);
        $stmt2->execute();
        $stmt2->close();
    }

    respuestaOk([
        'msg'                        => 'Incidencia registrada correctamente.',
        'id'                         => $nuevo_id,
        'estado_maquina_actualizado' => $nuevo_estado_maquina !== null,
        'nuevo_estado_maquina'       => $nuevo_estado_maquina
    ]);
}

// ============================================================================
// MÉTODO PUT — Actualizar incidencia + liberar máquina al cerrar/resolver
// ============================================================================
/**
 * Payload JSON esperado:
 *   - id_incidencia    (int,    obligatorio)
 *   - estado           (string, obligatorio) — abierta | en_curso | resuelta | cerrada
 *   - solucion_aplicada (string, opcional)
 *
 * Lógica de negocio:
 *   - Si el nuevo estado es 'cerrada' o 'resuelta':
 *       · Se establece fecha_resolucion = NOW()
 *       · La máquina asociada vuelve a estado = 'disponible'
 */
if ($metodo === 'PUT') {
    // Decodificar el cuerpo JSON
    $b = json_decode(file_get_contents('php://input'), true) ?? [];

    $id       = (int)($b['id_incidencia']     ?? 0);
    $estado   = trim($b['estado']             ?? '');
    $solucion = trim($b['solucion_aplicada']  ?? '');

    // Validaciones
    if ($id <= 0) {
        respuestaError('ID de incidencia no valido.');
    }
    if (!$estado) {
        respuestaError('El campo estado es obligatorio.');
    }

    // Obtener el id_maquina asociado a esta incidencia (necesario para actualizarla después)
    $stmtGet = $conn->prepare("SELECT id_maquina FROM incidencias WHERE id_incidencia = ?");
    $stmtGet->bind_param("i", $id);
    $stmtGet->execute();
    $incRow = $stmtGet->get_result()->fetch_assoc();
    $stmtGet->close();

    if (!$incRow) {
        respuestaError('Incidencia no encontrada.', 404);
    }
    $id_maquina = (int)$incRow['id_maquina'];

    // Actualizar la incidencia.
    // Si el estado es 'cerrada' o 'resuelta', se registra la fecha de resolución.
    $stmt = $conn->prepare(
        "UPDATE incidencias
         SET estado = ?,
             solucion_aplicada = ?,
             fecha_resolucion = IF(? IN ('cerrada','resuelta'), NOW(), fecha_resolucion)
         WHERE id_incidencia = ?"
    );
    $stmt->bind_param("sssi", $estado, $solucion, $estado, $id);

    if (!$stmt->execute()) {
        respuestaError('Error al actualizar la incidencia: ' . $stmt->error, 500);
    }
    $stmt->close();

    // Si la incidencia se cierra o se resuelve, liberar la máquina (→ 'disponible')
    $maquina_liberada = false;
    if (in_array($estado, ['cerrada', 'resuelta'])) {
        $stmt2 = $conn->prepare("UPDATE maquinas SET estado = 'disponible' WHERE id_maquina = ?");
        $stmt2->bind_param("i", $id_maquina);
        $stmt2->execute();
        $stmt2->close();
        $maquina_liberada = true;
    }

    respuestaOk([
        'msg'              => 'Incidencia actualizada correctamente.',
        'maquina_liberada' => $maquina_liberada
    ]);
}

// ============================================================================
// MÉTODO DELETE — Eliminar una incidencia
// ============================================================================
/**
 * Payload JSON esperado:
 *   - id_incidencia (int, obligatorio)
 */
if ($metodo === 'DELETE') {
    $b  = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id_incidencia'] ?? 0);

    if ($id <= 0) {
        respuestaError('ID de incidencia no valido.');
    }

    $stmt = $conn->prepare("DELETE FROM incidencias WHERE id_incidencia = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        respuestaOk(['msg' => 'Incidencia eliminada correctamente.']);
    } else {
        respuestaError('Error al eliminar: ' . $stmt->error, 500);
    }
    $stmt->close();
}

// Cerrar la conexion con la base de datos
$conn->close();

// Si se llega aqui, el metodo HTTP no esta soportado
respuestaError('Metodo no permitido.', 405);