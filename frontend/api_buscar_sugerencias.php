<?php
// Endpoint AJAX para sugerencias de búsqueda
header('Content-Type: application/json');
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/sanitize.php';

$query = sanitize_html($_GET['q'] ?? '');

if (empty($query) || strlen($query) < 2) {
    echo json_encode(['sugerencias' => []]);
    exit;
}

$sugerencias = getSugerenciasBusqueda($query, 5);

echo json_encode([
    'sugerencias' => $sugerencias,
    'query' => $query
]);

