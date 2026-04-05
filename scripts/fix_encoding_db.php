<?php
/**
 * Esfero — Fix de encoding UTF-8 en la base de datos
 *
 * Problema: MySQL almacenó los bytes UTF-8 de tildes/ñ como si fueran latin1
 * durante la inicialización de Docker, causando "ElectrÃ³nica" en lugar de
 * "Electrónica". El truco BINARY corrige esto a nivel de columna.
 *
 * Uso: php scripts/fix_encoding_db.php
 */

function log_ok(string $m):   void { echo "\033[32m[OK]\033[0m  $m\n"; }
function log_info(string $m): void { echo "\033[36m[INFO]\033[0m $m\n"; }
function log_warn(string $m): void { echo "\033[33m[WARN]\033[0m $m\n"; }

// ── Conexión ──────────────────────────────────────────────────────────────────

$db_keys = ['DB_HOST','DB_NAME','DB_USER','DB_PASSWORD','DB_PORT'];
$has_env = array_reduce($db_keys, fn($c, $k) => $c && getenv($k) !== false, true);

if ($has_env) {
    foreach ($db_keys as $k) $_ENV[$k] = getenv($k);
    log_info("Variables de entorno del sistema (Docker).");
} else {
    $env_path = dirname(__DIR__) . '/.env';
    if (!file_exists($env_path)) die("[ERROR] No se encontró .env\n");
    foreach (file($env_path) as $line) {
        $line = trim($line);
        if (!$line || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
    log_info("Usando archivo .env.");
}

try {
    // Conexión SIN especificar charset — imprescindible para que el truco funcione
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s',
        $_ENV['DB_HOST'] ?? 'localhost',
        $_ENV['DB_PORT'] ?? '3306',
        $_ENV['DB_NAME'] ?? 'esfero'
    );
    $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASSWORD'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    log_ok("Conexión establecida.");
} catch (PDOException $e) {
    die("[ERROR] " . $e->getMessage() . "\n");
}

// ── Tablas y columnas a reparar ───────────────────────────────────────────────
// Solo las tablas sembradas por schema.sql (que pueden tener double-encoded UTF-8).
// Los productos vienen de DummyJSON (ASCII) y no se tocan.

$fixes = [
    ['categorias',  'nombre'           ],
    ['testimonios', 'titulo'           ],
    ['testimonios', 'contenido'        ],
    ['testimonios', 'ubicacion'        ],
    ['guias',       'titulo'           ],
    ['guias',       'descripcion_corta'],
    ['guias',       'contenido'        ],
    ['ayuda_faqs',  'pregunta'         ],
    ['ayuda_faqs',  'respuesta'        ],
    ['usuarios',    'nombre'           ],
    ['usuarios',    'apellidos'        ],
    ['perfiles',    'descripcion'      ],
    ['perfiles',    'ubicacion_estado' ],
    ['perfiles',    'ubicacion_ciudad' ],
];

// ── Aplicar conversión correcta ───────────────────────────────────────────────
// Problema: bytes UTF-8 almacenados como utf8mb4 (double-encoded).
//   "ó" → bytes 0xC3 0xB3 insertados como latin1 → guardados como bytes 0xC3 0x83 0xC2 0xB3
// Fix: CONVERT(col USING latin1) extrae los bytes raw,
//      BINARY() los preserva, CONVERT(... USING utf8mb4) los reinterpreta como UTF-8.
//
// Detección con HEX(): evita colisiones de collation.
//   "Ã" en UTF-8 = U+00C3 = bytes C3 83  → HEX contiene 'C383'
//   "Â" en UTF-8 = U+00C2 = bytes C3 82  → HEX contiene 'C382'

$pdo->exec("SET NAMES utf8mb4");

echo "\n── Reparando encoding (double-encoded UTF-8) ────────────────────────────\n";

foreach ($fixes as [$tabla, $columna]) {
    try {
        $sql = "UPDATE `$tabla`
                SET    `$columna` = CONVERT(BINARY(CONVERT(`$columna` USING latin1)) USING utf8mb4)
                WHERE  HEX(`$columna`) LIKE '%C383%'
                    OR HEX(`$columna`) LIKE '%C382%'
                    OR HEX(`$columna`) LIKE '%C2B1%'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->rowCount();
        if ($rows > 0) {
            log_ok("  $tabla.$columna — $rows fila(s) reparada(s).");
        } else {
            log_info("  $tabla.$columna — sin cambios necesarios.");
        }
    } catch (PDOException $e) {
        log_warn("  $tabla.$columna: " . $e->getMessage());
    }
}

// ── Verificar resultado ───────────────────────────────────────────────────────

echo "\n── Verificación: categorías ─────────────────────────────────────────────\n";
foreach ($pdo->query("SELECT id, nombre, slug FROM categorias ORDER BY id") as $row) {
    log_ok("  [{$row['id']}] {$row['nombre']} ({$row['slug']})");
}

echo "\n";
log_ok("Fix completado. Recarga la página para ver los cambios.");
echo "\n";
