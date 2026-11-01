<?php
require 'inc/config.php';
require 'inc/layout.php';
$u = requiere_rol('CAJERO');

$ok = $error = null;
if (es_post_valido()) {
    $nombre = trim($_POST['nombre'] ?? '');
    $numero = trim($_POST['numero'] ?? '');
    $dpi    = trim($_POST['dpi'] ?? '');
    $monto  = trim($_POST['monto'] ?? '');
    if ($nombre === '')                            $error = 'El nombre de la cuenta es requerido.';
    elseif (!preg_match('/^\d{6,20}$/', $numero))  $error = 'El número de cuenta debe tener entre 6 y 20 dígitos.';
    elseif (!preg_match('/^\d{13}$/', $dpi))       $error = 'El DPI debe tener 13 dígitos.';
    elseif (!monto_valido($monto))                 $error = 'Monto inicial inválido.';
    else {
        try {
            sp('sp_cuenta_crear', [$nombre, $numero, $dpi, $monto, $u['id']]);
            $ok = "Cuenta $numero creada correctamente.";
            $_POST = [];
        } catch (PDOException $ex) {
            $error = error_bd($ex);
        }
    }
}

encabezado('Crear cuenta monetaria');
?>
<div class="card angosto">
  <?php alerta($ok, $error); ?>
  <form method="post" autocomplete="off"><?= csrf_campo() ?>
    <label for="nombre">Nombre de la cuenta</label><input id="nombre" name="nombre" required maxlength="100" value="<?= e($_POST['nombre'] ?? '') ?>">
    <label for="numero">No. Cuenta</label><input id="numero" name="numero" inputmode="numeric" pattern="\d{6,20}" required value="<?= e($_POST['numero'] ?? '') ?>">
    <label for="dpi">Identificación (DPI)</label><input id="dpi" name="dpi" inputmode="numeric" pattern="\d{13}" maxlength="13" required value="<?= e($_POST['dpi'] ?? '') ?>">
    <label for="monto">Monto inicial (Q)</label><input id="monto" name="monto" type="number" min="0" step="0.01" required value="<?= e($_POST['monto'] ?? '') ?>">
    <button>Crear cuenta</button>
  </form>
</div>
<?php pie();
