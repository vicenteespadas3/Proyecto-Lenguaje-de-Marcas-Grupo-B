<?php
// ============================================================
// api/inventario/piezas.php — CRUD completo para Piezas y Asignaciones
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

// No autocreamos la tabla piezas_repuesto porque ya existe en la base de datos con una estructura específica.

switch ($metodo) {

    case 'GET':
        if (isset($_GET['id_maquina'])) {
            // Recuperar piezas asignadas a UNA máquina específica
            $id = (int)$_GET['id_maquina'];
            $query = "SELECT p.* FROM piezas_repuesto p 
                      INNER JOIN maquinas_piezas mp ON p.id_pieza = mp.id_pieza
                      WHERE mp.id_maquina = $id ORDER BY p.nombre ASC";
        } else {
            // Recuperar TODAS las piezas (inventario general)
            $query = "SELECT * FROM piezas_repuesto ORDER BY id_pieza DESC";
        }
        
        $res = $conn->query($query);
        $piezas = [];
        if ($res) {
            while ($f = $res->fetch_assoc()) $piezas[] = $f;
        }
        
        respuestaOk(['piezas' => $piezas]);
        break;

    case 'POST':
        $b = json_decode(file_get_contents('php://input'), true) ?? [];
        
        if (isset($b['id_maquina'])) {
            // Asignar una pieza a una máquina (tabla intermedia)
            $stmt = $conn->prepare("INSERT INTO maquinas_piezas (id_maquina, id_pieza) VALUES (?, ?)");
            $stmt->bind_param("ii", $b['id_maquina'], $b['id_pieza']);
            
            if ($stmt->execute()) respuestaOk(['msg' => 'Pieza asignada correctamente.']);
            else respuestaError('Error al asignar: ' . $stmt->error, 500);
        } else {
            // Crear una NUEVA pieza en el inventario
            $nombre = trim($b['nombre'] ?? '');
            $cod_int = trim($b['codigo_interno'] ?? '');
            $cod_prov = trim($b['codigo_proveedor'] ?? '');
            $desc = trim($b['descripcion'] ?? '');
            $stock = (int)($b['stock_actual'] ?? 0);
            $min = (int)($b['stock_minimo'] ?? 0);
            $max = (int)($b['stock_maximo'] ?? 0);
            $precio = (float)($b['precio_unitario'] ?? 0);
            $ubicacion = trim($b['ubicacion_almacen'] ?? '');
            $id_proveedor = (int)($b['id_proveedor'] ?? 0);

            if (!$nombre) respuestaError('El nombre de la pieza es obligatorio.');

            // Si id_proveedor es 0, lo ponemos a NULL para evitar error de clave foránea
            $id_prov_val = $id_proveedor > 0 ? $id_proveedor : null;

            $stmt = $conn->prepare("INSERT INTO piezas_repuesto (nombre, codigo_interno, codigo_proveedor, descripcion, stock_actual, stock_minimo, stock_maximo, precio_unitario, ubicacion_almacen, id_proveedor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssiiidsi", $nombre, $cod_int, $cod_prov, $desc, $stock, $min, $max, $precio, $ubicacion, $id_prov_val);

            if ($stmt->execute()) {
                respuestaOk(['msg' => 'Pieza creada con éxito.', 'id_pieza' => $stmt->insert_id]);
            } else {
                respuestaError('Error al crear pieza: ' . $stmt->error, 500);
            }
        }
        break;

    case 'PUT':
        // Actualizar una pieza existente
        $b = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = (int)($b['id_pieza'] ?? 0);
        $nombre = trim($b['nombre'] ?? '');
        $cod_int = trim($b['codigo_interno'] ?? '');
        $cod_prov = trim($b['codigo_proveedor'] ?? '');
        $desc = trim($b['descripcion'] ?? '');
        $stock = (int)($b['stock_actual'] ?? 0);
        $min = (int)($b['stock_minimo'] ?? 0);
        $max = (int)($b['stock_maximo'] ?? 0);
        $precio = (float)($b['precio_unitario'] ?? 0);
        $ubicacion = trim($b['ubicacion_almacen'] ?? '');
        $id_proveedor = (int)($b['id_proveedor'] ?? 0);

        if ($id <= 0) respuestaError('ID de pieza no válido.');
        if (!$nombre) respuestaError('El nombre es obligatorio.');

        // Si id_proveedor es 0, lo ponemos a NULL
        $id_prov_val = $id_proveedor > 0 ? $id_proveedor : null;

        $stmt = $conn->prepare("UPDATE piezas_repuesto SET nombre=?, codigo_interno=?, codigo_proveedor=?, descripcion=?, stock_actual=?, stock_minimo=?, stock_maximo=?, precio_unitario=?, ubicacion_almacen=?, id_proveedor=? WHERE id_pieza=?");
        $stmt->bind_param("ssssiiidsii", $nombre, $cod_int, $cod_prov, $desc, $stock, $min, $max, $precio, $ubicacion, $id_prov_val, $id);

        if ($stmt->execute()) respuestaOk(['msg' => 'Pieza actualizada con éxito.']);
        else respuestaError('Error al actualizar pieza: ' . $stmt->error, 500);
        break;

    case 'DELETE':
        $b = json_decode(file_get_contents('php://input'), true) ?? [];
        
        // Si viene id_maquina por URL o por body, es una desasignación
        $id_m = (int)($_GET['id_maquina'] ?? $b['id_maquina'] ?? 0);
        $id_p = (int)($_GET['id_pieza'] ?? $b['id_pieza'] ?? 0);

        if ($id_m > 0 && $id_p > 0) {
            // Desasignar pieza de máquina
            $stmt = $conn->prepare("DELETE FROM maquinas_piezas WHERE id_maquina=? AND id_pieza=?");
            $stmt->bind_param("ii", $id_m, $id_p);
            
            if ($stmt->execute()) respuestaOk(['msg' => 'Pieza desasignada correctamente.']);
            else respuestaError('Error al quitar pieza: ' . $stmt->error, 500);
        } else if ($id_p > 0) {
            // Eliminar pieza definitivamente del inventario
            // Primero, borrar las asignaciones por integridad referencial
            $conn->query("DELETE FROM maquinas_piezas WHERE id_pieza = $id_p");
            // Luego borrar la pieza
            $stmt = $conn->prepare("DELETE FROM piezas_repuesto WHERE id_pieza=?");
            $stmt->bind_param("i", $id_p);

            if ($stmt->execute()) respuestaOk(['msg' => 'Pieza eliminada correctamente.']);
            else respuestaError('Error al eliminar pieza: ' . $stmt->error, 500);
        } else {
            respuestaError('Faltan parámetros para eliminar.', 400);
        }
        break;

    default:
        respuestaError('Método no soportado.', 405);
        break;
}

$conn->close();
?>