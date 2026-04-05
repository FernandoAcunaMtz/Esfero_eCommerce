<?php
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/sanitize.php';

header('Content-Type: application/json; charset=utf-8');

$guia_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($guia_id <= 0) {
    echo json_encode(['error' => 'ID inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!isset($pdo)) {
        echo json_encode(['error' => 'Error de conexión'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM guias WHERE id = ? AND activo = 1");
    $stmt->execute([$guia_id]);
    $guia = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$guia) {
        echo json_encode(['error' => 'Guía no encontrada'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Incrementar vistas — envuelto en su propio try para no bloquear la respuesta
    // si la columna `vistas` aún no existe en la tabla
    try {
        $pdo->prepare("UPDATE guias SET vistas = vistas + 1 WHERE id = ?")->execute([$guia_id]);
    } catch (Exception $e) {
        error_log("api_get_guia: no se pudo incrementar vistas: " . $e->getMessage());
    }

    $response = [
        'id'                => (int)$guia['id'],
        'titulo'            => $guia['titulo'],
        'slug'              => $guia['slug'] ?? '',
        'descripcion_corta' => $guia['descripcion_corta'] ?? '',
        'contenido'         => $guia['contenido'] ?? '',
        'categoria'         => $guia['categoria'] ?? '',
        'imagen_url'        => $guia['imagen_url'] ?? '',
        'vistas'            => (int)($guia['vistas'] ?? 0),
        'fecha_publicacion' => $guia['fecha_publicacion'] ?? null,
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Error en api_get_guia.php: " . $e->getMessage());
    echo json_encode(['error' => 'Error al obtener la guía'], JSON_UNESCAPED_UNICODE);
}
