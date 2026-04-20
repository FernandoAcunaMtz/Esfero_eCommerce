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

$h = getenv('DB_HOST');
$u = getenv('DB_USER');
$p = getenv('DB_PASSWORD');
$db = getenv('DB_NAME');
$mysql = "mysql -h $h -u $u -p$p --skip-ssl $db";

echo "Aplicando schema...\n"; flush();
shell_exec("$mysql < /var/www/esfero/sql/schema.sql 2>&1");
echo "Schema OK\n"; flush();

echo "Aplicando patches...\n"; flush();
foreach (glob('/var/www/esfero/sql/patch_*.sql') as $patch) {
    $out = shell_exec("$mysql < $patch 2>&1");
    echo basename($patch) . ": " . ($out ?: "OK") . "\n";
    flush();
}

$output = shell_exec('php /var/www/esfero/scripts/seed_productos.php 2>&1');
echo $output;
echo "\nListo.";
