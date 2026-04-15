<?php
// ============================================================
//  api/log_acceso.php
//  POST  → registra visita
//  GET   → lista con paginación (20 por página)
//
//  cURL:
//  curl -b cookies.txt -X POST http://localhost/api/log_acceso.php \
//       -H "Content-Type: application/json" -d '{"pagina":"pagina1"}'
//
//  curl -b cookies.txt "http://localhost/api/log_acceso.php?pagina=1"
// ============================================================
require_once 'helpers.php';
require_once 'db_config.php';
$usuario = requireAuth();

if($_SERVER['REQUEST_METHOD']==='POST'){
    $b=json_decode(file_get_contents('php://input'),true)??[];
    $pagina=trim($b['pagina']??'?');
    $conn=conectar();
    registrarLog($conn,$usuario,$pagina);
    $conn->close();
    respuestaOk(['msg'=>'OK']);
}
if($_SERVER['REQUEST_METHOD']==='GET'){
    $pag=max(1,(int)($_GET['pagina']??1));
    $por=20; $off=($pag-1)*$por;
    $conn=conectar();
    $total=(int)$conn->query("SELECT COUNT(*) c FROM log_accesos")->fetch_assoc()['c'];
    $s=$conn->prepare("SELECT usuario,pagina,timestamp,session_id FROM log_accesos ORDER BY timestamp DESC LIMIT ? OFFSET ?");
    $s->bind_param("ii",$por,$off); $s->execute();
    $r=$s->get_result(); $filas=[];
    while($f=$r->fetch_assoc()) $filas[]=$f;
    $s->close(); $conn->close();
    respuestaOk([
        'registros'=>$filas,'total'=>$total,'pagina'=>$pag,
        'por_pagina'=>$por,'total_paginas'=>(int)ceil($total/$por)
    ]);
}
respuestaError('Método no soportado.',405);
