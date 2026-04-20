<?php
/**
 * ============================================================
 * Esfero — Seeder de Productos
 * Fuente: DummyJSON API (https://dummyjson.com) — 194 productos reales con imágenes
 *
 * Uso desde la raíz del proyecto:
 *   php scripts/seed_productos.php
 *
 * Opciones:
 *   php scripts/seed_productos.php --reset    (elimina productos sembrados antes de re-sembrar)
 *   php scripts/seed_productos.php --dry-run  (muestra cuántos productos se importarían)
 * ============================================================
 */

// ── Helpers de logging (deben declararse antes de usarse) ────────────────────
function log_ok(string $msg):   void { echo "\033[32m[OK]\033[0m  $msg\n"; }
function log_info(string $msg): void { echo "\033[36m[INFO]\033[0m $msg\n"; }
function log_warn(string $msg): void { echo "\033[33m[WARN]\033[0m $msg\n"; }

// ── Configuración ────────────────────────────────────────────────────────────

define('SCRIPT_START', microtime(true));
define('MXN_RATE',     17.5);          // USD → MXN
define('MAX_STOCK',    5);             // C2C: máximo 5 unidades por anuncio
define('DESTACADOS',   12);            // cuántos marcar como destacados
define('DUMMYJSON_URL','https://dummyjson.com/products?limit=100&skip=0');
define('DUMMYJSON_URL2','https://dummyjson.com/products?limit=94&skip=100');

// Detectar flags de línea de comandos
$reset   = in_array('--reset',   $argv ?? []);
$dry_run = in_array('--dry-run', $argv ?? []);

// ── Cargar variables de entorno ───────────────────────────────────────────────
// Prioridad: variables del sistema (Docker) > archivo .env

$db_keys = ['DB_HOST','DB_NAME','DB_USER','DB_PASSWORD','DB_PORT','DB_CHARSET'];
$has_env_vars = array_reduce($db_keys, fn($carry, $k) => $carry && getenv($k) !== false, true);

if ($has_env_vars) {
    // Estamos dentro de Docker (o env vars ya cargadas por el sistema)
    foreach ($db_keys as $k) $_ENV[$k] = getenv($k);
    log_info("Usando variables de entorno del sistema (Docker).");
} else {
    // Carga desde archivo .env
    $env_path = dirname(__DIR__) . '/.env';
    if (!file_exists($env_path)) {
        die("[ERROR] No se encontró .env en: $env_path\n");
    }
    foreach (file($env_path) as $line) {
        $line = trim($line);
        if (!$line || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
    }
    log_info("Usando archivo .env.");
}

// ── Conexión PDO ──────────────────────────────────────────────────────────────

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $_ENV['DB_HOST']    ?? 'localhost',
        $_ENV['DB_PORT']    ?? '3306',
        $_ENV['DB_NAME']    ?? 'esfero',
        $_ENV['DB_CHARSET'] ?? 'utf8mb4'
    );
    $pdo = new PDO($dsn, $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASSWORD'] ?? '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    log_ok("Conexión a MySQL establecida ({$_ENV['DB_NAME']})");
} catch (PDOException $e) {
    die("[ERROR] No se pudo conectar a la BD: " . $e->getMessage() . "\n");
}

// ── Mapeo de categorías DummyJSON → Esfero ────────────────────────────────────
// Categorías Esfero: 1=Electrónica, 2=Ropa, 3=Hogar, 4=Deportes, 5=Libros, 6=Juguetes, 7=Vehículos, 8=Música

$category_map = [
    'smartphones'         => 1,
    'laptops'             => 1,
    'tablets'             => 1,
    'mobile-accessories'  => 1,
    'mens-watches'        => 1,
    'womens-watches'      => 2,
    'mens-shirts'         => 2,
    'mens-shoes'          => 2,
    'womens-dresses'      => 2,
    'womens-bags'         => 2,
    'womens-shoes'        => 2,
    'womens-jewellery'    => 2,
    'sunglasses'          => 2,
    'tops'                => 2,
    'fragrances'          => 2,
    'beauty'              => 3,
    'furniture'           => 3,
    'home-decoration'     => 3,
    'kitchen-accessories' => 3,
    'skin-care'           => 3,
    'groceries'           => 3,
    'sports-accessories'  => 4,
    'motorcycle'          => 7,
    'vehicle'             => 7,
];

// ── Localizaciones mexicanas ──────────────────────────────────────────────────

$locations = [
    ['Jalisco',           'Guadalajara'],
    ['Ciudad de México',  'Ciudad de México'],
    ['Nuevo León',        'Monterrey'],
    ['Estado de México',  'Ecatepec'],
    ['Jalisco',           'Zapopan'],
    ['Puebla',            'Puebla'],
    ['Guanajuato',        'León'],
    ['Baja California',   'Tijuana'],
    ['Veracruz',          'Veracruz'],
    ['Sonora',            'Hermosillo'],
    ['Yucatán',           'Mérida'],
    ['Coahuila',          'Saltillo'],
    ['Querétaro',         'Querétaro'],
    ['Chihuahua',         'Chihuahua'],
    ['Sinaloa',           'Culiacán'],
];

// ── Condiciones del producto ──────────────────────────────────────────────────
// Distribución realista para un C2C de segunda mano

$conditions = [
    'nuevo', 'nuevo', 'nuevo',
    'excelente', 'excelente', 'excelente', 'excelente',
    'bueno', 'bueno', 'bueno', 'bueno', 'bueno',
    'regular',
];

// ── Usuarios vendedores demo ──────────────────────────────────────────────────
// Password "Esfero2024!" hasheado con bcrypt (compatible PHP + Python)

$bcrypt_hash = '$2y$12$' . substr(str_replace(['/', '+'], ['_', '-'],
    base64_encode(random_bytes(22))), 0, 22);

// Recalculamos con password_hash para que sea correcto
$pw_hash = password_hash('Esfero2024!', PASSWORD_BCRYPT, ['cost' => 10]);

$demo_vendedores = [
    ['lucia.vargas@demo.esfero',    'Lucía',    'Vargas',     '5511001100', 'CDMX',              'Ciudad de México', 'Vendedora de ropa y accesorios de moda. Más de 200 ventas exitosas.', 4.90],
    ['roberto.soto@demo.esfero',    'Roberto',  'Soto',       '5522002200', 'Nuevo León',         'Monterrey',        'Especialista en electrónica y gadgets. Envíos express a todo México.', 4.75],
    ['diana.flores@demo.esfero',    'Diana',    'Flores',     '5533003300', 'Jalisco',            'Guadalajara',      'Amante del hogar. Vendo artículos de decoración y cocina en excelente estado.', 4.85],
    ['miguel.reyes@demo.esfero',    'Miguel',   'Reyes',      '5544004400', 'Estado de México',   'Ecatepec',         'Deportista vendiendo equipo que ya no uso. Precios justos y envíos rápidos.', 4.60],
    ['sofia.herrera@demo.esfero',   'Sofía',    'Herrera',    '5555005500', 'Puebla',             'Puebla',           'Coleccionista de artículos únicos. Siempre empaco con cuidado.', 4.95],
];

// ── Dry run ───────────────────────────────────────────────────────────────────

if ($dry_run) {
    $prods = fetch_all_products();
    $count = count($prods);
    log_info("Dry run: se importarían $count productos de DummyJSON.");
    log_info("Tasa de conversión: USD × " . MXN_RATE . " = MXN");
    log_info("Vendedores demo a crear: " . count($demo_vendedores));
    $cats = array_count_values(array_map(fn($p) => $p['category'], $prods));
    log_info("Categorías encontradas:");
    foreach ($cats as $cat => $n) {
        $mapped = isset($category_map[$cat]) ? "→ categoria_id={$category_map[$cat]}" : "→ [sin mapeo, se omite]";
        echo "   $cat ($n productos) $mapped\n";
    }
    exit(0);
}

// ── Reset opcional ────────────────────────────────────────────────────────────

if ($reset) {
    log_warn("--reset: eliminando productos sembrados por este script...");
    $pdo->exec("DELETE FROM productos WHERE vendedor_id IN (
        SELECT id FROM usuarios WHERE email LIKE '%@demo.esfero'
    )");
    $pdo->exec("DELETE FROM usuarios WHERE email LIKE '%@demo.esfero'");
    log_ok("Datos anteriores eliminados.");
}

// ── 1. Crear / recuperar vendedores demo ─────────────────────────────────────

echo "\n── Paso 1: Vendedores demo ─────────────────────────────────────────────\n";

$vendor_ids = [];

// Incluir los vendedores del schema original (IDs 2 y 5)
$stmt = $pdo->query("SELECT id FROM usuarios WHERE email IN ('vendedor@esfero.com','ana.vendedora@example.com')");
foreach ($stmt->fetchAll() as $row) {
    $vendor_ids[] = (int)$row['id'];
    // Asegurarse de que tengan puede_vender=1
    $pdo->prepare("UPDATE usuarios SET puede_vender = 1 WHERE id = ?")->execute([$row['id']]);
}

$insert_user = $pdo->prepare("
    INSERT INTO usuarios (email, password_hash, nombre, apellidos, telefono, rol, puede_vender, estado)
    VALUES (?, ?, ?, ?, ?, 'usuario', 1, 'activo')
    ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
");

$insert_perfil = $pdo->prepare("
    INSERT INTO perfiles (usuario_id, descripcion, ubicacion_estado, ubicacion_ciudad, calificacion_promedio)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)
");

foreach ($demo_vendedores as [$email, $nombre, $apellidos, $tel, $estado, $ciudad, $desc, $rating]) {
    $insert_user->execute([$email, $pw_hash, $nombre, $apellidos, $tel]);
    $uid = (int)$pdo->lastInsertId();
    if (!$uid) {
        $row = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?")->execute([$email]);
        $uid = (int)$pdo->query("SELECT id FROM usuarios WHERE email = '$email'")->fetchColumn();
    }
    $insert_perfil->execute([$uid, $desc, $estado, $ciudad, $rating]);
    $vendor_ids[] = $uid;
    log_ok("  Vendedor: $nombre $apellidos <$email> (id=$uid)");
}

$vendor_count = count($vendor_ids);
log_ok("Total vendedores disponibles: $vendor_count");

// ── 2. Descargar productos ────────────────────────────────────────────────────

echo "\n── Paso 2: Descargando productos de DummyJSON ──────────────────────────\n";
$products = fetch_all_products();
log_ok("Total productos descargados: " . count($products));

// ── 3. Insertar productos ─────────────────────────────────────────────────────

echo "\n── Paso 3: Insertando productos en MySQL ───────────────────────────────\n";

$insert_prod = $pdo->prepare("
    INSERT INTO productos
        (titulo, descripcion, precio, precio_original, moneda, stock, estado_producto,
         categoria_id, vendedor_id, activo, vendido, destacado, vistas,
         ubicacion_estado, ubicacion_ciudad, fecha_publicacion)
    VALUES
        (?, ?, ?, ?, 'MXN', ?, ?, ?, ?, 1, 0, ?, ?, ?, ?, NOW() - INTERVAL ? DAY)
");

$insert_img = $pdo->prepare("
    INSERT INTO imagenes_productos (producto_id, url_imagen, es_principal, orden)
    VALUES (?, ?, ?, ?)
");

$inserted = 0;
$skipped  = 0;

foreach ($products as $i => $p) {
    // Categoría
    $cat_slug  = $p['category'] ?? '';
    $cat_id    = $category_map[$cat_slug] ?? null;
    if (!$cat_id) { $skipped++; continue; }

    // Precio MXN
    $precio_usd = (float)($p['price'] ?? 0);
    if ($precio_usd <= 0) { $skipped++; continue; }
    $precio_mxn = round($precio_usd * MXN_RATE, 2);

    // Precio original (si tiene descuento)
    $discount = (float)($p['discountPercentage'] ?? 0);
    $precio_orig = null;
    if ($discount > 2) {
        $precio_orig = round($precio_mxn / (1 - $discount / 100), 2);
    }

    // Stock (cap para C2C)
    $stock = min((int)($p['stock'] ?? 1), MAX_STOCK);
    $stock = max($stock, 1);

    // Condición del producto
    $condition = $conditions[array_rand($conditions)];

    // Vendedor (round-robin)
    $vendor_id = $vendor_ids[$i % $vendor_count];

    // Ubicación
    $loc = $locations[array_rand($locations)];

    // Destacado (primeros N)
    $destacado = ($inserted < DESTACADOS) ? 1 : 0;

    // Vistas aleatorias (para que se vea real)
    $vistas = rand(5, 350);

    // Días atrás (distribución realista)
    $days_ago = rand(1, 120);

    // Título limpio
    $titulo = mb_substr(htmlspecialchars_decode($p['title'] ?? 'Producto'), 0, 255);

    // Descripción
    $descripcion = mb_substr($p['description'] ?? '', 0, 2000);

    try {
        $insert_prod->execute([
            $titulo, $descripcion, $precio_mxn, $precio_orig,
            $stock, $condition, $cat_id, $vendor_id,
            $destacado, $vistas, $loc[0], $loc[1], $days_ago
        ]);
        $prod_id = (int)$pdo->lastInsertId();

        // Imagen principal (thumbnail)
        $thumb = sanitize_url($p['thumbnail'] ?? '');
        if ($thumb) {
            $insert_img->execute([$prod_id, $thumb, 1, 0]);
        }

        // Imágenes adicionales
        $imgs = $p['images'] ?? [];
        foreach (array_slice($imgs, 0, 4) as $order => $img_url) {
            $url = sanitize_url($img_url);
            if ($url && $url !== $thumb) {
                $insert_img->execute([$prod_id, $url, 0, $order + 1]);
            }
        }

        $inserted++;
        if ($inserted % 20 === 0) {
            log_ok("  $inserted productos insertados...");
        }

    } catch (PDOException $e) {
        log_warn("  Producto #{$p['id']} '{$titulo}': " . $e->getMessage());
        $skipped++;
    }
}

// ── 4. Resumen ────────────────────────────────────────────────────────────────

$elapsed = round(microtime(true) - SCRIPT_START, 2);

echo "\n── Resumen ──────────────────────────────────────────────────────────────\n";
log_ok("Productos insertados : $inserted");
log_info("Productos omitidos   : $skipped (sin categoría mapeable)");
log_info("Vendedores demo      : $vendor_count");
log_info("Tiempo total         : {$elapsed}s");
echo "\n";
log_ok("¡Seeder completado! Ya puedes ver los productos en el catálogo.");
log_info("Credenciales de vendedores demo: password = Esfero2024!");
echo "\n";

// ── Funciones helper ──────────────────────────────────────────────────────────

function fetch_all_products(): array {
    $all = [];
    foreach ([DUMMYJSON_URL, DUMMYJSON_URL2] as $url) {
        $json = @file_get_contents($url);
        if (!$json) {
            // Fallback con cURL
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
                                     CURLOPT_SSL_VERIFYPEER => false]);
            $json = curl_exec($ch);
            curl_close($ch);
        }
        if (!$json) { log_warn("No se pudo descargar: $url"); continue; }
        $data = json_decode($json, true);
        if (!isset($data['products'])) { log_warn("Respuesta inesperada de: $url"); continue; }
        $all = array_merge($all, $data['products']);
        log_info("  Lote descargado: " . count($data['products']) . " productos de $url");
    }
    return $all;
}

function sanitize_url(string $url): string {
    $url = trim($url);
    if (!filter_var($url, FILTER_VALIDATE_URL)) return '';
    if (!str_starts_with($url, 'https://')) return '';
    return $url;
}
