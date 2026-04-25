<?php
// ============================================================
// api/inventario/piezas.php — CRUD completo para Piezas
// ============================================================
require_once '../config/helpers.php';
require_once '../config/db_config.php';

$conn = conectar();
$metodo = $_SERVER['REQUEST_METHOD'];

// Autocrear tabla puente si no existe (importante para que no falle al probarlo)
$conn->query("CREATE TABLE IF NOT EXISTS maquinas_piezas (
    id_maquina INT,
    id_pieza INT,
    PRIMARY KEY (id_maquina, id_pieza)
)");

// ── LÓGICA SEGÚN EL MÉTODO HTTP (GET, POST, DELETE) ───────────────
// Este switch maneja las operaciones CRUD para la tabla intermedia "maquinas_piezas",
// la cual relaciona las máquinas con las piezas que tienen instaladas.
switch ($metodo) {

    case 'GET':
        // MÉTODO GET: Recuperar piezas asignadas
        // Realiza una consulta con INNER JOIN para traer solo las piezas que están
        // vinculadas a alguna máquina en la tabla maquinas_piezas.
        $query = "SELECT p.* FROM piezas_repuesto p 
                  INNER JOIN maquinas_piezas mp ON p.id_pieza = mp.id_pieza";
        
        // Si se envía el parámetro 'id_maquina' en la URL, filtra los resultados
        // para mostrar únicamente las piezas de esa máquina específica.
        if (isset($_GET['id_maquina'])) {
            $id = (int)$_GET['id_maquina'];
            $query .= " WHERE mp.id_maquina = $id";
        }
        
        $res = $conn->query($query . " ORDER BY p.id_pieza ASC");
        $piezas = [];
        while ($f = $res->fetch_assoc()) $piezas[] = $f;
        
        // Devuelve el array de piezas en formato JSON
        respuestaOk(['piezas' => $piezas]);
        break;

    case 'POST':
        // MÉTODO POST: Asignar una pieza a una máquina
        // Lee los datos enviados en el cuerpo de la petición (JSON)
        $b = json_decode(file_get_contents('php://input'), true) ?? [];
        
        // Prepara la inserción segura en la tabla intermedia para crear el vínculo
        $stmt = $conn->prepare("INSERT INTO maquinas_piezas (id_maquina, id_pieza) VALUES (?, ?)");
        $stmt->bind_param("ii", $b['id_maquina'], $b['id_pieza']);
        
        if ($stmt->execute()) respuestaOk(['msg' => 'Pieza asignada correctamente.']);
        else respuestaError('Error al asignar: ' . $stmt->error, 500);
        break;

    case 'DELETE':
        // MÉTODO DELETE: Quitar una pieza de una máquina (Desasignar)
        // Recoge los IDs de la URL para saber qué relación eliminar
        $id_m = (int)($_GET['id_maquina'] ?? 0);
        $id_p = (int)($_GET['id_pieza'] ?? 0);

        // Borra el registro de la tabla intermedia que une esa máquina con esa pieza
        $stmt = $conn->prepare("DELETE FROM maquinas_piezas WHERE id_maquina=? AND id_pieza=?");
        $stmt->bind_param("ii", $id_m, $id_p);
        
        if ($stmt->execute()) respuestaOk(['msg' => 'Pieza desasignada correctamente.']);
        else respuestaError('Error al quitar: ' . $stmt->error, 500);
        break;

    default:
        // Si el cliente usa un método HTTP no soportado (como PUT o PATCH)
        respuestaError('Método no soportado.', 405);
        break;
}

$conn->close();
?>