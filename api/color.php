<?php
// api/color.php  —  GET obtiene color | POST guarda color
// cURL GET:  curl -b cookies.txt http://localhost/api/color.php
// cURL POST: curl -b cookies.txt -X POST http://localhost/api/color.php \
//            -H "Content-Type: application/json" -d '{"color":"lightblue"}'
require_once 'helpers.php';
$usuario = requireAuth();
$archivo = __DIR__.'/../colores.txt';

function leerColores($f){
    $c=[];
    if(!file_exists($f)) return $c;
    foreach(file($f,FILE_IGNORE_NEW_LINES) as $l){
        if(trim($l)==='') continue;
        [$u,$v]=explode(':',$l,2);
        $c[trim($u)]=trim($v);
    }
    return $c;
}
$permitidos=['white','lightblue','lightgreen','lightyellow','lightpink','lightcoral','lavender'];

if($_SERVER['REQUEST_METHOD']==='GET'){
    $c=leerColores($archivo);
    respuestaOk(['color'=>$c[$usuario]??'white']);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    $b=json_decode(file_get_contents('php://input'),true)??[];
    $color=trim($b['color']??'');
    if(!in_array($color,$permitidos)) respuestaError('Color no válido.');
    $c=leerColores($archivo); $c[$usuario]=$color;
    $s=''; foreach($c as $u=>$v) $s.="$u:$v\n";
    file_put_contents($archivo,$s);
    respuestaOk(['color'=>$color]);
}
respuestaError('Método no soportado.',405);
