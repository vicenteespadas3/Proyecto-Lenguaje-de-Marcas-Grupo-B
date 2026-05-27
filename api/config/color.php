<?php
/**
 * ============================================================================
 * API Endpoint: /api/config/color.php
 * ============================================================================
 * Propósito:
 *   Este archivo gestiona el color de acento personalizado elegido por cada
 *   usuario para personalizar su interfaz. Los colores se guardan en un archivo
 *   de texto plano (colores.txt) en lugar de la base de datos por simplicidad.
 *
 * Referencia en la web:
 *   - Menú de Configuración/Perfil del usuario (HTML/perfil.html).
 *   - Se invoca desde app.js para aplicar el color al CSS.
 *
 * Métodos soportados:
 *   - GET  : Obtiene el color actual del usuario logueado.
 *   - POST : Guarda o actualiza el color elegido por el usuario.
 * ============================================================================
 */

// Incluir helpers para respuestas JSON y manejo de errores
require_once 'helpers.php';

// Validar que el usuario tenga una sesión activa. Si no, devuelve error 401.
$usuario = requireAuth();

// Ruta absoluta al archivo donde se almacenan los colores de todos los usuarios
$archivo = __DIR__.'/../colores.txt';
$archivo = __DIR__.'/../colores.txt';

/**
 * Función auxiliar para leer y parsear el archivo de texto colores.txt
 * 
 * @param string $f Ruta del archivo a leer
 * @return array Array asociativo con pares [email_usuario => color]
 */
function leerColores($f){
    $c=[];
    // Si el archivo no existe, devuelve un array vacío
    if(!file_exists($f)) return $c;
    
    // Lee el archivo línea por línea ignorando saltos de línea finales
    foreach(file($f,FILE_IGNORE_NEW_LINES) as $l){
        if(trim($l)==='') continue;
        
        // Cada línea tiene formato "email:color"
        [$u,$v]=explode(':',$l,2);
        
        // Lo guarda en el array usando el email como clave
        $c[trim($u)]=trim($v);
    }
    return $c;
}

// Lista blanca de colores permitidos por seguridad (evita inyección de CSS malicioso)
$permitidos=['white','lightblue','lightgreen','lightyellow','lightpink','lightcoral','lavender'];

// ----------------------------------------------------------------------------
// MÉTODO GET: Obtener el color del usuario actual
// ----------------------------------------------------------------------------
if($_SERVER['REQUEST_METHOD']==='GET'){
    $c=leerColores($archivo);
    
    // Devuelve el color guardado o 'white' por defecto si el usuario no tiene color
    respuestaOk(['color'=>$c[$usuario]??'white']);
}

// ----------------------------------------------------------------------------
// MÉTODO POST: Guardar el nuevo color del usuario
// ----------------------------------------------------------------------------
if($_SERVER['REQUEST_METHOD']==='POST'){
    // Decodificar el JSON enviado por el frontend (app.js)
    $b=json_decode(file_get_contents('php://input'),true)??[];
    $color=trim($b['color']??'');
    
    // Validar que el color esté en la lista de permitidos
    if(!in_array($color,$permitidos)) respuestaError('Color no válido.');
    
    // Leer colores existentes
    $c=leerColores($archivo); 
    
    // Asignar el nuevo color al usuario actual
    $c[$usuario]=$color;
    
    // Reconstruir el string para guardar en el archivo de texto
    $s=''; 
    foreach($c as $u=>$v) {
        $s.="$u:$v\n";
    }
    
    // Escribir los cambios en el archivo físico
    file_put_contents($archivo,$s);
    
    // Responder con éxito
    respuestaOk(['color'=>$color]);
}

// Si se usa un método distinto a GET o POST, rechazar petición
respuestaError('Método no soportado.',405);
