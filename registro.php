<?php
require 'inc/config.php';
require 'inc/layout.php';

$ok = $error = null;
if (es_post_valido()) {
    $cuenta = trim($_POST['cuenta'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $dpi    = trim($_POST['dpi'] ?? '');
    $clave  = $_POST['clave'] ?? '';
    $conf   = $_POST['confirmacion'] ?? '';

    if (!preg_match('/^\d{6,20}$/', $cuenta))            $error = 'Número de cuenta inválido.';
    elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) $error = 'Correo electrónico inválido.';
    elseif (!preg_match('/^\d{13}$/', $dpi))             $error = 'El DPI debe tener 13 dígitos.';
    elseif (!clave_valida($clave))                       $error = 'La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula y un número.';
    elseif ($clave !== $conf)                            $error = 'Las contraseñas no coinciden.';
    else {
        try {
            sp('sp_registrar_cliente', [$cuenta, $correo, $dpi, $clave]);
            $ok = 'Usuario registrado correctamente. Ya puede iniciar sesión.';
            $_POST = [];
        } catch (PDOException $ex) {
            $error = error_bd($ex);
        }
    }
}

encabezado('Registro de nuevo usuario');
?>
<a class="volver" href="/banca-en-linea/" aria-label="Volver al inicio">&larr; Volver al inicio</a>
<div class="card angosto">
  <?php alerta($ok, $error); ?>
  <form method="post" autocomplete="off">
    <?= csrf_campo() ?>
    <label for="cuenta">No. Cuenta Bancaria</label>
    <input id="cuenta" name="cuenta" inputmode="numeric" pattern="\d{6,20}" required value="<?= e($_POST['cuenta'] ?? '') ?>">
    <label for="correo">Correo electrónico (será su usuario)</label>
    <input id="correo" name="correo" type="email" required maxlength="100" value="<?= e($_POST['correo'] ?? '') ?>">
    <label for="dpi">DPI</label>
    <input id="dpi" name="dpi" inputmode="numeric" pattern="\d{13}" maxlength="13" required value="<?= e($_POST['dpi'] ?? '') ?>">
    <label for="clave">Contraseña</label>
    <input id="clave" name="clave" type="password" minlength="8" required>
    <label for="confirmacion">Confirmación de contraseña</label>
    <input id="confirmacion" name="confirmacion" type="password" minlength="8" required>
    <button>Registrarme</button>
  </form>
  <?php if ($ok): ?><a class="btn" href="login.php?rol=cliente">Iniciar sesión</a><?php endif; ?>
</div>
<?php pie();
