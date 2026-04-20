<?php
// Protección básica — elimina este archivo después de correr el seed
$token = $_GET['token'] ?? '';
if ($token !== 'esfero-seed-2026') {
    http_response_code(403);
    die('Forbidden');
}

header('Content-Type: text/plain');
echo "Corriendo seed...\n";
echo "DB_HOST=" . getenv('DB_HOST') . "\n";
echo "DB_PORT=" . getenv('DB_PORT') . "\n";
echo "DB_NAME=" . getenv('DB_NAME') . "\n";
echo "DB_USER=" . getenv('DB_USER') . "\n";
flush();

echo "Aplicando schema...\n"; flush();
$schema = shell_exec('mysql --ssl=0 -h mysql.railway.internal -u root -pyFjnOvDVvzawljkDIvkSpWSSgDoEmpJB railway < /var/www/esfero/sql/schema.sql 2>&1');
if (strpos($schema, 'unknown variable') !== false) {
    $schema = shell_exec('mysql -h mysql.railway.internal -u root -pyFjnOvDVvzawljkDIvkSpWSSgDoEmpJB --skip-ssl railway < /var/www/esfero/sql/schema.sql 2>&1');
}
echo "Schema: " . ($schema ?: "OK") . "\n"; flush();

$output = shell_exec('php /var/www/esfero/scripts/seed_productos.php 2>&1');
echo $output;
echo "\nListo.";
