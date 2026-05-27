<?php
/**
 * ============================================================================
 * Archivo: api/auth/log_acceso.php
 * ============================================================================
 * Propósito:
 *   Gestiona el registro (auditoría) de los accesos de los usuarios.
 *   Permite grabar cada vez que un usuario visita una página y permite 
 *   consultar ese historial (paginado) desde el panel de administración.
 *
 * Referencia en la web:
 *   - POST (grabar): Se llama invisiblemente desde Js/app.js en cada navegación.
 *   - GET (leer): Se llama desde HTML/administracionMenu/usuarios.html para mostrar la tabla de logs.
 * ============================================================================
 */
require_once '../config/helpers.php';
require_once '../config/db_config.php';

// Bloquea el acceso si el usuario no tiene una sesión iniciada
$usuario = requireAuth();

// ----------------------------------------------------------------------------
// MÉTODO POST: Registrar una nueva visita
// ----------------------------------------------------------------------------
if($_SERVER['REQUEST_METHOD']==='POST'){
    // Decodifica el body JSON (espera {"pagina": "nombre_pantalla"})
    $b=json_decode(file_get_contents('php://input'),true)??[];
    $pagina=trim($b['pagina']??'?');
    
    // Conecta y llama al helper que guarda en la tabla log_accesos
    $conn=conectar();
    registrarLog($conn,$usuario,$pagina);
    $conn->close();
    
    // Devuelve confirmación silenciosa
    respuestaOk(['msg'=>'OK']);
}
// ----------------------------------------------------------------------------
// MÉTODO GET: Listar historial de accesos (paginado)
// ----------------------------------------------------------------------------
if($_SERVER['REQUEST_METHOD']==='GET'){
    // Calcula la paginación (por defecto página 1)
    $pag=max(1,(int)($_GET['pagina']??1));
    $por=20; // Registros por página
    $off=($pag-1)*$por; // Offset (desde dónde empezar)
    
    $conn=conectar();
    
    // 1. Obtener el número total de registros para calcular las páginas totales
    $total=(int)$conn->query("SELECT COUNT(*) c FROM log_accesos")->fetch_assoc()['c'];
    
    // 2. Obtener los registros de la página actual ordenados de más recientes a más antiguos
    $s=$conn->prepare("SELECT usuario,pagina,timestamp,session_id FROM log_accesos ORDER BY timestamp DESC LIMIT ? OFFSET ?");
    $s->bind_param("ii",$por,$off); 
    $s->execute();
    
    $r=$s->get_result(); 
    $filas=[];
    while($f=$r->fetch_assoc()) {
        $filas[]=$f; // Añadir cada registro al array de respuesta
    }
    
    $s->close(); 
    $conn->close();
    
    // Devuelve los datos paginados para que el frontend pueda construir la tabla
    respuestaOk([
        'registros'    => $filas,
        'total'        => $total,
        'pagina'       => $pag,
        'por_pagina'   => $por,
        'total_paginas'=> (int)ceil($total/$por)
    ]);
}

// Si llega hasta aquí, se usó un método incorrecto (ej. PUT, DELETE)
respuestaError('Método no soportado.',405);
