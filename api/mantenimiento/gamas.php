<?php
// ============================================================
//  api/gamas.php  —  CRUD para Gamas de Mantenimiento
// ============================================================
require_once '../config/helpers.php';
require_once '../config/db_config.php';

// Descomentar la siguiente línea si se requiere autenticación para este endpoint
// $yo = requireAuth();

$conn = conectar();
$m    = $_SERVER['REQUEST_METHOD'];

// ── GET ───────────────────────────────────────────────────────
//Get: Leer recursos, sin cambiar el estado del servidor(operacion "segura")

if ($m === 'GET') {
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $s  = $conn->prepare("SELECT id_gama, nombre, tipo_mantenimiento, tipo_gama FROM gamas_mantenimiento WHERE id_gama=?");
        $s->bind_param("i", $id); 
        $s->execute();
        //En $f se almacenan los resultados de $s sacados de la funcion get_result, y con la función fetch_assoc(), que devuelve un array asociativo con los datos de la gama encontrada.
        // Si no se encuentra ninguna gama con el ID proporcionado, $f será false.
        $f  = $s->get_result()->fetch_assoc(); 
        $s->close(); 
        $conn->close();
        // Si no se encuentra la gama, se devuelve un error 404. Si se encuentra, se devuelve la información de la gama en formato JSON.
        if (!$f) respuestaError('Gama no encontrada.', 404);
        respuestaOk(['gamas' => $f]);
        // Si se proporciona un ID, se devuelve la gama específica. Si no se proporciona un ID, se devuelve la lista completa de gamas.
    } else {
        $buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
        
        // Si se proporciona un término de búsqueda, se realiza una consulta preparada para buscar gamas cuyo nombre contenga ese término. Si no se proporciona un término de búsqueda, se devuelve la lista completa de gamas ordenada por nombre.
        if ($buscar !== '') {
            $s = $conn->prepare("SELECT id_gama, nombre, tipo_mantenimiento, tipo_gama FROM gamas_mantenimiento WHERE nombre LIKE ? OR id_gama LIKE ? ORDER BY nombre");
            $like = "%" . $buscar . "%";
            $s->bind_param("ss", $like, $like);
            $s->execute();
            $r = $s->get_result();
            $lista = [];
            if ($r) {
                while ($f = $r->fetch_assoc()) { 
                    $lista[] = $f; 
                }
            }
            $s->close();
        } else {
            $r = $conn->query("SELECT id_gama, nombre, tipo_mantenimiento, tipo_gama FROM gamas_mantenimiento ORDER BY nombre");
            $lista = [];
            if ($r) {
                while ($f = $r->fetch_assoc()) { 
                    $lista[] = $f; 
                }
            }
        }
        
        $conn->close();
        respuestaOk(['gamas' => $lista, 'total' => count($lista)]);
    }
}

// ── POST (alta) ───────────────────────────────────────────────
//Post: Crear recursos, con datos enviados en el cuerpo de la petición (body), generalmente en formato JSON. Esta operación no es "segura" porque modifica el estado del servidor.
    //No usamos $_POST porque si el cliente manda un JSON el $_POST llega vacío y tendrías que usar php://input para leer el cuerpo de la petición.
    // Por eso se ignora el $_POST y se lee directamente el cuerpo de la petición con file_get_contents('php://input'), que devuelve el contenido del cuerpo de la petición como una cadena. 
    // Luego, esa cadena se decodifica como JSON usando json_decode, lo que convierte el JSON en un array asociativo de PHP. Si no se recibe un JSON válido, se asigna un array vacío.

if ($m === 'POST') {
    //Lee el cuerpo de la petición como JSON y lo convierte en un array PHP. Si no se recibe un JSON válido, se asigna un array vacío.
    $b                  = json_decode(file_get_contents('php://input'), true) ?? [];
    $nombre             = trim($b['nombre'] ?? '');
    $tipo_mantenimiento = trim($b['tipo_mantenimiento'] ?? 'preventivo');
    $tipo_gama          = trim($b['tipo_gama'] ?? 'mecanico');

    if (!$nombre) respuestaError('El nombre de la gama es obligatorio.');
    //Preparamos la conexión para insertar una nueva gama en la base de datos. La consulta SQL se prepara con 
    //marcadores de posición (?) para evitar inyecciones SQL. Luego, se vinculan los parámetros con los valores proporcionados por el cliente y se ejecuta la consulta.
    $s = $conn->prepare("INSERT INTO gamas_mantenimiento (nombre, tipo_mantenimiento, tipo_gama) VALUES (?, ?, ?)");
    //Con bind_param, se vinculan los valores de $nombre, $tipo_mantenimiento y $tipo_gama a los marcadores de posición en la consulta SQL. El primer argumento "sss" indica que los tres parámetros son cadenas de texto (string).
    $s->bind_param("sss", $nombre, $tipo_mantenimiento, $tipo_gama);
    //Ejecutamos la consulta y almacenamos el resultado en $ok. Si la ejecución es exitosa, $ok será true; de lo contrario, será false.
    $ok = $s->execute(); 
    $db_error = $s->error; // Capturamos el error exacto de la base de datos
    //Obtenemos el ID de la gama insertada
    $nid = $conn->insert_id; 
    $s->close(); 
    $conn->close();
    if (!$ok) respuestaError('Error al crear la gama: ' . $db_error, 500);
    respuestaOk(['msg' => "Gama '$nombre' creada.", 'id_gama' => $nid]);
}

// ── PUT (modificación) ────────────────────────────────────────
if ($m === 'PUT') {
    //No usamos $_POST porque si el cliente manda un JSON el $_POST llega vacío y tendrías que usar php://input para leer el cuerpo de la petición.
    // Por eso se ignora el $_POST y se lee directamente el cuerpo de la petición con file_get_contents('php://input'), que devuelve el contenido del cuerpo de la petición como una cadena. 
    // Luego, esa cadena se decodifica como JSON usando json_decode, lo que convierte el JSON en un array asociativo de PHP. Si no se recibe un JSON válido, se asigna un array vacío.
    $b                  = json_decode(file_get_contents('php://input'), true) ?? [];
    //php://input te da ek body crudo nosotros decidimos si es JSON, XML y lo parseamos manualmente
    $id_gama            = (int)($b['id_gama'] ?? 0);
    $nombre             = trim($b['nombre'] ?? '');
    $tipo_mantenimiento = trim($b['tipo_mantenimiento'] ?? 'preventivo');
    $tipo_gama          = trim($b['tipo_gama'] ?? 'mecanico');

    if ($id_gama <= 0) respuestaError('ID de gama no válido.');
    if (!$nombre) respuestaError('El nombre de la gama es obligatorio.');

    //Antes de intentar modificar la gama, verificamos que exista en la base de datos. Si no existe, devolvemos un error 404. Si existe, procedemos a actualizar sus datos.
    $chk = $conn->prepare("SELECT id_gama FROM gamas_mantenimiento WHERE id_gama=?");
    $chk->bind_param("i", $id_gama); 
    $chk->execute(); 
    $chk->store_result();
    if ($chk->num_rows === 0) { 
        $chk->close(); 
        $conn->close(); 
        respuestaError('Gama no encontrada.', 404); 
    }
    $chk->close();

    //Preparamos la consulta SQL para actualizar la gama en la base de datos. La consulta se prepara con marcadores de posición (?) para evitar inyecciones SQL. Luego, se vinculan los 
    //parámetros con los valores proporcionados por el cliente y se ejecuta la consulta.
    $s = $conn->prepare("UPDATE gamas_mantenimiento SET nombre=?, tipo_mantenimiento=?, tipo_gama=? WHERE id_gama=?");
    $s->bind_param("sssi", $nombre, $tipo_mantenimiento, $tipo_gama, $id_gama);
    $ok = $s->execute(); 
    $s->close(); 
    $conn->close();
    if (!$ok) respuestaError('Error al modificar la gama.', 500);
    respuestaOk(['msg' => 'Gama actualizada correctamente.']);
}

// ── DELETE (baja) ─────────────────────────────────────────────
if ($m === 'DELETE') {
    //No usamos $_POST porque si el cliente manda un JSON el $_POST llega vacío y tendrías que usar php://input para leer el cuerpo de la petición.
    // Por eso se ignora el $_POST y se lee directamente el cuerpo de la petición con file_get_contents('php://input'), que devuelve el contenido del cuerpo de la petición como una cadena. 
    // Luego, esa cadena se decodifica como JSON usando json_decode, lo que convierte el JSON en un array asociativo de PHP. Si no se recibe un JSON válido, se asigna un array vacío.

    $b       = json_decode(file_get_contents('php://input'), true) ?? [];
    $id_gama = (int)($b['id_gama'] ?? 0);
    if ($id_gama <= 0) respuestaError('ID de gama no válido.');

    $s = $conn->prepare("DELETE FROM gamas_mantenimiento WHERE id_gama=?");
    $s->bind_param("i", $id_gama); 
    $ok = $s->execute(); 
    $s->close(); 
    $conn->close();
    if (!$ok) respuestaError('Error al eliminar la gama.', 500);
    respuestaOk(['msg' => "Gama eliminada correctamente."]);
}

respuestaError('Método no soportado.', 405);
