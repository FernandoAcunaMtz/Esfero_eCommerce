<?php
/**
 * Motor de Simulaciones — Esfero Admin
 *
 * Patrón: Transaction Rollback
 *   - Ejecuta lógica real contra datos reales dentro de una transacción PDO
 *   - Hace ROLLBACK al final → ningún dato persiste
 *   - El único write real es el INSERT en simulaciones_log (fuera de la transacción)
 */

class SimulacionMotor
{
    private PDO   $pdo;
    private array $pasos = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ------------------------------------------------------------------ //
    //  SIMULACIÓN 1 — Login
    // ------------------------------------------------------------------ //
    public function simularLogin(string $email, string $password): array
    {
        $this->pasos = [];

        try {
            // Paso 1: Validar formato de entrada
            if (empty(trim($email)) || empty($password)) {
                $this->paso('Validar datos de entrada', false, 'Email o contraseña vacíos');
                return $this->resultado(false, 'Datos incompletos');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->paso('Validar formato de email', false, "Email con formato inválido: $email");
                return $this->resultado(false, 'Formato de email inválido');
            }
            $this->paso('Validar formato de credenciales', true, "Email: $email — Contraseña: " . str_repeat('*', strlen($password)));

            // Paso 2: Buscar usuario en BD
            $stmt = $this->pdo->prepare(
                "SELECT id, nombre, apellidos, email, password_hash, rol, estado FROM usuarios WHERE email = ? LIMIT 1"
            );
            $stmt->execute([trim($email)]);
            $user = $stmt->fetch();

            if (!$user) {
                $this->paso('Buscar usuario en base de datos', false, "No existe ningún usuario con el email '$email'");
                return $this->resultado(false, 'Usuario no encontrado');
            }
            $this->paso('Buscar usuario en base de datos', true,
                "Encontrado: {$user['nombre']} {$user['apellidos']} | Rol: {$user['rol']} | Estado: {$user['estado']}");

            // Paso 3: Verificar contraseña (bcrypt — compatibilidad $2b/$2y)
            $hash = str_replace('$2b$', '$2y$', $user['password_hash'] ?? '');
            if (!password_verify($password, $hash)) {
                $this->paso('Verificar contraseña (bcrypt)', false, 'La contraseña no coincide con el hash almacenado');
                return $this->resultado(false, 'Contraseña incorrecta');
            }
            $this->paso('Verificar contraseña (bcrypt)', true, 'Hash verificado exitosamente');

            // Paso 4: Verificar estado de la cuenta
            if ($user['estado'] !== 'activo') {
                $this->paso('Verificar estado de cuenta', false, "La cuenta está en estado: {$user['estado']}");
                return $this->resultado(false, "Cuenta {$user['estado']}");
            }
            $this->paso('Verificar estado de cuenta', true, 'Cuenta activa y en regla');

            // Paso 5: Simulación de creación de sesión
            $token_simulado = substr(bin2hex(random_bytes(16)), 0, 24) . '...';
            $redirect = $user['rol'] === 'admin' ? 'admin_dashboard.php' : 'catalogo.php';
            $this->paso('Crear sesión y token (SIMULADO)', true,
                "Token generado: $token_simulado | Redirección a: $redirect | Ninguna sesión real fue creada");

            return $this->resultado(true,
                "Login exitoso (simulado). El usuario '{$user['nombre']} {$user['apellidos']}' con rol '{$user['rol']}' " .
                "se habría autenticado correctamente y sería redirigido a $redirect. Operación no persistida.");

        } catch (Exception $e) {
            $this->paso('Error interno del sistema', false, $e->getMessage());
            return $this->resultado(false, 'Error interno: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------ //
    //  SIMULACIÓN 2 — Registro de usuario
    // ------------------------------------------------------------------ //
    public function simularRegistro(string $email, string $nombre, string $apellidos, string $password, string $rol): array
    {
        $this->pasos = [];

        try {
            // Paso 1: Validar campos obligatorios
            if (empty(trim($email)) || empty(trim($nombre)) || empty($password)) {
                $this->paso('Validar campos obligatorios', false, 'Email, nombre y contraseña son requeridos');
                return $this->resultado(false, 'Faltan campos obligatorios');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->paso('Validar formato de email', false, "Email inválido: $email");
                return $this->resultado(false, 'Formato de email inválido');
            }
            $this->paso('Validar campos del formulario', true,
                "Email: $email | Nombre: $nombre $apellidos | Rol: $rol");

            // Paso 2: Validar fortaleza de contraseña
            if (strlen($password) < 6) {
                $this->paso('Validar fortaleza de contraseña', false,
                    "La contraseña tiene " . strlen($password) . " caracteres. Mínimo requerido: 6");
                return $this->resultado(false, 'Contraseña muy corta (mínimo 6 caracteres)');
            }
            $this->paso('Validar fortaleza de contraseña', true,
                strlen($password) . " caracteres — cumple requisito mínimo");

            // Paso 3: Verificar unicidad de email
            $stmt = $this->pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([trim($email)]);
            if ($stmt->fetch()) {
                $this->paso('Verificar unicidad de email', false,
                    "El email '$email' ya está registrado en el sistema");
                return $this->resultado(false, 'Email ya registrado');
            }
            $this->paso('Verificar unicidad de email', true, "Email '$email' disponible");

            // Paso 4: Hashear contraseña
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $this->paso('Hashear contraseña (bcrypt cost=10)', true,
                'Hash generado: ' . substr($hash, 0, 29) . '... [truncado por seguridad]');

            // Paso 5: INSERT usuarios (transacción + rollback)
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "INSERT INTO usuarios (email, password_hash, nombre, apellidos, rol, estado) VALUES (?, ?, ?, ?, ?, 'activo')"
            );
            $stmt->execute([trim($email), $hash, trim($nombre), trim($apellidos), $rol]);
            $nuevo_id = (int)$this->pdo->lastInsertId();
            $this->paso('INSERT en tabla usuarios (SIMULADO)', true,
                "Registro insertado — ID temporal asignado: $nuevo_id");

            // Paso 6: INSERT perfil
            $stmt2 = $this->pdo->prepare("INSERT INTO perfiles (usuario_id) VALUES (?)");
            $stmt2->execute([$nuevo_id]);
            $this->paso('Crear perfil inicial (SIMULADO)', true,
                "Perfil creado para usuario ID: $nuevo_id");

            // ROLLBACK
            $this->pdo->rollBack();
            $this->paso('ROLLBACK — base de datos restaurada', true,
                'La transacción fue revertida. Ningún registro fue guardado permanentemente.');

            return $this->resultado(true,
                "Registro exitoso (simulado). El usuario '$nombre $apellidos' con email '$email' y rol '$rol' " .
                "habría sido creado con ID $nuevo_id. Operación completamente revertida.");

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->paso('Error interno del sistema', false, $e->getMessage());
            return $this->resultado(false, 'Error interno: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------ //
    //  SIMULACIÓN 3 — Publicar producto
    // ------------------------------------------------------------------ //
    public function simularPublicarProducto(int $vendedor_id, string $titulo, float $precio, int $stock, int $categoria_id, string $estado_producto): array
    {
        $this->pasos = [];

        try {
            // Paso 1: Validar datos del producto
            if (empty(trim($titulo)) || $precio <= 0) {
                $this->paso('Validar datos del producto', false,
                    'Título vacío o precio igual/menor a cero');
                return $this->resultado(false, 'Datos del producto inválidos');
            }
            if ($stock < 0) {
                $this->paso('Validar stock', false, 'El stock no puede ser negativo');
                return $this->resultado(false, 'Stock inválido');
            }
            $this->paso('Validar datos del producto', true,
                "Título: '$titulo' | Precio: $" . number_format($precio, 2) . " | Stock: $stock | Estado: $estado_producto");

            // Paso 2: Verificar vendedor
            $stmt = $this->pdo->prepare(
                "SELECT id, nombre, apellidos, rol, puede_vender, estado FROM usuarios WHERE id = ?"
            );
            $stmt->execute([$vendedor_id]);
            $vendedor = $stmt->fetch();

            if (!$vendedor) {
                $this->paso('Verificar existencia del vendedor', false,
                    "No existe ningún usuario con ID: $vendedor_id");
                return $this->resultado(false, 'Vendedor no encontrado');
            }
            $this->paso('Verificar existencia del vendedor', true,
                "{$vendedor['nombre']} {$vendedor['apellidos']} (ID: {$vendedor['id']})");

            // Paso 3: Verificar permisos (puede_vender=1 o admin)
            $autorizado = $vendedor['puede_vender'] || $vendedor['rol'] === 'admin';
            if (!$autorizado) {
                $this->paso('Verificar permisos de publicación', false,
                    "El usuario no tiene activada la cuenta de vendedor (puede_vender=0)");
                return $this->resultado(false, 'El usuario no ha activado su cuenta de vendedor');
            }
            if ($vendedor['estado'] !== 'activo') {
                $this->paso('Verificar estado del vendedor', false,
                    "La cuenta del vendedor está: {$vendedor['estado']}");
                return $this->resultado(false, "Vendedor con cuenta {$vendedor['estado']}");
            }
            $etiqueta = $vendedor['rol'] === 'admin' ? 'admin' : 'vendedor activo';
            $this->paso('Verificar permisos del vendedor', true,
                "Perfil: $etiqueta | puede_vender={$vendedor['puede_vender']} | Estado: {$vendedor['estado']}");

            // Paso 4: Verificar categoría
            $cat_nombre = 'Sin categoría';
            if ($categoria_id > 0) {
                $stmt = $this->pdo->prepare("SELECT nombre FROM categorias WHERE id = ? AND activo = 1");
                $stmt->execute([$categoria_id]);
                $cat = $stmt->fetch();
                if (!$cat) {
                    $this->paso('Verificar categoría', false,
                        "La categoría ID $categoria_id no existe o está inactiva");
                    return $this->resultado(false, 'Categoría inválida');
                }
                $cat_nombre = $cat['nombre'];
                $this->paso('Verificar categoría', true, "Categoría válida: {$cat['nombre']}");
            } else {
                $this->paso('Verificar categoría', true, 'Sin categoría asignada (permitido)');
            }

            // Paso 5: INSERT producto (transacción + rollback)
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "INSERT INTO productos (titulo, descripcion, precio, stock, estado_producto, categoria_id, vendedor_id, activo)
                 VALUES (?, 'Descripción de prueba generada por simulación.', ?, ?, ?, ?, ?, 1)"
            );
            $stmt->execute([
                trim($titulo), $precio, $stock, $estado_producto,
                $categoria_id > 0 ? $categoria_id : null,
                $vendedor_id
            ]);
            $nuevo_id = (int)$this->pdo->lastInsertId();
            $this->paso('INSERT en tabla productos (SIMULADO)', true,
                "Producto insertado — ID temporal: $nuevo_id | Categoría: $cat_nombre");

            $this->pdo->rollBack();
            $this->paso('ROLLBACK — base de datos restaurada', true,
                'La transacción fue revertida. Ningún producto fue guardado permanentemente.');

            return $this->resultado(true,
                "Publicación exitosa (simulada). El producto '$titulo' habría sido publicado por " .
                "{$vendedor['nombre']} {$vendedor['apellidos']} al precio de $" . number_format($precio, 2) .
                " con $stock unidad(es). ID temporal: $nuevo_id. Operación completamente revertida.");

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->paso('Error interno del sistema', false, $e->getMessage());
            return $this->resultado(false, 'Error interno: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------ //
    //  SIMULACIÓN 4 — Proceso de compra (checkout completo)
    // ------------------------------------------------------------------ //
    public function simularCompra(int $producto_id, int $comprador_id, int $cantidad): array
    {
        $this->pasos = [];

        try {
            if ($cantidad < 1) {
                $this->paso('Validar cantidad', false, 'La cantidad debe ser al menos 1');
                return $this->resultado(false, 'Cantidad inválida');
            }
            $this->paso('Validar parámetros de entrada', true,
                "Producto ID: $producto_id | Comprador ID: $comprador_id | Cantidad: $cantidad");

            // Paso 2: Verificar producto
            $stmt = $this->pdo->prepare(
                "SELECT p.*, u.nombre as vendedor_nombre, u.apellidos as vendedor_apellidos
                 FROM productos p
                 JOIN usuarios u ON p.vendedor_id = u.id
                 WHERE p.id = ?"
            );
            $stmt->execute([$producto_id]);
            $producto = $stmt->fetch();

            if (!$producto) {
                $this->paso('Verificar existencia del producto', false,
                    "No existe ningún producto con ID: $producto_id");
                return $this->resultado(false, 'Producto no encontrado');
            }
            $this->paso('Verificar existencia del producto', true,
                "'{$producto['titulo']}' — Vendedor: {$producto['vendedor_nombre']} {$producto['vendedor_apellidos']}");

            // Paso 3: Verificar disponibilidad
            if (!$producto['activo']) {
                $this->paso('Verificar disponibilidad', false, 'El producto está desactivado');
                return $this->resultado(false, 'Producto no disponible (inactivo)');
            }
            if ($producto['vendido']) {
                $this->paso('Verificar disponibilidad', false, 'El producto ya fue vendido');
                return $this->resultado(false, 'Producto ya vendido');
            }
            $this->paso('Verificar disponibilidad del producto', true,
                "Producto activo y disponible para compra");

            // Paso 4: Verificar stock
            if ($producto['stock'] < $cantidad) {
                $this->paso('Verificar stock suficiente', false,
                    "Stock disponible: {$producto['stock']} | Cantidad solicitada: $cantidad — insuficiente");
                return $this->resultado(false,
                    "Stock insuficiente: disponible {$producto['stock']}, solicitado $cantidad");
            }
            $this->paso('Verificar stock', true,
                "Stock disponible: {$producto['stock']} | Solicitado: $cantidad — OK");

            // Paso 5: Verificar comprador
            $stmt = $this->pdo->prepare(
                "SELECT id, nombre, apellidos, estado, rol FROM usuarios WHERE id = ?"
            );
            $stmt->execute([$comprador_id]);
            $comprador = $stmt->fetch();

            if (!$comprador) {
                $this->paso('Verificar comprador', false,
                    "No existe ningún usuario con ID: $comprador_id");
                return $this->resultado(false, 'Comprador no encontrado');
            }
            if ($comprador['estado'] !== 'activo') {
                $this->paso('Verificar estado del comprador', false,
                    "La cuenta del comprador está: {$comprador['estado']}");
                return $this->resultado(false, "Comprador con cuenta {$comprador['estado']}");
            }
            $this->paso('Verificar comprador', true,
                "{$comprador['nombre']} {$comprador['apellidos']} | Rol: {$comprador['rol']} | Estado: activo");

            // Paso 6: Regla de negocio — comprador ≠ vendedor
            if ($comprador_id === (int)$producto['vendedor_id']) {
                $this->paso('Validar regla de negocio', false,
                    'Un vendedor no puede comprar sus propios productos');
                return $this->resultado(false, 'El comprador no puede ser el mismo que el vendedor');
            }
            $this->paso('Validar reglas de negocio', true,
                'Comprador y vendedor son usuarios distintos — regla cumplida');

            // Paso 7: Calcular totales
            $subtotal      = round((float)$producto['precio'] * $cantidad, 2);
            $envio         = 0.00;
            $total         = $subtotal + $envio;
            $numero_orden  = 'SIM-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $this->paso('Calcular totales de la orden', true,
                "Precio unitario: $" . number_format($producto['precio'], 2) .
                " × $cantidad = Subtotal: $" . number_format($subtotal, 2) .
                " | Envío: Gratis | Total: $" . number_format($total, 2));

            // Paso 8: INSERT orden + UPDATE stock (transacción + rollback)
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "INSERT INTO ordenes (numero_orden, comprador_id, vendedor_id, subtotal, envio, total, estado, estado_pago)
                 VALUES (?, ?, ?, ?, ?, ?, 'pendiente', 'pendiente')"
            );
            $stmt->execute([
                $numero_orden, $comprador_id, $producto['vendedor_id'],
                $subtotal, $envio, $total
            ]);
            $orden_id = (int)$this->pdo->lastInsertId();
            $this->paso('Crear orden de compra (SIMULADO)', true,
                "Orden: $numero_orden | ID temporal: $orden_id | Estado: pendiente");

            // Insertar orden_item
            $stmt = $this->pdo->prepare(
                "INSERT INTO orden_items (orden_id, producto_id, cantidad, precio_unitario, subtotal, producto_titulo)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $orden_id, $producto_id, $cantidad,
                $producto['precio'], $subtotal, $producto['titulo']
            ]);
            $this->paso('Registrar ítem en orden (SIMULADO)', true,
                "Producto '{$producto['titulo']}' × $cantidad unidades registrado en orden");

            // Actualizar stock
            $stock_nuevo = (int)$producto['stock'] - $cantidad;
            $stmt = $this->pdo->prepare("UPDATE productos SET stock = ? WHERE id = ?");
            $stmt->execute([$stock_nuevo, $producto_id]);
            $this->paso('Actualizar stock del producto (SIMULADO)', true,
                "Stock: {$producto['stock']} → $stock_nuevo unidades disponibles");

            $this->pdo->rollBack();
            $this->paso('ROLLBACK — base de datos restaurada', true,
                'La transacción fue revertida. Ninguna orden fue creada ni stock modificado.');

            return $this->resultado(true,
                "Compra exitosa (simulada). La orden '$numero_orden' habría sido creada por " .
                "{$comprador['nombre']} {$comprador['apellidos']} por un total de $" . number_format($total, 2) .
                ". El stock habría pasado de {$producto['stock']} a $stock_nuevo unidades. Operación completamente revertida.");

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->paso('Error interno del sistema', false, $e->getMessage());
            return $this->resultado(false, 'Error interno: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------ //
    //  SIMULACIÓN 5 — Solicitud de ayuda
    // ------------------------------------------------------------------ //
    public function simularSolicitudAyuda(string $nombre, string $email, string $asunto, string $categoria, string $mensaje): array
    {
        $this->pasos = [];

        try {
            // Paso 1: Validar campos
            if (empty(trim($nombre)) || empty(trim($email)) || empty(trim($asunto)) || empty(trim($mensaje))) {
                $this->paso('Validar campos obligatorios', false,
                    'Nombre, email, asunto y mensaje son requeridos');
                return $this->resultado(false, 'Faltan campos obligatorios');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->paso('Validar formato de email', false, "Email inválido: $email");
                return $this->resultado(false, 'Email inválido');
            }
            if (strlen(trim($asunto)) < 5) {
                $this->paso('Validar longitud del asunto', false,
                    'El asunto es demasiado corto (mínimo 5 caracteres)');
                return $this->resultado(false, 'Asunto muy corto');
            }
            $this->paso('Validar datos del formulario', true,
                "Nombre: $nombre | Email: $email | Categoría: $categoria | Asunto: " . substr($asunto, 0, 50));

            // Paso 2: Buscar usuario registrado
            $stmt = $this->pdo->prepare("SELECT id, nombre FROM usuarios WHERE email = ?");
            $stmt->execute([trim($email)]);
            $user = $stmt->fetch();
            $usuario_id = $user ? (int)$user['id'] : null;
            $this->paso('Buscar usuario registrado', true,
                $user
                    ? "Usuario registrado encontrado: {$user['nombre']} (ID: {$user['id']})"
                    : "No existe cuenta registrada — solicitud anónima permitida");

            // Paso 3: Determinar prioridad automática
            $prioridad = 'normal';
            $keywords_alta    = ['urgente', 'fraude', 'robo', 'hackeo', 'error', 'falla'];
            $keywords_urgente = ['estafa', 'hackeo', 'cuenta bloqueada', 'dinero perdido'];
            $asunto_lower = strtolower($asunto . ' ' . $mensaje);
            foreach ($keywords_urgente as $kw) {
                if (str_contains($asunto_lower, $kw)) { $prioridad = 'urgente'; break; }
            }
            if ($prioridad === 'normal') {
                foreach ($keywords_alta as $kw) {
                    if (str_contains($asunto_lower, $kw)) { $prioridad = 'alta'; break; }
                }
            }
            $this->paso('Determinar prioridad automática', true,
                "Prioridad asignada: $prioridad (basado en análisis de palabras clave del asunto/mensaje)");

            // Paso 4: Generar número de ticket
            $ticket = 'TKT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $this->paso('Generar número de ticket único', true, "Ticket: $ticket");

            // Paso 5: INSERT ayuda_solicitudes (transacción + rollback)
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "INSERT INTO ayuda_solicitudes (numero_ticket, usuario_id, nombre, email, asunto, categoria, mensaje, estado, prioridad)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', ?)"
            );
            $stmt->execute([
                $ticket, $usuario_id, trim($nombre), trim($email),
                trim($asunto), $categoria, trim($mensaje), $prioridad
            ]);
            $nuevo_id = (int)$this->pdo->lastInsertId();
            $this->paso('INSERT en ayuda_solicitudes (SIMULADO)', true,
                "Solicitud insertada — ID temporal: $nuevo_id | Estado: pendiente | Prioridad: $prioridad");

            $this->pdo->rollBack();
            $this->paso('ROLLBACK — base de datos restaurada', true,
                'La transacción fue revertida. Ninguna solicitud fue guardada permanentemente.');

            return $this->resultado(true,
                "Solicitud de ayuda exitosa (simulada). El ticket '$ticket' habría sido creado para '$nombre' " .
                "con categoría '$categoria' y prioridad '$prioridad'. Estado inicial: Pendiente. Operación completamente revertida.");

        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->paso('Error interno del sistema', false, $e->getMessage());
            return $this->resultado(false, 'Error interno: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------ //
    //  Guardar log en BD (siempre fuera de transacción, auto-commit)
    // ------------------------------------------------------------------ //
    public function guardarLog(int $admin_id, string $tipo, array $parametros, bool $exito, string $mensaje): int
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO simulaciones_log (admin_id, tipo, parametros, pasos, resultado, mensaje_final)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $admin_id,
                $tipo,
                json_encode($parametros, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                json_encode($this->pasos,  JSON_UNESCAPED_UNICODE),
                $exito ? 'exitoso' : 'fallido',
                $mensaje
            ]);
            return (int)$this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log('SimulacionMotor::guardarLog error: ' . $e->getMessage());
            return 0;
        }
    }

    // ------------------------------------------------------------------ //
    //  Helpers privados
    // ------------------------------------------------------------------ //
    private function paso(string $descripcion, bool $exito, string $detalle = ''): void
    {
        $this->pasos[] = [
            'descripcion' => $descripcion,
            'exito'       => $exito,
            'detalle'     => $detalle,
        ];
    }

    private function resultado(bool $exito, string $mensaje): array
    {
        return [
            'exito'  => $exito,
            'mensaje' => $mensaje,
            'pasos'  => $this->pasos,
        ];
    }
}
