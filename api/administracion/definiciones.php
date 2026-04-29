<?php
/**
 * PROYECTO: ERP Robots - Sistema de Gestión
 * AUTORA: Guadalupe MGM
 * DESCRIPCIÓN: Diccionario de configuración y categorías del sistema.
 * * USO TÉCNICO:
 * - Permite organizar parámetros por categorías y códigos internos.
 */

// ============================================================
//  api/definiciones.php  —  Diccionario de configuración
// ============================================================
require_once '../config/helpers.php';
require_once '../config/db_config.php';

$conn = conectar();
$m = $_SERVER['REQUEST_METHOD'];

// ── GET: Listar definiciones (todas o por categoría) ──────────
if ($m === 'GET') {
    // Si pasas ?categoria=X filtra, si no, muestra todas
    if (isset($_GET['categoria'])) {
        $cat = $_GET['categoria'];
        $s = $conn->prepare("SELECT * FROM definiciones WHERE categoria = ? ORDER BY codigo ASC");
        $s->bind_param("s", $cat);
        $s->execute();
        $r = $s->get_result();
    } else {
        $r = $conn->query("SELECT * FROM definiciones ORDER BY categoria, codigo ASC");
    }

    $lista = [];
    while ($f = $r->fetch_assoc()) { 
        $lista[] = $f; 
    }
    $conn->close();
    respuestaOk(['definiciones' => $lista]);
}

// ── POST: Crear una nueva definición ──────────────────────────
if ($m === 'POST') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $categoria   = trim($b['categoria'] ?? '');
    $codigo      = trim($b['codigo'] ?? '');
    $descripcion = trim($b['descripcion'] ?? '');

    if (!$categoria || !$codigo) {
        respuestaError('La categoría y el código son obligatorios.');
    }

    // Verificar si ya existe una definición con la misma categoría y código
    $chk = $conn->prepare("SELECT id_definicion FROM definiciones WHERE categoria=? AND codigo=?");
    $chk->bind_param("ss", $categoria, $codigo); $chk->execute(); $chk->store_result();
    if ($chk->num_rows > 0) { $chk->close(); $conn->close(); respuestaError('Ya existe una definición con esa categoría y código.'); }
    $chk->close();

    $s = $conn->prepare("INSERT INTO definiciones (categoria, codigo, descripcion) VALUES (?, ?, ?)");
    $s->bind_param("sss", $categoria, $codigo, $descripcion);
    
    $ok = $s->execute();
    $nid = $conn->insert_id;
    $s->close(); $conn->close();

    if (!$ok) respuestaError('Error al guardar la definición.', 500);
    respuestaOk(['msg' => "Definición '$codigo' creada.", 'id' => $nid]);
}

// ── DELETE: Eliminar una definición ───────────────────────────
if ($m === 'DELETE') {
    $b = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($b['id_definicion'] ?? 0);

    if ($id <= 0) respuestaError('ID no válido.');

    $s = $conn->prepare("DELETE FROM definiciones WHERE id_definicion = ?");
    $s->bind_param("i", $id);
    $ok = $s->execute();
    $s->close(); $conn->close();

    if (!$ok) respuestaError('Error al eliminar.', 500);
    respuestaOk(['msg' => 'Definición eliminada correctamente.']);
}

respuestaError('Método no soportado.', 405);