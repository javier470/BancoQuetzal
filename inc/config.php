<?php
// ===== Configuración de conexión (ajustar según el hosting) =====
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'banca_linea');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('BANCO',   'Banco Quetzal en Línea');

ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
session_start();
date_default_timezone_set('America/Guatemala');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec("SET time_zone = '-06:00'");
        } catch (PDOException $e) {
            echo "<p>[banca-en-linea] Fallo de conexión a la base de datos: " . $e->getMessage() . "</p>";
            throw $e;
        }
    }
    return $pdo;
}

function sp(string $nombre, array $params = []): array {
    $marcas = implode(',', array_fill(0, count($params), '?'));
    $st = db()->prepare("CALL $nombre($marcas)");
    $st->execute(array_values($params));
    $filas = $st->columnCount() ? $st->fetchAll() : [];
    while ($st->nextRowset()) {  }
    $st->closeCursor();
    return $filas;
}

function error_bd(PDOException $e): string {
    $info = $e->errorInfo;
    if (isset($info[0]) && $info[0] === '45000') return $info[2];
    error_log($e->getMessage());
    return 'Ocurrió un error al procesar la solicitud.';
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function q($n): string { return 'Q ' . number_format((float)$n, 2); }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_campo(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function es_post_valido(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals(csrf_token(), $_POST['csrf'] ?? '');
}

function requiere_rol(string $rol): array {
    $u = $_SESSION['usuario'] ?? null;
    if (!$u || $u['rol'] !== $rol || !sp('sp_usuario_activo', [$u['id'], $rol])) {
        $_SESSION = [];
        session_destroy();
        header('Location: index.php');
        exit;
    }
    return $u;
}

function clave_valida(string $c): bool {
    return strlen($c) >= 8 && preg_match('/[A-Z]/', $c) && preg_match('/[a-z]/', $c) && preg_match('/\d/', $c);
}

function monto_valido($v): bool {
    return is_numeric($v) && preg_match('/^\d{1,12}(\.\d{1,2})?$/', (string)$v);
}
