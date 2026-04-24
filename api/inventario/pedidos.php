<?php
// ============================================================
//  api/inventario/pedidos.php
//  API REST para CRUD de pedidos_compra
// ============================================================
require_once '../config/helpers.php';
require_once '../config/db_config.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$conn = conectar();

switch ($metodo) {
    case 'GET':
        // Obtener la lista de pedidos
        $query = "
            SELECT 
                p.id_pedido, 
                p.fecha_pedido, 
                p.fecha_entrega_esperada, 
                p.fecha_entrega_real, 
                p.observaciones,
                p.id_proveedor,
                pr.nombre AS nombre_proveedor,
                p.id_estado,
                d.descripcion AS nombre_estado,
                (SELECT COALESCE(SUM(cantidad_solicitada * precio_unitario), 0) 
                 FROM pedidos_detalle 
                 WHERE id_pedido = p.id_pedido) AS importe_total
            FROM pedidos_compra p
            LEFT JOIN proveedores_maquinas pr ON p.id_proveedor = pr.id_proveedor
            LEFT JOIN definiciones d ON p.id_estado = d.id_definicion
            ORDER BY p.id_pedido DESC
        ";
        
        $res = $conn->query($query);
        if (!$res) {
            $conn->close();
            respuestaError('Error en consulta: ' . $conn->error, 500);
        }
        
        $pedidos = [];
        while ($fila = $res->fetch_assoc()) {
            $pedidos[] = $fila;
        }
        $conn->close();
        respuestaOk(['pedidos' => $pedidos]);
        break;

    case 'POST':
        // Crear un nuevo pedido
        $datos = json_decode(file_get_contents('php://input'), true);
        if (!$datos || empty($datos['id_proveedor'])) {
            $conn->close();
            respuestaError('Faltan datos obligatorios (Proveedor)', 400);
        }

        $id_proveedor = (int)$datos['id_proveedor'];
        $fecha_pedido = $datos['fecha_pedido'] ?? date('Y-m-d');
        $fecha_entrega_esperada = !empty($datos['fecha_entrega_esperada']) ? "'" . $conn->real_escape_string($datos['fecha_entrega_esperada']) . "'" : "NULL";
        $observaciones = $conn->real_escape_string($datos['observaciones'] ?? '');
        
        // Asignar un estado por defecto (asumiendo que 1 es 'Pendiente' o que se inserta sin él y coge default)
        // Pediremos el id_estado si viene, si no le ponemos NULL o 1.
        $id_estado = !empty($datos['id_estado']) ? (int)$datos['id_estado'] : 1; 

        $query = "INSERT INTO pedidos_compra (fecha_pedido, id_proveedor, id_estado, fecha_entrega_esperada, observaciones) 
                  VALUES ('$fecha_pedido', $id_proveedor, $id_estado, $fecha_entrega_esperada, '$observaciones')";
                  
        if ($conn->query($query)) {
            $id_generado = $conn->insert_id;
            $conn->close();
            respuestaOk(['id_pedido' => $id_generado, 'mensaje' => 'Pedido creado correctamente']);
        } else {
            $error = $conn->error;
            $conn->close();
            respuestaError('Error al crear pedido: ' . $error, 500);
        }
        break;

    case 'PUT':
        // Actualizar un pedido
        $datos = json_decode(file_get_contents('php://input'), true);
        if (!$datos || empty($datos['id_pedido'])) {
            $conn->close();
            respuestaError('Falta el ID del pedido', 400);
        }

        $id_pedido = (int)$datos['id_pedido'];
        $id_proveedor = (int)$datos['id_proveedor'];
        $fecha_pedido = $conn->real_escape_string($datos['fecha_pedido']);
        $fecha_entrega_esperada = !empty($datos['fecha_entrega_esperada']) ? "'" . $conn->real_escape_string($datos['fecha_entrega_esperada']) . "'" : "NULL";
        $id_estado = (int)$datos['id_estado'];
        $observaciones = $conn->real_escape_string($datos['observaciones'] ?? '');

        $query = "UPDATE pedidos_compra SET 
                    id_proveedor = $id_proveedor,
                    fecha_pedido = '$fecha_pedido',
                    fecha_entrega_esperada = $fecha_entrega_esperada,
                    id_estado = $id_estado,
                    observaciones = '$observaciones'
                  WHERE id_pedido = $id_pedido";
                  
        if ($conn->query($query)) {
            $conn->close();
            respuestaOk(['mensaje' => 'Pedido actualizado']);
        } else {
            $error = $conn->error;
            $conn->close();
            respuestaError('Error al actualizar: ' . $error, 500);
        }
        break;

    case 'DELETE':
        // Eliminar pedido
        $datos = json_decode(file_get_contents('php://input'), true);
        $id_pedido = isset($_GET['id']) ? (int)$_GET['id'] : (isset($datos['id_pedido']) ? (int)$datos['id_pedido'] : 0);
        
        if (!$id_pedido) {
            $conn->close();
            respuestaError('Falta el ID del pedido', 400);
        }

        // Primero borrar detalles para evitar error de FK (si no hay CASCADE)
        $conn->query("DELETE FROM pedidos_detalle WHERE id_pedido = $id_pedido");
        
        $query = "DELETE FROM pedidos_compra WHERE id_pedido = $id_pedido";
        if ($conn->query($query)) {
            $conn->close();
            respuestaOk(['mensaje' => 'Pedido eliminado']);
        } else {
            $error = $conn->error;
            $conn->close();
            respuestaError('Error al eliminar: ' . $error, 500);
        }
        break;

    default:
        $conn->close();
        respuestaError('Método no soportado', 405);
        break;
}
