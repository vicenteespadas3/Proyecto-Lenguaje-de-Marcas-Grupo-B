<?php
/**
 * ============================================================================
 * API Endpoint: /api/incidencias/analisis_maquinas.php
 * ============================================================================
 * Propósito:
 *   Proporciona un servicio RESTful para la gestión (CRUD) de los registros
 *   de análisis técnico de las máquinas del taller. Permite registrar lecturas
 *   de variables (presión, temperatura, vibración, etc.), consultarlas,
 *   editarlas y eliminarlas.
 *
 * Estructura de la tabla `analisis_maquinas`:
 *   - id_analisis     (int, PK, AUTO_INCREMENT)
 *   - id_maquina      (int, FK → maquinas.id_maquina)
 *   - id_ejecucion    (int, FK → ejecuciones.id_ejecucion, nullable)
 *   - nombre_variable (varchar) — ej. 'presion_hidraulica', 'temperatura_aceite'
 *   - valor           (decimal) — valor numérico medido
 *   - unidad_medida   (varchar) — ej. 'bar', '°C', 'mm/s'
 *   - fecha_registro  (datetime) — cuándo se tomó la medición
 *   - dentro_rango    (tinyint/bool) — 1 = dentro del rango aceptable, 0 = fuera de rango
 *
 * Métodos disponibles:
 *   - GET    : Listar todos los análisis o filtrar por ?id=X o ?id_maquina=Y
 *   - POST   : Registrar un nuevo análisis
 *   - PUT    : Actualizar un análisis existente
 *   - DELETE : Eliminar un análisis por su id_analisis
 *
 * Dependencias:
 *   - ../config/helpers.php  : Funciones de respuesta JSON (respuestaOk, respuestaError)
 *   - ../config/db_config.php: Conector centralizado mysqli
 * ============================================================================
 */

require_once '../config/helpers.php';
require_once '../config/db_config.php';

// Establecer conexión con la base de datos
$conn = conectar();

// Leer el método HTTP de la petición (GET, POST, PUT, DELETE)
$metodo = $_SERVER['REQUEST_METHOD'];

// ============================================================================
// MÉTODO GET — Consulta de análisis
// ============================================================================
/**
 * Casos soportados:
 *   1. ?id=X        → Devuelve el análisis con id_analisis = X
 *   2. ?id_maquina=Y → Devuelve todos los análisis de una máquina concreta
 *   3. Sin parámetros → Devuelve todos los análisis ordenados por fecha DESC
 *
 * En todos los casos se hace LEFT JOIN con 'maquinas' para incluir el nombre
 * de la máquina (alias 'nombre_maquina') en la respuesta JSON.
 */
if ($metodo === 'GET') {

    // --- Caso 1: Un análisis específico por su id_analisis ---
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];

        $stmt = $conn->prepare(
            "SELECT a.*, m.nombre AS nombre_maquina
             FROM analisis_maquinas a
             LEFT JOIN maquinas m ON a.id_maquina = m.id_maquina
             WHERE a.id_analisis = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res  = $stmt->get_result();
        $fila = $res->fetch_assoc();
        $stmt->close();

        if ($fila) {
            respuestaOk(['analisis' => $fila]);
        } else {
            respuestaError('Análisis no encontrado.', 404);
        }

    // --- Caso 2: Todos los análisis de una máquina concreta ---
    } elseif (isset($_GET['id_maquina'])) {
        $id_maquina = (int)$_GET['id_maquina'];

        $stmt = $conn->prepare(
            "SELECT a.*, m.nombre AS nombre_maquina
             FROM analisis_maquinas a
             LEFT JOIN maquinas m ON a.id_maquina = m.id_maquina
             WHERE a.id_maquina = ?
             ORDER BY a.fecha_registro DESC"
        );
        $stmt->bind_param("i", $id_maquina);
        $stmt->execute();
        $res   = $stmt->get_result();
        $lista = [];
        while ($fila = $res->fetch_assoc()) {
            $lista[] = $fila;
        }
        $stmt->close();
        respuestaOk(['analisis' => $lista]);

    // --- Caso 3: Listar todos los análisis sin filtro ---
    } else {
        $res   = $conn->query(
            "SELECT a.*, m.nombre AS nombre_maquina
             FROM analisis_maquinas a
             LEFT JOIN maquinas m ON a.id_maquina = m.id_maquina
             ORDER BY a.fecha_registro DESC, a.id_analisis DESC"
        );

        if (!$res) {
            respuestaError('Error BD: ' . $conn->error, 500);
        }

        $lista = [];
        while ($fila = $res->fetch_assoc()) {
            $lista[] = $fila;
        }
        respuestaOk(['analisis' => $lista]);
    }
}

// ============================================================================
// MÉTODO POST — Registrar nuevo análisis
// ============================================================================
/**
 * Payload JSON esperado:
 *   - id_maquina      (int,    obligatorio)
 *   - id_ejecucion    (int,    opcional — puede ser null)
 *   - nombre_variable (string, obligatorio) — ej. 'presion_hidraulica'
 *   - valor           (float,  obligatorio) — valor numérico medido
 *   - unidad_medida   (string, opcional)    — ej. 'bar', '°C'
 *   - fecha_registro  (string, opcional)    — formato 'Y-m-d H:i:s'; si no se envía, se usa NOW()
 *   - dentro_rango    (int,    opcional)    — 1 (dentro) ó 0 (fuera); por defecto 1
 */
if ($metodo === 'POST') {
    // Decodificar el cuerpo JSON de la petición
    $b = json_decode(file_get_contents('php://input'), true) ?? [];

    // Extraer y sanear los campos recibidos
    $id_maquina      = (int)($b['id_maquina'] ?? 0);
    $id_ejecucion    = !empty($b['id_ejecucion'])    ? (int)$b['id_ejecucion']    : null;
    $nombre_variable = trim($b['nombre_variable'] ?? '');
    $valor           = isset($b['valor'])            ? (float)$b['valor']          : null;
    $unidad_medida   = trim($b['unidad_medida'] ?? '');
    $fecha_registro  = trim($b['fecha_registro'] ?? '');  // Si viene vacío usaremos NOW() en SQL
    $dentro_rango    = isset($b['dentro_rango'])     ? (int)(bool)$b['dentro_rango'] : 1;

    // Validaciones obligatorias
    if ($id_maquina <= 0) {
        respuestaError('El campo id_maquina es obligatorio y debe ser mayor que 0.');
    }
    if (!$nombre_variable) {
        respuestaError('El campo nombre_variable es obligatorio.');
    }
    if ($valor === null) {
        respuestaError('El campo valor es obligatorio.');
    }

    // Insertar usando NOW() si no se proporcionó fecha
    if ($fecha_registro !== '') {
        // El usuario proporcionó fecha explícita
        $stmt = $conn->prepare(
            "INSERT INTO analisis_maquinas
             (id_maquina, id_ejecucion, nombre_variable, valor, unidad_medida, fecha_registro, dentro_rango)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("iisdssi",
            $id_maquina,
            $id_ejecucion,
            $nombre_variable,
            $valor,
            $unidad_medida,
            $fecha_registro,
            $dentro_rango
        );
    } else {
        // Sin fecha: MySQL establece NOW() automáticamente
        $stmt = $conn->prepare(
            "INSERT INTO analisis_maquinas
             (id_maquina, id_ejecucion, nombre_variable, valor, unidad_medida, fecha_registro, dentro_rango)
             VALUES (?, ?, ?, ?, ?, NOW(), ?)"
        );
        $stmt->bind_param("iisdsi",
            $id_maquina,
            $id_ejecucion,
            $nombre_variable,
            $valor,
            $unidad_medida,
            $dentro_rango
        );
    }

    if ($stmt->execute()) {
        respuestaOk(['msg' => 'Análisis registrado correctamente.', 'id' => $stmt->insert_id]);
    } else {
        respuestaError('Error al registrar el análisis: ' . $stmt->error, 500);
    }
    $stmt->close();
}

// ============================================================================
// MÉTODO PUT — Actualizar un análisis existente
// ============================================================================
/**
 * Payload JSON esperado:
 *   - id_analisis     (int,    obligatorio) — ID del registro a modificar
 *   - id_maquina      (int,    obligatorio)
 *   - id_ejecucion    (int,    opcional — puede ser null)
 *   - nombre_variable (string, obligatorio)
 *   - valor           (float,  obligatorio)
 *   - unidad_medida   (string, opcional)
 *   - fecha_registro  (string, obligatorio) — formato 'Y-m-d H:i:s'
 *   - dentro_rango    (int,    obligatorio) — 1 ó 0
 */
if ($metodo === 'PUT') {
    // Decodificar el cuerpo JSON de la petición
    $b = json_decode(file_get_contents('php://input'), true) ?? [];

    // Extraer y sanear los campos
    $id_analisis     = (int)($b['id_analisis'] ?? 0);
    $id_maquina      = (int)($b['id_maquina'] ?? 0);
    $id_ejecucion    = !empty($b['id_ejecucion'])    ? (int)$b['id_ejecucion']    : null;
    $nombre_variable = trim($b['nombre_variable'] ?? '');
    $valor           = isset($b['valor'])            ? (float)$b['valor']          : null;
    $unidad_medida   = trim($b['unidad_medida'] ?? '');
    $fecha_registro  = trim($b['fecha_registro'] ?? '');
    $dentro_rango    = isset($b['dentro_rango'])     ? (int)(bool)$b['dentro_rango'] : 1;

    // Validaciones obligatorias
    if ($id_analisis <= 0) {
        respuestaError('ID de análisis no válido.');
    }
    if ($id_maquina <= 0) {
        respuestaError('El campo id_maquina es obligatorio.');
    }
    if (!$nombre_variable) {
        respuestaError('El campo nombre_variable es obligatorio.');
    }
    if ($valor === null) {
        respuestaError('El campo valor es obligatorio.');
    }
    if (!$fecha_registro) {
        respuestaError('El campo fecha_registro es obligatorio.');
    }

    // Actualizar el registro en la base de datos
    $stmt = $conn->prepare(
        "UPDATE analisis_maquinas
         SET id_maquina=?, id_ejecucion=?, nombre_variable=?, valor=?,
             unidad_medida=?, fecha_registro=?, dentro_rango=?
         WHERE id_analisis=?"
    );
    $stmt->bind_param("iisdssii",
        $id_maquina,
        $id_ejecucion,
        $nombre_variable,
        $valor,
        $unidad_medida,
        $fecha_registro,
        $dentro_rango,
        $id_analisis
    );

    if ($stmt->execute()) {
        respuestaOk(['msg' => 'Análisis actualizado correctamente.']);
    } else {
        respuestaError('Error al actualizar el análisis: ' . $stmt->error, 500);
    }
    $stmt->close();
}

// ============================================================================
// MÉTODO DELETE — Eliminar un análisis
// ============================================================================
/**
 * Payload JSON esperado:
 *   - id_analisis (int, obligatorio) — ID del registro que se desea eliminar
 */
if ($metodo === 'DELETE') {
    // Decodificar el cuerpo JSON
    $b           = json_decode(file_get_contents('php://input'), true) ?? [];
    $id_analisis = (int)($b['id_analisis'] ?? 0);

    // Validación del ID
    if ($id_analisis <= 0) {
        respuestaError('ID de análisis no válido.');
    }

    // Ejecutar el borrado
    $stmt = $conn->prepare("DELETE FROM analisis_maquinas WHERE id_analisis = ?");
    $stmt->bind_param("i", $id_analisis);

    if ($stmt->execute()) {
        respuestaOk(['msg' => 'Análisis eliminado correctamente.']);
    } else {
        respuestaError('Error al eliminar el análisis: ' . $stmt->error, 500);
    }
    $stmt->close();
}

// Cerrar la conexión con la base de datos
$conn->close();

// Si se llega aquí, el método HTTP usado no está contemplado
respuestaError('Método no permitido.', 405);
