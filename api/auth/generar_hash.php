<?php
// Utilidad para generar hash de contraseñas
if (isset($_GET['pass'])) {
    $pass = $_GET['pass'];
    echo "<h1>Generador de Hash</h1>";
    echo "<p>Contraseña original: <strong>" . htmlspecialchars($pass) . "</strong></p>";
    echo "<p>Hash a copiar en la base de datos (columna password_hash):</p>";
    echo "<input type='text' value='" . password_hash($pass, PASSWORD_DEFAULT) . "' style='width: 100%; padding: 10px; font-family: monospace;'>";
} else {
    echo "Añade ?pass=CONTRASEÑA a la URL para generar su hash.";
}
