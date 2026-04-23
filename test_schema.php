<?php
require_once 'api/config/db_config.php';
$conn = conectar();
$res = $conn->query("SHOW TABLES");
$tables = [];
while ($row = $res->fetch_array()) {
    $t = $row[0];
    $tables[$t] = [];
    $res2 = $conn->query("DESCRIBE `$t`");
    while ($r2 = $res2->fetch_assoc()) {
        $tables[$t][] = $r2['Field'];
    }
}
echo json_encode($tables, JSON_PRETTY_PRINT);
