<?php
require_once '../config/helpers.php';
require_once '../config/db_config.php';

$conn = conectar();

// 1. Obtener Incidencias Activas
// Intentamos leer de la vista si existe, o de la tabla directamente
$incidencias = [];
$res_inc = $conn->query("SELECT * FROM incidencias WHERE estado IN ('abierta', 'en_curso') ORDER BY fecha_reporte DESC LIMIT 4");
if ($res_inc) {
    while ($row = $res_inc->fetch_assoc()) {
        // Adaptar al frontend: usar descripcion como titulo si no hay titulo
        if (!isset($row['titulo']) && isset($row['descripcion'])) {
            $row['titulo'] = mb_substr($row['descripcion'], 0, 50) . '...';
        }
        $incidencias[] = $row;
    }
}

// 2. Obtener Tareas / Órdenes de Mantenimiento Planeadas
$tareas = [];
$hoy = date('Y-m-d');

// Buscamos tareas para hoy o en el futuro
$query_tareas = "SELECT id_orden, maquina, tipo, prioridad, fecha_programada, estado 
                    FROM v_ordenes_activas 
                    WHERE fecha_programada >= '$hoy' 
                    ORDER BY fecha_programada ASC LIMIT 4";
$res_tar = $conn->query($query_tareas);

if ($res_tar && $res_tar->num_rows > 0) {
    while ($row = $res_tar->fetch_assoc()) {
        $tareas[] = $row;
    }
} else {
    // Si no hay ista o falla, intentamos leer de ordenes_trabajo directamente
    $query_fallback = "SELECT id_orden, id_maquina as maquina, 'Mantenimiento' as tipo, id_prioridad as prioridad, fecha_programada, id_estado as estado 
                    FROM ordenes_trabajo 
                    WHERE fecha_programada >= '$hoy' 
                    ORDER BY fecha_programada ASC LIMIT 4";
    $res_fall = $conn->query($query_fallback);
    if ($res_fall) {
        while ($row = $res_fall->fetch_assoc()) {
            $tareas[] = $row;
        }
    }
}

respuestaOk([
    'incidencias' => $incidencias,
    'tareas' => $tareas
]);

$conn->close();
