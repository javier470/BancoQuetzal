<?php
require 'inc/config.php';
require 'inc/layout.php';
$u = requiere_rol('CLIENTE');

$ok = $error = null;
if (es_post_valido()) {
    $numero = trim($_POST['numero'] ?? '');
    $max    = trim($_POST['monto_maximo'] ?? '');
    $trans  = trim($_POST['max_trans'] ?? '');
    $alias  = trim($_POST['alias'] ?? '');
    if (!preg_match('/^\d{6,20}$/', $numero))               $error = 'Número de cuenta inválido.';
    elseif (!monto_valido($max) || $max <= 0)               $error = 'El monto máximo debe ser mayor a cero.';
    elseif (!ctype_digit($trans) || (int)$trans < 1)        $error = 'La cantidad máxima de transacciones debe ser un entero mayor a cero.';
    elseif ($alias === '' || mb_strlen($alias) > 50)        $error = 'El alias es requerido (máx. 50 caracteres).';
    else {
        try {
            sp('sp_tercero_agregar', [$u['id'], $numero, $max, (int)$trans, $alias]);
            $ok = 'Cuenta de tercero agregada.';
            $_POST = [];
        } catch (PDOException $ex) {
            $error = error_bd($ex);
        }
    }
}
$terceros = sp('sp_terceros_listar', [$u['id']]);

encabezado('Cuentas de terceros');
alerta($ok, $error);
?>
<div class="card angosto">
  <h2>Agregar cuenta</h2>
  <form method="post" autocomplete="off"><?= csrf_campo() ?>
    <label for="numero">No. Cuenta</label><input id="numero" name="numero" inputmode="numeric" pattern="\d{6,20}" required value="<?= e($_POST['numero'] ?? '') ?>">
    <label for="monto_maximo">Monto máximo por transferencia (Q)</label><input id="monto_maximo" name="monto_maximo" type="number" min="0.01" step="0.01" required value="<?= e($_POST['monto_maximo'] ?? '') ?>">
    <label for="max_trans">Cantidad máxima de transacciones diarias</label><input id="max_trans" name="max_trans" type="number" min="1" step="1" required value="<?= e($_POST['max_trans'] ?? '') ?>">
    <label for="alias">Alias</label><input id="alias" name="alias" maxlength="50" required value="<?= e($_POST['alias'] ?? '') ?>">
    <button>Agregar</button>
  </form>
</div>
<div class="card tabla">
  <table>
    <tr><th>Alias</th><th>Cuenta</th><th>Titular</th><th class="num">Monto máx.</th><th class="num">Trans./día</th></tr>
    <?php foreach ($terceros as $t): ?>
    <tr><td><?= e($t['alias']) ?></td><td><?= e($t['numero']) ?></td><td><?= e($t['nombre']) ?></td>
        <td class="num"><?= q($t['monto_maximo']) ?></td><td class="num"><?= (int)$t['usadas_hoy'] ?> / <?= (int)$t['max_trans_diarias'] ?></td></tr>
    <?php endforeach; if (!$terceros): ?><tr><td colspan="5">Aún no tiene cuentas de terceros.</td></tr><?php endif; ?>
  </table>
</div>
<?php pie();
