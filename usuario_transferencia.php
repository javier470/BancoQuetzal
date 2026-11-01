<?php
require 'inc/config.php';
require 'inc/layout.php';
$u = requiere_rol('CLIENTE');

$ok = $error = null;
if (es_post_valido()) {
    $tercero = (int)($_POST['tercero'] ?? 0);
    $monto   = trim($_POST['monto'] ?? '');
    if ($tercero <= 0)                               $error = 'Seleccione una cuenta de tercero.';
    elseif (!monto_valido($monto) || $monto <= 0)    $error = 'El monto debe ser mayor a cero.';
    else {
        try {
            sp('sp_transferir', [$u['id'], $tercero, $monto]);
            $ok = 'Transferencia de ' . q($monto) . ' realizada con éxito.';
        } catch (PDOException $ex) {
            $error = error_bd($ex);
        }
    }
}
$info = sp('sp_cliente_info', [$u['id']])[0];
$terceros = sp('sp_terceros_listar', [$u['id']]);

encabezado('Transferencia a cuenta de tercero');
?>
<div class="card angosto">
  <?php alerta($ok, $error); ?>
  <p>Cuenta origen: <strong><?= e($info['numero']) ?></strong> · Saldo disponible: <strong><?= q($info['saldo']) ?></strong></p>
  <?php if (!$terceros): ?>
    <p>Primero debe <a href="usuario_terceros.php">agregar una cuenta de tercero</a>.</p>
  <?php else: ?>
  <form method="post"><?= csrf_campo() ?>
    <label for="tercero">Cuenta destino</label>
    <select id="tercero" name="tercero" required>
      <option value="">-- Seleccione --</option>
      <?php foreach ($terceros as $t): ?>
        <option value="<?= (int)$t['id'] ?>"><?= e("{$t['alias']} - {$t['numero']} (máx. " . q($t['monto_maximo']) . ", {$t['usadas_hoy']}/{$t['max_trans_diarias']} hoy)") ?></option>
      <?php endforeach; ?>
    </select>
    <label for="monto">Monto a transferir (Q)</label>
    <input id="monto" name="monto" type="number" min="0.01" step="0.01" required>
    <button>Transferir</button>
  </form>
  <?php endif; ?>
</div>
<?php pie();
