<?php
require 'inc/config.php';
require 'inc/layout.php';
requiere_rol('ADMIN');

$ok = $error = null;
if (es_post_valido()) {
    try {
        if (($_POST['accion'] ?? '') === 'bloqueo') {
            sp('sp_cajero_toggle_bloqueo', [(int)$_POST['id']]);
            $ok = 'Estado del cajero actualizado.';
        } else {
            $nombre  = trim($_POST['nombre'] ?? '');
            $usuario = trim($_POST['usuario'] ?? '');
            $clave   = $_POST['clave'] ?? '';
            if ($nombre === '' || !preg_match('/^[A-Za-z0-9._-]{4,30}$/', $usuario))
                $error = 'Nombre requerido; el usuario debe tener 4-30 caracteres (letras, números, . _ -).';
            elseif (!clave_valida($clave))
                $error = 'La clave debe tener al menos 8 caracteres, una mayúscula, una minúscula y un número.';
            elseif ($clave !== ($_POST['confirmacion'] ?? ''))
                $error = 'Las claves no coinciden.';
            else {
                sp('sp_cajero_crear', [$nombre, $usuario, $clave]);
                $ok = 'Cajero creado correctamente.';
            }
        }
    } catch (PDOException $ex) {
        $error = error_bd($ex);
    }
}
$cajeros = sp('sp_cajeros_listar');

encabezado('Gestión de usuarios de cajeros');
alerta($ok, $error);
?>
<div class="card tabla">
  <table>
    <tr><th>Nombre</th><th>Usuario</th><th>Creado</th><th>Estado</th><th></th></tr>
    <?php foreach ($cajeros as $c): ?>
    <tr>
      <td><?= e($c['nombre']) ?></td><td><?= e($c['usuario']) ?></td><td><?= e($c['creado_en']) ?></td>
      <td><span class="estado <?= $c['bloqueado'] ? 'off' : 'on' ?>"><?= $c['bloqueado'] ? 'Bloqueado' : 'Activo' ?></span></td>
      <td>
        <form method="post"><?= csrf_campo() ?>
          <input type="hidden" name="accion" value="bloqueo"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button class="sec"><?= $c['bloqueado'] ? 'Desbloquear' : 'Bloquear' ?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; if (!$cajeros): ?><tr><td colspan="5">No hay cajeros registrados.</td></tr><?php endif; ?>
  </table>
</div>
<div class="card angosto">
  <h2>Agregar cajero</h2>
  <form method="post" autocomplete="off"><?= csrf_campo() ?>
    <label for="nombre">Nombre completo</label><input id="nombre" name="nombre" required maxlength="100">
    <label for="usuario">Usuario</label><input id="usuario" name="usuario" required pattern="[A-Za-z0-9._\-]{4,30}">
    <label for="clave">Clave</label><input id="clave" name="clave" type="password" minlength="8" required>
    <label for="confirmacion">Confirmación de clave</label><input id="confirmacion" name="confirmacion" type="password" minlength="8" required>
    <button>Agregar</button>
  </form>
</div>
<?php pie();
