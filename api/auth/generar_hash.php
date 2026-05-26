<?php
/**
 * ============================================================================
 * Archivo: api/auth/generar_hash.php
 * ============================================================================
 * Propósito:
 *   Utilidad interna para administradores o desarrolladores.
 *   Permite generar el hash criptográfico (Bcrypt) de una contraseña en texto plano,
 *   para poder insertarlo manualmente en la base de datos (columna password_hash).
 * 
 * Uso:
 *   Acceder desde el navegador a: http://.../api/auth/generar_hash.php?pass=MI_CONTRASEÑA
 *
 * Referencia en la web:
 *   - No enlazado desde ninguna pantalla del ERP. Es solo una herramienta de backend.
 * ============================================================================
 */

// Si se recibe el parámetro 'pass' por GET (en la URL)
if (isset($_GET['pass'])) {
    $pass = $_GET['pass'];
    echo "<h1>Generador de Hash</h1>";
    echo "<p>Contraseña original: <strong>" . htmlspecialchars($pass) . "</strong></p>";
    echo "<p>Hash a copiar en la base de datos (columna password_hash):</p>";
    
    // password_hash usa el algoritmo PASSWORD_DEFAULT (actualmente bcrypt)
    // Genera un string de ~60 caracteres único para cada contraseña (con salt integrado)
    echo "<input type='text' value='" . password_hash($pass, PASSWORD_DEFAULT) . "' style='width: 100%; padding: 10px; font-family: monospace;'>";
} else {
    // Si no se envía parámetro, mostrar instrucciones de uso
    echo "Añade ?pass=CONTRASEÑA a la URL para generar su hash.";
}
