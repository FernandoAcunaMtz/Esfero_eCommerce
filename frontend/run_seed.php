<?php
// Protección básica — elimina este archivo después de correr el seed
$token = $_GET['token'] ?? '';
if ($token !== 'esfero-seed-2026') {
    http_response_code(403);
    die('Forbidden');
}

header('Content-Type: text/plain');
echo "Corriendo seed...\n";
flush();

$output = shell_exec('find / -name "seed_productos.php" 2>/dev/null');
echo "Ubicación: " . $output . "\n";
$output2 = shell_exec('php /var/www/esfero/scripts/seed_productos.php 2>&1');
$output = $output2;
echo $output;
echo "\nListo.";
