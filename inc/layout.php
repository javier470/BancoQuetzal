<?php
const MENUS = [
    'ADMIN'   => ['admin_cajeros.php' => 'Gestión de cajeros', 'admin_monitor.php' => 'Monitor de transferencias'],
    'CAJERO'  => ['cajero_cuenta.php' => 'Crear cuenta', 'cajero_deposito.php' => 'Depósito', 'cajero_retiro.php' => 'Retiro'],
    'CLIENTE' => ['usuario_terceros.php' => 'Cuentas de terceros', 'usuario_transferencia.php' => 'Transferir', 'usuario_estado.php' => 'Estado de cuenta'],
];

function encabezado(string $titulo): void {
    $u = $_SESSION['usuario'] ?? null;
    $actual = basename($_SERVER['PHP_SELF']);
    ?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?> | <?= e(BANCO) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="top">
  <a class="marca" href="index.php"><img src="assets/BancQuetzalLogo.png" alt="" width="40" height="40"><span><?= e(BANCO) ?></span></a>
  <?php if ($u): ?>
  <input type="checkbox" id="nav-t" hidden><label for="nav-t" class="hamb" aria-label="Menú">☰</label>
  <nav>
    <?php foreach (MENUS[$u['rol']] as $url => $txt): ?>
      <a href="<?= $url ?>" class="<?= $url === $actual ? 'activo' : '' ?>"><?= e($txt) ?></a>
    <?php endforeach; ?>
    <a href="logout.php" class="salir">Salir</a>
  </nav>
  <?php endif; ?>
</header>
<main>
<?php if ($u): ?><p class="saludo">Sesión: <strong><?= e($u['nombre']) ?></strong> (<?= e(strtolower($u['rol'])) ?>)</p><?php endif; ?>
<h1><?= e($titulo) ?></h1>
<?php
}

function alerta(?string $ok, ?string $err): void {
    if ($ok)  echo '<div class="alerta ok">' . e($ok) . '</div>';
    if ($err) echo '<div class="alerta err">' . e($err) . '</div>';
}

function pie(string $extra = ''): void {
    echo "</main><footer>&copy; " . date('Y') . ' ' . e(BANCO) . "</footer>$extra</body></html>";
}
