<?php
// ============================================================
//  api/usuarios.php  —  CRUD completo (solo admins)
// ============================================================
//  GET           → listar todos
//  GET ?id=N     → obtener uno
//  POST          → alta
//  PUT           → modificación
//  DELETE        → baja
//
//  cURL (requiere cookie de sesión admin):
//
//  # Login previo:
//  curl -c cookies.txt -X POST http://localhost/api/login.php \
//       -H "Content-Type: application/json" \
//       -d '{"usuario":"admin","password":"1234","metodo":"v1"}'
//
//  # Listar:
//  curl -b cookies.txt http://localhost/api/usuarios.php
//
//  # Obtener uno:
//  curl -b cookies.txt "http://localhost/api/usuarios.php?id=2"
//
//  # Alta:
//  curl -b cookies.txt -X POST http://localhost/api/usuarios.php \
//       -H "Content-Type: application/json" \
//       -d '{"usuario":"marta","password":"1234","nombre":"Marta","apellidos":"Ruiz","es_admin":0}'
//
//  # Modificar:
//  curl -b cookies.txt -X PUT http://localhost/api/usuarios.php \
//       -H "Content-Type: application/json" \
//       -d '{"id":3,"usuario":"marta","nombre":"Marta","apellidos":"Lopez","es_admin":1}'
//
//  # Baja:
//  curl -b cookies.txt -X DELETE http://localhost/api/usuarios.php \
//       -H "Content-Type: application/json" \
//       -d '{"id":3}'
// ============================================================
require_once '../config/helpers.php';
require_once '../config/db_config.php';

$yo   = requireAdmin();
$conn = conectar();
$m    = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────
if ($m === 'GET') {
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $s  = $conn->prepare("SELECT id,usuario,nombre,apellidos,es_admin FROM usuarios WHERE id=?");
        $s->bind_param("i",$id); $s->execute();
        $f  = $s->get_result()->fetch_assoc(); $s->close(); $conn->close();
        if (!$f) respuestaError('No encontrado.',404);
        $f['es_admin'] = (int)$f['es_admin'];
        respuestaOk(['usuario'=>$f]);
    } else {
        $r = $conn->query("SELECT id,usuario,nombre,apellidos,es_admin FROM usuarios ORDER BY apellidos,nombre");
        $lista = [];
        while ($f = $r->fetch_assoc()) { $f['es_admin']=(int)$f['es_admin']; $lista[]=$f; }
        $conn->close();
        respuestaOk(['usuarios'=>$lista,'total'=>count($lista)]);
    }
}

// ── POST (alta) ───────────────────────────────────────────────
if ($m === 'POST') {
    $b         = json_decode(file_get_contents('php://input'),true)??[];
    $usuario   = trim($b['usuario']   ?? '');
    $password  = trim($b['password']  ?? '');
    $nombre    = trim($b['nombre']    ?? '');
    $apellidos = trim($b['apellidos'] ?? '');
    $es_admin  = isset($b['es_admin']) ? (int)$b['es_admin'] : 0;

    if (!$usuario)  respuestaError('El usuario es obligatorio.');
    if (!$password) respuestaError('La contraseña es obligatoria.');
    if (strlen($usuario)<3) respuestaError('El usuario necesita al menos 3 caracteres.');

    $chk = $conn->prepare("SELECT id FROM usuarios WHERE usuario=?");
    $chk->bind_param("s",$usuario); $chk->execute(); $chk->store_result();
    if ($chk->num_rows>0) { $chk->close();$conn->close(); respuestaError('Usuario ya existe.'); }
    $chk->close();

    $s = $conn->prepare("INSERT INTO usuarios (usuario,password,nombre,apellidos,es_admin) VALUES (?,?,?,?,?)");
    $s->bind_param("ssssi",$usuario,$password,$nombre,$apellidos,$es_admin);
    $ok = $s->execute(); $nid = $conn->insert_id; $s->close(); $conn->close();
    if (!$ok) respuestaError('Error al crear.',500);
    respuestaOk(['msg'=>"Usuario '$usuario' creado.",'id'=>$nid]);
}

// ── PUT (modificación) ────────────────────────────────────────
if ($m === 'PUT') {
    $b         = json_decode(file_get_contents('php://input'),true)??[];
    $id        = (int)($b['id']       ?? 0);
    $usuario   = trim($b['usuario']   ?? '');
    $nombre    = trim($b['nombre']    ?? '');
    $apellidos = trim($b['apellidos'] ?? '');
    $es_admin  = isset($b['es_admin']) ? (int)$b['es_admin'] : 0;
    $newpass   = trim($b['password']  ?? '');

    if ($id<=0)    respuestaError('ID no válido.');
    if (!$usuario) respuestaError('El usuario es obligatorio.');

    // Existe?
    $chk = $conn->prepare("SELECT id FROM usuarios WHERE id=?");
    $chk->bind_param("i",$id); $chk->execute(); $chk->store_result();
    if ($chk->num_rows===0) { $chk->close();$conn->close(); respuestaError('No encontrado.',404); }
    $chk->close();

    // Nombre duplicado?
    $chk2 = $conn->prepare("SELECT id FROM usuarios WHERE usuario=? AND id!=?");
    $chk2->bind_param("si",$usuario,$id); $chk2->execute(); $chk2->store_result();
    if ($chk2->num_rows>0) { $chk2->close();$conn->close(); respuestaError('Ese usuario ya lo usa otro.'); }
    $chk2->close();

    if ($newpass !== '') {
        $s = $conn->prepare("UPDATE usuarios SET usuario=?,password=?,nombre=?,apellidos=?,es_admin=? WHERE id=?");
        $s->bind_param("ssssii",$usuario,$newpass,$nombre,$apellidos,$es_admin,$id);
    } else {
        $s = $conn->prepare("UPDATE usuarios SET usuario=?,nombre=?,apellidos=?,es_admin=? WHERE id=?");
        $s->bind_param("sssii",$usuario,$nombre,$apellidos,$es_admin,$id);
    }
    $ok = $s->execute(); $s->close(); $conn->close();
    if (!$ok) respuestaError('Error al modificar.',500);
    respuestaOk(['msg'=>'Usuario actualizado.']);
}

// ── DELETE (baja) ─────────────────────────────────────────────
if ($m === 'DELETE') {
    $b  = json_decode(file_get_contents('php://input'),true)??[];
    $id = (int)($b['id']??0);
    if ($id<=0) respuestaError('ID no válido.');

    $chk = $conn->prepare("SELECT usuario FROM usuarios WHERE id=?");
    $chk->bind_param("i",$id); $chk->execute();
    $f = $chk->get_result()->fetch_assoc(); $chk->close();
    if (!$f)                   { $conn->close(); respuestaError('No encontrado.',404); }
    if ($f['usuario']===$yo)   { $conn->close(); respuestaError('No puedes eliminarte.'); }

    $s = $conn->prepare("DELETE FROM usuarios WHERE id=?");
    $s->bind_param("i",$id); $ok=$s->execute(); $s->close(); $conn->close();
    if (!$ok) respuestaError('Error al eliminar.',500);
    respuestaOk(['msg'=>"Usuario '{$f['usuario']}' eliminado."]);
}

respuestaError('Método no soportado.',405);
