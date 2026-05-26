<?php
// ============================================================
//  api/inventario/pedidos_opciones.php
//  Obtiene listas para selects (proveedores y estados)
// ============================================================
// Referencia en la web:
//   - Pantalla "Gestión de Pedidos" (HTML/inventarioMenu/pedidos.html) -> modal de creación
require_once '../config/helpers.php';
require_once '../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respuestaError('Método no permitido', 405);
}

$conn = conectar();

// Obtener proveedores
$res_prov = $conn->query("SELECT id_proveedor, nombre FROM proveedores_maquinas ORDER BY nombre ASC");
$proveedores = [];
if ($res_prov) {
    while ($f = $res_prov->fetch_assoc()) {
        $proveedores[] = $f;
    }
}

// Obtener estados de pedido (asumimos que en definiciones hay un tipo 'estado_pedido' o devolvemos todos)
// Ajusta el WHERE si tu tabla definiciones usa un campo 'tipo' o 'categoria' específico.
$res_est = $conn->query("SELECT id_definicion, descripcion AS valor FROM definiciones LIMIT 50");
$estados = [];
if ($res_est) {
    while ($f = $res_est->fetch_assoc()) {
        $estados[] = $f;
    }
}

// Obtener piezas
$res_piezas = $conn->query("SELECT id_pieza, nombre, precio_unitario FROM piezas_repuesto ORDER BY nombre ASC");
$piezas = [];
if ($res_piezas) {
    while ($f = $res_piezas->fetch_assoc()) {
        $piezas[] = $f;
    }
}

$conn->close();

respuestaOk([
    'proveedores' => $proveedores,
    'estados' => $estados,
    'piezas' => $piezas
]);
