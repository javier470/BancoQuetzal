<?php
require 'inc/config.php';
require 'inc/layout.php';

$roles = [
    'admin'   => ['ADMIN',   'Administrador', 'admin_monitor.php',  'Usuario'],
    'cajero'  => ['CAJERO',  'Cajero',        'cajero_cuenta.php',  'Usuario'],
    'cliente' => ['CLIENTE', 'Usuario',       'usuario_estado.php', 'Correo electrónico'],
];
$clave_rol = $_GET['rol'] ?? 'cliente';
if (!isset($roles[$clave_rol])) $clave_rol = 'cliente';
[$rol, $titulo, $destino, $etiqueta] = $roles[$clave_rol];

$error = null;
$intentos = $_SESSION['intentos'] ?? ['n' => 0, 'hasta' => 0];
if (es_post_valido()) {
    $usuario = trim($_POST['usuario'] ?? '');
    $clave = $_POST['clave'] ?? '';
    if ($intentos['hasta'] > time()) {
        $error = 'Demasiados intentos fallidos. Espere 5 minutos e intente de nuevo.';
    } elseif ($usuario === '' || $clave === '') {
        $error = 'Ingrese usuario y clave.';
    } else {
        try {
            $filas = sp('sp_login', [$usuario, $clave, $rol]);
            session_regenerate_id(true);
            unset($_SESSION['intentos']);
            $_SESSION['usuario'] = $filas[0];
            header("Location: $destino");
            exit;
        } catch (PDOException $ex) {
            $error = error_bd($ex);
            if (++$intentos['n'] >= 5) $intentos = ['n' => 0, 'hasta' => time() + 300];
            $_SESSION['intentos'] = $intentos;
        }
    }
}

encabezado("Inicio de sesión - $titulo");
?>
<a class="volver" href="/banca-en-linea/" aria-label="Volver al inicio">&larr; Volver al inicio</a>
<div class="card angosto">
  <?php alerta(null, $error); ?>
  <form method="post" autocomplete="off">
    <?= csrf_campo() ?>
    <label for="usuario"><?= e($etiqueta) ?></label>
    <input id="usuario" name="usuario" required maxlength="100" <?= $rol === 'CLIENTE' ? 'type="email"' : '' ?> value="<?= e($_POST['usuario'] ?? '') ?>">
    <label for="clave">Clave</label>
    <input id="clave" name="clave" type="password" required>
    <button>Ingresar</button>
  </form>
  <?php if ($rol === 'CLIENTE'): ?><p>¿No tienes usuario? <a href="registro.php">Regístrate</a></p><?php endif; ?>
</div>
<?php pie();
